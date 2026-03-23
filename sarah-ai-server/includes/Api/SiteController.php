<?php

declare(strict_types=1);

namespace SarahAiServer\Api;

use SarahAiServer\Infrastructure\SiteRepository;
use SarahAiServer\Infrastructure\TenantRepository;
use SarahAiServer\Infrastructure\AgentRepository;
use SarahAiServer\Infrastructure\AccountKeyRepository;
use SarahAiServer\Infrastructure\SiteTokenRepository;
use SarahAiServer\Infrastructure\KnowledgeResourceRepository;
use SarahAiServer\Infrastructure\PlanRepository;

class SiteController
{
    private SiteRepository $sites;
    private TenantRepository $tenants;
    private AgentRepository $agents;
    private AccountKeyRepository $accountKeys;
    private SiteTokenRepository $siteTokens;
    private KnowledgeResourceRepository $knowledge;
    private PlanRepository $plans;

    public function __construct()
    {
        $this->sites       = new SiteRepository();
        $this->tenants     = new TenantRepository();
        $this->agents      = new AgentRepository();
        $this->accountKeys = new AccountKeyRepository();
        $this->siteTokens  = new SiteTokenRepository();
        $this->knowledge   = new KnowledgeResourceRepository();
        $this->plans       = new PlanRepository();
    }

    public function isAdmin(): bool
    {
        return current_user_can('manage_options');
    }

    public function registerRoutes(): void
    {
        register_rest_route('sarah-ai-server/v1', '/sites', [
            'methods'             => 'POST',
            'callback'            => [$this, 'store'],
            'permission_callback' => [$this, 'isAdmin'],
        ]);

        register_rest_route('sarah-ai-server/v1', '/tenants/(?P<uuid>[0-9a-f-]{36})/sites', [
            'methods'             => 'GET',
            'callback'            => [$this, 'indexByTenant'],
            'permission_callback' => [$this, 'isAdmin'],
        ]);

        register_rest_route('sarah-ai-server/v1', '/sites/(?P<uuid>[0-9a-f-]{36})', [
            'methods'             => 'GET',
            'callback'            => [$this, 'show'],
            'permission_callback' => [$this, 'isAdmin'],
        ]);

        register_rest_route('sarah-ai-server/v1', '/sites/(?P<uuid>[0-9a-f-]{36})/status', [
            'methods'             => 'POST',
            'callback'            => [$this, 'updateStatus'],
            'permission_callback' => [$this, 'isAdmin'],
        ]);

        register_rest_route('sarah-ai-server/v1', '/sites/(?P<uuid>[0-9a-f-]{36})/plan', [
            'methods'             => 'POST',
            'callback'            => [$this, 'updatePlan'],
            'permission_callback' => [$this, 'isAdmin'],
        ]);

        register_rest_route('sarah-ai-server/v1', '/sites/(?P<uuid>[0-9a-f-]{36})/agent-identity', [
            ['methods' => 'GET',  'callback' => [$this, 'getAgentIdentity'],    'permission_callback' => [$this, 'isAdmin']],
            ['methods' => 'POST', 'callback' => [$this, 'updateAgentIdentity'], 'permission_callback' => [$this, 'isAdmin']],
        ]);
    }

    public function store(\WP_REST_Request $request): \WP_REST_Response
    {
        $tenantUuid = trim((string) ($request->get_param('tenant_uuid') ?? ''));
        $name       = trim((string) ($request->get_param('name') ?? ''));
        $url        = trim((string) ($request->get_param('url') ?? ''));
        $meta       = $request->get_param('meta');

        if (! $tenantUuid || $name === '' || $url === '') {
            return new \WP_REST_Response(['success' => false, 'message' => 'tenant_uuid, name, and url are required'], 400);
        }

        $tenant = $this->tenants->findByUuid($tenantUuid);
        if (! $tenant) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Tenant not found'], 404);
        }

        $metaArray = is_array($meta) ? $meta : [];

        // Auto-assign plan: customer if tenant has WHMCS key, otherwise trial
        $planSlug  = ! empty($tenant['whmcs_key']) ? 'customer' : 'trial';
        $plan      = $this->plans->findBySlug($planSlug) ?? $this->plans->findBySlug('trial');
        $planId    = $plan ? (int) $plan['id'] : null;

