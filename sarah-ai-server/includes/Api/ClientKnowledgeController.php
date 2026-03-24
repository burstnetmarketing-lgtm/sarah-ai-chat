<?php

declare(strict_types=1);

namespace SarahAiServer\Api;

use SarahAiServer\DB\KnowledgeResourceTable;
use SarahAiServer\Infrastructure\CredentialValidator;
use SarahAiServer\Infrastructure\KnowledgeResourceRepository;
use SarahAiServer\Infrastructure\KnowledgeResourceTypeRepository;
use SarahAiServer\Infrastructure\SettingsRepository;
use SarahAiServer\Processing\KnowledgeProcessingService;

/**
 * Client-side Knowledge Base endpoints.
 *
 * Allows the sarah-ai-client plugin to manage KB resources for its site
 * without requiring a WordPress admin login on the server.
 *
 * Authentication (all three required):
 *   1. X-Sarah-Platform-Key header  — static platform secret (stored in platform settings)
 *   2. account_key (body/query)     — identifies tenant
 *   3. site_key    (body/query)     — identifies site
 *
 * All operations are automatically scoped to the resolved site.
 *
 * Routes:
 *   GET    /client/knowledge-resource-types          — list enabled resource types
 *   GET    /client/knowledge-resources               — list resources for the site
 *   POST   /client/knowledge-resources               — create a text or link resource
 *   DELETE /client/knowledge-resources/{uuid}        — soft-delete a resource
 *   POST   /client/knowledge-resources/{uuid}/status — update resource status
 *   POST   /client/knowledge-resources/{uuid}/process — run processing pipeline
 */
class ClientKnowledgeController
{
    private KnowledgeResourceRepository     $resources;
    private KnowledgeResourceTypeRepository $types;
    private CredentialValidator             $credentials;
    private SettingsRepository              $settings;

    public function __construct()
    {
        $this->resources   = new KnowledgeResourceRepository();
        $this->types       = new KnowledgeResourceTypeRepository();
        $this->credentials = new CredentialValidator();
        $this->settings    = new SettingsRepository();
    }

