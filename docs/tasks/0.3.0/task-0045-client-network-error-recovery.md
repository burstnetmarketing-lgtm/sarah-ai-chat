# Task 0045 — Client Task 4: Network Error Recovery

## Goal
Handle API failures gracefully — show error in UI with a retry button, prevent UI lock.

## Changes

### `ChatWindow.jsx`
- `lastFailed` state tracks the text of the last failed send
- On catch: error bubble added with `isError: true` and `retryText: trimmed`
- `handleRetry(text)`: removes the error bubble, clears `lastFailed`, calls `sendMessage(text)` again
- Input is re-enabled after error (existing behavior via `finally(() => setIsTyping(false))`)

### `MessageArea.jsx`
- Accepts new `onRetry` prop
- Error bubbles (`msg.isError`) get `.sac-bubble-error` CSS class
- Renders `↺ Try again` button inside error bubble when `msg.retryText && onRetry`

## Behavior
1. User sends message → error occurs → error bubble appears with "Unable to connect. Please try again."
2. Retry button visible below error text
3. Click retry → error bubble removed → message re-sent without re-typing
4. Input is never permanently disabled — UI always recoverable

## Commit
0033
