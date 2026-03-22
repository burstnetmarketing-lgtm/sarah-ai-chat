# Task 0038 — Server Admin Settings Page

## Goal
Add a Settings page to the sarah-ai-server admin panel so the OpenAI API key and other platform settings can be configured without direct database access.

## Problem
The `openai_api_key` had no UI for editing. The `MenuRepository::ensureCoreItems()` was actively removing the `settings` menu item. Admins had no way to switch from mock responses to real AI without modifying the database directly.

## Scope

### PHP
- `includes/Api/PlatformSettingsController.php` (new) — `GET /platform-settings` + `POST /platform-settings`
  - Allowed keys: `platform_name`, `openai_api_key`, `platform_api_key`, `logging_enabled`, `trial_duration_days`, `default_agent_slug`
  - OpenAI key masked on read (shows `••••••••{last4}`), placeholder not saved back
  - Auth: `manage_options`
- `includes/Core/Plugin.php` — registers `PlatformSettingsController`
- `sarah-ai-server.php` — `require_once` for new controller
- `includes/Infrastructure/MenuRepository.php` — added `settings` core item, removed `removeIfExists('settings')`

### React
- `assets/src/pages/Settings.jsx` (new) — form with all editable platform settings
  - OpenAI API Key: password field, masked display, "Set / Not set" badge
  - Logging: toggle switch
  - Save reloads settings after successful save to show updated masked key
- `assets/src/api/provisioning.js` — added `getPlatformSettings()` and `updatePlatformSettings()`
- `assets/src/App.jsx` — added `settings` route

## Commit
0029
