# Sarah Chat – Phase 3 (WordPress Settings & UI Customization)

## 🎯 Goal
Add a WordPress admin settings panel to allow customization of the chat widget appearance and basic content.

This phase connects WordPress configuration → React UI.

---

## 🧠 Context
- Phase 1: UI widget built
- Phase 1.5: UI polished
- Phase 2: Mock chat interaction added

Now we want:
👉 Control widget appearance from WordPress admin

---

## 🚫 Strict Rule

DO NOT:
- Add backend/API connection
- Add billing logic
- Add authentication
- Add real AI integration

This phase is only about:
👉 WordPress settings + UI customization

---

## 📦 Scope (What MUST be implemented)

### 1. WordPress Settings Page

Create a settings page inside WordPress admin:

Menu:
- "Sarah Chat"

Inside it, add fields for:

Fields:
- Chat Title
- Welcome Message
- Primary Color
- Widget Position (left / right)

---

### 2. Store Settings

- Use WordPress Options API
- Save settings properly
- Values must persist after reload

---

### 3. Pass Settings to React

You MUST pass settings from WordPress → React UI

Recommended approach:
- wp_localize_script OR
- global JS object

Example:
window.SARAH_CONFIG = {
  title: "...",
  welcomeMessage: "...",
  color: "...",
  position: "right"
}

---

### 4. Apply Settings in UI

React must dynamically apply settings:

Chat Title:
- Replace header title

Welcome Message:
- Replace empty state message

Primary Color:
- Apply to:
  - Header background
  - Send button
  - Launcher button

Position:
- Change widget alignment:
  - bottom-right
  - bottom-left

---

### 5. Default Values

If no settings exist:
- Title: "Sarah Assistant"
- Welcome: "Hi 👋 How can I help you?"
- Color: blue
- Position: right

---

## 🧱 Technical Requirements

- Use WordPress Settings API
- Keep plugin clean and minimal
- No heavy admin UI libraries
- React should read config only once on init

---

## 📋 Tasks

1. Create WordPress admin menu
2. Build settings form
3. Save settings using WP options
4. Pass settings to frontend JS
5. Update React to read config
6. Apply styles dynamically
7. Test fallback behavior

---

## ✅ Acceptance Criteria

- Settings page exists in admin
- Values save correctly
- Changes reflect on frontend after refresh
- UI updates based on settings:
  - Title changes
  - Colors change
  - Position changes
  - Welcome message updates
- No JS or PHP errors

---

## 📤 Expected Output

A WordPress plugin that:
- Has a working admin settings page
- Controls the appearance of the chat widget
- Fully customizable UI from WordPress

---

## 🧩 End of Phase 3

After this phase:
👉 Widget is customizable  
👉 Looks like a real product  
👉 Ready for backend integration
