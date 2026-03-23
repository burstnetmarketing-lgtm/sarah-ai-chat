# Task 0056 — Knowledge Fields API + businessProvider Integration

## Goal
Implement `GET /sites/{uuid}/knowledge-fields` so the widget can fetch structured KB data directly without relying on the AI as intermediary. Connect `businessProvider.js` to this real endpoint.

## New: `includes/Api/KnowledgeFieldsController.php`

### Routes
| Method | Path | Auth | Description |
|--------|------|------|-------------|
| GET | `/sites/{uuid}/knowledge-fields` | account_key + site_key | Returns merged public structured fields |
| POST | `/knowledge-resources/{uuid}/visibility` | manage_options | Toggles public/private |

### `fields()` logic
1. Validate `account_key` + `site_key` via `CredentialValidator::resolveContext()`
2. Verify requested UUID matches the resolved site (prevents cross-site leakage)
3. Load all active public resources via `findPublicActiveBySite()`
4. Merge `meta.structured_fields` from each resource using `KnowledgeFieldSchema::extractFromResource()`
5. First-write-wins on key collisions (lower sort_order resource wins)
6. Return `{ success: true, fields: { ... } }`

### `updateVisibility()` logic
- Validates visibility value via `KnowledgePolicyFilter::isValidVisibility()`
- Updates via `KnowledgeResourceRepository::updateVisibility()`

## Modified: `assets/src/api/provisioning.js` (sarah-ai-server)
- Added `updateKnowledgeVisibility(uuid, visibility)`

## Modified: `assets/src/widget/businessProvider.js` (sarah-ai-client)
- Replaced mock `_fields = {}` with real `fetch()` call to `/knowledge-fields`
- Session-level cache with deduplication (`_fetchPromise`)
- Graceful fallback to `{}` on network error or missing config
- `fetchBusinessFields()`, `getBusinessField(key)`, `getAllBusinessFields()`, `clearBusinessFieldsCache()`
- All functions are now async (previously sync returning null/empty)

## Tenant Isolation
- `CredentialValidator` resolves site from account_key + site_key — KB data is always scoped to one site
- UUID in URL path is cross-checked against resolved site — no cross-site access possible

## Commit
0056
