# Task 0047 — Client Task 6: Reset Chat / Clear Session

## Goal
Allow user to start a fresh conversation by clearing the session and messages.

## Changes

### `Header.jsx`
- Accepts `onReset` prop
- Renders reset button (↺ icon, SVG refresh) between title and close button
- `aria-label="New chat"`, `title="Start a new conversation"`
- Wrapped in `sac-header-actions` div alongside close button

### `ChatWindow.jsx`
- `handleReset()`:
  1. `clearStoredSession()` — removes from localStorage
  2. `setSessionUuid(null)` — forgets current session
  3. `setLastFailed(null)` — clears any pending retry
  4. `setMessages(greetingMessage())` — shows greeting (or empty if not configured)
- Passed as `onReset` to `<Header />`

## Behavior
- Reset is instant — no server call needed
- New greeting shown (same as fresh open)
- Next message starts a brand new session on the server
- Previous session data remains on the server (not deleted)

## Commit
0033
