<?php

declare(strict_types=1);

namespace SarahAiServer\Api;

use SarahAiServer\Infrastructure\TenantRepository;
use SarahAiServer\Infrastructure\SiteRepository;
use SarahAiServer\Infrastructure\AccountKeyRepository;
use SarahAiServer\Infrastructure\SiteTokenRepository;
use SarahAiServer\Infrastructure\AgentRepository;
use SarahAiServer\Infrastructure\PlanRepository;
use SarahAiServer\Infrastructure\SettingsRepository;
use SarahAiServer\Infrastructure\SiteApiKeyRepository;
use SarahAiServer\Infrastructure\KnowledgeResourceRepository;
use SarahAiServer\Infrastructure\WhmcsLicenseService;

/**
 * One-call provisioning endpoint for the sarah-ai-client Quick Setup wizard.
 *
 * POST /sarah-ai-server/v1/quick-setup
 * Auth: X-Sarah-Platform-Key header
 * Body:
 *   site_name      string  required — display name for the new site
 *   site_url       string  required — URL of the client WordPress site
 *   whmcs_key      string  optional — WHMCS license key; activates customer plan
 *   openai_api_key string  optional — site's own OpenAI key; falls back to platform key if omitted
 *   kb_link        string  optional — URL to seed as the first knowledge base entry
 *
 * Response:
 *   {
 *     success: true,
 *     data: {
 *       account_key:    "...",
 *       site_key:       "...",
 *       agent_slug:     "...",
 *       plan:           "trial|customer",
 *       site_uuid:      "...",
 *       has_openai_key: bool,
 *       has_kb:         bool
 *     }
 *   }
 *
 * The tenant name is derived from site_name.
 * A single account key and site key are issued automatically.
 * The default agent (from platform settings) is assigned to the site.
 */
class QuickSetupController
{
    private TenantRepository              $tenants;
    private SiteRepository                $sites;
    private AccountKeyRepository          $accountKeys;
    private SiteTokenRepository           $siteTokens;
    private AgentRepository               $agents;
    private PlanRepository                $plans;
    private SettingsRepository            $settings;
    private SiteApiKeyRepository          $siteApiKeys;
    private KnowledgeResourceRepository   $knowledge;

    public function __construct()
    {
        $this->tenants     = new TenantRepository();
        $this->sites       = new SiteRepository();
        $this->accountKeys = new AccountKeyRepository();
        $this->siteTokens  = new SiteTokenRepository();
        $this->agents      = new AgentRepository();
        $this->plans       = new PlanRepository();
        $this->settings    = new SettingsRepository();
        $this->siteApiKeys = new SiteApiKeyRepository();
        $this->knowledge   = new KnowledgeResourceRepository();
    }

    public function registerRoutes(): void
    {
        register_rest_route('sarah-ai-server/v1', '/quick-setup', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handle'],
            'permission_callback' => '__return_true',
        ]);

