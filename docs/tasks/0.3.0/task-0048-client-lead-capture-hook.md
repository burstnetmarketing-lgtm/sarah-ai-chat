# Task 0048 — Client Task 7: Lead Capture Hook

## Goal
Support sending optional lead data (`name`, `phone`, `email`) with each chat message.

## Changes

### `chatApi.js`
- `sendChatMessage(text, sessionUuid, lead)` — added `lead` as 3rd parameter (default: null)
- If `lead` has at least one non-empty field → `body.lead = lead`
- Structure: `{ name?: string, phone?: string, email?: string }`

### `ChatWindow.jsx`
- `getLead()` — reads `window.SarahAiWidget.connection.lead` if set
- Passed as 3rd argument to `sendChatMessage(trimmed, sessionUuid, getLead())`

## Notes
- Server already accepts `lead` in POST /chat body (`ChatController.php` line 52)
- `ChatRuntime` passes `$leadInfo` through to session creation
- No UI for lead collection added in this task — that is a future task
- Any component can set `window.SarahAiWidget.connection.lead` before a message is sent (e.g., a pre-chat form)

## Commit
0033
