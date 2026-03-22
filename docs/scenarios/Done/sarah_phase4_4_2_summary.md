# Phase 4.4.2 — Implementation Summary
## Client Widget Real API Integration

---

## 1. Overview

Phase 4.4.2 connects the sarah-ai-client chat widget to the live sarah-ai-server runtime.

Before this phase, the widget sent messages and received hardcoded mock responses from a local JavaScript array. After this phase, the widget calls the real `POST /chat` endpoint, maintains `session_uuid` continuity across messages in a conversation, and falls back gracefully when the server is unreachable or credentials are missing.

---

## 2. Changes

### sarah-ai-client (client plugin)

| File | Change |
|------|--------|
| `assets/src/widget/chatApi.js` | **New** — `sendChatMessage(text, sessionUuid)` — fetches `{server_url}/chat` using connection settings from `window.SarahAiWidget.connection` |
| `assets/src/widget/ChatWindow.jsx` | Replaced mock response logic with real `sendChatMessage()` call; added `sessionUuid` state for conversation continuity |
| `assets/src/pages/Settings.jsx` | Added Server Connection section: Server URL, Account Key, Site Key fields with save form |
| `includes/Api/SettingsController.php` | Extended GET/POST `/widget-settings` to include `server_url`, `account_key`, `site_key` |
| `includes/Core/Plugin.php` | Added `connection` key to `wp_localize_script` payload → `window.SarahAiWidget.connection` |

---

## 3. Widget Runtime Flow (after this phase)

```
1. Visitor opens widget → ChatWindow mounts, sessionUuid = null
2. Visitor types message → sendMessage() called
3. chatApi.js reads window.SarahAiWidget.connection (server_url, account_key, site_key)
4. POST {server_url}/chat  { account_key, site_key, message, session_uuid? }
5. On success → store returned session_uuid (first message only)
6. Display assistant reply from data.message
7. Subsequent messages include session_uuid → server continues same conversation
8. On network/server error → friendly error bubble shown
```

---

## 4. Connection Config Flow

### Runtime (widget on visitor's browser)
```
window.SarahAiWidget.connection = {
  server_url:  'https://sarah-server.example.com/wp-json/sarah-ai-server/v1',
  account_key: '<tenant account key>',
  site_key:    '<site key>',
}
```
Populated by `Plugin::enqueueWidget()` via `wp_localize_script`.

### Admin (Settings page)
- **Server URL** — text input, validated as URL
- **Account Key** — monospace text input, paste from sarah-ai-server Account Keys tab
- **Site Key** — monospace text input, paste from sarah-ai-server Site Keys tab
- Single **Save Settings** button saves all fields via `POST /widget-settings`

---

## 5. Error Handling

| Scenario | Widget behaviour |
|----------|-----------------|
| `server_url` / `account_key` / `site_key` not configured | AI bubble: "Chat is not configured yet." |
| Network failure / non-2xx response | AI bubble: "Unable to connect. Please try again." |
| Server returns `success: false` with message | AI bubble shows server-provided message |

---

## 6. Session Continuity

- `sessionUuid` is stored in React component state (in-memory for the page load)
- First response from server sets `sessionUuid`
- All subsequent requests include `session_uuid` in the body
- Refreshing the page starts a new session (no localStorage persistence — intentional for Phase 4.4.2)

---

## 7. No SDK / No CORS Concerns

- `chatApi.js` uses native `fetch()` — no library dependency
- The `POST /chat` endpoint on sarah-ai-server must have appropriate CORS headers if the client site is on a different domain than the server (Phase 4.4.3 concern — not addressed here)

---

## 8. Phase Boundary

Phase 4.4.2 ends with the widget making real server calls and maintaining session continuity.

Phase 4.4.3 will address:
- CORS headers on sarah-ai-server endpoints (for cross-origin deployments)
- Lead capture (visitor name/email) from within the widget
- Session persistence across page loads (optional localStorage)
