# Task 0027: Phase 4.3 — Admin Provisioning & Tenant Setup

- **Task Number:** 0027
- **Title:** Phase 4.3 — Admin Provisioning, Dual-Credential Model, and Tenant Setup
- **Version:** 0.3.0
- **Date:** 2026-03-22

---

## User Request

Implement Phase 4.3: transform the passive data model into a configurable platform. An administrator must be able to construct a fully functional tenant environment through REST endpoints without touching the database directly. Implement a dual-credential authentication model (Account Key + Site Key) with a separate table for tenant-level credentials.

---

## Implementation Summary

### New Table: `sarah_ai_server_account_keys`

Tenant-level credentials, independent of `site_tokens`. SHA-256 hash only stored; raw key returned once and never recoverable. Multiple keys per tenant for rotation and environment separation.

### Dual-Credential Model

| Credential | Identifies | Table |
|---|---|---|
| Account Key | Tenant | `sarah_ai_server_account_keys` |
| Site Key | Site | `sarah_ai_server_site_tokens` |

`CredentialValidator::resolveContext(accountKey, siteKey)` — validates both credentials and confirms `site.tenant_id == tenant.id`. Ready for Phase 4.4 client request handling.

### Full Provisioning Flow (via REST)

1. `POST /tenants` — create tenant + auto trial subscription
2. `POST /tenants/{id}/users` — associate WP user with role; optional welcome email
3. `POST /sites` — create site under tenant
4. `POST /tenants/{id}/account-keys` — issue account key (raw key once)
5. `POST /sites/{id}/site-keys` — issue site key (raw key once)
6. `POST /sites/{id}/agent` — assign agent (updates `active_agent_id` + logs to `site_agents`)
7. `POST /knowledge-resources` — attach knowledge to site (Phase 4.2)

### Admin Visibility Endpoints

- `GET /tenants/{id}` — tenant, subscription, sites, users
- `GET /sites/{id}` — site, agent, account keys, site keys, knowledge
- All list and show responses strip `key_hash` / `token_hash`

### New Infrastructure

- `AccountKeyRepository` — issue, findByRawKey, findByTenant, revoke
- `CredentialValidator` — resolveContext for Phase 4.4
- `SiteAgentRepository` — log() + findBySite() for audit trail

---

## Affected Files

- `sarah-ai-server/includes/DB/AccountKeyTable.php` — new
- `sarah-ai-server/includes/Infrastructure/AccountKeyRepository.php` — new
- `sarah-ai-server/includes/Infrastructure/CredentialValidator.php` — new
- `sarah-ai-server/includes/Infrastructure/SiteAgentRepository.php` — new
- `sarah-ai-server/includes/Api/TenantController.php` — new
- `sarah-ai-server/includes/Api/UserTenantController.php` — new
- `sarah-ai-server/includes/Api/SiteController.php` — new
- `sarah-ai-server/includes/Api/AccountKeyController.php` — new
- `sarah-ai-server/includes/Api/SiteTokenController.php` — new
- `sarah-ai-server/includes/Api/AgentController.php` — new
- `sarah-ai-server/sarah-ai-server.php` — updated
- `sarah-ai-server/includes/Core/Plugin.php` — updated
- `sarah-ai-server/includes/Core/Activator.php` — updated
- `docs/scenarios/sarah_phase4_3_summary.md` — system design summary
