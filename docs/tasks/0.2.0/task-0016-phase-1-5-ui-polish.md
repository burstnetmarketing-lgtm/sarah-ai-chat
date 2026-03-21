# Task 0016: Phase 1.5 UI Polish

- **Task Number:** 0016
- **Title:** Phase 1.5 UI Polish
- **Version:** 0.2.0
- **Date:** 2026-03-22

---

## User Request

Polish the chat widget UI: fix launcher duplicate close button, improve empty state, improve input contrast, and general layout improvements.

---

## Implementation Summary

- `ChatWidget.jsx`: Launcher only renders when chat is closed — no duplicate close button.
- `LauncherButton.jsx`: Removed `isOpen` prop and close icon — always shows chat icon.
- `MessageArea.jsx`: Replaced SVG empty state with "Hi 👋 / How can I help you today?" welcome design.
- `widget.css`: Replaced `.sac-empty-state` with `.sac-welcome` styles (emoji, title, subtitle). Improved input border contrast (`#cbd5e1`), removed background tint on input, increased send button size to 40px, improved input area padding.

---

## Affected Files

- `sarah-ai-client/assets/src/widget/ChatWidget.jsx` — hide launcher when open
- `sarah-ai-client/assets/src/widget/LauncherButton.jsx` — simplified, removed isOpen
- `sarah-ai-client/assets/src/widget/MessageArea.jsx` — welcome state
- `sarah-ai-client/assets/css/widget.css` — welcome + input polish
- `sarah-ai-client/assets/dist/widget.js` — rebuilt
- `sarah-ai-client/assets/dist/widget.css` — rebuilt
