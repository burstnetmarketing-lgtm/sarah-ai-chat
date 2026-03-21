# Task 0008: Remove Settings Page from Client

- **Task Number:** 0008
- **Title:** Remove Settings Page from Client
- **Version:** 0.2.0
- **Date:** 2026-03-22

---

## User Request

Remove the Settings > Sarah AI Client page entirely from sarah-ai-client.

---

## Implementation Summary

- Deleted `includes/Admin/SettingsPage.php`.
- Removed `SettingsPage` and `SettingsRepository` use statements and boot calls from `Plugin.php`.
- Removed `SettingsPage.php` require from `sarah-ai-client.php`.

---

## Affected Files

- `sarah-ai-client/includes/Admin/SettingsPage.php` — deleted
- `sarah-ai-client/includes/Core/Plugin.php` — cleaned up
- `sarah-ai-client/sarah-ai-client.php` — removed require

