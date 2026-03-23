# Task 0062 — Client Quick Setup Wizard

## Goal
Show a full-page Quick Setup wizard when the sarah-ai-client plugin is first installed (not yet connected to server).
User enters Server URL + Platform Key + optional WHMCS key → one API call provisions everything → credentials saved automatically.

## Flow
1. Plugin installed → admin panel opens → `isConfigured = false` → QuickSetup shown instead of normal layout
2. User fills: Server URL, Platform Key, WHMCS Key (optional)
3. POST to server's `POST /quick-setup` (Header: X-Sarah-Platform-Key)
4. Server creates: tenant + site + account_key + site_key + assigns default agent
5. Client saves returned credentials via `POST /widget-settings`
6. Page reloads → `isConfigured = true` → normal dashboard opens

## Changes

### Modified: `sarah-ai-client/includes/Admin/DashboardPage.php`
- Added `siteName` (get_bloginfo('name')) and `siteUrl` (get_bloginfo('url')) to JS config
- Added `isConfigured` flag: true only when server_url + account_key + site_key are all set

### Created: `sarah-ai-client/assets/src/pages/QuickSetup.jsx`
- Full-page card layout (no sidebar/topbar)
- Fields: Server URL (required), Platform Key (required), WHMCS Key (optional, password masked)
- On submit: calls server /quick-setup → saves credentials → shows success screen
- Success screen shows plan (trial/customer) and assigned agent slug
- "Open Dashboard" button reloads page

### Modified: `sarah-ai-client/assets/src/App.jsx`
- Added `import QuickSetup`
- Early return: if `!window.SarahAiClientConfig?.isConfigured` → render `<QuickSetup />`

## Commit
0062
