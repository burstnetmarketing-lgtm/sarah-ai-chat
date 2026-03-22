# Phase 4.1 — System Design Summary
## Core Infrastructure, Database & System Foundations

---

## 1. Overview

Phase 4.1 establishes the foundational data layer for Sarah as a multi-tenant SaaS platform. No end-user features are exposed in this phase. The goal is to create a stable, extensible schema and a clean set of repository classes that all future phases can build on without structural redesign.

The implementation lives entirely in the `sarah-ai-server` WordPress plugin, organized across three layers:

- **DB/** — table creation via `dbDelta`, one class per table
- **Infrastructure/** — repository classes, one per domain entity
- **Core/** — bootstrapping, activation, and idempotent seeding

---

## 2. Entities and Responsibilities

### 2.1 Tenant (`sarah_ai_server_tenants`)
The central business unit of the platform. Every customer is a tenant. A tenant owns sites, carries a subscription, and is the primary context for billing, usage reporting, and access control.

**Key fields:**
- `status` — lifecycle state: `trialing`, `active`, `inactive`, `suspended`, `archived`
- `meta` — JSON blob for future tenant-level configuration without schema changes
- `deleted_at` — soft-delete timestamp; records are never physically removed

**Design note:** `status` is stored as `VARCHAR(30)` rather than MySQL `ENUM`. This allows new lifecycle states to be added without a schema migration. The trade-off is that enforcement moves to application code rather than the database engine.

---

### 2.2 User-Tenant Association (`sarah_ai_server_user_tenant`)
Sarah maintains its own application-level ownership model, independent of WordPress native roles. A WordPress user can be associated with one or more tenants. Each association carries a Sarah-level `role` (`owner`, `admin`, `member`) and a `status`.

**Key design decisions:**
- Unique constraint on `(wp_user_id, tenant_id)` — prevents duplicate associations
- `role` is `VARCHAR(80)` — allows future roles without migration
- Deactivation sets `status = inactive` rather than deleting the row — preserves history

**Future-readiness:** The pivot table structure already supports the "user in multiple tenants" use case if that requirement surfaces later.

---

### 2.3 Site (`sarah_ai_server_sites`)
A site represents a customer's website that has integrated the Sarah client plugin. Sites are owned by tenants (`tenant_id` FK). A site has its own `status` and can be soft-deleted independently of its tenant.

**Key fields:**
- `url` — the origin of the client integration
- `active_agent_id` — denormalized FK pointing to the currently assigned agent; enables fast lookups without a JOIN to the `site_agents` pivot table
- `deleted_at` — soft-delete; allows lifecycle-aware queries

**Dual-model agent assignment:** Both `active_agent_id` on the site row and the `site_agents` pivot table coexist. `active_agent_id` is the fast read path. `site_agents` is the historical assignment log and the source of truth for future multi-agent or scheduled-assignment scenarios.

---

### 2.4 Site Token (`sarah_ai_server_site_tokens`)
Credentials that allow a client plugin installation to identify itself to the server without a logged-in user session. These are analogous to API keys or Personal Access Tokens.

**Security model:**
- Only the SHA-256 hash of the raw token is stored (`token_hash VARCHAR(64)`)
- The raw token is returned to the admin exactly once, at issuance
- If a token is lost, it must be revoked and reissued — the server cannot recover it
- `expires_at` supports time-limited tokens; `null` means no expiry

**Future-readiness:** Multiple tokens per site are supported by design, enabling key rotation, environment-specific tokens (staging vs. production), and per-integration labeling.

---

### 2.5 Agent (`sarah_ai_server_agents`)
Agents are configurable application entities that define how Sarah responds to chat messages. In this phase, two dummy agents are seeded (`sarah-basic`, `sarah-pro`). They carry no real response logic — their purpose is to anchor the agent model in the schema.

**Key fields:**
- `type` — the implementation class or strategy identifier (`dummy`, `ai`, `rules-based`, etc.)
- `config` — JSON blob for agent-specific parameters; avoids a separate config table
- `status` — `active` or `inactive`; soft-disable without deletion

**Future-readiness:** The `type` field is the extension point. When a new agent implementation is introduced (e.g., an OpenAI-backed agent), it registers as a new `type` value. The `config` JSON absorbs provider-specific settings (model, temperature, system prompt) without schema changes.

---

### 2.6 Site-Agent Assignment (`sarah_ai_server_site_agents`)
A pivot table recording which agents have been assigned to which sites over time. Not used for the current active-agent fast-path (that is `sites.active_agent_id`), but preserved as a history log and as the foundation for:

- Multi-agent assignment in the future
- Scheduled agent switching
- Audit trails of who assigned what and when (`assigned_at`)

---

### 2.7 Plan (`sarah_ai_server_plans`)
Plans define what a tenant is entitled to. In this phase, one plan is seeded: the 14-day `trial` plan.

**Key fields:**
- `duration_days` — `0` means no fixed duration (for future indefinite/monthly plans)
- `features` — JSON blob; current seed contains `max_sites`, `max_messages`, `agents`, `support_level`
- `status` — allows plans to be retired without deleting subscription history

**Future-readiness:** The `features` JSON is intentionally unstructured for now. As billing requirements solidify, specific quota columns can be added to the table alongside the blob. The blob handles everything else without forcing a migration.

---

### 2.8 Subscription (`sarah_ai_server_subscriptions`)
Links a tenant to a plan and tracks the lifecycle of that subscription.

**Status values:** `trialing`, `active`, `expired`, `cancelled`, `suspended`

**Key fields:**
- `starts_at` / `ends_at` — temporal boundaries; `ends_at = null` means open-ended
- `meta` — JSON for future billing metadata (invoice IDs, payment references, etc.)

**Assumption:** One active subscription per tenant at a time. The `findActiveByTenant()` method returns the most recent non-cancelled/expired subscription. If concurrent subscriptions become a requirement, this query needs revision.

---

### 2.9 Email Template (`sarah_ai_server_email_templates`)
A reusable template registry for transactional email. Templates use `{{variable}}` placeholder syntax rendered at send time by `EmailTemplateRepository::render()`.

**Key fields:**
- `slug` — stable identifier used in code (`welcome`, `trial-expiry`, etc.)
- `type` — category (`welcome`, `transactional`, `notification`, `alert`)
- `variables` — JSON array documenting expected placeholder names (for tooling, not runtime validation)

**Seeded template:** `welcome` — delivered when a new customer account is created.

**Future-readiness:** The `render()` method returns a `['subject' => ..., 'body' => ...]` array compatible with `wp_mail()`. Adding HTML templates, tenant-level overrides, or a template engine is a drop-in extension at the repository layer.

---

### 2.10 Usage Log (`sarah_ai_server_usage_logs`)
A structural placeholder for runtime usage tracking. No application logic writes to this table yet.

**Key fields:**
- `tenant_id`, `site_id`, `agent_id`, `subscription_id` — context dimensions for filtering and reporting
- `event_type` — string event classifier (`chat_request`, `token_consumed`, etc.)
- `tokens_in` / `tokens_out` — explicit AI token columns; expected to be the primary billing metric
- `meta` — JSON for everything else

**Design rationale:** Token columns are explicit rather than buried in `meta` because they will almost certainly become aggregate targets (`SUM(tokens_in)`) in billing queries. Querying JSON for aggregation is expensive and fragile.

---

### 2.11 Settings (`sarah_ai_server_settings`)
Key-value store for platform configuration. Extended in this phase with a `setting_group` column to support logical grouping (`platform`, `email`, `appearance`, etc.).

**Unique constraint:** `setting_key` remains globally unique (not per-group). This means keys must be globally distinct. The `setting_group` column is organizational and used for filtered reads via `getGroup()`.

**Migration note:** The `uniq_setting_key` index was in place before this phase. `dbDelta` adds the new `setting_group` column and `idx_setting_group` index on upgrade, but does not alter the existing unique key. Both constraints coexist safely.

---

## 3. Ownership Model

```
WordPress User
    └── UserTenant (role, status)
            └── Tenant (status, deleted_at)
                    ├── Subscription → Plan
                    └── Site (status, active_agent_id, deleted_at)
                            ├── SiteToken (hashed, revocable)
                            └── SiteAgent (assignment log) → Agent
```

All runtime activity (chat requests, token usage) will eventually resolve upward through:
`UsageLog → Site → Tenant → Subscription`

This chain is what makes per-tenant reporting and quota enforcement possible in later phases.

---

## 4. Seeded Baseline Data

| Entity | Slug | Notes |
|---|---|---|
| Agent | `sarah-basic` | Dummy echo agent; default for trial tenants |
| Agent | `sarah-pro` | Dummy simulation agent; for staging/integration |
| Plan | `trial` | 14-day, 1 site, 500 messages, community support |
| Email Template | `welcome` | Variables: `name`, `site_url`, `username`, `trial_days` |
| Settings | `platform.*` | `platform_name`, `trial_duration_days`, `default_agent_slug` |

All seed operations use `insertIfMissing` — idempotent and safe to run on every plugin boot.

---

## 5. Extensibility Decisions

| Decision | Rationale |
|---|---|
| `VARCHAR` for status fields instead of `ENUM` | New states can be added without `ALTER TABLE` |
| `meta LONGTEXT` (JSON) on Tenant, Site, Subscription | Absorbs future config dimensions without schema changes |
| `config LONGTEXT` on Agent | Agent-specific parameters stay with the agent record |
| `features LONGTEXT` on Plan | Plan entitlements can grow without migrations |
| `deleted_at` on Tenant and Site | Lifecycle-aware queries; no destructive deletes |
| `token_hash` only stored, never raw token | Industry-standard credential storage; breach-safe |
| `active_agent_id` on Site + `site_agents` pivot | Fast current-state read + full history |
| Explicit `tokens_in / tokens_out` on UsageLog | Aggregate-friendly; not buried in JSON |
| `setting_group` on Settings | Namespaced config without a separate table |

---

## 6. Simplifications and Assumptions

- **No FK constraints in MySQL.** WordPress plugins conventionally avoid foreign key constraints in MySQL because WordPress allows running without InnoDB and does not manage FK migrations. Referential integrity is enforced at the repository layer.

- **One active subscription per tenant.** `findActiveByTenant()` returns the most recent non-terminal subscription. If dual-subscription scenarios arise (plan upgrades mid-period, credit packs), this method needs revision.

- **`setting_key` is globally unique.** Settings are not truly namespaced per group — the group is organizational only. If two groups ever need the same key name, the unique constraint would need to change to `(setting_group, setting_key)`, which requires dropping the old index.

- **No autoloader.** All classes are manually `require_once`'d in `sarah-ai-server.php`. This is consistent with the existing pattern and avoids a Composer dependency. It does mean that adding new classes requires a manual entry in the main plugin file.

- **Seeder runs on every boot.** `Seeder::run()` is called inside `Plugin::boot()`. Since all seed methods are idempotent, this is safe but adds a small set of DB reads on every page load. If this becomes a performance concern, it can be gated behind a version flag stored in Settings.

---

## 7. Migration Risks for Future Phases

| Risk | Trigger | Mitigation |
|---|---|---|
| `setting_key` uniqueness conflict | Two groups need identical key names | Change unique key to `(setting_group, setting_key)`; requires `ALTER TABLE` and a data migration |
| `findActiveByTenant()` logic breaks | Concurrent/stacked subscriptions introduced | Revise query; add `is_primary` flag or rethink subscription model |
| `active_agent_id` drift | Site agent changed in `site_agents` without updating the denormalized column | Enforce update in `SiteRepository::updateActiveAgent()` — already implemented |
| Token expiry not enforced | Phase 5+ adds real auth validation | `findByRawToken()` currently only checks `status = active`; add `expires_at` check when runtime auth is implemented |
| No autoloader friction | Many new classes added in Phase 4.2+ | Consider adding a simple PSR-4 autoloader or Composer if require list grows unwieldy |

---

## 8. What Phase 5 Can Immediately Build On

- **Tenant onboarding flow** — `TenantRepository::create()` + `UserTenantRepository::associate()` + `SubscriptionRepository::create()` + `SiteTokenRepository::issue()` are all ready
- **Client authentication** — `SiteTokenRepository::findByRawToken()` is the validation entry point
- **Agent assignment UI** — `AgentRepository::allActive()` + `SiteRepository::updateActiveAgent()` are ready
- **Welcome email send** — `EmailTemplateRepository::render('welcome', [...])` returns subject + body for `wp_mail()`
- **Usage recording** — `UsageLogTable` columns are ready; just needs a write path
- **Plan enforcement** — `SubscriptionRepository::findActiveByTenant()` + `PlanRepository::findById()` give the active entitlements

---

*Generated: 2026-03-22 — Phase 4.1 implementation complete.*