        $siteId = $this->sites->create((int) $tenant['id'], $name, $url, $metaArray, $planId);
        $site   = $this->sites->findById($siteId);

        return new \WP_REST_Response(['success' => true, 'data' => $site], 201);
    }

    public function indexByTenant(\WP_REST_Request $request): \WP_REST_Response
    {
        $tenant = $this->tenants->findByUuid((string) $request->get_param('uuid'));
        if (! $tenant) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Tenant not found'], 404);
        }

        $sites = $this->sites->findByTenant((int) $tenant['id']);
        return new \WP_REST_Response(['success' => true, 'data' => $sites], 200);
    }

    public function show(\WP_REST_Request $request): \WP_REST_Response
    {
        $site = $this->sites->findByUuid((string) $request->get_param('uuid'));
        if (! $site) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Site not found'], 404);
        }

        $siteId = (int) $site['id'];
        $agent  = null;
        if ($site['active_agent_id']) {
            $agent = $this->agents->findById((int) $site['active_agent_id']);
        }

        return new \WP_REST_Response([
            'success' => true,
            'data'    => [
                'site'         => $site,
                'agent'        => $agent,
                'account_keys' => $this->accountKeys->findByTenant((int) $site['tenant_id']),
                'site_keys'    => $this->siteTokens->findBySite($siteId),
                'knowledge'    => $this->knowledge->findBySite($siteId),
            ],
        ], 200);
    }

    /** GET /sites/{uuid}/agent-identity */
    public function getAgentIdentity(\WP_REST_Request $request): \WP_REST_Response
    {
        $site = $this->sites->findByUuid((string) $request->get_param('uuid'));
        if (! $site) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Site not found.'], 404);
        }

        return new \WP_REST_Response([
            'success' => true,
            'data'    => $this->sites->getAgentIdentity((int) $site['id']),
        ], 200);
    }

    /**
     * POST /sites/{uuid}/agent-identity
     * Body: agent_display_name, greeting_message, intro_message (all optional)
     */
    public function updateAgentIdentity(\WP_REST_Request $request): \WP_REST_Response
    {
        $site = $this->sites->findByUuid((string) $request->get_param('uuid'));
        if (! $site) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Site not found.'], 404);
        }

        $this->sites->updateAgentIdentity((int) $site['id'], [
            'agent_display_name' => $request->has_param('agent_display_name') ? trim((string) $request->get_param('agent_display_name')) : null,
            'greeting_message'   => $request->has_param('greeting_message')   ? trim((string) $request->get_param('greeting_message'))   : null,
            'intro_message'      => $request->has_param('intro_message')      ? trim((string) $request->get_param('intro_message'))      : null,
        ]);

        return new \WP_REST_Response([
            'success' => true,
            'data'    => $this->sites->getAgentIdentity((int) $site['id']),
        ], 200);
    }

    /** POST /sites/{uuid}/plan — change the plan for a site. Body: { plan_slug } */
    public function updatePlan(\WP_REST_Request $request): \WP_REST_Response
    {
        $site = $this->sites->findByUuid((string) $request->get_param('uuid'));
        if (! $site) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Site not found'], 404);
        }

        $planSlug = trim((string) ($request->get_param('plan_slug') ?? ''));
        $plan     = $this->plans->findBySlug($planSlug);
        if (! $plan) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Plan not found'], 404);
        }

        $this->sites->updatePlan((int) $site['id'], (int) $plan['id']);
        return new \WP_REST_Response(['success' => true, 'data' => $this->sites->findById((int) $site['id'])], 200);
    }

    public function updateStatus(\WP_REST_Request $request): \WP_REST_Response
    {
        $site = $this->sites->findByUuid((string) $request->get_param('uuid'));
        if (! $site) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Site not found'], 404);
        }

        $status  = trim((string) ($request->get_param('status') ?? ''));
        $allowed = ['active', 'inactive', 'suspended'];
        if (! in_array($status, $allowed, true)) {
            return new \WP_REST_Response(['success' => false, 'message' => 'status must be one of: ' . implode(', ', $allowed)], 400);
        }

        $this->sites->updateStatus((int) $site['id'], $status);
        return new \WP_REST_Response(['success' => true, 'data' => $this->sites->findById((int) $site['id'])], 200);
    }
}
