<?php

declare(strict_types=1);

namespace SarahAiServer\Api;

use SarahAiServer\Infrastructure\TenantRepository;
use SarahAiServer\Infrastructure\SubscriptionRepository;
use SarahAiServer\Infrastructure\PlanRepository;
use SarahAiServer\Infrastructure\SiteRepository;
use SarahAiServer\Infrastructure\UserTenantRepository;

class TenantController
{
    private TenantRepository $tenants;
    private SubscriptionRepository $subscriptions;
    private PlanRepository $plans;
    private SiteRepository $sites;
    private UserTenantRepository $userTenants;

    public function __construct()
    {
        $this->tenants       = new TenantRepository();
        $this->subscriptions = new SubscriptionRepository();
        $this->plans         = new PlanRepository();
        $this->sites         = new SiteRepository();
        $this->userTenants   = new UserTenantRepository();
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
    }

    public function store(\WP_REST_Request $request): \WP_REST_Response
    {
        $name = trim((string) ($request->get_param('name') ?? ''));
        $slug = trim((string) ($request->get_param('slug') ?? ''));
        $meta = $request->get_param('meta');

        if ($name === '') {
            return new \WP_REST_Response(['success' => false, 'message' => 'name is required'], 400);
        }

        $metaArray = is_array($meta) ? $meta : [];
        $tenantId  = $this->tenants->create($name, $slug ?: $this->slugify($name), 'active', $metaArray);

        $plan = $this->plans->findBySlug('trial');
        if ($plan) {
            $startsAt = current_time('mysql');
            $endsAt   = null;
            if ((int) $plan['duration_days'] > 0) {
                $endsAt = date('Y-m-d H:i:s', strtotime($startsAt) + ((int) $plan['duration_days'] * 86400));
            }
            $this->subscriptions->create($tenantId, (int) $plan['id'], 'trialing', $startsAt, $endsAt);
        }

        $tenant       = $this->tenants->findById($tenantId);
        $subscription = $this->subscriptions->findActiveByTenant($tenantId);

        return new \WP_REST_Response(['success' => true, 'data' => ['tenant' => $tenant, 'subscription' => $subscription]], 201);
    }

    public function index(\WP_REST_Request $request): \WP_REST_Response
    {
        $tenants = $this->tenants->all();

        $result = array_map(function (array $tenant) {
            $subscription = $this->subscriptions->findActiveByTenant((int) $tenant['id']);
            return ['tenant' => $tenant, 'subscription_status' => $subscription ? $subscription['status'] : null];
        }, $tenants);

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
                'tenant'       => $tenant,
                'subscription' => $this->subscriptions->findActiveByTenant($id),
                'sites'        => $this->sites->findByTenant($id),
                'users'        => $this->userTenants->findByTenant($id),
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

    private function slugify(string $name): string
    {
        $slug = strtolower($name);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        return trim($slug, '-');
    }
}
