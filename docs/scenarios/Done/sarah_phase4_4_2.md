# Phase 4.4.2 — System Design Summary
## Client Integration & Runtime Connection (React + Vite)

---

## 1. Overview

Phase 4.4.2 connects the existing client (chat widget) to the server runtime implemented in Phase 4.4.1.

Before this phase, the client operates with mock or local responses. After this phase, the client must communicate with the real backend using the `/chat` endpoint, maintain session continuity, and render server responses in real time.

This phase must not redesign the UI. It focuses on replacing mock logic with a real runtime integration while keeping the client architecture clean, predictable, and environment-aware.

---

## 2. Primary Objective

The client must:

- send chat messages to the server runtime
- receive and render assistant responses
- manage `session_uuid` for conversation continuity
- optionally send lead data when available
- optionally load previous messages when reopening the widget
- operate against different server environments without code changes

---

## 3. Server Contract (Source of Truth)

The client must strictly follow the existing API contract:

- POST `/chat` → start or continue conversation
- optional `session_uuid`
- optional `lead` object
- GET `/sessions/{uuid}/messages` → load history

The client must not modify or extend this contract.

---

## 4. Environment Handling (Vite Responsibility)

The client must not hardcode server URLs.

Environment selection must be handled by Vite at build time.

### Requirements

- Support:
  - development
  - deploy (staging)
  - production

- The client must use a single resolved API base URL at runtime
- No runtime switching logic must exist inside the client

### Design Rule

Environment selection is a build concern, not a runtime concern.

---

## 5. Chat Flow

### Start Conversation

- user sends message
- client calls POST `/chat` without `session_uuid`
- server returns:
  - response message
  - `session_uuid`

- client must store `session_uuid` locally

---

### Continue Conversation

- client sends message with `session_uuid`
- server continues the same session
- client updates UI with response

---

## 6. Session Management

The client must:

- store `session_uuid` in memory (and optionally local storage)
- reuse it for subsequent messages
- reset it when chat is cleared or restarted

The client must not generate session identifiers.

---

## 7. Lead Data Handling

The client may send lead data when available.

Example:

- after user enters name/email/phone
- attach as `lead` object in `/chat` request

This is optional and must not block chat flow.

---

## 8. History Loading (Optional)

When widget is reopened:

- if `session_uuid` exists
- call GET `/sessions/{uuid}/messages`
- render previous messages

This must be non-blocking.

---

## 9. Error Handling

The client must:

- handle network failures gracefully
- handle invalid responses
- avoid breaking UI on error

Errors must not crash the widget.

---

## 10. Non-Goals

This phase must not include:

- UI redesign
- admin panel changes
- analytics dashboards
- advanced state management
- environment switching in UI

---

## 11. Success Criteria

This phase is complete when:

- the widget sends real requests to `/chat`
- responses are rendered correctly
- session continuity works
- environment switching works via build (Vite)
- no mock logic remains

---

## 12. Phase Boundary

After this phase:

- the system becomes fully usable end-to-end
- client and server are connected
- ready for real-world testing

---

*Generated: Phase 4.4.2*
