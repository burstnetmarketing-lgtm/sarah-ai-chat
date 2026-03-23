# Task 0068 — WhmcsLicenseService Rewrite (BurstDealer Pro Architecture)

## Goal
Rewrite `WhmcsLicenseService.php` to match the proven BurstDealer Pro licensing pattern:
proper caching, local key support, multi-format response parser, HMAC signature verification,
and a last-known-good fallback so legitimate customers are never locked out due to transient
network issues.

## Key Changes vs. Previous Implementation

| Aspect | Before | After |
|---|---|---|
| Endpoint | `/includes/api.php` (WHMCS API) | `/modules/servers/licensing/verify.php` (BurstNET licensing module) |
| Request params | `licensekey` only | + `domain`, `ip`, `dir`, `localkey` |
| Cache storage | `whmcs_lastcheck` DB column (24h) | WP transient per key (active: 1h / inactive: 5min) |
| Last-good fallback | None | WP option, used for 10min if server unreachable |
| Local key | Not supported | Stored per-key in WP options, sent on subsequent requests |
| Response formats | JSON only | JSON + PHP serialized + key=value + querystring + XML |
| Signature verification | None | HMAC-SHA256 (optional — requires `SARAH_AI_WHMCS_LICENSE_SECRET` in wp-config) |

## Cache Keys (per license key, keyed by `md5($licenseKey)`)
- `sarah_ai_whmcs_lic_{hash}` — WP transient (result)
- `sarah_ai_whmcs_lastgood_{hash}` — WP option (last active result)
- `sarah_ai_whmcs_localkey_{hash}` — WP option (WHMCS-issued local key)

## Optional Configuration
Add to `wp-config.php` to enable signature verification:
```php
define('SARAH_AI_WHMCS_LICENSE_SECRET', 'your-secret');
```

## Public API (unchanged)
```php
$service->isValid(array $site, string $whmcsKey): bool
```
- `$site['id']` used to update `whmcs_lastcheck` on successful validation (audit trail)
- Grace mode: if `whmcs_api_url` platform setting is empty, all keys return `true`

## Commit
0068
