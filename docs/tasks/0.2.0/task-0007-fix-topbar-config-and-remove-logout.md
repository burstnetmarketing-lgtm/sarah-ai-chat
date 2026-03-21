# Task 0007: Fix Topbar Config and Remove Logout

- **Task Number:** 0007
- **Title:** Fix Topbar Config and Remove Logout
- **Version:** 0.2.0
- **Date:** 2026-03-22

---

## User Request

- Menu Manager and Log buttons in the Topbar dropdown were disabled.
- Remove the "Exit / Log out" button from the dropdown.
- Keep the "Back to WordPress" arrow button.

---

## Implementation Summary

- Fixed `Topbar.jsx`: changed `window.ProjectNameConfig` → `window.SarahAiClientConfig` (was missed during namespace refactor). This caused `canManageMenus` to be undefined, disabling Menu Manager and Log buttons.
- Removed `logoutUrl` destructure and the "Log out" dropdown item from `Topbar.jsx`.
- Updated Sidebar brand label from "Project Name" to "Sarah AI".
- Removed `logoutUrl` from the config array in `DashboardPage.php` (no longer used).

---

## Affected Files

- `sarah-ai-client/assets/src/components/Topbar.jsx` — fixed config key, removed logout
- `sarah-ai-client/assets/src/components/Sidebar.jsx` — updated brand label
- `sarah-ai-client/includes/Admin/DashboardPage.php` — removed logoutUrl from config

---

## Archive Notes

- Root cause: the namespace refactor script only walked `includes/` and `assets/src/api/` + `assets/src/utils/` — it did not update `Topbar.jsx`.