        // Client-scoped alias — same handler, grouped under /client/ for consistency
        register_rest_route('sarah-ai-server/v1', '/client/setup', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handle'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function handle(\WP_REST_Request $request): \WP_REST_Response
    {
        // Authenticate via X-Sarah-Platform-Key
        $platformKey    = (string) ($request->get_header('X-Sarah-Platform-Key') ?? '');
        $storedKey      = $this->settings->get('platform_api_key', '', 'platform');

        if ($platformKey === '' || $storedKey === '' || $platformKey !== $storedKey) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $siteName    = trim((string) ($request->get_param('site_name')      ?? ''));
        $siteUrl     = trim((string) ($request->get_param('site_url')       ?? ''));
        $whmcsKey    = trim((string) ($request->get_param('whmcs_key')      ?? ''));
        $openAiKey   = trim((string) ($request->get_param('openai_api_key') ?? ''));

        if ($siteName === '' || $siteUrl === '') {
            return new \WP_REST_Response(['success' => false, 'message' => 'site_name and site_url are required'], 400);
        }

        if ($whmcsKey === '') {
            return new \WP_REST_Response(['success' => false, 'message' => 'whmcs_key is required'], 422);
        }

        if ($openAiKey === '') {
            return new \WP_REST_Response(['success' => false, 'message' => 'openai_api_key is required'], 422);
        }

        // Enforce WHMCS key if platform requires it
        $whmcsRequired = $this->settings->get('whmcs_key_required', '0', 'platform') === '1';
        if ($whmcsRequired && $whmcsKey === '') {
            return new \WP_REST_Response(['success' => false, 'message' => 'A valid WHMCS license key is required to activate this service.'], 422);
        }

        // Validate WHMCS key against licensing server before provisioning
        if ($whmcsKey !== '') {
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
        }

        // Determine plan based on WHMCS key presence
        $planSlug = $whmcsKey !== '' ? 'customer' : 'trial';
        $plan     = $this->plans->findBySlug($planSlug) ?? $this->plans->findBySlug('trial');
        $planId   = $plan ? (int) $plan['id'] : null;

        // Create tenant slug: domain label + creation timestamp
        // e.g. https://my-shop.com.au → "my-shop-202603241100"
        $slug = ($this->slugifyFromUrl($siteUrl) ?: $this->slugify($siteName))
              . '-' . date('YmdHi');
        $slug = $this->uniqueSlug($slug);
        $tenantId = $this->tenants->create($siteName, $slug, 'active', [], $whmcsKey);

        // Create site
        $siteId = $this->sites->create($tenantId, $siteName, $siteUrl, [], $planId);
        $site   = $this->sites->findById($siteId);

        // Issue account key (raw — returned once, never stored in plain)
        $rawAccountKey = $this->generateKey();
        $this->accountKeys->issue($tenantId, $rawAccountKey, 'quick-setup');

        // Issue site key
        $rawSiteKey = $this->generateKey();
        $this->siteTokens->issue($siteId, $rawSiteKey, 'quick-setup');

        // Assign default agent
        $defaultSlug = $this->settings->get('default_agent_slug', 'gpt-4o-mini', 'platform');
        $agent       = $this->agents->findBySlug($defaultSlug) ?? $this->agents->findBySlug('gpt-4o-mini');
        if ($agent) {
            $this->sites->updateActiveAgent($siteId, (int) $agent['id']);
        }

        // Optional: store site's own OpenAI API key
        $hasOpenAiKey = false;
        if ($openAiKey !== '') {
            $this->siteApiKeys->set($siteId, 'openai', $openAiKey);
            $hasOpenAiKey = true;
        }

        // Auto-seed knowledge base resources (created only — processing is triggered by the admin UI).
        $hasKb    = false;
        $siteBase = rtrim($siteUrl, '/');

        // KB 1: llms-full.txt — if it exists on the client site
        $llmsUrl = $siteBase . '/llms-full.txt';
        $head    = wp_remote_head($llmsUrl, ['timeout' => 5, 'sslverify' => false]);
        if (! is_wp_error($head) && (int) wp_remote_retrieve_response_code($head) === 200) {
            $this->knowledge->create($siteId, 'link', 'LLMs Full Text', $llmsUrl);
            $hasKb = true;
        }

        // KB 2: site URL itself
        $this->knowledge->create($siteId, 'link', $siteName, $siteBase . '/');
        $hasKb = true;

        return new \WP_REST_Response([
            'success' => true,
            'data'    => [
                'account_key'    => $rawAccountKey,
                'site_key'       => $rawSiteKey,
                'agent_slug'     => $agent ? (string) $agent['slug'] : $defaultSlug,
                'plan'           => $planSlug,
                'site_uuid'      => (string) ($site['uuid'] ?? ''),
                'has_openai_key' => $hasOpenAiKey,
                'has_kb'         => $hasKb,
            ],
        ], 201);
    }

    private function generateKey(): string
    {
        return bin2hex(random_bytes(32));
    }

    private function slugifyFromUrl(string $url): string
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $host = preg_replace('/^www\./', '', $host);
        $name = explode('.', $host)[0] ?? '';
        $slug = preg_replace('/[^a-z0-9-]+/', '', $name);
        return trim($slug, '-');
    }

    private function slugify(string $name): string
    {
        $slug = strtolower($name);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        return trim($slug, '-');
    }

    private function uniqueSlug(string $base): string
    {
        $slug     = $base;
        $existing = $this->tenants->findBySlug($slug);
        if (! $existing) {
            return $slug;
        }
        // Append a short random suffix to avoid collision
        return $slug . '-' . substr(bin2hex(random_bytes(3)), 0, 6);
    }
}
