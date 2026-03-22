# Sarah AI — Complete System Documentation

> Single reference document covering all phases, all APIs, all components, and the real-user testing checklist.
> Generated: 2026-03-23

---

## Table of Contents

1. [Project Overview](#1-project-overview)
2. [Architecture](#2-architecture)
3. [Phase 4.1 — Database & Core Infrastructure](#3-phase-41--database--core-infrastructure)
4. [Phase 4.2 — Knowledge Base Foundation](#4-phase-42--knowledge-base-foundation)
5. [Phase 4.3 — Provisioning & Dual-Credential Auth](#5-phase-43--provisioning--dual-credential-auth)
6. [Phase 4.4.1 — Chat Runtime & Session Engine](#6-phase-441--chat-runtime--session-engine)
7. [Phase 4.4.2 — Client Widget API Integration](#7-phase-442--client-widget-api-integration)
8. [Phase 4.5 — Usage Observability](#8-phase-45--usage-observability)
9. [Phase 5 — Agent Behavior & Control](#9-phase-5--agent-behavior--control)
10. [Phase 6.1 — Knowledge Processing Pipeline](#10-phase-61--knowledge-processing-pipeline)
11. [Phase 6.2 — RAG Retrieval Runtime](#11-phase-62--rag-retrieval-runtime)
12. [Client Widget Tasks (1–7)](#12-client-widget-tasks-17)
13. [Complete API Reference](#13-complete-api-reference)
14. [Database Schema Reference](#14-database-schema-reference)
15. [Real-User Testing Checklist](#15-real-user-testing-checklist)

---

## 1. Project Overview

Sarah AI is a dual-plugin WordPress system. One plugin runs on the server and manages AI configuration, tenants, and sessions. The other runs on any client site and renders a floating chat widget.

### Two Plugins

| Plugin | Role |
|--------|------|
| `sarah-ai-server` | SaaS platform backend: tenants, sites, credentials, agents, knowledge, RAG pipeline, session tracking, usage logging, admin UI |
| `sarah-ai-client` | Client-side chat widget: floating button, conversation UI, session persistence, error recovery, history restore |

### Technology Stack

- **Backend:** PHP (WordPress plugin), MySQL, OpenAI API (Chat Completions + Embeddings)
- **Admin UI:** React (SPA inside wp-admin), bundled with Vite
- **Widget:** React, bundled with Vite, injected into client WordPress frontend
- **No Composer:** All PHP uses `wp_remote_post` / `wp_remote_get` — zero server-side dependencies

---

## 2. Architecture

### Ownership Model

```
WordPress User
    └── UserTenant (role, status)
            └── Tenant (status, deleted_at)
                    ├── Subscription → Plan
                    └── Site (status, active_agent_id, deleted_at)
                            ├── AccountKey (SHA-256 hashed, revocable)
                            ├── SiteToken / SiteKey (SHA-256 hashed, revocable)
                            ├── KnowledgeResource (status, processing_status)
                            │       └── KnowledgeChunk (chunk_text, embedding)
                            └── ChatSession
                                    ├── ChatMessage (role, content)
                                    └── UsageLog (tokens_in, tokens_out)
```

### Dual Credential System

Every client request authenticates using two keys:

| Key | Identifies | Stored As |
|-----|-----------|-----------|
| `account_key` | Tenant | SHA-256 hash |
| `site_key` | Site | SHA-256 hash |

The server resolves `account_key → tenant`, `site_key → site`, then verifies `site.tenant_id === tenant.id`. All three must pass.

### Data Flow (Chat Request)

```
Widget (React) → POST /chat (account_key, site_key, message, session_uuid?)
    → CredentialValidator: resolve tenant + site
    → RuntimeEligibilityChecker: 6 checks
    → ChatRuntime: find/create session → persist message
    → SemanticRetriever: embed query → cosine similarity → top-5 chunks
    → OpenAiAgentExecutor: build system prompt → OpenAI Chat Completions
    → Persist assistant reply → log usage
    → Return { success, session_uuid, message, agent }
```

---

## 3. Phase 4.1 — Database & Core Infrastructure

**Goal:** Establish the complete data model for the multi-tenant platform. No user features — all foundation.

### Tables Created

| Table | Purpose |
|-------|---------|
| `sarah_ai_server_tenants` | Central business unit — owns sites |
| `sarah_ai_server_user_tenant` | WP user ↔ tenant associations with role |
| `sarah_ai_server_sites` | Client websites under a tenant |
| `sarah_ai_server_site_tokens` | Site keys (hashed) |
| `sarah_ai_server_agents` | AI agent configs (model, params) |
| `sarah_ai_server_site_agents` | Agent assignment history log |
| `sarah_ai_server_plans` | Subscription plan definitions |
| `sarah_ai_server_subscriptions` | Tenant → plan link with lifecycle |
| `sarah_ai_server_email_templates` | Transactional email templates |
| `sarah_ai_server_usage_logs` | Per-request token tracking |
| `sarah_ai_server_settings` | Key-value platform config |

### Key Design Decisions

- All status fields use `VARCHAR(30)` not `ENUM` — new states without `ALTER TABLE`
- `meta LONGTEXT` (JSON) on tenant/site/subscription — avoids schema changes for config
- Raw tokens never stored — only `SHA-256` hashes
- `active_agent_id` on site (fast read path) + `site_agents` pivot (audit log)
- Explicit `tokens_in` / `tokens_out` columns on usage log — aggregate-friendly
- Soft-delete with `deleted_at` on tenant and site

### Seeded Baseline

| Entity | Value |
|--------|-------|
| Agent `sarah-basic` | Dummy echo agent |
| Agent `sarah-pro` | Dummy simulation agent |
| Plan `trial` | 14-day, 1 site, 500 messages |
| Email template `welcome` | New customer welcome |
| Settings `platform.*` | `platform_name`, `trial_duration_days`, `default_agent_slug` |

---

## 4. Phase 4.2 — Knowledge Base Foundation

**Goal:** Knowledge resources table with lifecycle and processing state. No RAG or chunking yet.

### Table: `sarah_ai_server_knowledge_resources`

| Column | Type | Purpose |
|--------|------|---------|
| `site_id` | BIGINT | Owning site |
| `title` | VARCHAR(190) | Human label |
| `resource_type` | VARCHAR(80) | `text`, `link`, `pdf`, `docx`, `txt` (open classifier) |
| `content_group` | VARCHAR(80) | Logical category: `faq`, `policy`, `product`, etc. |
| `status` | VARCHAR(30) | Admin intent: `pending`, `active`, `inactive`, `archived` |
| `source_content` | LONGTEXT | Raw text or URL |
| `processing_status` | VARCHAR(30) | Pipeline outcome: `none`, `queued`, `done`, `failed` |
| `meta` | LONGTEXT JSON | Non-query metadata |
| `deleted_at` | DATETIME | Soft-delete |

### Two State Dimensions (Critical Design)

These are **orthogonal** — never conflate them:

| Field | Owner | Meaning |
|-------|-------|---------|
| `status` | Admin | Is this resource available to agents? |
| `processing_status` | Pipeline | Has the content been extracted and embedded? |

Example: `status=active, processing_status=none` — resource is live, using raw source_content.

### Ownership Rule

Knowledge belongs to a **site**, not a tenant or agent:
```
Agent → Site → KnowledgeResources
```
Tenant is derived: `resource.site_id → sites.tenant_id`

---

## 5. Phase 4.3 — Provisioning & Dual-Credential Auth

**Goal:** REST endpoints for full tenant setup. Dual-credential authentication model.

### New Table: `sarah_ai_server_account_keys`

| Column | Purpose |
|--------|---------|
| `tenant_id` | Owning tenant |
| `key_hash` | SHA-256 of raw key (raw never stored) |
| `label` | Human label (e.g. `production`, `staging`) |
| `status` | `active` or `revoked` |
| `expires_at` | Optional expiry; null = no expiry |

### Full Tenant Provisioning Flow

```
1. POST /tenants                       → tenant + trial subscription
2. POST /tenants/{id}/users            → associate WP user with role
3. POST /sites                         → create site under tenant
4. POST /tenants/{id}/account-keys     → issue account key (raw returned once)
5. POST /sites/{id}/site-keys          → issue site key (raw returned once)
6. POST /sites/{id}/agent              → assign agent
7. POST /knowledge-resources           → attach knowledge to site
```

### `CredentialValidator::resolveContext()`

```
1. account_key → AccountKeyRepository::findByRawKey() → tenant_id → Tenant
2. site_key    → SiteTokenRepository::findByRawToken() → site_id   → Site
3. site.tenant_id must === tenant.id
```
Returns `['tenant' => [...], 'site' => [...]]` or `null` on any failure (no enumeration leak).

### Security Properties

- Raw keys returned exactly once at issuance — cannot be recovered
- `key_hash` stripped from all API responses
- Revocation is per-key — other keys for the same tenant/site unaffected
- Multiple keys per tenant/site → zero-downtime rotation

---

## 6. Phase 4.4.1 — Chat Runtime & Session Engine

**Goal:** Live session-aware conversation runtime. Server transforms from provisioning tool to AI chat backend.

### Runtime Pipeline (10 Steps)

```
1.  CredentialValidator::resolveContext()      → tenant + site
2.  RuntimeEligibilityChecker::check()         → 6 checks
3.  Session resolution                         → find existing or create new
3b. Lead info attachment                       → if lead provided in request
4.  Persist customer message                   → ChatMessageRepository::add()
5.  Load site knowledge                        → KnowledgeResourceRepository::findActiveBySite()
6.  Load conversation history                  → last 20 messages
7.  Execute agent                              → AgentExecutorInterface::execute()
8.  Persist assistant reply                    → ChatMessageRepository::add()
9.  Log usage                                  → UsageLogRepository::log()
10. Return response                            → { success, session_uuid, message, agent }
```

### Eligibility Check (6 Steps)

1. Tenant status = `active`
2. Site status = `active`
3. Active subscription exists
4. Site has an active agent assigned
5. Agent status = `active`
6. Agent is allowed by the plan

### Session Schema

| Column | Purpose |
|--------|---------|
| `uuid` | Public identifier — passed to client |
| `tenant_id`, `site_id`, `agent_id` | Context at session creation |
| `subscription_id` | Subscription snapshot |
| `status` | `open`, `closed`, `archived`, `abandoned` |
| `visitor_name`, `visitor_phone`, `visitor_email` | Lead data |
| `captured_data` | JSON — flexible extras (suburb, budget, etc.) |

### Message Schema

| Column | Purpose |
|--------|---------|
| `session_id` | Owning session |
| `role` | `customer`, `assistant`, `system` |
| `content` | Message text |
| `meta` | JSON: provider, model, tokens_in, tokens_out |

### OpenAI Executor

- `wp_remote_post` — no Composer dependency
- API key: `openai_api_key` from settings (empty → mock response)
- `o1` model: `max_completion_tokens`, no `temperature`
- Mock response when no key: `[TEST MODE] Hello! I am {agent}...`
- History: last 20 messages as context

### Auth Model

| Endpoint | Auth |
|----------|------|
| `POST /chat` | `account_key` + `site_key` (body) |
| `GET /sessions` | account_key + site_key + `X-Sarah-Platform-Key` header |

---

## 7. Phase 4.4.2 — Client Widget API Integration

**Goal:** Connect the React widget to the live server. Replace mock responses with real `POST /chat` calls.

### Connection Config (Runtime)

```javascript
window.SarahAiWidget.connection = {
  server_url:  'https://server.example.com/wp-json/sarah-ai-server/v1',
  account_key: '<tenant account key>',
  site_key:    '<site key>',
}
```
Populated via `wp_localize_script` from Settings.

### Widget Flow

```
1. Widget opens → ChatWindow mounts, sessionUuid = null
2. User types → sendMessage()
3. chatApi.js reads window.SarahAiWidget.connection
4. POST {server_url}/chat { account_key, site_key, message, session_uuid? }
5. On success → store returned session_uuid (first message only)
6. Display assistant reply
7. Subsequent messages include session_uuid → server continues session
8. On error → friendly error bubble
```

### Error Handling

| Scenario | Widget Behaviour |
|----------|-----------------|
| Settings not configured | "Chat is not configured yet." |
| Network failure / non-2xx | "Unable to connect. Please try again." |
| Server returns `success: false` | Server-provided message shown |

---

## 8. Phase 4.5 — Usage Observability

**Goal:** Expose token usage data through API and admin UI.

### API Endpoints

#### GET /wp-json/sarah-ai-server/v1/usage

Filter params: `tenant_id`, `site_id`, `agent_id`, `session_id`, `date_from`, `date_to`, `limit` (max 200), `offset`

```json
{
  "success": true,
  "data": [{ "id": 1, "tenant_id": 1, "site_id": 1, "tokens_in": 142, "tokens_out": 88, "meta": { "model": "gpt-4o-mini" }, "created_at": "..." }],
  "meta": { "limit": 50, "offset": 0, "count": 1 }
}
```

#### GET /wp-json/sarah-ai-server/v1/usage/summary

Same filters (no pagination).

```json
{ "success": true, "data": { "total_requests": 47, "total_tokens_in": 6230, "total_tokens_out": 3815 } }
```

### Admin Usage Page (`#/usage`)

- Summary cards: Total Requests, Tokens In, Tokens Out
- Filter form: Tenant, Site, Agent, Session, Date range
- Paginated table (50/page): all usage log columns

---

## 9. Phase 5 — Agent Behavior & Control

**Goal:** Controllable AI agent personality. Role, tone, custom system prompt per agent.

### New Files / Changes

| File | Change |
|------|--------|
| `OpenAiAgentExecutor.php` | Rewrote `buildSystemPrompt()` — role, tone, description, guardrails, custom override |
| `AgentRepository.php` | Added `updateBehavior(id, role, tone, systemPrompt)` |
| `AgentController.php` | Added `PUT /agents/{id}/behavior` |
| `Agents.jsx` | Per-agent role/tone/system-prompt editor |

### System Prompt Priority

```
1. config.system_prompt (non-empty) → used as full prompt (custom override)
2. Otherwise → composed from role + tone + description + guardrails
```

### Composed Prompt Structure

```
You are a {role}.
{description}
{tone instruction}

## Behaviour Rules
- Answer only what you know. If unsure, say so.
- Do not make up facts, names, prices, dates.
- Stay within your defined role and domain.
- If outside scope, politely decline.
- Do not generate harmful or misleading content.

## Knowledge Base    ← if resources exist
...
```

### Tone Options

| Value | Instruction |
|-------|-------------|
| `friendly` | Be warm, approachable, and friendly. |
| `professional` | Maintain a professional and formal tone. |
| `concise` | Be brief and to the point. |
| `formal` | Use formal language. Avoid contractions. |

### Agent Config Fields

| Field | Purpose |
|-------|---------|
| `model` | OpenAI model (e.g. `gpt-4o-mini`) |
| `max_tokens` | Max completion tokens |
| `temperature` | Sampling temperature |
| `role` | Agent purpose (system prompt) |
| `tone` | Communication style |
| `system_prompt` | Custom override — replaces composed prompt |

### API Endpoint

```
PUT /wp-json/sarah-ai-server/v1/agents/{id}/behavior
Body: { "role": "...", "tone": "friendly", "system_prompt": "" }
Auth: manage_options
```

---

## 10. Phase 6.1 — Knowledge Processing Pipeline

**Goal:** Transform stored knowledge resources into retrieval-ready chunks with OpenAI embeddings.

### New Files

| File | Role |
|------|------|
| `DB/KnowledgeChunksTable.php` | New DB table |
| `Infrastructure/KnowledgeChunkRepository.php` | Chunk data access |
| `Processing/KnowledgeTextExtractor.php` | Type-specific text extraction |
| `Processing/KnowledgeChunker.php` | Paragraph-aware sliding window chunker |
| `Processing/EmbeddingService.php` | OpenAI embeddings API wrapper |
| `Processing/KnowledgeProcessingService.php` | Pipeline orchestrator |
| `Api/KnowledgeProcessingController.php` | REST endpoints |

### New Table: `sarah_ai_server_knowledge_chunks`

| Column | Type | Purpose |
|--------|------|---------|
| `uuid` | VARCHAR(36) | External ID |
| `resource_id` | BIGINT | Owning knowledge resource |
| `site_id` | BIGINT | Denormalized for bulk reads |
| `chunk_index` | INT | Position within resource |
| `chunk_text` | LONGTEXT | Extracted text slice |
| `embedding` | LONGTEXT | JSON float array — 1536 dims |
| `embedding_model` | VARCHAR(80) | e.g. `text-embedding-3-small` |
| `token_count` | INT | ~chars / 4 |

### Pipeline Steps

```
source_content
    ↓ 1. KnowledgeTextExtractor  (type dispatch)
raw text
    ↓ 2. normalize()  (line endings, control chars, whitespace)
clean text
    ↓ 3. KnowledgeChunker  (paragraph sliding window)
[ chunk_0, chunk_1, ..., chunk_N ]
    ↓ 4. EmbeddingService  (OpenAI text-embedding-3-small, batch 20)
[ embedding_0, ..., embedding_N ]
    ↓ 5. KnowledgeChunkRepository::saveChunks()
DB rows
    ↓ 6. processing_status = done
```

### Supported Resource Types

| Type | Extraction |
|------|-----------|
| `text` / `txt` | `source_content` direct |
| `link` | `wp_remote_get` + strip HTML + collapse whitespace |
| `pdf` | Fetch binary + decompress FlateDecode + BT/ET regex |
| `docx` | Fetch binary + `ZipArchive` + parse `word/document.xml` |

### Chunking Strategy

- Chunk size: 1500 chars (~375 tokens)
- Overlap: 200 chars — preserves sentence context at boundaries
- Paragraph-first: accumulates paragraphs until limit
- Oversized paragraph: sentence split → hard split

### Embedding Service

- Provider: OpenAI `/v1/embeddings`
- Model: `text-embedding-3-small` (1536 dimensions)
- Batch size: 20 texts per API call
- Graceful skip: if no API key → chunks saved without vectors, still `done`

### Processing Lifecycle

| `processing_status` | Meaning |
|--------------------|---------|
| `none` | Not yet processed |
| `queued` | Pipeline triggered |
| `done` | Chunks saved (with or without embeddings) |
| `failed` | Error — check `meta.processing_error` |

Reprocessing is safe — old chunks deleted before new ones written.

### API Endpoints

```
POST /wp-json/sarah-ai-server/v1/knowledge-resources/{uuid}/process
→ { "success": true, "chunks": 12, "message": "Chunks and embeddings saved." }

GET  /wp-json/sarah-ai-server/v1/knowledge-resources/{uuid}/chunks
→ { "success": true, "chunk_count": 12, "chunks": [...] }

Auth: manage_options
```

### Admin UI Changes

- Knowledge table in TenantDetail: added `Processing` column
- Badge colors: `none` = grey, `queued` = blue, `done` = green, `failed` = red
- `⚙ Process` button per row — disables while in-flight, reloads on complete

---

## 11. Phase 6.2 — RAG Retrieval Runtime

**Goal:** At chat time, embed the user query, find semantically relevant chunks, inject into system prompt.

### New Files

| File | Change |
|------|--------|
| `Processing/SemanticRetriever.php` | New — query embedding + cosine similarity + top-K |
| `Infrastructure/KnowledgeChunkRepository.php` | Added `findWithEmbeddingsBySite()` |
| `Runtime/OpenAiAgentExecutor.php` | Integrated retrieval; updated `buildSystemPrompt()` |

### Runtime Flow

```
User message → OpenAiAgentExecutor::execute()
    ↓
SemanticRetriever::retrieve(siteId, userMessage)
    1. Load all chunks with embeddings (active+done resources)
    2. Embed user message → query vector
    3. Cosine similarity: query vs. each chunk
    4. Sort descending, take top-5
    ↓
Retrieved chunks: [{ chunk_text, resource_title, score }]
    ↓
buildSystemPrompt() → inject into ## Knowledge Base section
    ↓
OpenAI Chat Completions API
    ↓
Answer grounded in site knowledge
```

### Knowledge Injection Mode

| Condition | Mode |
|-----------|------|
| `retrievedChunks` non-empty | **RAG mode** — top-K chunk_text injected |
| `retrievedChunks` empty | **Fallback mode** — raw source_content from all active resources |

The fallback ensures sites without processed knowledge still work (backward compatible).

### Cosine Similarity

```
similarity = dot(a, b) / (|a| × |b|)
```
Range: [-1, 1]. Higher = more relevant.

### RAG System Prompt Structure

```
You are a {role}.
{tone instruction}

## Behaviour Rules
...

{identity section if configured}

## Knowledge Base

Use the following information. Rely only on what is provided — do not invent facts.

### {resource_title}
{chunk_text_0}

### {resource_title}
{chunk_text_1}
...
```

### Backward Compatibility

- No DB migration, no API changes, no admin UI changes required
- Sites without processed knowledge: retriever returns `[]` → fallback to raw content
- Partial processing: only `done` resources contribute to RAG

---

## 12. Client Widget Tasks (1–7)

These tasks enhance the `sarah-ai-client` widget for production stability and UX.

### Task 1 — Session Persistence (localStorage)

**Files:** `chatApi.js`, `ChatWindow.jsx`

- Storage key: `sarah_ai_session_{site_key}` in `localStorage`
- UUID stored **after first server response** — never generated client-side
- On mount: check localStorage → if found, restore session UUID
- Helpers: `storageKey()`, `loadStoredSession()`, `saveStoredSession(uuid)`, `clearStoredSession()`

### Task 2 — History Restore

**Files:** `ChatController.php` (new endpoint), `chatApi.js`, `ChatWindow.jsx`

- New endpoint: `GET /chat/history?account_key=&site_key=&session_uuid=`
  - Auth: account_key + site_key (no platform key — safe to expose in widget)
  - Returns: `{ success, session_uuid, messages: [{ role, content }] }`
  - 404 if session not found → widget shows greeting (fresh start)
- On mount: if stored session UUID exists → fetch history → render messages
- `historyLoading` state disables input during fetch

### Task 3 — Greeting Message

**Files:** `ChatWindow.jsx`

- `greetingMessage()` → `[{ id, type:'ai', text }]` or `[]` if not configured
- Greeting shown only when no history exists (fresh start or after reset)
- Not duplicated on widget re-open (history is present after first message)

### Task 4 — Network Error Recovery

**Files:** `ChatWindow.jsx`, `MessageArea.jsx`

- On API failure: push error bubble `{ id, type:'ai', text, isError:true, retryText: trimmedMessage }`
- Error bubble styled with `.sac-bubble-error` class
- `↺ Try again` button rendered inside error bubble
- `handleRetry(text)`: removes error bubble → calls `sendMessage(text)` directly
- Input re-enabled after failure (never locked)

### Task 5 — Typing / Loading State

**Files:** `TypingIndicator.jsx`, `ChatWindow.jsx`, `MessageArea.jsx`, `InputBox.jsx`

*Already implemented in earlier phases. No new code.*

- `TypingIndicator`: three animated dots in AI bubble
- `isTyping` state: `true` before API call → `false` in `finally()`
- `MessageArea`: renders `<TypingIndicator />` when `isTyping`
- `InputBox`: `disabled={isTyping}` — send button + input both disabled
- Auto-scrolls to bottom on `isTyping` change

### Task 6 — Reset Chat

**Files:** `Header.jsx`, `ChatWindow.jsx`

- `Header.jsx`: accepts `onReset` prop, renders `↺` (SVG refresh) button, `aria-label="New chat"`
- `handleReset()`:
  1. `clearStoredSession()` — removes from localStorage
  2. `setSessionUuid(null)` — forgets session
  3. `setLastFailed(null)` — clears retry state
  4. `setMessages(greetingMessage())` — shows greeting again
- Next message starts a fresh session on the server
- Previous session data not deleted from server

### Task 7 — Lead Capture Hook

**Files:** `chatApi.js`, `ChatWindow.jsx`

- `getLead()` — reads `window.SarahAiWidget.connection.lead` if set
- `sendChatMessage(text, sessionUuid, lead)` — sends lead as body parameter if any field non-empty
- Lead structure: `{ name?: string, phone?: string, email?: string }`
- Server already accepts lead in `POST /chat` — stored in session visitor fields
- No UI for lead collection — any code can set `window.SarahAiWidget.connection.lead` before sending

---

## 13. Complete API Reference

### Auth Modes

| Auth Type | Used For | How |
|-----------|----------|-----|
| `manage_options` | All admin endpoints | WordPress admin cookie |
| Dual-key | Client chat + session read | `account_key` + `site_key` in request body |
| Dual-key + platform key | Session inspection | `X-Sarah-Platform-Key` header |

### Server Admin Endpoints

**Base:** `/wp-json/sarah-ai-server/v1/`

#### Tenants

| Method | Path | Description |
|--------|------|-------------|
| POST | `/tenants` | Create tenant + trial subscription |
| GET | `/tenants` | List all tenants |
| GET | `/tenants/{id}` | Full context (tenant, subscription, sites, users) |
| POST | `/tenants/{id}/status` | Update lifecycle status |
| POST | `/tenants/{id}/users` | Associate WP user |
| GET | `/tenants/{id}/users` | List user associations |
| DELETE | `/tenants/{id}/users/{wpUserId}` | Deactivate association |
| POST | `/tenants/{id}/account-keys` | Issue account key |
| GET | `/tenants/{id}/account-keys` | List account keys |
| GET | `/tenants/{id}/sites` | List sites |

#### Sites

| Method | Path | Description |
|--------|------|-------------|
| POST | `/sites` | Create site |
| GET | `/sites/{id}` | Full site context |
| POST | `/sites/{id}/status` | Update status |
| POST | `/sites/{id}/site-keys` | Issue site key |
| GET | `/sites/{id}/site-keys` | List site keys |
| POST | `/sites/{id}/agent` | Assign agent |
| DELETE | `/sites/{uuid}/agent` | Unassign agent |
| DELETE | `/account-keys/{id}` | Revoke account key |
| DELETE | `/site-keys/{id}` | Revoke site key |

#### Agents

| Method | Path | Description |
|--------|------|-------------|
| GET | `/agents` | List all active agents |
| PUT | `/agents/{id}/behavior` | Update role/tone/system_prompt |

#### Knowledge Resources

| Method | Path | Description |
|--------|------|-------------|
| GET | `/knowledge-resources?site_id=X` | List (optionally filter by group, active_only) |
| POST | `/knowledge-resources` | Create |
| GET | `/knowledge-resources/{id}` | Get single |
| DELETE | `/knowledge-resources/{id}` | Soft-delete |
| POST | `/knowledge-resources/{id}/status` | Update lifecycle status |
| POST | `/knowledge-resources/{uuid}/process` | Trigger processing pipeline |
| GET | `/knowledge-resources/{uuid}/chunks` | Get chunks (no vectors) |

#### Site Identity

| Method | Path | Description |
|--------|------|-------------|
| GET | `/sites/{uuid}/identity` | Get agent display name, intro, greeting |
| POST | `/sites/{uuid}/identity` | Save identity settings |

#### Platform Settings

| Method | Path | Description |
|--------|------|-------------|
| GET | `/settings` | Get all platform settings |
| POST | `/settings` | Save settings |

#### Appearance

| Method | Path | Description |
|--------|------|-------------|
| GET | `/appearance` | Get colors/fonts |
| POST | `/appearance` | Save appearance |
| POST | `/appearance/reset` | Reset to defaults |

#### Usage

| Method | Path | Description |
|--------|------|-------------|
| GET | `/usage` | Paginated usage log |
| GET | `/usage/summary` | Aggregate totals |

#### Sessions (Admin)

| Method | Path | Auth |
|--------|------|------|
| GET | `/sessions` | account_key + site_key + platform key |
| GET | `/sessions/{uuid}` | account_key + site_key + platform key |
| GET | `/sessions/{uuid}/messages` | account_key + site_key + platform key |

### Client Endpoints

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| POST | `/chat` | account_key + site_key | Send message, get AI reply |
| GET | `/chat/history` | account_key + site_key | Restore session messages (Task 2) |

**POST /chat body:**
```json
{
  "account_key": "...",
  "site_key": "...",
  "message": "user message",
  "session_uuid": "...",
  "lead": { "name": "", "phone": "", "email": "" }
}
```

**GET /chat/history params:** `account_key`, `site_key`, `session_uuid`

### Client Widget Settings

| Method | Path | Description |
|--------|------|-------------|
| GET | `/widget-settings` | Get server_url, account_key, site_key, greeting |
| POST | `/widget-settings` | Save settings |

---

## 14. Database Schema Reference

| Table | Key Columns |
|-------|-------------|
| `sarah_ai_server_tenants` | id, uuid, name, status, meta, deleted_at |
| `sarah_ai_server_user_tenant` | wp_user_id, tenant_id, role, status |
| `sarah_ai_server_sites` | id, uuid, tenant_id, name, url, status, active_agent_id, deleted_at |
| `sarah_ai_server_account_keys` | id, tenant_id, key_hash, label, status, expires_at |
| `sarah_ai_server_site_tokens` | id, site_id, token_hash, label, status, expires_at |
| `sarah_ai_server_agents` | id, uuid, name, slug, type, config (JSON), status |
| `sarah_ai_server_site_agents` | site_id, agent_id, assigned_at |
| `sarah_ai_server_plans` | id, slug, name, duration_days, features (JSON), status |
| `sarah_ai_server_subscriptions` | id, tenant_id, plan_id, status, starts_at, ends_at |
| `sarah_ai_server_knowledge_resources` | id, uuid, site_id, title, resource_type, content_group, status, source_content, processing_status, meta |
| `sarah_ai_server_knowledge_chunks` | id, uuid, resource_id, site_id, chunk_index, chunk_text, embedding (JSON), embedding_model, token_count |
| `sarah_ai_server_chat_sessions` | id, uuid, tenant_id, site_id, agent_id, subscription_id, status, visitor_name, visitor_phone, visitor_email, captured_data |
| `sarah_ai_server_chat_messages` | id, uuid, session_id, role, content, meta |
| `sarah_ai_server_usage_logs` | id, tenant_id, site_id, agent_id, session_id, event_type, tokens_in, tokens_out, meta |
| `sarah_ai_server_settings` | id, setting_key, setting_value, setting_group |
| `sarah_ai_server_email_templates` | id, slug, type, subject, body, variables |

---

## 15. Real-User Testing Checklist

> Pre-requisite: Both plugins deployed and active. WordPress running on Local by Flywheel.

---

### Prerequisites

- [ ] Plugin **sarah-ai-server** active
- [ ] Plugin **sarah-ai-client** active
- [ ] **Sarah AI Server** menu visible in server WP admin sidebar
- [ ] **Sarah AI** menu visible in client WP admin sidebar
- [ ] Both plugins have the latest build (`npm run build` run)

---

### 1 — Server Admin: Platform Settings

Path: Sarah AI Server → Settings

- [ ] Settings page loads
- [ ] **OpenAI API Key** field shown (masked: `••••••••xxxx`)
- [ ] Enter new key and Save — "Saved" message appears
- [ ] Reload — key shown masked
- [ ] **Platform Name** field saves
- [ ] **Logging** toggle works

---

### 2 — Server Admin: Sidebar Navigation

- [ ] **Dashboard** loads
- [ ] **Tenants** loads
- [ ] **Agents** loads
- [ ] **Usage** loads
- [ ] **Settings** loads
- [ ] Active links have highlight in sidebar

---

### 3 — Server Admin: Agent Behavior

Path: Sarah AI Server → Agents

- [ ] Agent list shown (gpt-4o-mini, gpt-4o, o1)
- [ ] Change **Role** (e.g. "customer support specialist") — Save — "Saved" appears
- [ ] Change **Tone** (e.g. Friendly) — Save
- [ ] Enter **Custom System Prompt** — badge "Custom prompt active" appears
- [ ] Clear custom prompt — badge "Composed from role + tone" appears

---

### 4 — Provisioning: Full Tenant Setup

Path: Sarah AI Server → Tenants → + New Tenant

**Step 1 — Tenant Info**
- [ ] Enter tenant name → Create
- [ ] Tenant detail opens, Status: Active

**Step 2 — Users (optional)**
- [ ] Can add a user

**Step 3 — Site**
- [ ] Enter site name → Create Site
- [ ] Site appears in list with Status: Active

**Step 4 — Account Keys**
- [ ] Create Account Key → key created, copy it

**Step 5 — Site Keys**
- [ ] Create Site Key → key created, copy it

**Step 6 — Agent Assignment**
- [ ] Select agent from dropdown → Assign → agent shown as assigned

**Step 7 — Agent Identity**
- [ ] Select target site
- [ ] Enter **Agent Display Name** (e.g. "سارا")
- [ ] Enter **Intro Message**
- [ ] Enter **Greeting Message**
- [ ] Save — "Saved" appears

**Step 8 — Knowledge Resources**
- [ ] Click Add
- [ ] Enter Title, Type: `text`, Source Content (a few paragraphs)
- [ ] Add → item appears in list
- [ ] Processing column shows `none` or `queued`
- [ ] Click **⚙ Process**
- [ ] Button changes to `…` (in-flight)
- [ ] After a moment: Processing column → `done` (green)
  - No API key: `done` without embeddings
  - API key set: `done` with full embeddings
- [ ] Add a resource with empty content → Process → column shows `failed` (red)

---

### 5 — Client: Plugin Settings

Path: Client WP Admin → Sarah AI → Settings

- [ ] Settings page loads
- [ ] Enter **Server URL** (server REST API address)
- [ ] Enter **Account Key**
- [ ] Enter **Site Key**
- [ ] Enter **Greeting Message**
- [ ] Save Settings — "Saved" appears
- [ ] Reload page — values preserved

---

### 6 — Widget: Display and Greeting

Path: Frontend of client site

- [ ] Launcher button (circle) visible in corner
- [ ] Click → chat window opens
- [ ] **Greeting message** shown immediately (no delay — from config)
- [ ] Close and reopen → greeting **not repeated** (history exists)

---

### 7 — Widget: Real AI Chat

- [ ] Type a question → press Enter
- [ ] **Typing indicator** (three animated dots) appears
- [ ] AI response received
- [ ] No API key: response is `[TEST MODE]`
- [ ] API key set: real OpenAI response
- [ ] Cannot send another message while receiving response (input disabled)

---

### 8 — Widget: Session Persistence (Task 1)

- [ ] Send a message → receive response
- [ ] DevTools → Application → Local Storage: key `sarah_ai_session_{site_key}` exists
- [ ] Value is a UUID
- [ ] **Refresh** the page
- [ ] Open chat window
- [ ] Previous messages restored (Task 2)
- [ ] Greeting **not shown again** (history replaces it)

---

### 9 — Widget: History Restore (Task 2)

- [ ] Send a few messages
- [ ] Fully refresh the page
- [ ] Open widget → **same conversation** shown
- [ ] Can continue the conversation (same session_uuid)
- [ ] From DevTools: clear localStorage
- [ ] Refresh → widget shows greeting again (fresh start)

---

### 10 — Widget: Error Recovery (Task 4)

- [ ] Change **Server URL** in Settings to a wrong address
- [ ] Open widget, send a message
- [ ] Error message appears: "Unable to connect. Please try again."
- [ ] **↺ Try again** button visible below error
- [ ] Input re-enabled (can type)
- [ ] Click Try again → same message resent (no retyping needed)
- [ ] Restore correct Server URL

---

### 11 — Widget: Reset Chat (Task 6)

- [ ] Send a few messages
- [ ] **↺** (New Chat) button visible in chat header
- [ ] Click it
- [ ] Messages cleared
- [ ] Greeting shown again
- [ ] DevTools → LocalStorage: session_uuid removed
- [ ] Send a new message → new session_uuid stored in localStorage

---

### 12 — Widget: Typing Indicator (Task 5)

- [ ] Send a message
- [ ] **Three animated dots** appear immediately in AI bubble
- [ ] After response arrives: dots disappear, response replaces them
- [ ] While indicator showing: Send button and input both disabled

---

### 13 — RAG: Knowledge in AI Response

> Requires OpenAI key and knowledge processed (Processing = done)

- [ ] Add knowledge resource with specific content (e.g. "Store hours are 9am–6pm")
- [ ] Process it — column shows `done`
- [ ] In widget: ask "What are your hours?"
- [ ] AI response contains correct information from knowledge
- [ ] AI does **not** invent information not in the knowledge base

---

### 14 — Site Identity in AI Response

> Requires OpenAI key and Agent Display Name configured

- [ ] Ask agent: "What's your name?"
- [ ] Response contains the **Agent Display Name** configured in step 7
- [ ] Intro Message reflected in agent behavior

---

### 15 — Usage Dashboard

Path: Sarah AI Server → Usage

- [ ] After sending real messages, open Usage page
- [ ] **Total Requests**, **Tokens In**, **Tokens Out** cards show non-zero values
- [ ] Session list table displayed
- [ ] Date filter works

---

### 16 — Lead Capture (Task 7)

> Test via DevTools Console

- [ ] Before opening widget, in Console:
  ```javascript
  window.SarahAiWidget.connection.lead = { name: "Test", phone: "09123456789" };
  ```
- [ ] Send a message
- [ ] On server: check Sessions — lead data is stored in the session

---

### 17 — Edge Cases

**Rapid open/close**
- [ ] Open and close widget 3 times rapidly — no crash
- [ ] Greeting shown only once (history after first message)

**Long conversation**
- [ ] Send 10+ messages — auto-scroll works
- [ ] Refresh — all 10+ messages restored

**Knowledge processing failure**
- [ ] Add a `link` resource with an invalid URL
- [ ] Process → column shows `failed`
- [ ] System does not crash

**No internet / no API key**
- [ ] Remove OpenAI key
- [ ] Chat → response is `[TEST MODE]`
- [ ] Knowledge processing: chunks saved without embeddings (done, no error)

---

### Summary Table

| Section | Status |
|---------|--------|
| Server admin UI (navigation, settings, agents, usage) | ⬜ |
| Provisioning flow (tenant → site → keys → agent) | ⬜ |
| Site identity (agent name, intro, greeting) | ⬜ |
| Knowledge processing (add, process, chunks) | ⬜ |
| Widget basic chat | ⬜ |
| Session persistence (localStorage) | ⬜ |
| History restore | ⬜ |
| Error recovery + retry | ⬜ |
| Reset chat | ⬜ |
| RAG knowledge injection | ⬜ |
| Usage tracking | ⬜ |
