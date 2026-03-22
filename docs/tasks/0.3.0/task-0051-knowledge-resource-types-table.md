# Task 0051 — Knowledge Resource Types Table

## Goal
Store knowledge resource types in DB with enabled/disabled flag so pdf/docx can be disabled without code deployment.

## Changes

### New: `includes/DB/KnowledgeResourceTypeTable.php`
- Table: `sarah_ai_server_knowledge_resource_types`
- Columns: `id`, `type_key`, `label`, `enabled`, `sort_order`
- `dbDelta`-safe, created on every boot via `Plugin::boot()`

### New: `includes/Infrastructure/KnowledgeResourceTypeRepository.php`
- `findEnabled()` — returns enabled types ordered by `sort_order`
- `seed()` — `INSERT IGNORE` (idempotent)

### Modified: `includes/Core/Seeder.php`
- Added `seedKnowledgeResourceTypes()` called from `run()`
- Seeds 5 types: text (enabled), link (enabled), txt (enabled), **pdf (disabled)**, **docx (disabled)**

### Modified: `includes/Core/Plugin.php`
- Added `KnowledgeResourceTypeTable::create()` to boot sequence

### Modified: `includes/Api/KnowledgeController.php`
- Added `GET /knowledge-resource-types` → `listTypes()` — returns enabled types only

### Modified: `assets/src/api/provisioning.js`
- Added `getKnowledgeResourceTypes()`

### Modified: `assets/src/pages/TenantDetail.jsx`
- `KnowledgeSection` fetches types from API on mount
- Dropdown populated dynamically — pdf/docx absent until re-enabled in DB

## Re-enabling a type
```sql
UPDATE wp_sarah_ai_server_knowledge_resource_types SET enabled = 1 WHERE type_key = 'pdf';
```

## Commit
0051
