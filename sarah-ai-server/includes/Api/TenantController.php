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

    public function registerRoutes(): void
    {
        // POST /tenants
        register_rest_route('sarah-ai-server/v1', '/tenants', [
            'methods'             => 'POST',
            'callback'            => [$this, 'store'],
            'permission_callback' => '__return_true',
        ]);

        // GET /tenants
        register_rest_route('sarah-ai-server/v1', '/tenants', [
            'methods'             => 'GET',
            'callback'            => [$this, 'index'],
            'permission_callback' => '__return_true',
        ]);

        // GET /tenants/{id}
        register_rest_route('sarah-ai-server/v1', '/tenants/(?P<id>\d+)', [
            'methods'             => 'GET',
            'callback'            => [$this, 'show'],
            'permission_callback' => '__return_true',
        ]);

        // POST /tenants/{id}/status
        register_rest_route('sarah-ai-server/v1', '/tenants/(?P<id>\d+)/status', [
            'methods'             => 'POST',
            'callback'            => [$this, 'updateStatus'],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * Create a new tenant and automatically assign a trial subscription.
     *
     * Body: name (required), slug (optional), meta (optional)
     */
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

        // Auto-assign trial subscription
        $plan = $this->plans->findBySlug('trial');
        if ($plan) {
            $startsAt = current_time('mysql');
            $endsAt   = null;
            if ((int) $plan['duration_days'] > 0) {
                $endsAt = date('Y-m-d H:i:s', strtotime($startsAt) + ((int) $plan['duration_days'] * 86400));
            }
            $this->subscriptions->create(
                $tenantId,
                (int) $plan['id'],
                'trialing',
                $startsAt,
                $endsAt
            );
        }

        $tenant       = $this->tenants->findById($tenantId);
        $subscription = $this->subscriptions->findActiveByTenant($tenantId);

        return new \WP_REST_Response([
            'success' => true,
            'data'    => [
                'tenant'       => $tenant,
                'subscription' => $subscription,
            ],
        ], 201);
    }

    /** List all tenants with their active subscription status. */
    public function index(\WP_REST_Request $request): \WP_REST_Response
    {
        $tenants = $this->tenants->all();

        $result = array_map(function (array $tenant) {
            $subscription = $this->subscriptions->findActiveByTenant((int) $tenant['id']);
            return [
                'tenant'              => $tenant,
                'subscription_status' => $subscription ? $subscription['status'] : null,
            ];
        }, $tenants);

        return new \WP_REST_Response(['success' => true, 'data' => $result], 200);
    }

    /**
     * Full tenant context: tenant, subscription, sites, and associated users.
     * Provides admin visibility into the complete tenant configuration.
     */
    public function show(\WP_REST_Request $request): \WP_REST_Response
    {
        $id     = (int) $request->get_param('id');
        $tenant = $this->tenants->findById($id);

        if (! $tenant) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Tenant not found'], 404);
        }

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

    /** Update tenant lifecycle status. */
    public function updateStatus(\WP_REST_Request $request): \WP_REST_Response
    {
        $id     = (int) $request->get_param('id');
        $status = trim((string) ($request->get_param('status') ?? ''));

        $allowed = ['active', 'inactive', 'suspended', 'archived'];
        if (! in_array($status, $allowed, true)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'status must be one of: ' . implode(', ', $allowed),
            ], 400);
        }

        if (! $this->tenants->findById($id)) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Tenant not found'], 404);
        }

        $this->tenants->updateStatus($id, $status);
        return new \WP_REST_Response(['success' => true, 'data' => $this->tenants->findById($id)], 200);
    }

    private function slugify(string $name): string
    {
        $slug = strtolower($name);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        return trim($slug, '-');
    }
}
