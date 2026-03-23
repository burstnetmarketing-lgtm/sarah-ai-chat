<?php

declare(strict_types=1);

namespace SarahAiServer\Infrastructure;

/**
 * Determines whether a site is licensed to use the Sarah AI service.
 *
 * Two plans are recognised:
 *
 *   trial    — No WHMCS key required. Access is allowed for plan.duration_days
 *              days from the site's created_at timestamp. Once expired, blocked.
 *
 *   customer — Requires a valid WHMCS key on the tenant. The key is validated
 *              via WhmcsLicenseService (24-hour cache via site.whmcs_lastcheck).
 *              If the key is invalid or missing, access is blocked.
 *
 * Any site whose plan_id is NULL is treated as trial with the default duration.
 */
class LicenseValidator
{
    private const DEFAULT_TRIAL_DAYS = 30;

    private PlanRepository       $plans;
    private TenantRepository     $tenants;
    private WhmcsLicenseService  $whmcs;

    public function __construct()
    {
        $this->plans   = new PlanRepository();
        $this->tenants = new TenantRepository();
        $this->whmcs   = new WhmcsLicenseService();
    }

    /**
     * Returns true if the site is allowed to make API calls.
     *
     * @param array $site  Full site row (must include id, tenant_id, plan_id, created_at, whmcs_lastcheck).
     */
    public function isActive(array $site): bool
    {
        $tenant = $this->tenants->findById((int) $site['tenant_id']);
        if (! $tenant) {
            return false;
        }

        $planSlug = $this->resolvePlanSlug((int) ($site['plan_id'] ?? 0));

        if ($planSlug === 'customer') {
            $whmcsKey = (string) ($tenant['whmcs_key'] ?? '');
            return $this->whmcs->isValid($site, $whmcsKey);
        }

        // trial (or unknown plan) — check duration
        return $this->isTrialActive($site);
    }

    private function resolvePlanSlug(int $planId): string
    {
        if ($planId <= 0) {
            return 'trial';
        }
        $plan = $this->plans->findById($planId);
        return $plan ? (string) ($plan['slug'] ?? 'trial') : 'trial';
    }

    private function isTrialActive(array $site): bool
    {
        $planId   = (int) ($site['plan_id'] ?? 0);
        $duration = self::DEFAULT_TRIAL_DAYS;

        if ($planId > 0) {
            $plan     = $this->plans->findById($planId);
            $duration = $plan ? (int) ($plan['duration_days'] ?? self::DEFAULT_TRIAL_DAYS) : self::DEFAULT_TRIAL_DAYS;
        }

        if ($duration <= 0) {
            return true; // 0 = unlimited trial
        }

        $createdAt = strtotime((string) ($site['created_at'] ?? ''));
        if (! $createdAt) {
            return false;
        }

        $expiresAt = $createdAt + ($duration * 86400);
        return time() <= $expiresAt;
    }
}
