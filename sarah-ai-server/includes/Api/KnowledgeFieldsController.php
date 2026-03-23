<?php

declare(strict_types=1);

namespace SarahAiServer\Api;

use SarahAiServer\DB\KnowledgeResourceTable;
use SarahAiServer\Infrastructure\CredentialValidator;
use SarahAiServer\Infrastructure\KnowledgeResourceRepository;
use SarahAiServer\Processing\KnowledgeFieldSchema;
use SarahAiServer\Processing\KnowledgePolicyFilter;

/**
 * Public widget endpoint: structured knowledge fields for a site.
 *
 * Routes:
 *   GET  /sites/{uuid}/knowledge-fields   — returns merged public structured fields
 *   POST /knowledge-resources/{uuid}/visibility — admin: toggle public/private
 *
 * Authentication:
 *   knowledge-fields  → account_key + site_key (same as /chat — no WP login needed)
 *   visibility toggle → current_user_can('manage_options')
 *
 * Policy:
 *   Only resources with visibility = 'public' are included in the fields response.
 *   Private resources are silently excluded — no error is returned.
 *
 * Structured fields source:
 *   Each active public resource may carry meta.structured_fields — a flat JSON object
 *   of canonical_key → string value pairs (see KnowledgeFieldSchema).
 *   All public resources' structured_fields are merged and returned together.
 */
class KnowledgeFieldsController
{
    private KnowledgeResourceRepository $resources;
    private CredentialValidator         $credentials;

    public function __construct()
    {
        $this->resources   = new KnowledgeResourceRepository();
        $this->credentials = new CredentialValidator();
    }

    public function registerRoutes(): void
    {
        // Public widget endpoint — auth via account_key + site_key
        register_rest_route('sarah-ai-server/v1', '/sites/(?P<uuid>[0-9a-f-]{36})/knowledge-fields', [
            'methods'             => 'GET',
            'callback'            => [$this, 'fields'],
            'permission_callback' => '__return_true',
        ]);

        // Admin endpoint — toggle resource visibility
        register_rest_route('sarah-ai-server/v1', '/knowledge-resources/(?P<uuid>[0-9a-f-]{36})/visibility', [
            'methods'             => 'POST',
            'callback'            => [$this, 'updateVisibility'],
            'permission_callback' => fn() => current_user_can('manage_options'),
        ]);
    }

    /**
     * GET /sites/{uuid}/knowledge-fields?account_key=&site_key=
     *
     * Returns a merged map of all structured fields from public active KB resources
     * for the site. Only fields whose keys match the canonical schema are included.
     *
     * Response:
     *   { "success": true, "fields": { "contact.phone_admin": "...", ... } }
     */
    public function fields(\WP_REST_Request $request): \WP_REST_Response
    {
        $accountKey = trim((string) ($request->get_param('account_key') ?? ''));
        $siteKey    = trim((string) ($request->get_param('site_key')    ?? ''));

        if (! $accountKey || ! $siteKey) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'account_key and site_key are required.',
            ], 400);
        }

        $context = $this->credentials->resolveContext($accountKey, $siteKey);
        if (! $context) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Invalid credentials.'], 401);
        }

        // Validate UUID matches the resolved site (prevents cross-site leakage)
        $requestedUuid = (string) $request->get_param('uuid');
        if (($context['site']['uuid'] ?? '') !== $requestedUuid) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Site not found.'], 404);
        }

        $siteId    = (int) $context['site']['id'];
        $resources = $this->resources->findPublicActiveBySite($siteId);

        // Merge structured_fields from all public resources
        $merged = [];
        foreach ($resources as $resource) {
            $fields = KnowledgeFieldSchema::extractFromResource($resource);
            foreach ($fields as $key => $value) {
                // First-write wins — earlier resources (lower sort_order) take precedence
                if (! isset($merged[$key])) {
                    $merged[$key] = $value;
                }
            }
        }

        return new \WP_REST_Response([
            'success' => true,
            'fields'  => $merged,
        ]);
    }

    /**
     * POST /knowledge-resources/{uuid}/visibility
     * Body: { "visibility": "public" | "private" }
     *
     * Admin-only. Toggles the visibility of a single KB resource.
     */
    public function updateVisibility(\WP_REST_Request $request): \WP_REST_Response
    {
        $uuid       = (string) $request->get_param('uuid');
        $visibility = trim((string) ($request->get_param('visibility') ?? ''));

        if (! KnowledgePolicyFilter::isValidVisibility($visibility)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => "Invalid visibility value '{$visibility}'. Allowed: public, private.",
            ], 400);
        }

        $resource = $this->resources->findByUuid($uuid);
        if (! $resource) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Resource not found.'], 404);
        }

        $this->resources->updateVisibility((int) $resource['id'], $visibility);

        return new \WP_REST_Response([
            'success'    => true,
            'uuid'       => $uuid,
            'visibility' => $visibility,
        ]);
    }
}
