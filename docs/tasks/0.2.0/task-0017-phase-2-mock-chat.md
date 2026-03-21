# Task 0017: Phase 2 — Mock Chat Interaction

- **Task Number:** 0017
- **Title:** Phase 2 — Mock Chat Interaction
- **Version:** 0.2.0
- **Date:** 2026-03-22

---

## User Request

Implement Phase 2: full mock chat interaction with typing indicator, message bubbles, and admin-defined quick questions.

---

## Implementation Summary

### PHP — Backend
- `includes/DB/QuickQuestionsTable.php` — new table `sarah_ai_client_quick_questions` (id, question, sort_order, is_enabled)
- `includes/Infrastructure/QuickQuestionsRepository.php` — CRUD: all, allEnabled, find, create, update, delete
- `includes/Api/QuickQuestionsController.php` — REST API: GET/POST `/quick-questions`, PUT/DELETE `/quick-questions/{id}`
- `includes/Core/Activator.php` — added `QuickQuestionsTable::create()` on activation
- `includes/Core/Plugin.php` — registers QuickQuestionsController routes; passes enabled questions to widget via `wp_localize_script` as `window.SarahAiWidget.quickQuestions`
- `includes/Infrastructure/MenuRepository.php` — seeded `quick-questions` menu item (non-deletable)
- `sarah-ai-client.php` — added require_once for new files

### React Admin
- `assets/src/pages/QuickQuestions.jsx` — admin page: list, add, edit, toggle, delete quick questions
- `assets/src/App.jsx` — added `quick-questions` view

### React Widget
- `assets/src/widget/ChatWindow.jsx` — holds messages state and isTyping; `getMockResponse()` simulates delay (800–1500ms); passes handlers to children
- `assets/src/widget/MessageArea.jsx` — shows welcome + quick question buttons when empty; shows message bubbles + typing indicator when active; auto-scrolls
- `assets/src/widget/InputBox.jsx` — send on Enter or button click; clears input; disabled during typing
- `assets/src/widget/TypingIndicator.jsx` — animated three-dot bounce inside AI bubble

### CSS
- Added `.sac-bubble`, `.sac-bubble-user`, `.sac-bubble-ai`, `.sac-typing`, `.sac-quick-questions`, `.sac-quick-btn` styles
- Send button now has active/hover state; disabled when input empty or AI is typing

---

## Affected Files

- `sarah-ai-client/includes/DB/QuickQuestionsTable.php` — new
- `sarah-ai-client/includes/Infrastructure/QuickQuestionsRepository.php` — new
- `sarah-ai-client/includes/Api/QuickQuestionsController.php` — new
- `sarah-ai-client/includes/Core/Activator.php` — updated
- `sarah-ai-client/includes/Core/Plugin.php` — updated
- `sarah-ai-client/includes/Infrastructure/MenuRepository.php` — updated
- `sarah-ai-client/sarah-ai-client.php` — updated
- `sarah-ai-client/assets/src/pages/QuickQuestions.jsx` — new
- `sarah-ai-client/assets/src/App.jsx` — updated
- `sarah-ai-client/assets/src/widget/ChatWindow.jsx` — updated
- `sarah-ai-client/assets/src/widget/MessageArea.jsx` — updated
- `sarah-ai-client/assets/src/widget/InputBox.jsx` — updated
- `sarah-ai-client/assets/src/widget/TypingIndicator.jsx` — new
- `sarah-ai-client/assets/css/widget.css` — updated
- `sarah-ai-client/assets/dist/widget.js` — rebuilt
- `sarah-ai-client/assets/dist/widget.css` — rebuilt
