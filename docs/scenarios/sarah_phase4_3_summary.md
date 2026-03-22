# Phase 4.3 — System Design Summary
## Admin Provisioning & Tenant Setup

---

## 1. Overview

Phase 4.3 transforms the passive data model established in Phases 4.1 and 4.2 into a configurable platform. Before this phase, all tables and repositories existed but no operational flow was possible. After this phase, an administrator can construct a fully functional tenant environment — tenant, users, site, credentials, agent, subscription, and knowledge — entirely through REST endpoints, without touching the database directly.

The two core deliverables of this phase are:

1. **Admin provisioning endpoints** — covering the full tenant setup flow
2. **Dual-credential authentication model** — account key (tenant) + site key (site), with runtime validation ready for Phase 4.4

---

## 2. New Table: `sarah_ai_server_account_keys`

### Schema

| Column | Type | Purpose |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | Auto-increment |
| `tenant_id` | BIGINT UNSIGNED NOT NULL | Owning tenant (validated at repository layer) |
| `key_hash` | VARCHAR(64) UNIQUE NOT NULL | SHA-256 of the raw key — raw key never stored |
| `label` | VARCHAR(190) NULL | Human-readable label (e.g. `production`, `staging`) |
| `status` | VARCHAR(30) DEFAULT `active` | `active` or `revoked` |
| `expires_at` | DATETIME NULL | Optional expiry; null = no expiry |
| `created_at` | DATETIME NOT NULL | Record creation |
| `updated_at` | DATETIME NOT NULL | Last modification |

### Why a separate table

Account Keys and Site Keys are independent first-class authentication entities with separate lifecycle, rotation, and revocation behavior. Merging them into `site_tokens` would couple tenant-level credentials to site-level credentials, breaking the ownership model during key rotation, environment separation, or multi-site scenarios.

---

## 3. Dual-Credential Authentication Model

### Credential types

| Credential | Table | Identifies | Issued per |
|---|---|---|---|
| Account Key | `sarah_ai_server_account_keys` | Tenant | Tenant (multiple allowed) |
| Site Key | `sarah_ai_server_site_tokens` | Site | Site (multiple allowed) |

### Runtime validation — `CredentialValidator::resolveContext()`

```
1. account key  →  AccountKeyRepository::findByRawKey()  →  tenant_id  →  Tenant
2. site key     →  SiteTokenRepository::findByRawToken()  →  site_id   →  Site
3. site.tenant_id must equal tenant.id
```

All three checks must pass. If any step fails, `null` is returned — no distinction is exposed to the caller (prevents enumeration attacks).

The client sends only two values: account key + site key. No `tenant_id` or `site_id` is required in the request payload.

### Security properties

- Raw keys are never stored. Only the SHA-256 hash is persisted.
- Raw keys are returned exactly once at issuance and cannot be recovered.
- `key_hash` is stripped from all API list and show responses.
- Revocation is per-key and permanent. Other keys for the same tenant or site are unaffected.
- Multiple keys per tenant/site enable rotation and environment separation (staging vs. production) without downtime.

---

## 4. Admin Provisioning Flow

The full setup sequence for a new tenant:

```
1. POST /tenants                          → tenant + trial subscription created
2. POST /tenants/{id}/users               → associate WP user with role
3. POST /sites                            → site created under tenant
4. POST /tenants/{id}/account-keys        → account key issued (raw key returned once)
5. POST /sites/{id}/site-keys             → site key issued (raw key returned once)
6. POST /sites/{id}/agent                 → agent assigned
7. POST /knowledge-resources              → knowledge attached to site (Phase 4.2)
```

After step 7, the tenant environment is fully configured and ready for client requests.

---

## 5. New REST Endpoints

**Base:** `/wp-json/sarah-ai-server/v1/`

### Tenant

| Method | Endpoint | Description |
|---|---|---|
| POST | `/tenants` | Create tenant + auto trial subscription |
| GET | `/tenants` | List all tenants with subscription status |
| GET | `/tenants/{id}` | Full context: tenant, subscription, sites, users |
| POST | `/tenants/{id}/status` | Update tenant lifecycle status |

### Users

| Method | Endpoint | Description |
|---|---|---|
| POST | `/tenants/{id}/users` | Associate WP user with tenant (role, optional welcome email) |
| GET | `/tenants/{id}/users` | List user associations (enriched with WP login/email) |
| DELETE | `/tenants/{id}/users/{wpUserId}` | Deactivate association |

### Sites

| Method | Endpoint | Description |
|---|---|---|
| POST | `/sites` | Create site under tenant |
| GET | `/tenants/{id}/sites` | List sites for tenant |
| GET | `/sites/{id}` | Full context: site, agent, account keys, site keys, knowledge |
| POST | `/sites/{id}/status` | Update site lifecycle status |

