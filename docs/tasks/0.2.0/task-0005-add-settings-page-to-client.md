# Task 0005: Add Settings Page to Client

- **Task Number:** 0005
- **Title:** Add Settings Page to Client
- **Version:** 0.2.0
- **Date:** 2026-03-22

---

## User Request

Add a simple WordPress admin settings page to `sarah-ai-client`. No full dashboard — just a settings page under Settings > Sarah AI Client where future phases can expose options like button label, welcome message, etc.

---

## Implementation Summary

- Created `includes/Admin/SettingsPage.php` — registers a WP Settings API section and renders the settings form. Fields are intentionally empty for now; phase 2–3 will add them.
- Created `includes/Admin/AdminMenu.php` — registers the page under Settings (`add_options_page`), not as a top-level menu.
- Updated `includes/Core/Plugin.php` — boots `SettingsPage` and `AdminMenu` only when `is_admin()`.
- Updated `sarah-ai-client.php` — added requires for the two new Admin files.

---

## Affected Files

- `sarah-ai-client/includes/Admin/SettingsPage.php` — created
- `sarah-ai-client/includes/Admin/AdminMenu.php` — created
- `sarah-ai-client/includes/Core/Plugin.php` — updated
- `sarah-ai-client/sarah-ai-client.php` — updated

---

## Archive Notes

- Used `add_options_page` (under Settings menu) rather than `add_menu_page` — keeps the admin footprint minimal for a client-side plugin.
- `SettingsRepository` is injected into `SettingsPage` so future fields can read/write values easily.
- The `register_setting` group is `sarah_ai_client_settings` and the option key is `sarah_ai_client_options`.

---

## Follow-up Notes

- Phase 2–3: add fields to `SettingsPage::registerSettings()` (button label, welcome message, server URL, etc.).
