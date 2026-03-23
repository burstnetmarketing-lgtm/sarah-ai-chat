<?php

declare(strict_types=1);

namespace SarahAiServer\Infrastructure;

/**
 * Validates WHMCS license keys against a BurstNET-compatible licensing endpoint.
 *
 * Architecture adapted from BurstDealer Pro's licensing implementation.
 *
 * Cache strategy (per license key):
 *   - WP transient `sarah_ai_whmcs_lic_{keyHash}`:
 *       active   → 3600 s  (1 hour)
 *       inactive → 300  s  (5 minutes)
 *   - WP option `sarah_ai_whmcs_lastgood_{keyHash}`:
 *       last known active result, used as fallback for up to 600 s if the
 *       licensing server is unreachable (fail-safe: never lock out valid customers)
 *   - WP option `sarah_ai_whmcs_localkey_{keyHash}`:
 *       WHMCS-issued local key returned by the verify endpoint; sent on
 *       subsequent requests to avoid full remote checks on every call.
 *
 * Endpoint (configurable via platform setting `whmcs_api_url`):
 *   POST {whmcs_api_url}/modules/servers/licensing/verify.php
 *
 * If `whmcs_api_url` is not set, all keys are treated as valid (grace mode).
 *
 * Optional HMAC-SHA256 signature verification:
 *   Define the constant SARAH_AI_WHMCS_LICENSE_SECRET in wp-config.php.
 *   If the constant is not defined, signature checking is skipped.
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

    // ─── Public API ───────────────────────────────────────────────────────────

    /**
     * Returns true if the WHMCS key is currently valid.
     *
     * @param array  $site     Full site row (used to update whmcs_lastcheck on success).
     * @param string $whmcsKey WHMCS license key from the tenant row.
     */
    public function isValid(array $site, string $whmcsKey): bool
    {
        if ($whmcsKey === '') {
            return false;
        }

        $result = $this->getResult($whmcsKey);
        $status = strtolower(trim((string) ($result['status'] ?? '')));
        $valid  = ($status === 'active');

        if ($valid) {
            // Update DB timestamp for audit/display purposes.
            $this->sites->updateWhmcsLastcheck((int) $site['id']);
        }

        return $valid;
    }

    /**
     * Bypass cache and return the full raw result from the licensing server.
     * Used by the WHMCS Test page in the admin dashboard.
     *
     * @param string $licenseKey WHMCS license key to test.
     * @return array  Full decoded response including status, description, validdomain, validip, etc.
     */
    public function test(string $licenseKey): array
    {
        $licenseKey = trim($licenseKey);
        if ($licenseKey === '') {
            return ['status' => 'Invalid', 'description' => 'License key is empty.'];
        }
        return $this->callVerifyEndpoint($licenseKey);
    }

    // ─── Cache Layer ──────────────────────────────────────────────────────────

    /**
     * Returns the cached or freshly-fetched license result array for a key.
     */
    private function getResult(string $licenseKey): array
    {
        $hash        = md5($licenseKey);
        $cacheKey    = 'sarah_ai_whmcs_lic_' . $hash;
        $lastGoodKey = 'sarah_ai_whmcs_lastgood_' . $hash;

        $cached = get_transient($cacheKey);
        if (is_array($cached) && ! empty($cached)) {
            return $cached;
        }

        $result = $this->callVerifyEndpoint($licenseKey);
        $status = strtolower(trim((string) ($result['status'] ?? '')));

        if ($status === 'active') {
            update_option($lastGoodKey, $result, false);
            set_transient($cacheKey, $result, 3600);
            return $result;
        }

        // Failed — try last known good fallback.
        $lastGood       = get_option($lastGoodKey, []);
        $lastGoodStatus = is_array($lastGood) ? strtolower(trim((string) ($lastGood['status'] ?? ''))) : '';
        if ($lastGoodStatus === 'active') {
            set_transient($cacheKey, $lastGood, 600);
            return $lastGood;
        }

        // Genuinely inactive — cache briefly to avoid hammering the server.
        set_transient($cacheKey, $result, 300);
        return $result;
    }

    // ─── Remote Call ──────────────────────────────────────────────────────────

    private function callVerifyEndpoint(string $licenseKey): array
    {
        $apiUrl = $this->settings->get('whmcs_api_url', '', 'platform');
        if ($apiUrl === '') {
            // No endpoint configured — grace mode: treat all keys as valid.
            return ['status' => 'Active'];
        }

        $endpoint = rtrim($apiUrl, '/') . '/modules/servers/licensing/verify.php';

        $postfields = [
            'licensekey' => $licenseKey,
            'domain'     => $this->currentDomain(),
            'ip'         => $this->currentIp(),
            'dir'        => $this->pluginDir(),
        ];

        // Attach cached local key if available.
        $hash        = md5($licenseKey);
        $localKeyOpt = 'sarah_ai_whmcs_localkey_' . $hash;
        $localKey    = trim((string) get_option($localKeyOpt, ''));
        if ($localKey !== '') {
            $postfields['localkey'] = $localKey;
        }

        $result = $this->httpPost($endpoint, $postfields);
        $status = strtolower(trim((string) ($result['status'] ?? '')));

        // If inactive and a local key was used, retry without it (key may be stale).
        if ($status !== 'active' && $localKey !== '') {
            unset($postfields['localkey']);
            $result = $this->httpPost($endpoint, $postfields);
            $status = strtolower(trim((string) ($result['status'] ?? '')));
            if ($status === 'active') {
                update_option($localKeyOpt, '');  // Clear stale local key.
            }
        }

        // Persist new local key if the server returned one.
        $newLocalKey = trim((string) ($result['localkey'] ?? ''));
        if ($newLocalKey !== '') {
            update_option($localKeyOpt, $newLocalKey);
        }

        // Verify HMAC-SHA256 signature (optional — skipped if secret not defined).
        if (! $this->verifySignature($result, $licenseKey)) {
            $result['status']      = 'Invalid';
            $result['description'] = $result['description'] ?? 'Signature mismatch';
        }

        return $result;
    }

    // ─── HTTP ─────────────────────────────────────────────────────────────────

    private function httpPost(string $endpoint, array $postfields): array
    {
        $response = wp_remote_post($endpoint, [
            'timeout' => 20,
            'body'    => $postfields,
            'headers' => ['Accept' => 'application/json, text/plain, */*'],
        ]);

        if (is_wp_error($response)) {
            return ['status' => 'Invalid', '_error' => $response->get_error_message()];
        }

        $raw     = (string) wp_remote_retrieve_body($response);
        $decoded = $this->decodeResponse($raw);

        if (empty($decoded)) {
            return ['status' => 'Invalid', '_raw' => $raw];
        }

        $decoded['_raw'] = $raw;
        return $decoded;
    }

    // ─── Response Parser ──────────────────────────────────────────────────────

    /**
     * Decodes a WHMCS verify response in any of the supported formats:
     * JSON, PHP serialized, key=value lines, query-string, XML tags.
     */
    private function decodeResponse(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }

        // JSON
        $decoded = json_decode($raw, true);
        if (is_array($decoded) && ! empty($decoded)) {
            return $decoded;
        }

        // PHP serialized (no object instantiation — security safe)
        if (preg_match('/^[abisOdN]:/', $raw)) {
            $unserialized = @unserialize($raw, ['allowed_classes' => false]);
            if (is_array($unserialized) && ! empty($unserialized)) {
                return $unserialized;
            }
        }

        // key=value line format
        $results    = [];
        $normalized = str_replace(["\r\n", "\r"], "\n", $raw);
        foreach (explode("\n", $normalized) as $line) {
            $line = trim((string) $line);
            if ($line === '' || strpos($line, '=') === false) {
                continue;
            }
            [$k, $v] = array_pad(explode('=', $line, 2), 2, '');
            $k = trim((string) $k);
            if ($k !== '') {
                $results[$k] = trim((string) $v);
            }
        }
        if (! empty($results)) {
            return $results;
        }

        // Query-string format
        if (strpos($raw, '&') !== false && strpos($raw, '=') !== false) {
            $tmp = [];
            parse_str($raw, $tmp);
            if (is_array($tmp) && ! empty($tmp)) {
                return $tmp;
            }
        }

        // XML-like tag format
        if (strpos($raw, '<') !== false && strpos($raw, '>') !== false) {
            $xmlMapped = [];
            if (preg_match_all('/<([a-zA-Z0-9_]+)>(.*?)<\/\1>/s', $raw, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $m) {
                    $key = strtolower(trim((string) ($m[1] ?? '')));
                    $val = trim(html_entity_decode((string) ($m[2] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                    if ($key !== '') {
                        $xmlMapped[$key] = $val;
                    }
                }
            }
            if (! empty($xmlMapped)) {
                // Normalise alternative key names used by some WHMCS setups.
                if (isset($xmlMapped['validdomains']) && ! isset($xmlMapped['validdomain'])) {
                    $xmlMapped['validdomain'] = $xmlMapped['validdomains'];
                }
                if (isset($xmlMapped['validdirs']) && ! isset($xmlMapped['validdirectory'])) {
                    $xmlMapped['validdirectory'] = $xmlMapped['validdirs'];
                }
                return $xmlMapped;
            }
        }

        return [];
    }

    // ─── Signature Verification ───────────────────────────────────────────────

    private function verifySignature(array $result, string $licenseKey): bool
    {
        $secret = defined('SARAH_AI_WHMCS_LICENSE_SECRET')
            ? trim((string) SARAH_AI_WHMCS_LICENSE_SECRET)
            : '';

        if ($secret === '') {
            return true;  // No secret defined — skip verification.
        }

        $provided = trim((string) ($result['signature'] ?? ''));
        if ($provided === '') {
            // Server did not include a signature — skip verification.
            return true;
        }

        $payload = implode('|', [
            $licenseKey,
            (string) ($result['status']          ?? ''),
            (string) ($result['validdomain']      ?? ''),
            (string) ($result['validip']          ?? ''),
            (string) ($result['validdirectory']   ?? ''),
            (string) ($result['nextduedate']      ?? ''),
            (string) ($result['expirydate']       ?? ''),
            (string) ($result['regdate']          ?? ''),
            (string) ($result['checkdate']        ?? ''),
            (string) ($result['localkey']         ?? ''),
        ]);

        return hash_equals(
            strtolower(hash_hmac('sha256', $payload, $secret)),
            strtolower($provided)
        );
    }

    // ─── Environment Helpers ──────────────────────────────────────────────────

    private function currentDomain(): string
    {
        $domain = (string) parse_url(home_url('/'), PHP_URL_HOST);
        if ($domain === '' && isset($_SERVER['HTTP_HOST'])) {
            $domain = (string) $_SERVER['HTTP_HOST'];
        }
        if ($domain === '' && isset($_SERVER['SERVER_NAME'])) {
            $domain = (string) $_SERVER['SERVER_NAME'];
        }
        return $domain;
    }

    private function currentIp(): string
    {
        $ip     = isset($_SERVER['SERVER_ADDR']) ? (string) $_SERVER['SERVER_ADDR'] : '';
        $domain = $this->currentDomain();

        // Resolve private/loopback IPs to the public IP via DNS.
        if ($ip === '' || preg_match('/^(127\.|10\.|172\.(1[6-9]|2\d|3[01])\.|192\.168\.)/', $ip)) {
            if ($domain !== '') {
                $resolved = (string) gethostbyname($domain);
                if ($resolved !== '' && $resolved !== $domain) {
                    $ip = $resolved;
                }
            }
        }

        return $ip;
    }

    private function pluginDir(): string
    {
        return wp_normalize_path((string) dirname(__DIR__, 2));
    }
}
