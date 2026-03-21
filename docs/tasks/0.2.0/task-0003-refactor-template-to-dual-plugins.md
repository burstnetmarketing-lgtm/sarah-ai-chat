# Task 0003: Refactor Template to Dual Plugins

- **Task Number:** 0003
- **Title:** Refactor Template to Dual Plugins
- **Version:** 0.2.0
- **Date:** 2026-03-22

---

## User Request

The project contains two plugins: `sarah-ai-client` and `sarah-ai-server`. Both were still using the generic `project-name` template placeholder. The user requested that the project structure be properly initialized so both plugins have their own identities and can be developed independently before being connected via API.

---

## Implementation Summary

- Renamed `sarah-ai-client/project-name.php` → `sarah-ai-client/sarah-ai-client.php` with updated plugin header, PHP constants (`SARAH_AI_CLIENT_*`), and namespace references.
- Renamed `sarah-ai-server/project-name.php` → `sarah-ai-server/sarah-ai-server.php` with updated plugin header, PHP constants (`SARAH_AI_SERVER_*`), and namespace references.
- Updated all 11 PHP files in `sarah-ai-client/includes/` — namespace `ProjectName\` → `SarahAiClient\`, constants, DB table names, REST route prefix, log file name, admin page slug.
- Updated all 11 PHP files in `sarah-ai-server/includes/` — namespace `ProjectName\` → `SarahAiServer\`, constants, DB table names, REST route prefix, log file name, admin page slug.
- Updated `update.php` in both plugins with correct constants and plugin slugs.
- Updated `package.json` in both plugins with correct `name` field.
- Updated JS source files (`assets/src/api/client.js`, `assets/src/utils/logger.js`) in both plugins to use the correct `window.*Config` global.
- Updated root `package.json`: added `dev:client`, `dev:server`, `build:client`, `build:server`, `build` scripts; replaced `update:project-name` with `update:client` and `update:server`.
- Created `scripts/update-sarah-ai-client.js` and `scripts/update-sarah-ai-server.js`.
- Created `docs/technical/deploy/sarah-ai-client.js` and `docs/technical/deploy/sarah-ai-server.js`.
- Removed old `scripts/update-project-name.js` and `docs/technical/deploy/project-name.js`.

---

## Affected Files

- `sarah-ai-client/sarah-ai-client.php` — created (renamed from project-name.php)
- `sarah-ai-server/sarah-ai-server.php` — created (renamed from project-name.php)
- `sarah-ai-client/includes/**/*.php` (11 files) — namespace + constants updated
- `sarah-ai-server/includes/**/*.php` (11 files) — namespace + constants updated
- `sarah-ai-client/update.php` — constants and slug updated
- `sarah-ai-server/update.php` — constants and slug updated
- `sarah-ai-client/package.json` — name updated
- `sarah-ai-server/package.json` — name updated
- `sarah-ai-client/assets/src/api/client.js` — window config var updated
- `sarah-ai-client/assets/src/utils/logger.js` — window config var updated
- `sarah-ai-server/assets/src/api/client.js` — window config var updated
- `sarah-ai-server/assets/src/utils/logger.js` — window config var updated
- `package.json` — scripts updated for dual-plugin workflow
- `scripts/update-sarah-ai-client.js` — created
- `scripts/update-sarah-ai-server.js` — created
- `docs/technical/deploy/sarah-ai-client.js` — created
- `docs/technical/deploy/sarah-ai-server.js` — created
- `scripts/update-project-name.js` — removed
- `docs/technical/deploy/project-name.js` — removed

---

## Archive Notes

- Both plugins are fully independent and share no code. They will be connected later via REST API.
- DB table names: `sarah_ai_client_menu_items`, `sarah_ai_client_settings` for the client; `sarah_ai_server_menu_items`, `sarah_ai_server_settings` for the server.
- REST namespaces: `sarah-ai-client/v1` and `sarah-ai-server/v1`.
- The `assets/dist/app.js` compiled files still contain old references — they will be replaced on next `npm run build`.

---

## Follow-up Notes

- Run `npm run build` to rebuild both plugins and clear stale compiled assets.
- Update `docs/technical/deploy/*.js` target paths for local WordPress installations.
