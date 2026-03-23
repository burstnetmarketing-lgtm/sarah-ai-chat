# Task 0060 — API Guide Client Sub-menu

## Goal
Add "Client" as a fourth sub-menu item under API Guide in the sidebar, showing only the client KB endpoints (auth via platform key).

## Changes

### Modified: `sarah-ai-server/assets/src/App.jsx`
- Added `ApiGuideClient` wrapper component with `filter="client"`
- Registered `api-guide-client` in `VIEWS` and `LABELS`

### Modified: `sarah-ai-server/assets/src/pages/ApiGuide.jsx`
- Added `clientGroups` filter (`badge === 'bg-info'`)
- `filter="client"` now shows only client KB group
- Fixed `filter="admin"` to show only `bg-primary` groups (no longer includes client)
- Updated filter description labels for all four filters

### Modified: `sarah-ai-server/includes/Infrastructure/MenuRepository.php`
- Added `api-guide-client` → `Client` as child of `api-guide` in `ensureCoreItems()`

## Commit
0060
