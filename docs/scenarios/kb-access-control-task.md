# Task: KB Access Control System

## Objective
Prevent sensitive data (e.g. personal mobile numbers) from being exposed by the AI agent.

---

## Description
The AI agent currently returns data directly from the Knowledge Base without any access control.  
This task introduces a visibility layer to ensure only safe (public) information is returned.

---

## Subtasks

### 1. Define Visibility Structure
- Add `visibility` field to KB entries
- Supported values:
  - `public`
  - `private`

Example:
```json
{
  "phone": {
    "value": "0449...",
    "visibility": "private"
  }
}
```

---

### 2. Server-side Filtering
- Filter out all `private` fields before sending response to client
- Ensure no sensitive data reaches frontend

---

### 3. Safe Response Handling
- If user requests restricted data:
  - Return alternative response
  - Example:
    - "Please contact us via the website for assistance"

---

### 4. Intent Detection (Basic)
- Detect requests like:
  - "admin number"
  - "mobile"
- Route through access control logic

---

### 5. Test Scenarios
- Request admin phone → should NOT return
- Request marketing phone → return only if public
- Request website → always return
- Ask unrelated question → handled normally

---

## Notes
- This is critical for privacy, security, and future SaaS scaling
- Should be reusable across all agents (multi-tenant ready)

---

## Status
⏳ Pending
