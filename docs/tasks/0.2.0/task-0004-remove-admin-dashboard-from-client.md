# Task 0004: Remove Admin Dashboard from Client

- **Task Number:** 0004
- **Title:** Remove Admin Dashboard from Client
- **Version:** 0.2.0
- **Date:** 2026-03-22

---

## User Request

The `sarah-ai-client` plugin does not need a WordPress admin dashboard. It is a frontend chat widget only. Remove all admin-related code, leaving a clean base for the chat widget UI.

---

## Implementation Summary

- Deleted `includes/Admin/AdminMenu.php` and `includes/Admin/DashboardPage.php`.
- Deleted `includes/Api/MenuItemsController.php` and `includes/Api/LogController.php`.
- Deleted `includes/DB/MenuTable.php` and `includes/Infrastructure/MenuRepository.php`.
- Deleted React admin components: `Sidebar.jsx`, `Topbar.jsx`, `menu/MenuAccordion.jsx`.
- Deleted React admin pages: `Dashboard.jsx`, `Log.jsx`, `MenuManager.jsx`.
- Deleted `assets/src/hooks/useMenuItems.js` and `assets/css/admin.css`.
- Rewrote `Plugin.php` to enqueue assets on the frontend (`wp_enqueue_scripts`) and inject `<div id="sarah-chat-root"></div>` in the footer.
- Simplified `Activator.php` to only create `SettingsTable`.
- Cleaned `sarah-ai-client.php` to remove all deleted requires.
- Rewrote `main.jsx` to mount React on `#sarah-chat-root` without Bootstrap admin imports.
- Replaced `App.jsx` with an empty placeholder (`return null`).

---

## Affected Files

- `sarah-ai-client/sarah-ai-client.php` — removed admin/api/menu requires
- `sarah-ai-client/includes/Core/Plugin.php` — rewritten for frontend enqueue
- `sarah-ai-client/includes/Core/Activator.php` — simplified
- `sarah-ai-client/includes/Admin/AdminMenu.php` — deleted
- `sarah-ai-client/includes/Admin/DashboardPage.php` — deleted
- `sarah-ai-client/includes/Api/MenuItemsController.php` — deleted
- `sarah-ai-client/includes/Api/LogController.php` — deleted
- `sarah-ai-client/includes/DB/MenuTable.php` — deleted
- `sarah-ai-client/includes/Infrastructure/MenuRepository.php` — deleted
- `sarah-ai-client/assets/src/App.jsx` — replaced with empty placeholder
- `sarah-ai-client/assets/src/main.jsx` — mounts on #sarah-chat-root
- `sarah-ai-client/assets/src/components/Sidebar.jsx` — deleted
- `sarah-ai-client/assets/src/components/Topbar.jsx` — deleted
- `sarah-ai-client/assets/src/components/menu/MenuAccordion.jsx` — deleted
- `sarah-ai-client/assets/src/pages/Dashboard.jsx` — deleted
- `sarah-ai-client/assets/src/pages/Log.jsx` — deleted
- `sarah-ai-client/assets/src/pages/MenuManager.jsx` — deleted
- `sarah-ai-client/assets/src/hooks/useMenuItems.js` — deleted
- `sarah-ai-client/assets/css/admin.css` — deleted

---

## Archive Notes

- `SettingsTable` and `SettingsRepository` are kept — they will be useful for storing chat config (server URL, API key, etc.) in later phases.
- `Logger.php` is kept for error logging.
- The `sarah-ai-server` plugin retains the full admin dashboard unchanged.

---

## Follow-up Notes

- Next: implement the chat widget UI (Phase 1 per `docs/scenarios/sarah_phase1.md`).