### Account Keys

| Method | Endpoint | Description |
|---|---|---|
| POST | `/tenants/{id}/account-keys` | Issue account key (raw key returned once) |
| GET | `/tenants/{id}/account-keys` | List keys (no hash, no raw key) |
| DELETE | `/account-keys/{id}` | Revoke |

### Site Keys

| Method | Endpoint | Description |
|---|---|---|
| POST | `/sites/{id}/site-keys` | Issue site key (raw key returned once) |
| GET | `/sites/{id}/site-keys` | List keys (no hash, no raw key) |
| DELETE | `/site-keys/{id}` | Revoke |

### Agents

| Method | Endpoint | Description |
|---|---|---|
| GET | `/agents` | List all active agents |
| POST | `/sites/{id}/agent` | Assign agent to site |

---

## 6. New Infrastructure

### `AccountKeyRepository`

| Method | Notes |
|---|---|
| `issue(tenantId, rawKey, label, expiresAt)` | Hashes rawKey before storage; returns record ID |
| `findByRawKey(rawKey)` | Hashes internally; returns active record or null |
| `findByHash(hash)` | Direct hash lookup |
| `findByTenant(tenantId)` | All keys (active + revoked) for a tenant |
| `revoke(id)` | Sets status = revoked; does not affect other keys |

### `CredentialValidator`

| Method | Notes |
|---|---|
| `resolveContext(accountKey, siteKey)` | Returns `['tenant' => [...], 'site' => [...]]` or null |

Primary entry point for Phase 4.4 client-facing endpoints.

### `SiteAgentRepository`

| Method | Notes |
|---|---|
| `log(siteId, agentId)` | Records an assignment event in site_agents audit table |
| `findBySite(siteId)` | Returns full assignment history for a site |

Agent assignment always updates two places: `SiteRepository::updateActiveAgent()` (fast read path) and `SiteAgentRepository::log()` (audit trail).

---

## 7. Subscription Auto-Assignment

When a tenant is created via `POST /tenants`, the system automatically:

1. Looks up the `trial` plan via `PlanRepository::findBySlug('trial')`
2. Calculates `ends_at` from `plan.duration_days` (null if `duration_days = 0`)
3. Creates a subscription with `status = trialing`

This ensures every tenant has a subscription from day one. The subscription structure supports future transitions: `trialing → active → expired`.

---

## 8. Admin Visibility

The following endpoints provide full operational visibility without requiring a dedicated reporting layer:

| Query | Endpoint |
|---|---|
| Full tenant configuration | `GET /tenants/{id}` |
| All sites for a tenant | `GET /tenants/{id}/sites` |
| Full site configuration | `GET /sites/{id}` |
| User associations | `GET /tenants/{id}/users` |
| Account keys for tenant | `GET /tenants/{id}/account-keys` |
| Site keys for site | `GET /sites/{id}/site-keys` |
| Available agents | `GET /agents` |

`GET /sites/{id}` returns the full site context in one call: site record, assigned agent, account keys (for parent tenant), site keys, and knowledge resources.

---

## 9. Design Decisions

| Decision | Rationale |
|---|---|
| Account Key in a separate table | Independent lifecycle, rotation, and revocation from site keys. Merging would couple tenant and site credential management. |
| Raw key returned once, never stored | Industry-standard credential security. If lost, the key must be revoked and reissued. |
| `key_hash` stripped from all API responses | Hashes must not be exposed — they are the verification secret, not display data. |
| `CredentialValidator` as a standalone class | Phase 4.4 needs a single entry point for auth resolution. Keeping it out of controllers makes it testable and reusable. |
| Trial subscription auto-created at tenant creation | Every tenant must have a subscription from day one for quota and reporting to work consistently. |
| `SiteAgentRepository::log()` alongside `updateActiveAgent()` | Denormalized `active_agent_id` is the fast read path; `site_agents` table is the audit log. Both must be updated together. |
| User enrichment in list response | `GET /tenants/{id}/users` adds WP `user_login` and `user_email` to each association for admin usability without a separate lookup. |

---

## 10. What Phase 4.4 Can Immediately Build On

- `CredentialValidator::resolveContext(accountKey, siteKey)` — call this at the start of every client-facing endpoint to resolve and validate tenant + site context
- `KnowledgeResourceRepository::findActiveBySite()` — retrieve agent-usable knowledge for the resolved site
- `AgentRepository::findById()` — load the assigned agent config from the resolved site's `active_agent_id`
- All provisioning is complete — no structural changes required to begin handling client chat requests

---

*Generated: 2026-03-22 — Phase 4.3 implementation complete.*
