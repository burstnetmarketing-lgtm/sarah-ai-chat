<?php

declare(strict_types=1);

namespace SarahAiServer\Api;

use SarahAiServer\Infrastructure\AccountKeyRepository;
use SarahAiServer\Infrastructure\SiteTokenRepository;
use SarahAiServer\Infrastructure\TenantRepository;
use SarahAiServer\Infrastructure\SiteRepository;
use SarahAiServer\Infrastructure\LicenseValidator;
use SarahAiServer\Infrastructure\SettingsRepository;

/**
 * Developer diagnostic endpoint — tests each step of the credential validation chain.
 *
 * POST /sarah-ai-server/v1/client/auth-test
 * Auth: X-Sarah-Platform-Key header (platform key required)
 * Body: { account_key, site_key }
 *
 * Returns a step-by-step breakdown showing which check passes or fails.
 * Useful for diagnosing 401 errors in the chat endpoint.
 */
class AuthTestController
{
    private AccountKeyRepository $accountKeys;
    private SiteTokenRepository  $siteTokens;
    private TenantRepository     $tenants;
    private SiteRepository       $sites;
    private LicenseValidator     $license;
    private SettingsRepository   $settings;

    public function __construct()
    {
        $this->accountKeys = new AccountKeyRepository();
        $this->siteTokens  = new SiteTokenRepository();
        $this->tenants     = new TenantRepository();
        $this->sites       = new SiteRepository();
        $this->license     = new LicenseValidator();
        $this->settings    = new SettingsRepository();
    }

    public function registerRoutes(): void
    {
        register_rest_route('sarah-ai-server/v1', '/client/auth-test', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handle'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function handle(\WP_REST_Request $request): \WP_REST_Response
    {
        // Require platform key
        $platformKey = (string) ($request->get_header('X-Sarah-Platform-Key') ?? '');
        $storedKey   = $this->settings->get('platform_api_key', '', 'platform');

        if ($platformKey === '' || $storedKey === '' || $platformKey !== $storedKey) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $accountKey = trim((string) ($request->get_param('account_key') ?? ''));
        $siteKey    = trim((string) ($request->get_param('site_key')    ?? ''));

        $steps = [];

        // Step 1: account key lookup
        $accountKeyRecord = $accountKey !== '' ? $this->accountKeys->findByRawKey($accountKey) : null;
        $steps[] = [
            'step'   => 1,
            'name'   => 'account_key → account key record',
            'passed' => $accountKeyRecord !== null,
            'detail' => $accountKeyRecord
                ? 'Found: id=' . $accountKeyRecord['id'] . ' status=' . $accountKeyRecord['status'] . ' tenant_id=' . $accountKeyRecord['tenant_id']
                : 'Not found (key may be wrong, revoked, or not yet issued)',
        ];

        if (! $accountKeyRecord) {
            return new \WP_REST_Response(['success' => false, 'overall' => false, 'steps' => $steps]);
        }

        // Step 2: tenant lookup
        $tenant = $this->tenants->findById((int) $accountKeyRecord['tenant_id']);
        $steps[] = [
            'step'   => 2,
            'name'   => 'tenant_id → tenant record',
            'passed' => $tenant !== null,
            'detail' => $tenant
                ? 'Found: id=' . $tenant['id'] . ' name=' . $tenant['name'] . ' status=' . $tenant['status'] . ' whmcs_key=' . ($tenant['whmcs_key'] ? '[set]' : '[null]')
                : 'Tenant not found or soft-deleted',
        ];

        if (! $tenant) {
            return new \WP_REST_Response(['success' => false, 'overall' => false, 'steps' => $steps]);
        }

        // Step 3: site key lookup
        $siteKeyRecord = $siteKey !== '' ? $this->siteTokens->findByRawToken($siteKey) : null;
        $steps[] = [
            'step'   => 3,
            'name'   => 'site_key → site token record',
            'passed' => $siteKeyRecord !== null,
            'detail' => $siteKeyRecord
                ? 'Found: id=' . $siteKeyRecord['id'] . ' status=' . $siteKeyRecord['status'] . ' site_id=' . $siteKeyRecord['site_id']
                : 'Not found (key may be wrong, revoked, or not yet issued)',
        ];

        if (! $siteKeyRecord) {
            return new \WP_REST_Response(['success' => false, 'overall' => false, 'steps' => $steps]);
        }

        // Step 4: site lookup
        $site = $this->sites->findById((int) $siteKeyRecord['site_id']);
        $steps[] = [
            'step'   => 4,
            'name'   => 'site_id → site record',
            'passed' => $site !== null,
            'detail' => $site
                ? 'Found: id=' . $site['id'] . ' status=' . $site['status'] . ' plan_id=' . ($site['plan_id'] ?? 'null') . ' tenant_id=' . $site['tenant_id']
                : 'Site not found or soft-deleted',
        ];

        if (! $site) {
            return new \WP_REST_Response(['success' => false, 'overall' => false, 'steps' => $steps]);
        }

        // Step 5: ownership check
        $ownershipOk = (int) $site['tenant_id'] === (int) $tenant['id'];
        $steps[] = [
            'step'   => 5,
            'name'   => 'ownership check (site.tenant_id === tenant.id)',
            'passed' => $ownershipOk,
            'detail' => 'site.tenant_id=' . $site['tenant_id'] . ' tenant.id=' . $tenant['id'],
        ];

        if (! $ownershipOk) {
            return new \WP_REST_Response(['success' => false, 'overall' => false, 'steps' => $steps]);
        }

        // Step 6: license check
        $licenseOk = $this->license->isActive($site);
        $whmcsApiUrl = $this->settings->get('whmcs_api_url', '', 'platform');
        $steps[] = [
            'step'   => 6,
            'name'   => 'license check',
            'passed' => $licenseOk,
            'detail' => 'plan_id=' . ($site['plan_id'] ?? 'null')
                . ' whmcs_api_url=' . ($whmcsApiUrl !== '' ? '[set]' : '[not set — grace mode]')
                . ' whmcs_key_on_tenant=' . ($tenant['whmcs_key'] ? '[set]' : '[null]'),
        ];

        $overall = $licenseOk;

        return new \WP_REST_Response([
            'success' => true,
            'overall' => $overall,
            'message' => $overall ? 'All checks passed — credentials are valid.' : 'License check failed. See step 6 detail.',
            'steps'   => $steps,
        ]);
    }
}
