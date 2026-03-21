# Task 0019: Positioning & Mobile Fullscreen Fix

- **Task Number:** 0019
- **Title:** Positioning & Mobile Fullscreen Fix
- **Version:** 0.2.0
- **Date:** 2026-03-22

---

## User Request

Fix chat widget positioning (no gap, bottom-anchored) and add mobile fullscreen mode. Also fixed a PHP fatal error from Phase 3.

---

## Bug Fix

`Plugin::enqueueWidget()` is a static method — `$settingsRepo` from `boot()` was not accessible.
Fix: instantiated `new SettingsRepository()` inside `enqueueWidget()`.

---

## Implementation Summary

- `ChatWidget.jsx` — added `useEffect` that moves `#sarah-chat-root` to `bottom: 0` when open and restores to `bottom: 24px` when closed. Handles left/right position setting.
- `widget.css` — changed `.sac-window` from `bottom: 68px` to `bottom: 0` (no gap).
- `widget.css` — mobile breakpoint changed from `480px` to `768px`. On mobile, `.sac-window` uses `position: fixed; top:0; left:0; right:0; bottom:0; width:100%; height:100%; border-radius:0` for fullscreen app-like behavior.

---

## Affected Files

- `sarah-ai-client/includes/Core/Plugin.php` — bug fix: new SettingsRepository() in enqueueWidget()
- `sarah-ai-client/assets/src/widget/ChatWidget.jsx` — positioning useEffect
- `sarah-ai-client/assets/css/widget.css` — bottom: 0, mobile fullscreen
- `sarah-ai-client/assets/dist/widget.js` — rebuilt
- `sarah-ai-client/assets/dist/widget.css` — rebuilt
