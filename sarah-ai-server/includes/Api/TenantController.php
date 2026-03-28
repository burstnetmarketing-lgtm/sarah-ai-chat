<?php

declare(strict_types=1);

namespace SarahAiServer\Api;

use SarahAiServer\Infrastructure\TenantRepository;
use SarahAiServer\Infrastructure\SiteRepository;
use SarahAiServer\Infrastructure\UserTenantRepository;
use SarahAiServer\Infrastructure\AccountKeyRepository;
use SarahAiServer\Infrastructure\SiteTokenRepository;
use SarahAiServer\Infrastructure\AgentRepository;
use SarahAiServer\Infrastructure\PlanRepository;
use SarahAiServer\Infrastructure\KnowledgeResourceRepository;
use SarahAiServer\Infrastructure\SiteApiKeyRepository;
use SarahAiServer\Infrastructure\WhmcsLicenseService;

class TenantController
{
    private TenantRepository            $tenants;
    private SiteRepository              $sites;
    private UserTenantRepository        $userTenants;
    private AccountKeyRepository        $accountKeys;
    private SiteTokenRepository         $siteTokens;
    private AgentRepository             $agents;
    private PlanRepository              $plans;
    private KnowledgeResourceRepository $knowledge;
    private SiteApiKeyRepository        $siteApiKeys;

