# Client Enhancements Task List
## Sarah AI — Widget Stability & UX Improvements

---

## Overview

This document defines a set of client-side improvement tasks for the Sarah AI widget.

Each task is independent and can be implemented separately. Tasks are numbered so they can be referenced individually.

---

## Task 1 — Session Persistence (localStorage)

### Objective
Ensure chat sessions are not lost after page refresh.

### Requirements
- Store `session_uuid` in `localStorage` after first `/chat` response
- Retrieve it on widget initialization
- Do NOT generate session IDs client-side

---

## Task 2 — History Restore

### Objective
Restore previous messages when widget reloads.

### Requirements
- If `session_uuid` exists:
  - call `GET /sessions/{uuid}/messages`
  - render messages in UI
- Handle invalid session by clearing it

---

## Task 3 — Greeting Message Support

### Objective
Display greeting message instantly on widget open.

### Requirements
- Read `greeting_message` from config
- Show as first AI message without calling `/chat`
- Do not duplicate greeting on reopen

---

## Task 4 — Network Error Recovery

### Objective
Handle API failures gracefully.

### Requirements
- Show error message in UI
- Allow user to retry sending message
- Prevent UI lock or crash

---

## Task 5 — Typing / Loading State

### Objective
Improve user experience while waiting for AI response.

### Requirements
- Show loading or typing indicator
- Hide indicator when response arrives
- Prevent duplicate sends while loading

---

## Task 6 — Reset Chat / Clear Session

### Objective
Allow user to start a fresh conversation.

### Requirements
- Clear `session_uuid` from localStorage
- Clear UI messages
- Start new session on next message

---

## Task 7 — Lead Capture Hook

### Objective
Prepare client for sending lead data.

### Requirements
- Allow optional `lead` object in `/chat` request
- Structure:
```json
{
  "name": "",
  "phone": "",
  "email": ""
}
```
- Do not enforce UI yet (just support sending)

---

## Usage

You can reference tasks by number when assigning work:

- "Implement Task 1"
- "Do Task 2 and Task 5"

---

*Generated: Client Enhancements Task List*
