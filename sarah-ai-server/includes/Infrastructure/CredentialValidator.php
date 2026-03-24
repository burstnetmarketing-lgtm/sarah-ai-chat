<?php

declare(strict_types=1);

namespace SarahAiServer\Infrastructure;

/**
 * Validates dual-credential client authentication and resolves full request context.
 *
 * Runtime validation sequence:
 *   1. account key  → tenant   (via AccountKeyRepository)
 *   2. site key     → site     (via SiteTokenRepository)
 *   3. site.tenant_id must equal tenant.id
 *
 * This three-step check ensures that credentials from different tenants or
 * unrelated sites cannot be mixed. The client sends only the two keys — no
 * tenant_id or site_id is required in the request payload.
 *
 * Used by Phase 4.4+ client-facing endpoints. Phase 4.3 provisions the
 * credentials; this class provides the runtime validation path.
 */
class CredentialValidator
{
    private AccountKeyRepository $accountKeys;
    private SiteTokenRepository $siteTokens;
    private TenantRepository $tenants;
    private SiteRepository $sites;
    private LicenseValidator $license;

    public function __construct()
    {
        $this->accountKeys = new AccountKeyRepository();
        $this->siteTokens  = new SiteTokenRepository();
        $this->tenants     = new TenantRepository();
        $this->sites       = new SiteRepository();
        $this->license     = new LicenseValidator();
    }

    /**
     * Validates the account key + site key pair and resolves the full context.
     *
     * Returns an array with keys 'tenant' and 'site' if the credentials are valid
     * and the site belongs to the tenant. Returns null if any check fails.
     *
     * Failure cases (all return null, no distinction exposed to caller):
     *   - Account key not found or revoked
     *   - Tenant not found or deleted
     *   - Site key not found or revoked
     *   - Site not found or deleted
     *   - Site does not belong to the tenant identified by the account key
     *
     * @return array{tenant: array, site: array}|null
     */
    public function resolveContext(string $accountKey, string $siteKey): ?array
    {
        // Step 1: account key → tenant
        $accountKeyRecord = $this->accountKeys->findByRawKey($accountKey);
        if (! $accountKeyRecord) {
            return null;
        }

        $tenant = $this->tenants->findById((int) $accountKeyRecord['tenant_id']);
        if (! $tenant) {
            return null;
        }

        // Step 2: site key → site
        $siteKeyRecord = $this->siteTokens->findByRawToken($siteKey);
        if (! $siteKeyRecord) {
            return null;
        }

        $site = $this->sites->findById((int) $siteKeyRecord['site_id']);
        if (! $site) {
            return null;
        }

        // Step 3: site must belong to the tenant identified by the account key
        if ((int) $site['tenant_id'] !== (int) $tenant['id']) {
            return null;
        }

        // Step 4: license check
        if (! $this->license->isActive($site)) {
            return null;
        }

        return [
            'tenant' => $tenant,
            'site'   => $site,
        ];
    }
}
