# Phase 4.2 — Knowledge Base
## Knowledge Base Foundation & Content Association

---

## Purpose of This Document

This file serves two roles simultaneously. The first half (Sections 1–3) is the **scenario** — what the system must become after this phase. The second half (Sections 4–8) is the **implementation knowledge base** — what an agent must know about the existing system before writing any code. Read both halves before starting.

---

# PART ONE — SCENARIO

---

## 1. What This Phase Is

Phase 4.2 introduces the foundational content layer that Sarah agents will later rely on when answering users. The purpose is not to implement full retrieval, document parsing, semantic search, or production-ready RAG behavior. The purpose is to establish a stable and extensible structure for storing, organizing, and associating knowledge resources in a way that later agent execution can use without forcing major redesign.

The knowledge base in Sarah must be treated as a first-class application concept. It is not a miscellaneous attachment area or a temporary storage bucket. It is the structured content foundation that future dummy agents, AI agents, retrieval pipelines, and reporting layers will depend on. Because of that, this phase must focus on ownership, association, classification, lifecycle, and processing readiness.

---

## 2. Ownership Model

Knowledge must belong to a **site**, not to a site token. A site token is a credential used to identify and validate a site during communication — it is not a content ownership boundary. If knowledge were attached to a token, key rotation, credential replacement, or multiple credentials per site would create unnecessary ambiguity and future maintenance problems.

A site is the correct business-level context for knowledge because it represents the actual website or integration endpoint whose content the agent is expected to use.

The system must be able to represent one or more knowledge resources under a site. Each resource is a source of information that may later be exposed to an agent, processed into another form, indexed, filtered, disabled, replaced, or versioned. A site may have many knowledge resources, and those resources may differ in type, status, origin, and processing state.

---

## 3. Core Capabilities This Phase Must Deliver

### 3.1 Multiple Resource Types

The structure must be able to accommodate different categories of knowledge input, at minimum:

- **Direct text** — raw content entered by the admin
- **External link** — a URL pointing to an external page or resource
- **File-like reference** — an uploaded document or file path reference

The design must not assume that all resource types behave the same way internally. The implementation must leave room for future types without requiring the core model to be redesigned.

### 3.2 Source vs. Processed Form Distinction

The system must be able to distinguish between the original source and any future processed forms of that source (chunked text, extracted content, embeddings). The full processing pipeline does not need to exist in this phase, but the design must leave space for those later lifecycle steps without requiring the core ownership model to be rethought.

### 3.3 Lifecycle Awareness

A knowledge resource must be lifecycle-aware. The system must be able to represent whether a resource is:

- `active` — in use, available to agents
- `inactive` — disabled by admin, not available
- `pending` — submitted but not yet processed
- `processing` — currently being processed
- `failed` — processing failed, needs attention
- `archived` — retained for history but no longer in service

### 3.4 Classification and Queryability

The design should allow resources to be categorized, labeled, or grouped for later agent selection, filtering, and reporting. The platform should be able to answer:

- Which site owns this knowledge resource?
- Which tenant owns the site that owns this resource?
- What kind of resource is this?
- Is this resource currently usable?
- Was this resource provided as text, link, or file?
- Which resources are active for a given site?

### 3.5 Processing Readiness

Because future agent behavior may depend on whether knowledge is suitable for use, the system should be ready to represent processing readiness at a basic level. This includes storing whether a resource has been processed and whether processing succeeded or failed.

### 3.6 Agent Independence

The implementation must support future agent integration but must not prematurely bind knowledge resources to a specific agent implementation. Knowledge belongs to a site. An agent assigned to that site will later read from the site's knowledge base. The association is indirect: `Agent → Site → KnowledgeResources`.

### 3.7 What Is Explicitly Out of Scope

The following must not be implemented in this phase:

- Embeddings generation
- Chunking pipelines
- Semantic / vector search
- Document parsing (PDF, DOCX, etc.)
- Crawler or scraper logic
- OCR flows
- Prompt assembly from retrieved chunks
- Frontend knowledge management UI (unless minimal admin utility is needed for verification)

---

## 4. Acceptance Criteria

This phase is complete when all of the following are true:

