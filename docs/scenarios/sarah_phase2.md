# Sarah Chat – Phase 2 (Mock Chat Interaction)

## 🎯 Goal
Extend the existing widget to support basic chat interaction using mock responses.
No real backend or API connection is allowed in this phase.

The goal is to simulate a real chat experience.

---

## 🧠 Context
- Builds on Phase 1 plugin
- UI already exists
- Now we add interaction logic using React state

---

## 📦 Scope (What MUST be implemented)

### 1. Message Sending
- User can type message
- Press Enter or click Send
- Message appears in chat immediately

---

### 2. Message Rendering
Support two message types:
- User message
- AI (mock) message

Messages should appear as chat bubbles.

---

### 3. Mock Response System
- After user sends message:
  - Show typing indicator
  - Wait 500–1500ms
  - Return mock response

Example response:
"Thanks for your message. How can I help you further?"

---

### 4. Typing Indicator
- Show “Sarah is typing...”
- Remove when response arrives

---

### 5. Input Behavior
- Disable input while waiting for response
- Re-enable after response

---

## 🚫 Out of Scope

- ❌ No real API
- ❌ No persistence
- ❌ No WordPress settings
- ❌ No authentication

---

## 🧱 Technical Requirements

- Use React state (messages array)
- Component structure:
  - MessageList
  - MessageBubble
  - InputBox

---

## 📋 Tasks

1. Add message state
2. Implement send message logic
3. Render messages
4. Add typing indicator
5. Add mock delay + response
6. Disable input during loading

---

## ✅ Acceptance Criteria

- User can send messages
- Messages appear instantly
- Mock response always appears
- Typing indicator works
- No UI bugs

---

## 📤 Expected Output

A working chat UI that feels real but uses mock responses.

---

## 🧩 End of Phase 2

User can interact with chat and feel it's real (without backend).
