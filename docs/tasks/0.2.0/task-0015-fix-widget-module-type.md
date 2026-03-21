# Task 0015: Fix Widget Module Type

- **Task Number:** 0015
- **Title:** Fix Widget Module Type
- **Version:** 0.2.0
- **Date:** 2026-03-22

---

## User Request

Chat widget not showing on frontend.

---

## Root Cause

Vite outputs ES module format. The `widget.js` entry imports React from `chunks/client.js` using ES module `import` syntax. WordPress's `wp_enqueue_script` renders a plain `<script src="...">` tag without `type="module"`, so the browser rejects the ES module import statements.

---

## Implementation Summary

- Added `addModuleType()` static method to `Plugin.php`.
- Hooked into `script_loader_tag` filter to inject `type="module"` on the `sarah-ai-client-widget` handle only.

---

## Affected Files

- `sarah-ai-client/includes/Core/Plugin.php` — added script_loader_tag filter
