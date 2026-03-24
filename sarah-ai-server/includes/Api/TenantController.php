<?php

declare(strict_types=1);

namespace SarahAiServer\Api;

use SarahAiServer\Infrastructure\TenantRepository;
use SarahAiServer\Infrastructure\SiteRepository;
use SarahAiServer\Infrastructure\UserTenantRepository;

class TenantController
{
    private TenantRepository     $tenants;
    private SiteRepository       $sites;
    private UserTenantRepository $userTenants;

    public function __construct()
    {
        $this->tenants     = new TenantRepository();
        $this->sites       = new SiteRepository();
        $this->userTenants = new UserTenantRepository();
    }

    public function isAdmin(): bool
    {
        return current_user_can('manage_options');
    }

    public function registerRoutes(): void
    {
        register_rest_route('sarah-ai-server/v1', '/tenants', [
            'methods'             => 'POST',
            'callback'            => [$this, 'store'],
            'permission_callback' => [$this, 'isAdmin'],
        ]);

        register_rest_route('sarah-ai-server/v1', '/tenants', [
            'methods'             => 'GET',
            'callback'            => [$this, 'index'],
            'permission_callback' => [$this, 'isAdmin'],
        ]);

        register_rest_route('sarah-ai-server/v1', '/tenants/(?P<uuid>[0-9a-f-]{36})', [
            'methods'             => 'GET',
            'callback'            => [$this, 'show'],
            'permission_callback' => [$this, 'isAdmin'],
        ]);

        register_rest_route('sarah-ai-server/v1', '/tenants/(?P<uuid>[0-9a-f-]{36})/status', [
            'methods'             => 'POST',
            'callback'            => [$this, 'updateStatus'],
            'permission_callback' => [$this, 'isAdmin'],
        ]);

        register_rest_route('sarah-ai-server/v1', '/tenants/(?P<uuid>[0-9a-f-]{36})/setup-complete', [
            'methods'             => 'POST',
            'callback'            => [$this, 'markSetupComplete'],
            'permission_callback' => [$this, 'isAdmin'],
        ]);

        register_rest_route('sarah-ai-server/v1', '/tenants/(?P<uuid>[0-9a-f-]{36})/whmcs-key', [
            'methods'             => 'POST',
            'callback'            => [$this, 'updateWhmcsKey'],
            'permission_callback' => [$this, 'isAdmin'],
        ]);

        register_rest_route('sarah-ai-server/v1', '/tenants/(?P<uuid>[0-9a-f-]{36})', [
            'methods'             => 'DELETE',
            'callback'            => [$this, 'destroy'],
            'permission_callback' => [$this, 'isAdmin'],
        ]);
    }

    public function store(\WP_REST_Request $request): \WP_REST_Response
    {
        $name     = trim((string) ($request->get_param('name') ?? ''));
        $slug     = trim((string) ($request->get_param('slug') ?? ''));
        $whmcsKey = trim((string) ($request->get_param('whmcs_key') ?? ''));
        $meta     = $request->get_param('meta');

        if ($name === '') {
            return new \WP_REST_Response(['success' => false, 'message' => 'name is required'], 400);
        }

        $metaArray = is_array($meta) ? $meta : [];
        $tenantId  = $this->tenants->create($name, $slug ?: $this->slugify($name), 'active', $metaArray, $whmcsKey);
        $tenant    = $this->tenants->findById($tenantId);

        return new \WP_REST_Response(['success' => true, 'data' => ['tenant' => $tenant]], 201);
    }

    public function index(\WP_REST_Request $request): \WP_REST_Response
    {
        $tenants = $this->tenants->all();
        $result  = array_map(fn($t) => ['tenant' => $t], $tenants);
        return new \WP_REST_Response(['success' => true, 'data' => $result], 200);
    }

    public function show(\WP_REST_Request $request): \WP_REST_Response
    {
        $tenant = $this->tenants->findByUuid((string) $request->get_param('uuid'));

        if (! $tenant) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Tenant not found'], 404);
        }

        $id = (int) $tenant['id'];

        return new \WP_REST_Response([
            'success' => true,
            'data'    => [
                'tenant' => $tenant,
                'sites'  => $this->sites->findByTenant($id),
                'users'  => $this->userTenants->findByTenant($id),
            ],
        ], 200);
    }

    public function updateStatus(\WP_REST_Request $request): \WP_REST_Response
    {
        $tenant = $this->tenants->findByUuid((string) $request->get_param('uuid'));
        if (! $tenant) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Tenant not found'], 404);
        }

        $status  = trim((string) ($request->get_param('status') ?? ''));
        $allowed = ['active', 'inactive', 'suspended', 'archived'];
        if (! in_array($status, $allowed, true)) {
            return new \WP_REST_Response(['success' => false, 'message' => 'status must be one of: ' . implode(', ', $allowed)], 400);
        }

        $this->tenants->updateStatus((int) $tenant['id'], $status);
        return new \WP_REST_Response(['success' => true, 'data' => $this->tenants->findById((int) $tenant['id'])], 200);
    }

    public function markSetupComplete(\WP_REST_Request $request): \WP_REST_Response
    {
        $tenant = $this->tenants->findByUuid((string) $request->get_param('uuid'));
        if (! $tenant) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Tenant not found'], 404);
        }
        $this->tenants->markSetupComplete((int) $tenant['id']);
        return new \WP_REST_Response(['success' => true], 200);
    }

    /** POST /tenants/{uuid}/whmcs-key — set or update WHMCS license key for a tenant. */
    public function updateWhmcsKey(\WP_REST_Request $request): \WP_REST_Response
    {
        $tenant = $this->tenants->findByUuid((string) $request->get_param('uuid'));
        if (! $tenant) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Tenant not found'], 404);
        }

        $whmcsKey = trim((string) ($request->get_param('whmcs_key') ?? ''));
        $this->tenants->updateWhmcsKey((int) $tenant['id'], $whmcsKey);

        return new \WP_REST_Response(['success' => true, 'data' => $this->tenants->findById((int) $tenant['id'])], 200);
    }

    /** DELETE /tenants/{uuid} — hard-delete tenant and all related data. */
    public function destroy(\WP_REST_Request $request): \WP_REST_Response
    {
        $tenant = $this->tenants->findByUuid((string) $request->get_param('uuid'));
        if (! $tenant) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Tenant not found'], 404);
        }

        $this->tenants->purge((int) $tenant['id']);

        return new \WP_REST_Response(['success' => true, 'message' => 'Tenant and all related data deleted.'], 200);
    }

    private function slugify(string $name): string
    {
        $slug = strtolower($name);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        return trim($slug, '-');
    }
}