    public function registerRoutes(): void
    {
        // Allow X-Sarah-Platform-Key in CORS preflight responses
        add_filter('rest_allowed_cors_headers', function (array $headers): array {
            $headers[] = 'X-Sarah-Platform-Key';
            return $headers;
        });

        register_rest_route('sarah-ai-server/v1', '/client/knowledge-resource-types', [
            'methods'             => 'GET',
            'callback'            => [$this, 'listTypes'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('sarah-ai-server/v1', '/client/knowledge-resources', [
            'methods'             => 'GET',
            'callback'            => [$this, 'index'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('sarah-ai-server/v1', '/client/knowledge-resources', [
            'methods'             => 'POST',
            'callback'            => [$this, 'store'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('sarah-ai-server/v1', '/client/knowledge-resources/(?P<uuid>[0-9a-f-]{36})', [
            'methods'             => 'DELETE',
            'callback'            => [$this, 'destroy'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('sarah-ai-server/v1', '/client/knowledge-resources/(?P<uuid>[0-9a-f-]{36})/status', [
            'methods'             => 'POST',
            'callback'            => [$this, 'updateStatus'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('sarah-ai-server/v1', '/client/knowledge-resources/(?P<uuid>[0-9a-f-]{36})/process', [
            'methods'             => 'POST',
            'callback'            => [$this, 'process'],
            'permission_callback' => '__return_true',
        ]);
    }

    // ─── Auth ─────────────────────────────────────────────────────────────────

    /**
     * Validates all three credentials and returns the resolved context.
     * Returns null if any check fails.
     *
     * @return array{tenant: array, site: array}|null
     */
    private function resolveAuth(\WP_REST_Request $request): ?array
    {
        $storedKey   = trim((string) $this->settings->get('platform_api_key', '', 'platform'));
        $platformKey = trim((string) $request->get_header('X-Sarah-Platform-Key'));

        if (! $storedKey || ! $platformKey || ! hash_equals($storedKey, $platformKey)) {
            return null;
        }

        $accountKey = trim((string) ($request->get_param('account_key') ?? ''));
        $siteKey    = trim((string) ($request->get_param('site_key')    ?? ''));

        if (! $accountKey || ! $siteKey) {
            return null;
        }

        return $this->credentials->resolveContext($accountKey, $siteKey);
    }

    private function unauthorized(): \WP_REST_Response
    {
        return new \WP_REST_Response(['success' => false, 'message' => 'Authentication failed.'], 401);
    }

    /** Ensures the given resource belongs to the resolved site. */
    private function ownedResource(array $context, string $uuid): ?array
    {
        $resource = $this->resources->findByUuid($uuid);
        if (! $resource) {
            return null;
        }
        if ((int) $resource['site_id'] !== (int) $context['site']['id']) {
            return null;
        }
        return $resource;
    }

    // ─── Handlers ─────────────────────────────────────────────────────────────

    /**
     * GET /client/knowledge-resource-types?account_key=&site_key=
     */
    public function listTypes(\WP_REST_Request $request): \WP_REST_Response
    {
        $context = $this->resolveAuth($request);
        if (! $context) {
            return $this->unauthorized();
        }

        $types = $this->types->findEnabled();
        return new \WP_REST_Response(['success' => true, 'types' => $types]);
    }

    /**
     * GET /client/knowledge-resources?account_key=&site_key=
     */
    public function index(\WP_REST_Request $request): \WP_REST_Response
    {
        $context = $this->resolveAuth($request);
        if (! $context) {
            return $this->unauthorized();
        }

        $siteId    = (int) $context['site']['id'];
        $resources = $this->resources->findBySite($siteId);

        return new \WP_REST_Response(['success' => true, 'data' => $resources], 200);
    }

    /**
     * POST /client/knowledge-resources
     * Body: account_key, site_key, resource_type, title, source_content, [content_group], [meta]
     */
    public function store(\WP_REST_Request $request): \WP_REST_Response
    {
        $context = $this->resolveAuth($request);
        if (! $context) {
            return $this->unauthorized();
        }

        $resourceType  = trim((string) ($request->get_param('resource_type')  ?? ''));
        $title         = trim((string) ($request->get_param('title')          ?? ''));
        $sourceContent = trim((string) ($request->get_param('source_content') ?? ''));
        $contentGroup  = trim((string) ($request->get_param('content_group')  ?? ''));
        $meta          = $request->get_param('meta');

        if (! $resourceType) {
            return new \WP_REST_Response(['success' => false, 'message' => 'resource_type is required.'], 400);
        }

        if (! preg_match('/^[a-z0-9][a-z0-9_-]{0,79}$/', $resourceType)) {
            return new \WP_REST_Response(['success' => false, 'message' => 'resource_type must be a lowercase slug.'], 400);
        }

        $metaArray = is_array($meta) ? $meta : [];
        $id        = $this->resources->create(
            (int) $context['site']['id'],
            $resourceType,
            $title,
            $sourceContent,
            $contentGroup,
            $metaArray
        );

        if (! $id) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Failed to create resource.'], 500);
        }

        $resource = $this->resources->findById($id);

        // Fire-and-forget: trigger processing in the background without blocking this response
        if ($resource) {
            $platformKey = trim((string) $request->get_header('X-Sarah-Platform-Key'));
            $accountKey  = trim((string) ($request->get_param('account_key') ?? ''));
            $siteKey     = trim((string) ($request->get_param('site_key')    ?? ''));
            wp_remote_post(
                rest_url('sarah-ai-server/v1/client/knowledge-resources/' . $resource['uuid'] . '/process'),
                [
                    'timeout'   => 1,
                    'blocking'  => false,
                    'sslverify' => false,
                    'headers'   => [
                        'Content-Type'          => 'application/json',
                        'X-Sarah-Platform-Key'  => $platformKey,
                    ],
                    'body'      => wp_json_encode(['account_key' => $accountKey, 'site_key' => $siteKey]),
                ]
            );
        }

        return new \WP_REST_Response(['success' => true, 'data' => $resource], 201);
    }

    /**
     * DELETE /client/knowledge-resources/{uuid}?account_key=&site_key=
     */
    public function destroy(\WP_REST_Request $request): \WP_REST_Response
    {
        $context = $this->resolveAuth($request);
        if (! $context) {
            return $this->unauthorized();
        }

        $resource = $this->ownedResource($context, (string) $request->get_param('uuid'));
        if (! $resource) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Resource not found.'], 404);
        }

        $this->resources->softDelete((int) $resource['id']);
        return new \WP_REST_Response(['success' => true, 'message' => 'Resource deleted.'], 200);
    }

    /**
     * POST /client/knowledge-resources/{uuid}/status
     * Body: account_key, site_key, status
     */
    public function updateStatus(\WP_REST_Request $request): \WP_REST_Response
    {
        $context = $this->resolveAuth($request);
        if (! $context) {
            return $this->unauthorized();
        }

        $resource = $this->ownedResource($context, (string) $request->get_param('uuid'));
        if (! $resource) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Resource not found.'], 404);
        }

        $status  = trim((string) ($request->get_param('status') ?? ''));
        $allowed = [
            KnowledgeResourceTable::STATUS_ACTIVE,
            KnowledgeResourceTable::STATUS_INACTIVE,
            KnowledgeResourceTable::STATUS_PENDING,
            KnowledgeResourceTable::STATUS_PROCESSING,
            KnowledgeResourceTable::STATUS_FAILED,
            KnowledgeResourceTable::STATUS_ARCHIVED,
        ];

        if (! $status || ! in_array($status, $allowed, true)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'status must be one of: ' . implode(', ', $allowed),
            ], 400);
        }

        $this->resources->updateStatus((int) $resource['id'], $status);
        return new \WP_REST_Response([
            'success' => true,
            'data'    => $this->resources->findById((int) $resource['id']),
        ], 200);
    }

    /**
     * POST /client/knowledge-resources/{uuid}/process
     * Body: account_key, site_key
     */
    public function process(\WP_REST_Request $request): \WP_REST_Response
    {
        $context = $this->resolveAuth($request);
        if (! $context) {
            return $this->unauthorized();
        }

        $resource = $this->ownedResource($context, (string) $request->get_param('uuid'));
        if (! $resource) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Resource not found.'], 404);
        }

        $service = new KnowledgeProcessingService();
        $result  = $service->process((int) $resource['id']);

        return new \WP_REST_Response([
            'success' => $result['success'],
            'chunks'  => $result['chunks'],
            'message' => $result['message'],
        ], 200);
    }
}
