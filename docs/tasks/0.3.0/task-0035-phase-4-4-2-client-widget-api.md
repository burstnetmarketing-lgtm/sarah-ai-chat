# Task 0035 — Phase 4.4.2: Client Widget Real API Integration

## Goal
Replace mock responses in the sarah-ai-client chat widget with real calls to the sarah-ai-server POST /chat endpoint. Add connection settings (server_url, account_key, site_key) to the admin Settings page and pass them to the widget at runtime.

## Scope

### sarah-ai-client changes

#### Widget
- `assets/src/widget/chatApi.js` (new) — `sendChatMessage(text, sessionUuid)` fetches `{server_url}/chat`; reads connection config from `window.SarahAiWidget.connection`
- `assets/src/widget/ChatWindow.jsx` — replaced mock response logic with real API call; added `sessionUuid` state for conversation continuity across messages

#### Admin
- `assets/src/pages/Settings.jsx` — added Server Connection section (server_url, account_key, site_key fields + unified save form)

#### PHP
- `includes/Api/SettingsController.php` — extended GET/POST `/widget-settings` to include server_url, account_key, site_key
- `includes/Core/Plugin.php` — added `connection` key to `wp_localize_script` payload → `window.SarahAiWidget.connection`

## Behaviour
- First message → sessionUuid null → server creates new session → returns session_uuid
- Subsequent messages include session_uuid → conversation continues in same session
- Error states: not configured / network failure / server error → friendly message bubble shown

## Commit
0026