- The system can represent site-owned knowledge resources as first-class entities
- Resources are correctly associated with a site and, by extension, with a tenant
- The system distinguishes different resource types (text, link, file reference)
- Each resource has a queryable lifecycle status
- Resources can be created, listed, deactivated, and soft-deleted via repository methods
- The structure leaves clear room for source/processed-form separation in later phases
- No structural redesign is required for Phase 4.3 to begin building a retrieval or dummy-agent execution layer

---

# PART TWO — IMPLEMENTATION KNOWLEDGE BASE

---

## 5. What Phase 4.1 Built

Phase 4.1 established the complete multi-tenant data foundation. Everything described below already exists and is production-ready for Phase 4.2 to build on.

### 5.1 Available Tables

| Table | Purpose |
|---|---|
| `sarah_ai_server_tenants` | Customer accounts (lifecycle, soft-delete) |
| `sarah_ai_server_user_tenant` | WP user ↔ tenant association with Sarah-level roles |
| `sarah_ai_server_sites` | Sites owned by tenants; carry `active_agent_id` |
| `sarah_ai_server_site_tokens` | SHA-256 hashed API credentials per site |
| `sarah_ai_server_agents` | Agent catalog; seeded with `sarah-basic`, `sarah-pro` |
| `sarah_ai_server_site_agents` | Agent assignment log per site |
| `sarah_ai_server_plans` | Subscription plan definitions; seeded with `trial` (14d) |
| `sarah_ai_server_subscriptions` | Tenant subscription state and lifecycle |
| `sarah_ai_server_email_templates` | Transactional email templates with `{{variable}}` rendering |
| `sarah_ai_server_usage_logs` | Runtime usage events; `tokens_in`/`tokens_out` pre-built |
| `sarah_ai_server_settings` | Key-value config with `setting_group` namespacing |

### 5.2 Available Repository Methods

**SiteRepository** — most relevant to Phase 4.2:
```php
findById(int $id): ?array
findByTenant(int $tenantId): array
// Use these to validate ownership before associating knowledge resources
```

**TenantRepository:**
```php
findById(int $id): ?array
all(): array
```

**SettingsRepository:**
```php
get(string $key, string $default = '', string $group = 'general'): string
set(string $key, string $value, string $group = 'general'): void
getGroup(string $group): array
```

Full repository API reference with all method signatures: see [sarah_phase4_1_summary.md](sarah_phase4_1_summary.md), Section 2.

---

## 6. Project Structure and Conventions

### 6.1 File Structure
```
sarah-ai-server/
├── sarah-ai-server.php          ← ALL new require_once statements go here
├── includes/
│   ├── DB/                      ← One class per table. CREATE TABLE only.
│   ├── Infrastructure/          ← One repository per entity. All DB logic.
│   ├── Core/                    ← Plugin.php, Activator.php, Seeder.php
│   ├── Admin/                   ← Admin menu + SPA shell
│   └── Api/                     ← REST controllers
└── assets/src/pages/            ← React pages (one file per view)
```

### 6.2 Adding a New Table

1. Create `includes/DB/KnowledgeResourceTable.php` following the pattern:

```php
<?php
declare(strict_types=1);
namespace SarahAiServer\DB;

class KnowledgeResourceTable
{
    public const TABLE = 'sarah_ai_server_knowledge_resources';

    public static function create(): void
    {
        global $wpdb;
        $table   = $wpdb->prefix . self::TABLE;
        $charset = $wpdb->get_charset_collate();
        $sql     = "CREATE TABLE {$table} (
            ...
        ) {$charset};";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }
}
```

2. Add `require_once` in `sarah-ai-server.php` under `// DB Tables`
3. Call `KnowledgeResourceTable::create()` in both `Activator::activate()` and `Plugin::boot()`

### 6.3 Adding a New Repository

1. Create `includes/Infrastructure/KnowledgeResourceRepository.php`
2. Add `require_once` in `sarah-ai-server.php` under `// Infrastructure`
3. The repository is ready to instantiate anywhere — no registration needed