    public function __construct()
    {
        $this->tenants     = new TenantRepository();
        $this->sites       = new SiteRepository();
        $this->userTenants = new UserTenantRepository();
        $this->accountKeys = new AccountKeyRepository();
        $this->siteTokens  = new SiteTokenRepository();
        $this->agents      = new AgentRepository();
        $this->plans       = new PlanRepository();
        $this->knowledge   = new KnowledgeResourceRepository();
        $this->siteApiKeys = new SiteApiKeyRepository();
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

        register_rest_route('sarah-ai-server/v1', '/tenants/quick-create', [
            'methods'             => 'POST',
            'callback'            => [$this, 'quickCreate'],
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

    /**
     * POST /tenants/quick-create
     *
     * One-call admin provisioning. Creates a tenant + site + keys + agent + KB seed.
     *
     * Body:
     *   site_url    string  required — full URL of the client site (must be unique)
     *   whmcs_key   string  required — WHMCS license key (must be active)
     *   agent_slug  string  optional — agent slug to assign (default: gpt-4o-mini)
     *
     * Response:
     *   {
     *     success: true,
     *     data: {
     *       tenant_uuid:  "...",
     *       site_uuid:    "...",
     *       account_key:  "...",   ← raw, shown once
     *       site_key:     "...",   ← raw, shown once
     *       tenant_name:  "...",
     *       agent_slug:   "..."
     *     }
     *   }
     */
    public function quickCreate(\WP_REST_Request $request): \WP_REST_Response
    {
        $siteUrl    = trim((string) ($request->get_param('site_url')       ?? ''));
        $whmcsKey   = trim((string) ($request->get_param('whmcs_key')      ?? ''));
        $openAiKey  = trim((string) ($request->get_param('openai_api_key') ?? ''));
        $agentSlug  = trim((string) ($request->get_param('agent_slug')     ?? 'gpt-4o-mini'));

        // ── Validate required fields ──────────────────────────────────────────
        if ($siteUrl === '') {
            return new \WP_REST_Response(['success' => false, 'message' => 'site_url is required'], 400);
        }
        if ($whmcsKey === '') {
            return new \WP_REST_Response(['success' => false, 'message' => 'whmcs_key is required'], 400);
        }
        if ($openAiKey === '') {
            return new \WP_REST_Response(['success' => false, 'message' => 'openai_api_key is required'], 400);
        }

        // Normalise URL: ensure trailing slash removed for consistency
        $siteUrl = rtrim($siteUrl, '/');

        // ── WHMCS license validation (hard-fail) ──────────────────────────────
        $whmcs  = new WhmcsLicenseService();
        $result = $whmcs->test($whmcsKey);
        $status = strtolower(trim((string) ($result['status'] ?? '')));
        if ($status !== 'active') {
            $desc = trim((string) ($result['description'] ?? ''));
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'WHMCS license key is not active' . ($desc !== '' ? ': ' . $desc : '.'),
            ], 422);
        }

        // ── Site URL uniqueness ───────────────────────────────────────────────
        if ($this->sites->findByUrl($siteUrl) !== null) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'A site with this URL already exists.',
            ], 409);
        }

        // ── Fetch site name from the URL ──────────────────────────────────────
        $siteName = $this->fetchSiteTitle($siteUrl);
        if ($siteName === '') {
            // Fallback: derive a readable name from the domain
            $host     = strtolower((string) parse_url($siteUrl, PHP_URL_HOST));
            $siteName = ucfirst(str_replace(['-', '_'], ' ', explode('.', ltrim($host, 'www.'))[0]));
        }

        // ── Create tenant ─────────────────────────────────────────────────────
        $slug     = $this->slugifyFromUrl($siteUrl) ?: $this->slugify($siteName);
        $slug     = $slug . '-' . date('YmdHi');
        $slug     = $this->uniqueSlug($slug);
        $tenantId = $this->tenants->create($siteName, $slug, 'active', [], $whmcsKey);
        $tenant   = $this->tenants->findById($tenantId);

        // ── Create site ───────────────────────────────────────────────────────
        $planSlug = 'customer'; // whmcs key is validated → customer plan
        $plan     = $this->plans->findBySlug($planSlug) ?? $this->plans->findBySlug('trial');
        $planId   = $plan ? (int) $plan['id'] : null;

        $siteId = $this->sites->create($tenantId, $siteName, $siteUrl, [], $planId);
        $site   = $this->sites->findById($siteId);

        // ── Issue keys ────────────────────────────────────────────────────────
        $rawAccountKey = bin2hex(random_bytes(32));
        $this->accountKeys->issue($tenantId, $rawAccountKey, 'quick-create');

        $rawSiteKey = bin2hex(random_bytes(32));
        $this->siteTokens->issue($siteId, $rawSiteKey, 'quick-create');

        // ── Assign agent ──────────────────────────────────────────────────────
        $agent = $this->agents->findBySlug($agentSlug) ?? $this->agents->findBySlug('gpt-4o-mini');
        if ($agent) {
            $this->sites->updateActiveAgent($siteId, (int) $agent['id']);
        }

        // ── Store OpenAI API key ───────────────────────────────────────────────
        $this->siteApiKeys->set($siteId, 'openai', $openAiKey);

        // ── Seed knowledge base: site URL ─────────────────────────────────────
        $this->knowledge->create($siteId, 'link', $siteName, $siteUrl . '/');

        // ── Mark setup complete ────────────────────────────────────────────────
        $this->tenants->markSetupComplete($tenantId);

        return new \WP_REST_Response([
            'success' => true,
            'data'    => [
                'tenant_uuid'  => (string) ($tenant['uuid']  ?? ''),
                'site_uuid'    => (string) ($site['uuid']    ?? ''),
                'account_key'  => $rawAccountKey,
                'site_key'     => $rawSiteKey,
                'tenant_name'  => $siteName,
                'agent_slug'   => $agent ? (string) $agent['slug'] : $agentSlug,
            ],
        ], 201);
    }

    /** Fetches the <title> tag from a URL; returns empty string on any failure. */
    private function fetchSiteTitle(string $url): string
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $host = preg_replace('/^www\./', '', $host);
        $name = explode('.', $host)[0] ?? '';
        return $name ? ucfirst($name) : '';
    }

    private function slugifyFromUrl(string $url): string
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $host = preg_replace('/^www\./', '', $host);
        $name = explode('.', $host)[0] ?? '';
        $slug = preg_replace('/[^a-z0-9-]+/', '', $name);
        return trim($slug, '-');
    }

    private function uniqueSlug(string $base): string
    {
        $existing = $this->tenants->findBySlug($base);
        if (! $existing) {
            return $base;
        }
        return $base . '-' . substr(bin2hex(random_bytes(3)), 0, 6);
    }

    private function slugify(string $name): string
    {
        $slug = strtolower($name);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        return trim($slug, '-');
    }
}
