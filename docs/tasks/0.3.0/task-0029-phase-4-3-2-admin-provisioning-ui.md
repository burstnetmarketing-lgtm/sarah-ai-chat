# Task 0029: Phase 4.3.2 — Admin Provisioning UI

- **Task Number:** 0029
- **Title:** Phase 4.3.2 — Admin Provisioning UI (Operational Screens)
- **Version:** 0.3.0
- **Date:** 2026-03-22

---

## User Request

Build a minimal, functional admin UI for all Phase 4.3 provisioning operations so a platform administrator can set up a complete tenant environment without using Postman or direct API calls.

---

## Implementation Summary

Introduced two new admin pages — **Tenants** (list + create) and **TenantDetail** (full setup hub) — connected entirely to the existing Phase 4.3 REST endpoints. No business logic was duplicated in the frontend.

### New Pages

**Tenants** (`assets/src/pages/Tenants.jsx`)
- Lists all tenants with name, status, and subscription state
- Create form: name + optional slug, auto-creates trial subscription via backend
- "Setup →" button navigates to TenantDetail for the selected tenant

**TenantDetail** (`assets/src/pages/TenantDetail.jsx`)
- Full operational setup hub for a single tenant
- Sections: Readiness Check, Users, Account Keys, Sites
- Each site row is expandable and contains: Site Keys, Agent Assignment, Knowledge Resources
- Readiness Check shows 8 badges (Tenant, Subscription, User, Site, Account Key, Site Key, Agent, Knowledge) — turns green when all are satisfied

### Raw Key Handling
- Account key and site key issuance shows the raw key in a yellow warning box immediately after creation
- Hashes are never displayed
- Warning message: "Copy this key now — it will not be shown again."

### Routing
- App.jsx updated to parse `#/tenants/123` style routes
- `param` is passed to page components for ID-based views
- Both `onNavigate(view)` and `onNavigate(view, param)` supported

### API Layer
- New `assets/src/api/provisioning.js` — thin wrapper over all Phase 4.3 endpoints:
  - Tenants, Users, Sites, Account Keys, Site Keys, Agents, Knowledge Resources

### Sidebar Integration
- `tenants` menu item added to `MenuRepository::ensureCoreItems()` — appears automatically on boot
- `mdi:office-building-outline` icon added to VerticalNavigationBar icon map

---

## Affected Files

- `sarah-ai-server/assets/src/api/provisioning.js` — new
- `sarah-ai-server/assets/src/pages/Tenants.jsx` — new
- `sarah-ai-server/assets/src/pages/TenantDetail.jsx` — new
- `sarah-ai-server/assets/src/App.jsx` — updated (routing + new views)
- `sarah-ai-server/assets/src/components/layout/VerticalNavigationBar.jsx` — updated (tenants icon)
- `sarah-ai-server/includes/Infrastructure/MenuRepository.php` — updated (tenants menu item)
- `sarah-ai-server/assets/dist/app.js` — rebuilt
- `sarah-ai-server/assets/dist/app.css` — rebuilt
