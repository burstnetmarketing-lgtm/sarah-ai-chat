# Task 0044 — Client Task 3: Greeting Message (Already Done + Interaction Fix)

## Status
Core implementation was completed in Task 0039 (commit 0030).

## What Was Already Done
- `ChatWindow.jsx` reads `window.SarahAiWidget.connection.greeting_message` on mount
- Shown instantly as first AI bubble — no server round-trip
- `sarah-ai-client` SettingsController + Plugin.php pass `greeting_message` via `wp_localize_script`
- Client Settings.jsx has Greeting Message field

## Interaction Fix (this task)
With Task 2 (history restore) added, greeting must not duplicate when history is loaded.

### Rule in `ChatWindow.jsx`:
- `greetingMessage()` helper returns `[{id, type:'ai', text}]` or `[]`
- Called only when:
  - No stored session exists (fresh start)
  - Session exists but server returns empty history
  - Session is invalid (cleared)
  - After reset (Task 6)
- NOT called when history rows are returned by server

## Commit
0033
