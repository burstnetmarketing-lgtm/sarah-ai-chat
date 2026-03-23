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

/**
 * One-call provisioning endpoint for the sarah-ai-client Quick Setup wizard.
 *
 * POST /sarah-ai-server/v1/quick-setup
 * Auth: X-Sarah-Platform-Key header
 * Body:
 *   site_name  string  required — display name for the new site
 *   site_url   string  required — URL of the client WordPress site
 *   whmcs_key  string  optional — WHMCS license key; if valid, plan = customer
 *
 * Response:
 *   {
 *     success: true,
 *     data: {
 *       account_key: "...",
 *       site_key:    "...",
 *       agent_slug:  "..."
 *     }
 *   }
 *
 * The tenant name is derived from site_name.
 * A single account key and site key are issued automatically.
 * The default agent (from platform settings) is assigned to the site.
 */
class QuickSetupController
{
    private TenantRepository     $tenants;
    private SiteRepository       $sites;
    private AccountKeyRepository $accountKeys;
    private SiteTokenRepository  $siteTokens;
    private AgentRepository      $agents;
    private PlanRepository       $plans;
    private SettingsRepository   $settings;

    public function __construct()
    {
        $this->tenants     = new TenantRepository();
        $this->sites       = new SiteRepository();
        $this->accountKeys = new AccountKeyRepository();
        $this->siteTokens  = new SiteTokenRepository();
        $this->agents      = new AgentRepository();
        $this->plans       = new PlanRepository();
        $this->settings    = new SettingsRepository();
    }

    public function registerRoutes(): void
    {
        register_rest_route('sarah-ai-server/v1', '/quick-setup', [
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

        $siteName = trim((string) ($request->get_param('site_name') ?? ''));
        $siteUrl  = trim((string) ($request->get_param('site_url')  ?? ''));
        $whmcsKey = trim((string) ($request->get_param('whmcs_key') ?? ''));

        if ($siteName === '' || $siteUrl === '') {
            return new \WP_REST_Response(['success' => false, 'message' => 'site_name and site_url are required'], 400);
        }

        // Determine plan based on WHMCS key presence
        $planSlug = $whmcsKey !== '' ? 'customer' : 'trial';
        $plan     = $this->plans->findBySlug($planSlug) ?? $this->plans->findBySlug('trial');
        $planId   = $plan ? (int) $plan['id'] : null;

        // Create tenant (slug derived from site name)
        $slug     = $this->slugify($siteName);
        $slug     = $this->uniqueSlug($slug);
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

        return new \WP_REST_Response([
            'success' => true,
            'data'    => [
                'account_key' => $rawAccountKey,
                'site_key'    => $rawSiteKey,
                'agent_slug'  => $agent ? (string) $agent['slug'] : $defaultSlug,
                'plan'        => $planSlug,
                'site_uuid'   => (string) ($site['uuid'] ?? ''),
            ],
        ], 201);
    }

    private function generateKey(): string
    {
        return bin2hex(random_bytes(32));
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
