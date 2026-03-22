<?php

declare(strict_types=1);

namespace SarahAiServer\Api;

use SarahAiServer\DB\KnowledgeResourceTable;
use SarahAiServer\Infrastructure\KnowledgeResourceRepository;
use SarahAiServer\Infrastructure\SiteRepository;

class KnowledgeController
{
    private KnowledgeResourceRepository $repo;
    private SiteRepository $sites;

    public function __construct()
    {
        $this->repo  = new KnowledgeResourceRepository();
        $this->sites = new SiteRepository();
    }

    public function registerRoutes(): void
    {
        // GET  /knowledge-resources?site_id=X[&group=faq][&active_only=1]
        register_rest_route('sarah-ai-server/v1', '/knowledge-resources', [
            'methods'             => 'GET',
            'callback'            => [$this, 'index'],
            'permission_callback' => '__return_true',
        ]);

        // POST /knowledge-resources
        register_rest_route('sarah-ai-server/v1', '/knowledge-resources', [
            'methods'             => 'POST',
            'callback'            => [$this, 'store'],
            'permission_callback' => '__return_true',
        ]);

        // GET  /knowledge-resources/{id}
        register_rest_route('sarah-ai-server/v1', '/knowledge-resources/(?P<id>\d+)', [
            'methods'             => 'GET',
            'callback'            => [$this, 'show'],
            'permission_callback' => '__return_true',
        ]);

        // DELETE /knowledge-resources/{id}
        register_rest_route('sarah-ai-server/v1', '/knowledge-resources/(?P<id>\d+)', [
            'methods'             => 'DELETE',
            'callback'            => [$this, 'destroy'],
            'permission_callback' => '__return_true',
        ]);

        // POST /knowledge-resources/{id}/status
        register_rest_route('sarah-ai-server/v1', '/knowledge-resources/(?P<id>\d+)/status', [
            'methods'             => 'POST',
            'callback'            => [$this, 'updateStatus'],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * List knowledge resources for a site.
     *
     * Query params:
     *   site_id     (required) — owning site
     *   group       (optional) — filter by content_group (e.g. 'faq', 'policy')
     *   active_only (optional) — '1' to return only STATUS_ACTIVE resources
     */
    public function index(\WP_REST_Request $request): \WP_REST_Response
    {
        $siteId     = (int) $request->get_param('site_id');
        $group      = (string) ($request->get_param('group') ?? '');
        $activeOnly = $request->get_param('active_only') === '1';

        if (! $siteId) {
            return new \WP_REST_Response(['success' => false, 'message' => 'site_id is required'], 400);
        }

        if ($group !== '') {
            $resources = $activeOnly
                ? $this->repo->findActiveByGroup($siteId, $group)
                : $this->repo->findByGroup($siteId, $group);
        } else {
            $resources = $activeOnly
                ? $this->repo->findActiveBySite($siteId)
                : $this->repo->findBySite($siteId);
        }

        return new \WP_REST_Response(['success' => true, 'data' => $resources], 200);
    }

    /**
     * Create a new knowledge resource.
     *
     * Body params:
     *   site_id        (required)
     *   resource_type  (required) — extensible string classifier; built-in values are
     *                               'text', 'link', 'file', but any lowercase-slug value
     *                               is valid. The model is open-ended by design.
     *   title          (optional)
     *   source_content (optional) — raw text, URL, or file path
     *   content_group  (optional) — logical category: 'faq', 'policy', 'product', 'support', 'campaign', etc.
     *   meta           (optional) — JSON object for non-query attributes (file size, MIME type, etc.)
     */
    public function store(\WP_REST_Request $request): \WP_REST_Response
    {
        $siteId        = (int) $request->get_param('site_id');
        $resourceType  = (string) $request->get_param('resource_type');
        $title         = (string) ($request->get_param('title') ?? '');
        $sourceContent = (string) ($request->get_param('source_content') ?? '');
        $contentGroup  = (string) ($request->get_param('content_group') ?? '');
        $meta          = $request->get_param('meta');

        if (! $siteId || $resourceType === '') {
            return new \WP_REST_Response(['success' => false, 'message' => 'site_id and resource_type are required'], 400);
        }

        // Format validation only — resource_type is an open classifier, not a closed enum.
        // Built-in types (text, link, file) are examples, not an exhaustive allowlist.
        // Any future type (faq-entry, markdown-page, structured-record, etc.) is accepted
        // here without code change. Values must be non-empty lowercase slugs (a-z, 0-9, hyphens,
        // underscores) up to 80 characters, consistent with the VARCHAR(80) column.
        if (! preg_match('/^[a-z0-9][a-z0-9_-]{0,79}$/', $resourceType)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'resource_type must be a non-empty lowercase slug (letters, digits, hyphens, underscores)',
            ], 400);
        }

        $metaArray = is_array($meta) ? $meta : [];
        $id        = $this->repo->create($siteId, $resourceType, $title, $sourceContent, $contentGroup, $metaArray);

        if (! $id) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Site not found or is deleted'], 404);
        }

        $resource = $this->repo->findById($id);
        return new \WP_REST_Response(['success' => true, 'data' => $resource], 201);
    }

    /** Get a single knowledge resource by ID. */
    public function show(\WP_REST_Request $request): \WP_REST_Response
    {
        $id       = (int) $request->get_param('id');
        $resource = $this->repo->findById($id);

        if (! $resource) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Resource not found'], 404);
        }

        return new \WP_REST_Response(['success' => true, 'data' => $resource], 200);
    }

    /** Soft-delete a knowledge resource. */
    public function destroy(\WP_REST_Request $request): \WP_REST_Response
    {
        $id       = (int) $request->get_param('id');
        $resource = $this->repo->findById($id);

        if (! $resource) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Resource not found'], 404);
        }

        $this->repo->softDelete($id);
        return new \WP_REST_Response(['success' => true, 'message' => 'Resource deleted'], 200);
    }

    /**
     * Update the lifecycle status of a resource.
     * This controls admin intent (is the resource available to agents?).
     * It does not affect processing_status.
     */
    public function updateStatus(\WP_REST_Request $request): \WP_REST_Response
    {
        $id     = (int) $request->get_param('id');
        $status = (string) $request->get_param('status');

        $allowedStatuses = [
            KnowledgeResourceTable::STATUS_ACTIVE,
            KnowledgeResourceTable::STATUS_INACTIVE,
            KnowledgeResourceTable::STATUS_PENDING,
            KnowledgeResourceTable::STATUS_PROCESSING,
            KnowledgeResourceTable::STATUS_FAILED,
            KnowledgeResourceTable::STATUS_ARCHIVED,
        ];
        if (! $status || ! in_array($status, $allowedStatuses, true)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'status must be one of: ' . implode(', ', $allowedStatuses),
            ], 400);
        }

        $resource = $this->repo->findById($id);
        if (! $resource) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Resource not found'], 404);
        }

        $this->repo->updateStatus($id, $status);
        return new \WP_REST_Response(['success' => true, 'data' => $this->repo->findById($id)], 200);
    }
}
