# Task 0043 — Client Task 2: History Restore

## Goal
Restore previous messages when widget reloads with a stored session UUID.

## Server Change

### `sarah-ai-server/includes/Api/ChatController.php`
- Added `GET /chat/history?account_key=&site_key=&session_uuid=`
- Auth: account_key + site_key only (same as POST /chat — no platform key)
- Validates session belongs to resolved site
- Returns `{ success, session_uuid, messages: [{role, content}] }`
- 404 if session not found; 401 if bad credentials
- Injected `CredentialValidator`, `ChatSessionRepository`, `ChatMessageRepository`

## Client Change

### `chatApi.js`
- Added `fetchChatHistory(sessionUuid)` — GET /chat/history
- Returns `[{role, content}]` on success, `null` on 401/404 (session gone)

### `ChatWindow.jsx`
- On mount: if `loadStoredSession()` returns a UUID → call `fetchChatHistory()`
- History loaded → populate messages (role: 'customer'→'user', 'assistant'→'ai')
- Session gone (null) → `clearStoredSession()`, show greeting
- Empty history → show greeting
- Network error → keep session, show greeting, allow user to continue
- `historyLoading` state disables input and shows typing indicator while loading

## Commit
0033
