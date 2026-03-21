# Task 0009: Add Settings Menu to Sidebar Seed

- **Task Number:** 0009
- **Title:** Add Settings Menu to Sidebar Seed
- **Version:** 0.2.0
- **Date:** 2026-03-22

---

## User Request

Add a "Settings" menu item to the sidebar via seed data (non-deletable). Also fix `seedDefaults()` which was missing from MenuRepository.

---

## Implementation Summary

- Updated `ensureCoreItems()` in `MenuRepository.php`: added 'settings' as a non-deletable, non-child-bearing item; removed the stale `removeIfExists` calls for 'menu-manager' and 'settings'.
- Added `seedDefaults()` method to `MenuRepository` (was missing — Activator called it but it didn't exist).

---

## Affected Files

- `sarah-ai-client/includes/Infrastructure/MenuRepository.php` — updated ensureCoreItems, added seedDefaults
- `sarah-ai-client/includes/Core/Activator.php` — no functional change, confirmed correct
