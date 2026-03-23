# Task 0067 — WHMCS Key Required Setting

## Goal
Add a platform setting `whmcs_key_required` (toggle) in the server admin Settings page.
When enabled, the `/quick-setup` endpoint rejects provisioning requests that do not include
a WHMCS license key.

## Behaviour
| Setting | whmcs_key in request | Result |
|---|---|---|
| OFF (default) | absent | trial plan created |
| OFF | present | customer plan created |
| ON | absent | 422 error returned |
| ON | present | customer plan created |

## Changes

### Modified: `sarah-ai-server/includes/Core/Seeder.php`
- Added `'whmcs_key_required' => '0'` to `seedSettings()` defaults (off by default)

### Modified: `sarah-ai-server/includes/Api/PlatformSettingsController.php`
- Added `'whmcs_key_required'` to `ALLOWED_KEYS`

### Modified: `sarah-ai-server/assets/src/pages/Settings.jsx`
- Added toggle switch "Require WHMCS key on Quick Setup" below Logging switch

### Modified: `sarah-ai-server/includes/Api/QuickSetupController.php`
- After validating required fields, reads `whmcs_key_required` setting
- If `'1'` and `whmcs_key === ''` → returns 422 with descriptive message

## Commit
0067
