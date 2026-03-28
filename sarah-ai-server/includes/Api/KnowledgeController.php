<?php

declare(strict_types=1);

namespace SarahAiServer\Api;

use SarahAiServer\DB\KnowledgeResourceTable;
use SarahAiServer\Infrastructure\KnowledgeResourceRepository;
use SarahAiServer\Infrastructure\KnowledgeResourceTypeRepository;
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
        register_rest_route('sarah-ai-server/v1', '/knowledge-resource-types', [
            'methods'             => 'GET',
            'callback'            => [$this, 'listTypes'],
            'permission_callback' => [$this, 'isAdmin'],
        ]);

        register_rest_route('sarah-ai-server/v1', '/knowledge-resources/upload', [
            'methods'             => 'POST',
            'callback'            => [$this, 'upload'],
            'permission_callback' => [$this, 'isAdmin'],
        ]);

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

    public function listTypes(\WP_REST_Request $request): \WP_REST_Response
    {
        $types = (new KnowledgeResourceTypeRepository())->findEnabled();
        return new \WP_REST_Response(['success' => true, 'types' => $types]);
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

        $resource = $this->repo->findById($id);
        $this->dispatchAsyncProcessing((string) ($resource['uuid'] ?? ''));

        return new \WP_REST_Response(['success' => true, 'data' => $resource], 201);
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

    /**
     * POST /knowledge-resources/upload
     *
     * Accepts a PDF or DOCX file upload and creates a knowledge resource for it.
     * The file is stored at:  {wp-uploads}/sarah-ai/{site_uuid}/{resource_uuid}.{ext}
     * The original filename is preserved in meta.original_filename.
     */
    public function upload(\WP_REST_Request $request): \WP_REST_Response
    {
        $siteUuid = trim((string) ($request->get_param('site_uuid') ?? ''));
        if (! $siteUuid) {
            return new \WP_REST_Response(['success' => false, 'message' => 'site_uuid is required'], 400);
        }

        if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            $err = $_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE;
            return new \WP_REST_Response(['success' => false, 'message' => 'File upload error code: ' . $err], 400);
        }

        $site = $this->sites->findByUuid($siteUuid);
        if (! $site) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Site not found'], 404);
        }

        $originalName = sanitize_file_name(basename($_FILES['file']['name']));
        $ext          = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if (! in_array($ext, ['pdf', 'docx'], true)) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Only PDF and DOCX files are supported.'], 400);
        }

        // Build upload directory: {uploads}/sarah-ai/{site_uuid}/
        $uploadsDir = wp_upload_dir()['basedir'];
        $targetDir  = $uploadsDir . '/sarah-ai/' . $siteUuid;

        if (! wp_mkdir_p($targetDir)) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Could not create upload directory.'], 500);
        }

        // Store file as {resource_uuid}.{ext} to avoid collisions
        $fileUuid   = sarah_ai_uuid();
        $targetPath = $targetDir . '/' . $fileUuid . '.' . $ext;

        if (! move_uploaded_file($_FILES['file']['tmp_name'], $targetPath)) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Failed to move uploaded file.'], 500);
        }

        $title = trim((string) ($request->get_param('title') ?? ''));
        if ($title === '') {
            $title = pathinfo($originalName, PATHINFO_FILENAME);
        }

        $meta = ['original_filename' => $originalName, 'file_uuid' => $fileUuid];
        $id   = $this->repo->create((int) $site['id'], $ext, $title, $targetPath, '', $meta);

        if (! $id) {
            @unlink($targetPath);
            return new \WP_REST_Response(['success' => false, 'message' => 'Failed to create knowledge resource.'], 500);
        }

        $resource = $this->repo->findById($id);
        $this->dispatchAsyncProcessing((string) ($resource['uuid'] ?? ''));

        return new \WP_REST_Response(['success' => true, 'data' => $resource], 201);
    }

    /**
     * Fires a non-blocking HTTP POST to the process endpoint so chunking + embedding
     * happen in the background without blocking the current request.
     * Falls back gracefully if the loopback request cannot be sent.
     */
    private function dispatchAsyncProcessing(string $uuid): void
    {
        if ($uuid === '') {
            return;
        }

        $url = rest_url('sarah-ai-server/v1/knowledge-resources/' . $uuid . '/process');

        wp_remote_post($url, [
            'blocking'  => false,
            'timeout'   => 1,
            'sslverify' => apply_filters('https_local_ssl_verify', false),
            'headers'   => [
                'X-WP-Nonce' => wp_create_nonce('wp_rest'),
                'Cookie'     => isset($_SERVER['HTTP_COOKIE']) ? $_SERVER['HTTP_COOKIE'] : '',
            ],
        ]);
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
