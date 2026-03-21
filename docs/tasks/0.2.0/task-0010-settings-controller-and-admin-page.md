# Task 0010: Settings Controller and Admin Page

- **Task Number:** 0010
- **Title:** Settings Controller and Admin Page
- **Version:** 0.2.0
- **Date:** 2026-03-22

---

## User Request

Add a Settings admin page (in our own dashboard, not WordPress settings) with a widget enable/disable toggle. Settings are stored in our own DB via SettingsRepository.

---

## Implementation Summary

- Created `includes/Api/SettingsController.php` — REST endpoints `GET/POST sarah-ai-client/v1/widget-settings` for reading and writing `widget_enabled`.
- Created `assets/src/pages/Settings.jsx` — admin page with Bootstrap toggle switch to enable/disable the chat widget.
- Added `Settings` to `App.jsx` VIEWS and `Topbar.jsx` LABELS.
- Updated `Plugin.php`: registers SettingsController, reads `widget_enabled` from DB and conditionally enqueues widget assets + injects `#sarah-chat-root` on the frontend.
- Updated `sarah-ai-client.php` to require `SettingsController.php`.

---

## Affected Files

- `sarah-ai-client/includes/Api/SettingsController.php` — created
- `sarah-ai-client/assets/src/pages/Settings.jsx` — created
- `sarah-ai-client/assets/src/App.jsx` — added Settings view
- `sarah-ai-client/assets/src/components/Topbar.jsx` — added Settings label
- `sarah-ai-client/includes/Core/Plugin.php` — updated
- `sarah-ai-client/sarah-ai-client.php` — added SettingsController require
