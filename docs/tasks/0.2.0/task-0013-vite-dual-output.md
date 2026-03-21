# Task 0013: Vite Dual Output

- **Task Number:** 0013
- **Title:** Vite Dual Output
- **Version:** 0.2.0
- **Date:** 2026-03-22

---

## User Request

Build admin dashboard and chat widget as separate JS/CSS bundles. Widget should be lightweight and independent from Bootstrap.

---

## Implementation Summary

- Updated `vite.config.js` to use two Rollup entry points:
  - `app` → `assets/src/main.jsx` → `app.js` + `app.css` (admin SPA with Bootstrap)
  - `widget` → `assets/src/widget/main.jsx` → `widget.js` + `widget.css` (lightweight chat widget)
- Asset names now use `[name].js` / `[name].css` pattern instead of hardcoded `app.js`.

---

## Affected Files

- `sarah-ai-client/vite.config.js` — updated to dual entry
