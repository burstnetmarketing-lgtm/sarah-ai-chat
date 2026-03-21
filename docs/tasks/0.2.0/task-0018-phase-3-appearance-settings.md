# Task 0018: Phase 3 — Appearance Settings

- **Task Number:** 0018
- **Title:** Phase 3 — Appearance Settings & UI Customization
- **Version:** 0.2.0
- **Date:** 2026-03-22

---

## User Request

Admin can customize the chat widget appearance (title, welcome message, color, position) with a draft/publish system and live preview.

---

## Implementation Summary

### Draft / Publish System
- `SettingsTable.php` — added columns: `published_at`, `draft_value`, `draft_updated_at`
- `setting_value` = published value (what the widget reads)
- `draft_value` = working copy (not visible to visitors until published)
- Publish button active when `draft_updated_at > published_at`
- Discard resets draft back to published values

### PHP
- `includes/DB/SettingsTable.php` — added 3 columns via dbDelta
- `includes/Infrastructure/SettingsRepository.php` — added: `APPEARANCE_KEYS`, `APPEARANCE_DEFAULTS`, `ensureAppearanceDefaults()`, `getAllAppearance()`, `saveDraft()`, `publish()`, `discardDraft()`, `getPublishedSettings()`
- `includes/Api/AppearanceController.php` — REST API: GET /appearance, POST /appearance/draft, POST /appearance/publish, POST /appearance/discard
- `includes/Core/Plugin.php` — registers AppearanceController, calls ensureAppearanceDefaults(), passes published settings to widget via wp_localize_script
- `includes/Infrastructure/MenuRepository.php` — seeded `appearance` menu item (non-deletable)
- `sarah-ai-client.php` — added require_once for AppearanceController

### React Admin
- `assets/src/pages/AppearanceSettings.jsx` — two-column layout: left fieldsets (General + Style), right live preview
- Live preview updates in real-time as user types (no save needed)
- Buttons: Save Draft (active when form differs from savedDraft), Publish (active when canPublish), Discard (active when canPublish)
- Status indicator: "Unpublished changes" / "Up to date"
- `assets/src/App.jsx` — added `appearance` view

### React Widget + CSS
- `assets/src/widget/main.jsx` — reads published settings from `SarahAiWidget.settings`, sets `--sac-color` and `--sac-color-shadow` CSS variables, applies position (left/right)
- `assets/src/widget/Header.jsx` — reads `chatTitle` from config
- `assets/src/widget/MessageArea.jsx` — reads `welcomeMessage` from config
- `assets/css/widget.css` — all hardcoded `#2563eb` replaced with `var(--sac-color, #2563eb)`

### Settings Fields
| Key | Default |
|-----|---------|
| chat_title | Sarah Assistant |
| welcome_message | Hi 👋 How can I help you today? |
| primary_color | #2563eb |
| widget_position | right |

---

## Affected Files

- `sarah-ai-client/includes/DB/SettingsTable.php`
- `sarah-ai-client/includes/Infrastructure/SettingsRepository.php`
- `sarah-ai-client/includes/Api/AppearanceController.php` — new
- `sarah-ai-client/includes/Core/Plugin.php`
- `sarah-ai-client/includes/Infrastructure/MenuRepository.php`
- `sarah-ai-client/sarah-ai-client.php`
- `sarah-ai-client/assets/src/pages/AppearanceSettings.jsx` — new
- `sarah-ai-client/assets/src/App.jsx`
- `sarah-ai-client/assets/src/widget/main.jsx`
- `sarah-ai-client/assets/src/widget/Header.jsx`
- `sarah-ai-client/assets/src/widget/MessageArea.jsx`
- `sarah-ai-client/assets/css/widget.css`
- `sarah-ai-client/assets/dist/widget.js` — rebuilt
- `sarah-ai-client/assets/dist/widget.css` — rebuilt
- `sarah-ai-client/assets/dist/app.js` — rebuilt
