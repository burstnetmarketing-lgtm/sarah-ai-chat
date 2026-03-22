<?php

declare(strict_types=1);

namespace SarahAiServer\Api;

use SarahAiServer\Infrastructure\SiteRepository;
use SarahAiServer\Infrastructure\TenantRepository;
use SarahAiServer\Infrastructure\AgentRepository;
use SarahAiServer\Infrastructure\AccountKeyRepository;
use SarahAiServer\Infrastructure\SiteTokenRepository;
use SarahAiServer\Infrastructure\KnowledgeResourceRepository;

class SiteController
{
    private SiteRepository $sites;
    private TenantRepository $tenants;
    private AgentRepository $agents;
    private AccountKeyRepository $accountKeys;
    private SiteTokenRepository $siteTokens;
    private KnowledgeResourceRepository $knowledge;

    public function __construct()
    {
        $this->sites       = new SiteRepository();
        $this->tenants     = new TenantRepository();
        $this->agents      = new AgentRepository();
        $this->accountKeys = new AccountKeyRepository();
        $this->siteTokens  = new SiteTokenRepository();
        $this->knowledge   = new KnowledgeResourceRepository();
    }

    public function registerRoutes(): void
    {
        // POST /sites
        register_rest_route('sarah-ai-server/v1', '/sites', [
            'methods'             => 'POST',
            'callback'            => [$this, 'store'],
            'permission_callback' => '__return_true',
        ]);

        // GET /tenants/{id}/sites
        register_rest_route('sarah-ai-server/v1', '/tenants/(?P<id>\d+)/sites', [
            'methods'             => 'GET',
            'callback'            => [$this, 'indexByTenant'],
            'permission_callback' => '__return_true',
        ]);

        // GET /sites/{id}
        register_rest_route('sarah-ai-server/v1', '/sites/(?P<id>\d+)', [
            'methods'             => 'GET',
            'callback'            => [$this, 'show'],
            'permission_callback' => '__return_true',
        ]);

        // POST /sites/{id}/status
        register_rest_route('sarah-ai-server/v1', '/sites/(?P<id>\d+)/status', [
            'methods'             => 'POST',
            'callback'            => [$this, 'updateStatus'],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * Create a new site under a tenant.
     *
     * Body: tenant_id (required), name (required), url (required), meta (optional)
     */
    public function store(\WP_REST_Request $request): \WP_REST_Response
    {
        $tenantId = (int) $request->get_param('tenant_id');
        $name     = trim((string) ($request->get_param('name') ?? ''));
        $url      = trim((string) ($request->get_param('url') ?? ''));
        $meta     = $request->get_param('meta');

        if (! $tenantId || $name === '' || $url === '') {
            return new \WP_REST_Response(['success' => false, 'message' => 'tenant_id, name, and url are required'], 400);
        }

        if (! $this->tenants->findById($tenantId)) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Tenant not found'], 404);
        }

        $metaArray = is_array($meta) ? $meta : [];
        $siteId    = $this->sites->create($tenantId, $name, $url, $metaArray);
        $site      = $this->sites->findById($siteId);

        return new \WP_REST_Response(['success' => true, 'data' => $site], 201);
    }

    /** List all active sites for a tenant. */
    public function indexByTenant(\WP_REST_Request $request): \WP_REST_Response
    {
        $tenantId = (int) $request->get_param('id');

        if (! $this->tenants->findById($tenantId)) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Tenant not found'], 404);
        }

        $sites = $this->sites->findByTenant($tenantId);

        return new \WP_REST_Response(['success' => true, 'data' => $sites], 200);
    }

    /**
     * Full site context for admin visibility:
     * site record, assigned agent, account keys, site keys, knowledge resources.
     */
    public function show(\WP_REST_Request $request): \WP_REST_Response
    {
        $id   = (int) $request->get_param('id');
        $site = $this->sites->findById($id);

        if (! $site) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Site not found'], 404);
        }

        $agent = null;
        if ($site['active_agent_id']) {
            $agent = $this->agents->findById((int) $site['active_agent_id']);
        }

        return new \WP_REST_Response([
            'success' => true,
            'data'    => [
                'site'       => $site,
                'agent'      => $agent,
                'account_keys' => $this->accountKeys->findByTenant((int) $site['tenant_id']),
                'site_keys'    => $this->siteTokens->findBySite($id),
                'knowledge'    => $this->knowledge->findBySite($id),
            ],
        ], 200);
    }

    /** Update site lifecycle status. */
    public function updateStatus(\WP_REST_Request $request): \WP_REST_Response
    {
        $id     = (int) $request->get_param('id');
        $status = trim((string) ($request->get_param('status') ?? ''));

        $allowed = ['active', 'inactive', 'suspended'];
        if (! in_array($status, $allowed, true)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'status must be one of: ' . implode(', ', $allowed),
            ], 400);
        }

        if (! $this->sites->findById($id)) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Site not found'], 404);
        }

        $this->sites->updateStatus($id, $status);
        return new \WP_REST_Response(['success' => true, 'data' => $this->sites->findById($id)], 200);
    }
}
