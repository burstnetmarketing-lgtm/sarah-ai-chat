# Task 0059 — Client-Side Knowledge Base Management

## Goal
Allow the sarah-ai-client plugin to manage Knowledge Base resources directly from the customer's WordPress site, without requiring admin access to the sarah-ai-server.

## Auth Model
Three-layer authentication for all client KB endpoints:
1. `X-Sarah-Platform-Key` header — static platform secret (stored in platform settings)
2. `account_key` (body/query) — identifies tenant
3. `site_key` (body/query) — identifies site

All operations are automatically scoped to the resolved site.

## Changes

### New: `sarah-ai-server/includes/Api/ClientKnowledgeController.php`
Routes under `/client/` prefix:
- `GET    /client/knowledge-resource-types` — list enabled types
- `GET    /client/knowledge-resources` — list resources for site
- `POST   /client/knowledge-resources` — create text/link resource
- `DELETE /client/knowledge-resources/{uuid}` — soft-delete
- `POST   /client/knowledge-resources/{uuid}/status` — update status
- `POST   /client/knowledge-resources/{uuid}/process` — run pipeline
- Adds `X-Sarah-Platform-Key` to CORS allowed headers via `rest_allowed_cors_headers` filter

### Modified: `sarah-ai-server/includes/Core/Plugin.php`
- Registered `ClientKnowledgeController`

### Modified: `sarah-ai-server/sarah-ai-server.php`
- Added `require_once` for `ClientKnowledgeController.php`

### Modified: `sarah-ai-client/includes/Core/Plugin.php`
- Added `platform_key` to `connection` config injected into `window.SarahAiWidget`

### Modified: `sarah-ai-client/includes/Api/SettingsController.php`
- Added `platform_key` to GET and POST handlers

### New: `sarah-ai-client/assets/src/api/knowledgeApi.js`
- `getKnowledgeResourceTypes()` — list enabled types
- `listKnowledgeResources()` — list resources
- `createKnowledgeResource(fields)` — create resource
- `deleteKnowledgeResource(uuid)` — delete
- `updateKnowledgeResourceStatus(uuid, status)` — status update
- `processKnowledgeResource(uuid)` — run pipeline

### New: `sarah-ai-client/assets/src/pages/KnowledgeBase.jsx`
- Full CRUD table: title, type, status, processing status, actions
- Add form: type dropdown (from API), title, content/URL
- Per-row: Activate/Deactivate, Process, Delete
- Error message guides user to Settings if platform_key is missing/wrong

### Modified: `sarah-ai-client/assets/src/pages/Settings.jsx`
- Added Platform Key field to Server Connection card

### Modified: `sarah-ai-client/assets/src/App.jsx`
- Registered `knowledge-base` view → `KnowledgeBase`

### Modified: `sarah-ai-client/includes/Infrastructure/MenuRepository.php`
- Added `knowledge-base` → `Knowledge Base` to `ensureCoreItems()`

### Modified: `sarah-ai-client/assets/src/components/Sidebar.jsx`
- Added ICONS map with `bi-database` for `knowledge-base`

## Commit
0059
