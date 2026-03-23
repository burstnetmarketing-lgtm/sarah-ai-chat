# Task 0058 — API Guide Admin Page

## Goal
Add an "API Guide" page to the sarah-ai-server admin panel that documents all REST endpoints exposed to the outside world. The API Guide is a **parent menu item** in the sidebar with three sub-menu items (All, Public, Admin) — navigation is sidebar-driven, not tab-driven inside the page.

## Changes

### New: `assets/src/pages/ApiGuide.jsx`
- Lists all REST endpoints grouped by category
- Accepts `filter` prop: `"all"` | `"public"` | `"admin"` — controls which groups are visible
- Each endpoint is an accordion row — click to expand details
- Shows: HTTP method (colour-coded), path, auth type, parameters table, response shape
- Copy button for full endpoint URL
- Groups: Widget (Public), Sessions (Public), Tenants, Sites, Knowledge Base, Agents, Plans, Platform Settings

### Modified: `assets/src/App.jsx`
- Registered `api-guide`, `api-guide-all`, `api-guide-public`, `api-guide-admin` in `VIEWS` and `LABELS`
- Wrapper components (`ApiGuideAll`, `ApiGuidePublic`, `ApiGuideAdmin`) pass the correct `filter` prop to `ApiGuide`

### Modified: `assets/src/components/layout/VerticalNavigationBar.jsx`
- Added `'api-guide': 'mdi:book-open-outline'` to `ICONS` map

### Modified: `includes/Infrastructure/MenuRepository.php`
- Added `api-guide` (parent, `allowChildren=true`) + three children (`api-guide-all`, `api-guide-public`, `api-guide-admin`) to `ensureCoreItems()`
- Removed `menu-manager` from core items

## Commit
0058
