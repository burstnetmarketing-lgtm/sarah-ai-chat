# Phase 4.4.1 — Implementation Summary
## Server Runtime, Session Foundation & Request Handling

---

## 1. Overview

Phase 4.4.1 transforms the Sarah platform from a passive provisioning system into a live, session-aware conversation runtime.

Before this phase, the system had a complete data model, tenant provisioning flow, dual credentials, agent assignment, subscription structure, and knowledge resources — but no ability to execute a chat request. After this phase, a configured site can send a message with valid credentials and receive a server-generated response, with every message persisted under a tracked session.

This phase is strictly server-side. It does not depend on the admin UI and works with any correctly provisioned tenant, whether created through REST endpoints or admin screens.

---

## 2. New Files

### DB Tables
| File | Table | Purpose |
|------|-------|---------|
| `ChatSessionTable.php` | `sarah_ai_server_chat_sessions` | Chat session records — tenant, site, agent, lead info, status |
| `ChatMessageTable.php` | `sarah_ai_server_chat_messages` | Per-session messages — role, content, meta |
| `UsageLogTable.php` (updated) | `sarah_ai_server_usage_logs` | Added `session_id` column |

### Infrastructure
| File | Purpose |
|------|---------|
| `ChatSessionRepository.php` | create, findByUuid, findById, findBySite, findByTenant, updateLeadInfo, mergeCapturedData, close |
| `ChatMessageRepository.php` | add, findBySession (ordered ASC) |
| `UsageLogRepository.php` | log per-request usage event |

### Runtime
| File | Purpose |
|------|---------|
| `AgentExecutorInterface.php` | Contract: `execute(context): {content, tokens_in, tokens_out, provider, model}` |
| `OpenAiAgentExecutor.php` | OpenAI Chat Completions via `wp_remote_post` — no SDK dependency |
| `RuntimeEligibilityChecker.php` | 6-step check: tenant active → site active → subscription → agent assigned → agent active → agent in plan |
| `ChatRuntime.php` | Full 10-step pipeline orchestrator |

### API Controllers
| File | Endpoint | Auth |
|------|----------|------|
| `ChatController.php` | `POST /chat` | Public — account_key + site_key |
| `SessionController.php` | `GET /sessions`, `GET /sessions/{uuid}`, `GET /sessions/{uuid}/messages` | account_key + site_key + X-Sarah-Platform-Key header |

---

## 3. Runtime Pipeline (ChatRuntime)

```
1. Credential validation     → CredentialValidator::resolveContext()
2. Eligibility check         → RuntimeEligibilityChecker::check()
3. Session resolution        → find existing or create new
3b. Lead info attachment     → updateLeadInfo() if lead provided
4. Persist customer message  → ChatMessageRepository::add()
5. Load site knowledge       → KnowledgeResourceRepository::findActiveBySite()
6. Load history              → last 20 messages, excluding current
7. Execute agent             → AgentExecutorInterface::execute()
8. Persist assistant reply   → ChatMessageRepository::add()
9. Log usage                 → UsageLogRepository::log()
10. Return response          → {success, session_uuid, message, agent}
```

---

## 4. Authentication Model

### POST /chat
```
account_key + site_key  →  CredentialValidator resolves tenant + site
```

### GET /sessions (read-only inspection)
```
account_key + site_key + X-Sarah-Platform-Key header
```
- Platform key stored in `sarah_ai_server_settings` (key: `platform_api_key`, namespace: `platform`)
- Default seed value: `www.BurstNET.com.au`
- Sessions scoped to resolved site — cross-tenant access not possible
- No WordPress login required

---

## 5. Session Schema

| Column | Type | Purpose |
|--------|------|---------|
| `uuid` | VARCHAR(36) | Public identifier |
| `tenant_id` | BIGINT | Owning tenant |
| `site_id` | BIGINT | Owning site |
| `agent_id` | BIGINT | Agent at session creation |
| `subscription_id` | BIGINT | Subscription at session creation |
| `status` | VARCHAR(30) | `open`, `closed`, `archived`, `abandoned` |
| `visitor_name` | VARCHAR(190) | Lead — first-class field |
| `visitor_phone` | VARCHAR(50) | Lead — first-class field |
| `visitor_email` | VARCHAR(190) | Lead — first-class field |
| `captured_data` | LONGTEXT JSON | Flexible structured extras (suburb, budget, etc.) |
| `meta` | LONGTEXT JSON | Internal runtime metadata |

---

## 6. Message Schema

| Column | Type | Purpose |
|--------|------|---------|
| `uuid` | VARCHAR(36) | Public identifier |
| `session_id` | BIGINT | Owning session |
| `role` | VARCHAR(30) | `customer`, `assistant`, `system` |
| `content` | LONGTEXT | Message body |
| `meta` | LONGTEXT JSON | provider, model, tokens_in, tokens_out |

---

## 7. OpenAI Executor

- Uses `wp_remote_post` — no Composer dependencies
- API key: `openai_api_key` from settings (empty = mock response)
- Builds system prompt from active site knowledge resources
- History: last 20 messages passed as context
- `o1` model special case: uses `max_completion_tokens`, no `temperature`
- Mock response when key not set: `[TEST MODE] Hello! I am {agent}...`

---

## 8. UI Changes (TenantDetail)

- **AccountKeysSection**: fixed rawKey display bug — replaced `onReload` (caused parent unmount + lost state) with `onKeysChange` pattern
- **SiteKeysSection**: added site selector dropdown — supports multi-site tenants
- **AgentSection**: refactored to table view — shows all sites with their current agent, inline assign/change/remove
- **SiteCreateSection**: added Agent column — badge if assigned, dash if not
- **AgentController**: added `DELETE /sites/{uuid}/agent` unassign endpoint
- **TenantDetail load()**: now also fetches `listAgents()` on boot — passed to both SiteCreateSection and AgentSection

---

## 9. Test Script — `docs/technical/test-chat.bat`

Windows curl script covering all 4 runtime steps:

| Step | Action | Result |
|------|--------|--------|
| 1 | POST /chat (new session) | session_uuid returned |
| 2 | POST /chat + session_uuid | conversation continued |
| 3 | GET /sessions/{uuid} | session detail with lead fields |
| 4 | GET /sessions/{uuid}/messages | full ordered message history |

All output saved as JSON files + timestamped log.

---

## 10. Security Properties

- Server never trusts client-supplied `tenant_id`, `site_id`, or `agent_id`
- Session ownership verified before attachment (prevents cross-site session injection)
- Key hashes never exposed in responses
- Agent execution blocked unless full eligibility chain passes
- Platform key check uses `hash_equals()` (timing-safe comparison)

---

## 11. Phase Boundary

Phase 4.4.1 ends with a fully operational, session-aware server runtime.

Phase 4.4.2 adapts the client widget to consume this runtime — replacing mock responses with real server calls using the `POST /chat` endpoint and `session_uuid` continuity.
