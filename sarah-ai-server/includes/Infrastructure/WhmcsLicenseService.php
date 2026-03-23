<?php

declare(strict_types=1);

namespace SarahAiServer\Infrastructure;

/**
 * Validates WHMCS license keys against the WHMCS API.
 *
 * Cache strategy: the result is stored in the site row's whmcs_lastcheck column.
 * If whmcs_lastcheck is within 24 hours, skip the remote call and treat as valid.
 * When the key changes, whmcs_lastcheck must be reset to NULL to force re-validation.
 *
 * WHMCS License API endpoint (configurable via platform setting whmcs_api_url):
 *   POST {whmcs_api_url}/includes/api.php
 *   action=validateLicense&licensekey={key}&localkey=&check_token=
 *
 * Returns: status = Active | Invalid | Expired | Suspended
 */
class WhmcsLicenseService
{
    private SettingsRepository $settings;
    private SiteRepository     $sites;

    public function __construct()
    {
        $this->settings = new SettingsRepository();
        $this->sites    = new SiteRepository();
    }

    /**
     * Returns true if the site's WHMCS key is currently valid.
     * Uses whmcs_lastcheck for 24-hour caching — no remote call if cache is fresh.
     *
     * @param array $site  Full site row from the database.
     * @param string $whmcsKey  WHMCS key from the tenant row.
     */
    public function isValid(array $site, string $whmcsKey): bool
    {
        if ($whmcsKey === '') {
            return false;
        }

        // Cache hit: last check was within 24 hours
        if (! empty($site['whmcs_lastcheck'])) {
            $lastCheck = strtotime((string) $site['whmcs_lastcheck']);
            if ($lastCheck && (time() - $lastCheck) < 86400) {
                return true; // cached as valid
            }
        }

        // Cache miss: call WHMCS API
        $valid = $this->callWhmcsApi($whmcsKey);

        if ($valid) {
            // Update lastcheck timestamp so we skip for another 24 hours
            $this->sites->updateWhmcsLastcheck((int) $site['id']);
        }

        return $valid;
    }

    private function callWhmcsApi(string $licenseKey): bool
    {
        $apiUrl = $this->settings->get('whmcs_api_url', '', 'platform');
        if ($apiUrl === '') {
            // No WHMCS URL configured — treat key as valid (grace mode)
            return true;
        }

        $endpoint = rtrim($apiUrl, '/') . '/includes/api.php';

        $response = wp_remote_post($endpoint, [
            'timeout' => 10,
            'body'    => [
                'action'      => 'validateLicense',
                'licensekey'  => $licenseKey,
                'localkey'    => '',
                'check_token' => '',
            ],
        ]);

        if (is_wp_error($response)) {
            // Network error — fail open to avoid blocking legitimate customers
            return true;
        }

        $body   = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);

        if (! is_array($result)) {
            return true; // Unparseable response — fail open
        }

        $status = strtolower((string) ($result['status'] ?? ''));
        return $status === 'active';
    }
}
