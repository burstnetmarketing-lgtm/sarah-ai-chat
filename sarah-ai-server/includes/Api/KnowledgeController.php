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

    public function isAdmin(): bool
    {
        return current_user_can('manage_options');
    }

    public function registerRoutes(): void
    {
        register_rest_route('sarah-ai-server/v1', '/knowledge-resources', [
            'methods'             => 'GET',
            'callback'            => [$this, 'index'],
            'permission_callback' => [$this, 'isAdmin'],
        ]);

        register_rest_route('sarah-ai-server/v1', '/knowledge-resources', [
            'methods'             => 'POST',
            'callback'            => [$this, 'store'],
            'permission_callback' => [$this, 'isAdmin'],
        ]);

        register_rest_route('sarah-ai-server/v1', '/knowledge-resources/(?P<uuid>[0-9a-f-]{36})', [
            'methods'             => 'GET',
            'callback'            => [$this, 'show'],
            'permission_callback' => [$this, 'isAdmin'],
        ]);

        register_rest_route('sarah-ai-server/v1', '/knowledge-resources/(?P<uuid>[0-9a-f-]{36})', [
            'methods'             => 'DELETE',
            'callback'            => [$this, 'destroy'],
            'permission_callback' => [$this, 'isAdmin'],
        ]);

        register_rest_route('sarah-ai-server/v1', '/knowledge-resources/(?P<uuid>[0-9a-f-]{36})/status', [
            'methods'             => 'POST',
            'callback'            => [$this, 'updateStatus'],
            'permission_callback' => [$this, 'isAdmin'],
        ]);
    }

    public function index(\WP_REST_Request $request): \WP_REST_Response
    {
        $siteUuid   = (string) ($request->get_param('site_uuid') ?? '');
        $group      = (string) ($request->get_param('group') ?? '');
        $activeOnly = $request->get_param('active_only') === '1';

        if (! $siteUuid) {
            return new \WP_REST_Response(['success' => false, 'message' => 'site_uuid is required'], 400);
        }

        $site = $this->sites->findByUuid($siteUuid);
        if (! $site) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Site not found'], 404);
        }

        $siteId = (int) $site['id'];

        if ($group !== '') {
            $resources = $activeOnly ? $this->repo->findActiveByGroup($siteId, $group) : $this->repo->findByGroup($siteId, $group);
        } else {
            $resources = $activeOnly ? $this->repo->findActiveBySite($siteId) : $this->repo->findBySite($siteId);
        }

        return new \WP_REST_Response(['success' => true, 'data' => $resources], 200);
    }

    public function store(\WP_REST_Request $request): \WP_REST_Response
    {
        $siteUuid      = (string) ($request->get_param('site_uuid') ?? '');
        $resourceType  = (string) $request->get_param('resource_type');
        $title         = (string) ($request->get_param('title') ?? '');
        $sourceContent = (string) ($request->get_param('source_content') ?? '');
        $contentGroup  = (string) ($request->get_param('content_group') ?? '');
        $meta          = $request->get_param('meta');

        if (! $siteUuid || $resourceType === '') {
            return new \WP_REST_Response(['success' => false, 'message' => 'site_uuid and resource_type are required'], 400);
        }

        if (! preg_match('/^[a-z0-9][a-z0-9_-]{0,79}$/', $resourceType)) {
            return new \WP_REST_Response(['success' => false, 'message' => 'resource_type must be a non-empty lowercase slug'], 400);
        }

        $site = $this->sites->findByUuid($siteUuid);
        if (! $site) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Site not found'], 404);
        }

        $metaArray = is_array($meta) ? $meta : [];
        $id        = $this->repo->create((int) $site['id'], $resourceType, $title, $sourceContent, $contentGroup, $metaArray);

        if (! $id) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Failed to create resource'], 500);
        }

        return new \WP_REST_Response(['success' => true, 'data' => $this->repo->findById($id)], 201);
    }

    public function show(\WP_REST_Request $request): \WP_REST_Response
    {
        $resource = $this->repo->findByUuid((string) $request->get_param('uuid'));
        if (! $resource) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Resource not found'], 404);
        }
        return new \WP_REST_Response(['success' => true, 'data' => $resource], 200);
    }

    public function destroy(\WP_REST_Request $request): \WP_REST_Response
    {
        $resource = $this->repo->findByUuid((string) $request->get_param('uuid'));
        if (! $resource) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Resource not found'], 404);
        }
        $this->repo->softDelete((int) $resource['id']);
        return new \WP_REST_Response(['success' => true, 'message' => 'Resource deleted'], 200);
    }

    public function updateStatus(\WP_REST_Request $request): \WP_REST_Response
    {
        $resource = $this->repo->findByUuid((string) $request->get_param('uuid'));
        if (! $resource) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Resource not found'], 404);
        }

        $status = (string) $request->get_param('status');
        $allowed = [
            KnowledgeResourceTable::STATUS_ACTIVE, KnowledgeResourceTable::STATUS_INACTIVE,
            KnowledgeResourceTable::STATUS_PENDING, KnowledgeResourceTable::STATUS_PROCESSING,
            KnowledgeResourceTable::STATUS_FAILED,  KnowledgeResourceTable::STATUS_ARCHIVED,
        ];
        if (! $status || ! in_array($status, $allowed, true)) {
            return new \WP_REST_Response(['success' => false, 'message' => 'status must be one of: ' . implode(', ', $allowed)], 400);
        }

        $this->repo->updateStatus((int) $resource['id'], $status);
        return new \WP_REST_Response(['success' => true, 'data' => $this->repo->findById((int) $resource['id'])], 200);
    }
}
