# Task 0058 — API Guide Admin Page

## Goal
Add an "API Guide" page to the sarah-ai-server admin panel that documents all REST endpoints exposed to the outside world.

## Changes

### New: `assets/src/pages/ApiGuide.jsx`
- Lists all REST endpoints grouped by category
- Tabs: All / Public / Admin
- Each endpoint is an accordion row — click to expand details
- Shows: HTTP method (colour-coded), path, auth type, parameters table, response shape
- Copy button for full endpoint URL
- Groups: Widget (Public), Sessions (Public), Tenants, Sites, Knowledge Base, Agents, Plans, Platform Settings

### Modified: `assets/src/App.jsx`
- Registered `api-guide` view + label

### Modified: `includes/Infrastructure/MenuRepository.php`
- Added `api-guide` → `API Guide` to `ensureCoreItems()` — appears in sidebar on every boot

## Commit
0058
