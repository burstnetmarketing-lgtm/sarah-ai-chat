# Task 0065 — Deploy-time Server URL Config

## Goal
Allow integration partners to pre-configure the server URL at package/deploy time so
the Quick Setup wizard does not ask end-users to enter it manually.

## Changes

### New: `sarah-ai-client/config.php`
- Defines `SARAH_AI_CLIENT_SERVER_URL` constant (default empty string).
- Partners fill this in before distributing the plugin.
- Loaded before `update.php` in the main plugin file.

### Modified: `sarah-ai-client/sarah-ai-client.php`
- Added `require_once SARAH_AI_CLIENT_PATH . 'config.php';`

### Modified: `sarah-ai-client/includes/Admin/DashboardPage.php`
- Reads `SARAH_AI_CLIENT_SERVER_URL` constant into `$deployServerUrl`.
- Passes `serverUrl` to `window.SarahAiClientConfig`:
  - If constant is non-empty → constant value wins.
  - Otherwise → falls back to the DB-stored `server_url`.

### Modified: `sarah-ai-client/assets/src/pages/QuickSetup.jsx`
- Reads `cfg.serverUrl` into `fixedServerUrl`.
- Initialises form `server_url` with `fixedServerUrl` (pre-filled).
- Server URL `<input>` is wrapped in `{!fixedServerUrl && (...)}` — hidden when pre-configured.

## Behaviour
| Config state | Quick Setup form |
|---|---|
| `SARAH_AI_CLIENT_SERVER_URL` empty | Server URL field shown, user fills it in |
| `SARAH_AI_CLIENT_SERVER_URL` set | Server URL field hidden, value used automatically |

## Commit
0065
