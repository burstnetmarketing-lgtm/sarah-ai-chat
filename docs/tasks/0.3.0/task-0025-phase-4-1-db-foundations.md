# Task 0025: Phase 4.1 — Multi-tenant DB Foundations

- **Task Number:** 0025
- **Title:** Phase 4.1 — Multi-tenant DB Foundations, Repositories, and Seeder
- **Version:** 0.3.0
- **Date:** 2026-03-22

---

## User Request

Implement the full server-side data layer for Sarah as a multi-tenant SaaS platform (Phase 4.1 scenario).

---

## Implementation Summary

Established the complete foundational data layer for `sarah-ai-server`. All tables created via `dbDelta` (safe idempotent creation on every boot). No FK constraints; referential integrity enforced at the repository layer.

### Tables Added

| Table | Purpose |
|---|---|
| `sarah_ai_server_tenants` | Customer accounts with lifecycle and soft-delete |
| `sarah_ai_server_user_tenant` | WP user ↔ tenant association with Sarah-level roles |
| `sarah_ai_server_sites` | Sites owned by tenants; denormalized `active_agent_id` |
| `sarah_ai_server_site_tokens` | SHA-256 hashed API credentials per site |
| `sarah_ai_server_agents` | Agent catalog; seeded with `sarah-basic`, `sarah-pro` |
| `sarah_ai_server_site_agents` | Agent assignment log per site |
| `sarah_ai_server_plans` | Subscription plans; seeded with 14-day `trial` |
| `sarah_ai_server_subscriptions` | Tenant subscription state and lifecycle |
| `sarah_ai_server_email_templates` | Transactional templates with `{{variable}}` rendering |
| `sarah_ai_server_usage_logs` | Runtime usage events; `tokens_in`/`tokens_out` columns |

`SettingsTable` updated with `setting_group` column and `getGroup()` on `SettingsRepository`.

### Key Design Decisions

- `VARCHAR(30)` for all status fields instead of MySQL `ENUM` — new states without `ALTER TABLE`
- SHA-256 token hashing — raw token shown once, never stored
- Soft-delete via `deleted_at` on Tenant and Site
- Denormalized `active_agent_id` on Site (fast read) + `site_agents` pivot (audit history)
- Explicit `tokens_in`/`tokens_out` on UsageLog for aggregate billing queries
- Idempotent `Seeder::run()` called on every boot

### Seeded Data

- Agents: `sarah-basic` (dummy echo), `sarah-pro` (dummy simulation)
- Plan: `trial` — 14 days, 1 site, 500 messages
- Email template: `welcome`
- Settings: `platform_name`, `trial_duration_days`, `default_agent_slug`

---

## Affected Files

- `sarah-ai-server/sarah-ai-server.php` — new require_once entries
- `sarah-ai-server/includes/DB/` — 10 new table classes
- `sarah-ai-server/includes/Infrastructure/` — 8 new repository classes + SettingsRepository updated
- `sarah-ai-server/includes/Core/Seeder.php` — new; idempotent seed operations
- `sarah-ai-server/includes/Core/Activator.php` — updated
- `sarah-ai-server/includes/Core/Plugin.php` — updated
- `docs/scenarios/sarah_phase4_1_summary.md` — system design summary
