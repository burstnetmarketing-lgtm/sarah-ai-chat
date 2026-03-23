# Task 0069 — WHMCS Test Page (Admin Dashboard)

## Goal
Add a "WHMCS Test" page to the server admin dashboard that lets the admin test any
WHMCS license key directly against the licensing server, bypassing the cache.

## New Files

### `sarah-ai-server/includes/Api/WhmcsTestController.php`
- `POST /sarah-ai-server/v1/whmcs-test` (WP Admin auth)
- Calls `WhmcsLicenseService::test($licenseKey)` — bypasses cache
- Returns: `{ is_active, whmcs_api_url, result: { status, description, validdomain, validip, ... } }`

### `sarah-ai-server/assets/src/pages/WhmcsTest.jsx`
- Input field for license key
- "Test Key" button with loading spinner
- Shows endpoint URL being used
- Result card: status badge (green/red) + full response table

## Modified Files

### `WhmcsLicenseService.php`
- Added `public test(string $licenseKey): array` — calls `callVerifyEndpoint()` directly, no cache

### `sarah-ai-server.php`
- Added `require_once` for `WhmcsTestController.php`

### `Plugin.php`
- Registered `WhmcsTestController` route
- Bumped `DB_VERSION` to `0.1.22`

### `App.jsx`
- Added `'whmcs-test' => WhmcsTest` to VIEWS and LABELS

### `MenuRepository::ensureCoreItems()`
- Added `whmcs-test` menu item (seeded on next boot after DB_VERSION bump)

## Commit
0069
