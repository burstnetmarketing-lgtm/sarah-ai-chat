# Task 0006: Restore Admin Dashboard in Client

- **Task Number:** 0006
- **Title:** Restore Admin Dashboard in Client
- **Version:** 0.2.0
- **Date:** 2026-03-22

---

## User Request

Restore the full React admin dashboard in `sarah-ai-client` that was removed in task-0004. The plugin needs both a full admin panel and the settings page added in task-0005.

---

## Implementation Summary

- Restored from git (commit `dce136f`): `DashboardPage.php`, `LogController.php`, `MenuItemsController.php`, `MenuTable.php`, `MenuRepository.php`.
- Restored React files: `Sidebar.jsx`, `Topbar.jsx`, `MenuAccordion.jsx`, `Dashboard.jsx`, `Log.jsx`, `MenuManager.jsx`, `useMenuItems.js`, `admin.css`.
- Restored `App.jsx` and `main.jsx` to the full admin SPA versions.
- Restored `Plugin.php` to boot the full dashboard — also added `SettingsPage` registration alongside it.
- Updated `sarah-ai-client.php` requires to include all restored files plus `SettingsPage.php`.

---

## Affected Files

- `sarah-ai-client/sarah-ai-client.php` — updated
- `sarah-ai-client/includes/Core/Plugin.php` — restored + SettingsPage added
- `sarah-ai-client/includes/Core/Activator.php` — restored
- `sarah-ai-client/includes/Admin/DashboardPage.php` — restored
- `sarah-ai-client/includes/Api/LogController.php` — restored
- `sarah-ai-client/includes/Api/MenuItemsController.php` — restored
- `sarah-ai-client/includes/DB/MenuTable.php` — restored
- `sarah-ai-client/includes/Infrastructure/MenuRepository.php` — restored
- `sarah-ai-client/assets/src/App.jsx` — restored
- `sarah-ai-client/assets/src/main.jsx` — restored
- `sarah-ai-client/assets/src/components/Sidebar.jsx` — restored
- `sarah-ai-client/assets/src/components/Topbar.jsx` — restored
- `sarah-ai-client/assets/src/components/menu/MenuAccordion.jsx` — restored
- `sarah-ai-client/assets/src/pages/Dashboard.jsx` — restored
- `sarah-ai-client/assets/src/pages/Log.jsx` — restored
- `sarah-ai-client/assets/src/pages/MenuManager.jsx` — restored
- `sarah-ai-client/assets/src/hooks/useMenuItems.js` — restored
- `sarah-ai-client/assets/css/admin.css` — restored

---

## Archive Notes

- The plugin now has two admin entry points: the full React dashboard (top-level menu) and the settings page under Settings > Sarah AI Client.
- The chat widget frontend (Phase 1) will be added on top of this existing structure.
