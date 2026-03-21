# Sarah Chat – Phase 1 (Chat Widget UI Only)

## 🎯 Goal
Build a WordPress plugin that renders a floating chat widget UI using React.  
This phase is strictly visual. No real chat logic or backend connection is allowed.

The final result must allow us to **see and evaluate the UI/UX of the widget**.

---

## 🧠 Context
- This is part of a larger system called “Sarah Chat”
- This plugin will be installed on client WordPress websites
- React will be used for the UI layer
- WordPress will act only as a wrapper (loader + config)

---

## 📦 Scope (What MUST be implemented)

### 1. WordPress Plugin Base
- Create a valid WordPress plugin
- Plugin must:
  - Be installable via ZIP
  - Be activatable from WP admin
- Include:
  - Main plugin file (e.g. sarah-chat.php)
  - Proper plugin headers

---

### 2. React App Integration
- Create a React app (simple structure)
- Build it into static assets (JS + CSS)
- Load it via WordPress using:
  - wp_enqueue_script
  - wp_enqueue_style

- Mount React app inside a container injected into frontend:
  - Example: <div id="sarah-chat-root"></div>

---

### 3. Floating Chat Launcher
- A button fixed at bottom-right of screen
- Always visible on frontend
- Basic style:
  - Circular button
  - Chat icon or simple text

#### Behavior:
- Click → open chat window
- Click again (or close button) → close chat

---

### 4. Chat Window UI (Static)
When opened, show a chat panel with:

#### Header:
- Title (e.g. “Sarah Assistant”)
- Close button (X)

#### Body:
- Empty message area (no messages yet)

#### Input Area:
- Text input field
- Send button (non-functional)

---

### 5. UI Behavior
- Chat window should:
  - Appear above page content (fixed position)
  - Have smooth open/close behavior (basic animation optional)

- Must work on:
  - Desktop
  - Mobile (basic responsiveness)

---

## 🚫 Out of Scope (DO NOT implement)

- ❌ No API calls
- ❌ No message sending
- ❌ No chat logic
- ❌ No backend connection
- ❌ No WordPress settings page
- ❌ No persistence (localStorage, etc.)
- ❌ No authentication or tokens

---

## 🧱 Technical Requirements

- Use React (functional components)
- Keep structure modular:
  - ChatWidget (root)
  - LauncherButton
  - ChatWindow
  - Header
  - MessageArea
  - InputBox

- Bundle React into a single JS file for WordPress
- No external CDN dependencies

---

## 📋 Tasks to Execute

1. Create WordPress plugin structure
2. Setup React app (minimal)
3. Build React app into static files
4. Enqueue assets in WordPress
5. Inject root div into frontend
6. Implement launcher button UI
7. Implement chat window UI
8. Implement open/close state (React state)
9. Style basic layout (CSS)
10. Ensure no JS errors

---

## ✅ Acceptance Criteria

- Plugin installs and activates successfully
- Chat button appears on all pages
- Clicking button opens chat window
- Chat window renders correctly
- Closing works properly
- No console errors
- UI is stable and visible

---

## 📤 Expected Output

A working WordPress plugin (ZIP) that:
- Displays a floating chat widget
- Opens/closes correctly
- Shows a clean, static chat interface

---

## ⚠️ Important Constraints

- Keep everything minimal and clean
- Do NOT overbuild
- Do NOT anticipate future features
- Focus only on visual correctness

---

## 🧩 End of Phase 1

At the end of this phase, we should be able to:
👉 Install the plugin  
👉 See the widget  
👉 Evaluate the design  

Nothing more.
