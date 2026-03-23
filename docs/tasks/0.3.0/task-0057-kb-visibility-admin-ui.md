# Task 0057 — KB Visibility Admin UI

## Goal
Allow admins to toggle public/private visibility on each knowledge resource from the Tenant Detail page without needing DB access.

## Changes

### `assets/src/pages/TenantDetail.jsx` (sarah-ai-server)
- Imported `updateKnowledgeVisibility` from provisioning.js
- Added `handleToggleVisibility(uuid, currentVisibility)` — calls API, reloads list
- Added **Visibility** column to knowledge table header
- Each row shows a clickable badge button:
  - `🌐 public` (green outline) → click sets private
  - `🔒 private` (grey filled) → click sets public
- Default fallback: resources without visibility column show as public (matches DB default)

## Commit
0057