### 6.4 PHP Conventions
- `declare(strict_types=1)` on all files
- Namespace: `SarahAiServer\{Layer}\{ClassName}`
- Single-record methods return `?array` (null if not found)
- Multi-record methods return `array` (empty array if none)
- Always use `current_time('mysql')` for timestamps
- Always use `$wpdb->prepare()` for user-provided input
- No PHP enums — string constants only (PHP 7.4 target)
- JSON fields: `wp_json_encode()` to write, `json_decode($val, true)` to read

### 6.5 Adding a REST Controller

```php
// includes/Api/KnowledgeController.php
namespace SarahAiServer\Api;

class KnowledgeController
{
    public function registerRoutes(): void
    {
        register_rest_route('sarah-ai-server/v1', '/knowledge-resources', [
            'methods'             => 'GET',
            'callback'            => [$this, 'index'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function index(\WP_REST_Request $request): \WP_REST_Response
    {
        return new \WP_REST_Response(['success' => true, 'data' => []], 200);
    }
}
```

Then in `Plugin::boot()`:
```php
add_action('rest_api_init', [(new KnowledgeController()), 'registerRoutes']);
```

**Base URL:** `/wp-json/sarah-ai-server/v1/`
**Response envelope:** Always `{ success: bool, data: mixed, message?: string }`

### 6.6 Adding a React Page

1. Create `assets/src/pages/KnowledgeBase.jsx`
2. Import and add route in `App.jsx`
3. Add sidebar entry in `MenuRepository::ensureCoreItems()` (or via `create()`)
4. Run `npm run build` inside `sarah-ai-server/`

---

## 7. Schema Design Guidance for Phase 4.2

### 7.1 Recommended New Table: `sarah_ai_server_knowledge_resources`

Based on the scenario requirements, the table must carry:

| Concern | Suggested Approach |
|---|---|
| Ownership | `site_id BIGINT UNSIGNED NOT NULL` — not tenant_id directly; tenant is derived via site |
| Resource type | `resource_type VARCHAR(80)` — `text`, `link`, `file`; VARCHAR, not ENUM |
| Lifecycle status | `status VARCHAR(30)` — `active`, `inactive`, `pending`, `processing`, `failed`, `archived` |
| Source content | `source_content LONGTEXT NULL` — raw text or URL or file path depending on type |
| Processed form | `processed_content LONGTEXT NULL` — future: extracted/chunked text |
| Processing state | `processing_status VARCHAR(30) NULL` — `none`, `queued`, `done`, `failed` |
| Metadata | `meta LONGTEXT NULL` — JSON: file size, MIME type, source URL, etc. |
| Soft delete | `deleted_at DATETIME NULL` — consistent with tenants and sites |
| Label/title | `title VARCHAR(190) NULL` — human-readable name |
| Sort/grouping | `sort_order INT DEFAULT 0` — optional, for future UI ordering |

### 7.2 Consider a Processed Content Table (Optional)

If you want clean separation between source and processed form:

```
sarah_ai_server_knowledge_chunks
  id, resource_id, chunk_index, content LONGTEXT, meta LONGTEXT, created_at
```

This is optional for Phase 4.2 — the `processed_content` column on the resource table may be sufficient for now. If a chunking pipeline is anticipated soon, add the chunks table now to avoid a later migration.

### 7.3 Index Strategy

Index on `site_id` (most common query dimension) and `status` (for active-only reads). If `resource_type` will be a common filter, index that too.

---

## 8. Known Constraints to Carry Forward

**No FK enforcement.** Ownership must be validated in repository methods. Before creating a knowledge resource, confirm the `site_id` refers to a non-deleted, active site.

**No autoloader.** Every new class needs a `require_once` in `sarah-ai-server.php`.

**Token expiry not yet enforced.** When Phase 5 adds real client auth, `SiteTokenRepository::findByRawToken()` needs an `expires_at` check.

**`setting_key` is globally unique.** Use descriptive, namespaced key names (e.g., `knowledge_max_resources_per_site`) to avoid collisions.

**Seeder is idempotent.** If Phase 4.2 introduces seed data (e.g., a default resource type list or a settings key), add it to `Seeder::run()` using `insertIfMissing` or the settings guard pattern already in place.

---

*Last updated: 2026-03-22 — Phase 4.1 complete. Phase 4.2 scenario defined.*
