# Task 0042 — Client Task 1: Session Persistence (localStorage)

## Goal
Persist `session_uuid` in localStorage so the chat session survives page refresh.

## Changes

### `sarah-ai-client/assets/src/widget/ChatWindow.jsx`
- `storageKey()` — returns `sarah_ai_session_{site_key}` scoped per site
- `loadStoredSession()` — reads from localStorage on mount
- `saveStoredSession(uuid)` — writes when first `/chat` response arrives
- `clearStoredSession()` — called on reset or invalid session
- `sessionUuid` state initialized from `loadStoredSession()`
- After first chat response: `saveStoredSession(data.session_uuid)`

## Rules
- Session UUID is NEVER generated client-side — always comes from server
- Storage key is scoped to `site_key` (multiple sites on same domain don't collide)
- localStorage errors are caught silently (private browsing, storage quota)

## Commit
0033
