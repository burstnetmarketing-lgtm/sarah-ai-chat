# Phase 4.2 — System Design Summary
## Knowledge Base Foundation & Content Association

---

## 1. Overview

Phase 4.2 establishes the knowledge base layer — the structured content foundation that future agent execution, retrieval pipelines, and reporting will build on. No RAG, embeddings, chunking, or document parsing is implemented in this phase. The goal is a stable, lifecycle-aware ownership model that requires no structural redesign when those capabilities are added.

---

## 2. New Table: `sarah_ai_server_knowledge_resources`

### Schema

| Column | Type | Purpose |
|---|---|---|
| `id` | BIGINT UNSIGNED PK | Auto-increment primary key |
| `site_id` | BIGINT UNSIGNED NOT NULL | Owning site (validated at repository layer — no FK) |
| `title` | VARCHAR(190) NULL | Human-readable label |
| `resource_type` | VARCHAR(80) NOT NULL | Open string classifier — `text`, `link`, `file` are built-in starters, not the full allowed set |
| `content_group` | VARCHAR(80) NULL | Logical category for grouping and filtering (see §4) |
| `status` | VARCHAR(30) DEFAULT `pending` | Lifecycle: `active`, `inactive`, `pending`, `processing`, `failed`, `archived` |
| `source_content` | LONGTEXT NULL | Raw text, URL, or file path depending on type |
| `processed_content` | LONGTEXT NULL | Temporary compatibility column — see §5 |
| `processing_status` | VARCHAR(30) DEFAULT `none` | Pipeline outcome: `none`, `queued`, `done`, `failed` |
| `meta` | LONGTEXT NULL | Non-authoritative overflow JSON — see §6 for usage boundaries |
| `sort_order` | INT DEFAULT 0 | Optional ordering within a site |
| `deleted_at` | DATETIME NULL | Soft-delete |
| `created_at` | DATETIME NOT NULL | Record creation |
| `updated_at` | DATETIME NOT NULL | Last modification |

### Indexes

- `idx_site_id` — primary query dimension
- `idx_status` — active-only reads
- `idx_resource_type` — type-filtered queries
- `idx_content_group` — group-scoped reads for agents and reporting

---

## 3. Ownership and Association Chain

```
WordPress User
    └── Tenant
            └── Site
                    └── KnowledgeResource (resource_type, content_group, status)
```

The agent-to-knowledge path is indirect and intentional:

```
Agent → Site → KnowledgeResources
```

Knowledge belongs to a site. An agent assigned to a site reads from that site's knowledge base. Resources must never be attached to credentials (site tokens) or agent records. To derive tenant ownership from a resource: `resource.site_id → sites.tenant_id`.

---

## 4. Content Grouping — `content_group`

The `content_group` column is a first-class VARCHAR(80) field that supports logical categorization of resources without requiring a full taxonomy table. Typical values: `faq`, `policy`, `product`, `support`, `campaign`.

`content_group` is intentionally a plain string — no foreign key to a category table, no strict enum. New groups can be introduced by simply using a new string value. If a formal taxonomy with descriptions, ordering, or icons is needed in future, a `knowledge_groups` table can be added and `content_group` can reference it.

**Why it is a first-class column (not meta):** It will be a common WHERE filter in agent reads and a grouping dimension in reporting. Meta is not queryable without JSON extraction.

### Repository methods added for group-scoped reads

| Method | Use |
|---|---|
| `findByGroup(siteId, group)` | Admin views, reporting — all resources in a group |
| `findActiveByGroup(siteId, group)` | Agent scoping — "use only FAQ resources for this query" |

---

## 5. resource_type — Open Classifier, Not a Closed Enum

`resource_type` is a VARCHAR(80) open string classifier. The built-in starter values (`text`, `link`, `file`) are documented as constants on `KnowledgeResourceTable` but they are not an exhaustive or closed list.

**Extensibility rule:** Any additional resource type (e.g. `faq-entry`, `markdown-page`, `structured-record`, `imported-data`) can be introduced by simply using the new string. No schema migration, no code change to the repository, and no update to `KnowledgeResourceTable` is required.

**Validation:** The API accepts any non-empty lowercase slug (`[a-z0-9][a-z0-9_-]{0,79}`). It does not validate against a fixed allowlist. If a consumer of the API wants to restrict acceptable types to a project-specific set, that restriction belongs at the application layer, not in this foundation.

**Constants convention:** The `TYPE_TEXT`, `TYPE_LINK`, `TYPE_FILE` constants on `KnowledgeResourceTable` exist for safe string reuse in call sites that handle those specific types. They are descriptive references, not a validation allowlist.

---

## 6. Processed Content — Temporary Compatibility Bridge

The `processed_content` LONGTEXT column is a **single-row interim store** introduced in Phase 4.2. It is not the long-term processed-content model.

**What it is for now:** Holding simple extracted or flattened text so agents can use a processed version of the source without parsing the original inline.

**What replaces it in Phase 4.3+:** A `knowledge_chunks` table with this structure:

```
sarah_ai_server_knowledge_chunks
  id, resource_id, chunk_index, content LONGTEXT, meta LONGTEXT, created_at
```

That table supports chunk-level metadata, per-chunk embedding references, overlapping windows, and retrieval ranking. When it is introduced, `processed_content` should be treated as deprecated and phased out.

**How the current code enforces the bridge framing:**
- `updateProcessingStatus(int $id, string $processingStatus)` — pure state update only; does not touch `processed_content`
- `writeProcessedContentBridge(int $id, string $content)` — separate method with "Bridge" in its name; the name itself communicates migration intent to any future caller
- No retrieval logic reads from `processed_content` as a permanent source
- Callers that need to write processed output must explicitly opt in to the bridge method, making the dependency visible rather than implicit

---

## 7. Two State Dimensions — Lifecycle vs. Processing

These are orthogonal and must remain separate. Conflating them would make it impossible to distinguish "admin disabled this resource" from "the pipeline failed on this resource".

### Lifecycle status (`status`) — admin intent

Controls whether the resource is considered available to agents.

| Value | Meaning |
|---|---|
| `pending` | Submitted, not yet reviewed or processed |
| `active` | Available to agents |
| `inactive` | Disabled by admin |
| `processing` | Admin has queued it for a pipeline run |
| `failed` | Admin review needed after a failure |
| `archived` | Retained for history, out of service |

Constants: `KnowledgeResourceTable::STATUS_*`

### Processing status (`processing_status`) — pipeline outcome

Records what the processing pipeline has done to the source content.

| Value | Meaning |
|---|---|
| `none` | No processing attempted |
| `queued` | Queued for processing |
| `done` | Processing completed successfully |
| `failed` | Processing failed |

Constants: `KnowledgeResourceTable::PROCESSING_*`

**Example valid combined state:** `status = active`, `processing_status = none` — the resource is available as raw text without any pipeline processing. This is the expected initial state for `resource_type = text` resources that don't need extraction.

---

## 8. Meta Field — Usage Boundary

The `meta` JSON column is non-authoritative overflow storage. It exists for attributes that are not query targets, not filter dimensions, and not reporting aggregates.

**Appropriate meta contents:** file size, MIME type, original filename, source URL for crawled content, upload timestamp, encoding hints.

**Never push into meta:**
- `content_group` — will be filtered in WHERE clauses
- `resource_type` — already a first-class column, indexed
- `status` / `processing_status` — these are state columns, not metadata
- Any field expected to appear in a COUNT, SUM, or GROUP BY in a future reporting query

If a new attribute is expected to become a filter or aggregate, add a first-class column rather than reading it out of JSON at query time.

---

## 9. Repository API Reference

| Method | Signature | Notes |
|---|---|---|
| `create` | `(int $siteId, string $resourceType, string $title, string $sourceContent, string $contentGroup, array $meta): int` | Validates site; returns 0 on failure |
| `findById` | `(int $id): ?array` | Excludes soft-deleted |
| `findBySite` | `(int $siteId): array` | All non-deleted for site |
| `findActiveBySite` | `(int $siteId): array` | `status = active` — primary agent read path |
| `findByGroup` | `(int $siteId, string $contentGroup): array` | All non-deleted in group |
| `findActiveByGroup` | `(int $siteId, string $contentGroup): array` | Active in group — future agent scoping |
| `updateStatus` | `(int $id, string $status): void` | Lifecycle transitions only |
| `updateProcessingStatus` | `(int $id, string $processingStatus): void` | Pipeline state only — does not touch `processed_content` |
| `writeProcessedContentBridge` | `(int $id, string $content): void` | **Temporary bridge** — writes to `processed_content`; deprecated when chunk table is introduced |
| `softDelete` | `(int $id): void` | Sets `deleted_at`, transitions to `archived` |

---

## 10. REST API

**Base:** `/wp-json/sarah-ai-server/v1/`

| Method | Endpoint | Description |
|---|---|---|
| GET | `/knowledge-resources?site_id=X[&group=faq][&active_only=1]` | List with optional group and active filters |
| POST | `/knowledge-resources` | Create (`site_id`, `resource_type`, `title`, `source_content`, `content_group`, `meta`) |
| GET | `/knowledge-resources/{id}` | Get single resource |
| DELETE | `/knowledge-resources/{id}` | Soft-delete |
| POST | `/knowledge-resources/{id}/status` | Update lifecycle status |

---

## 11. Design Decisions

| Decision | Rationale |
|---|---|
| `site_id` owns resources, not `tenant_id` | Sites are the content boundary; tenant derived via join. Avoids issues with credential rotation. |
| `VARCHAR` for all state and type fields | New values without `ALTER TABLE`. |
| `content_group` first-class column with index | Will be a WHERE filter and reporting dimension — not suitable for meta. |
| `status` and `processing_status` kept separate | Orthogonal concerns. Admin intent must not be overwritten by pipeline outcomes. |
| Class constants for all status values | Single source of truth; eliminates magic strings across call sites. |
| `resource_type` validated by format, not allowlist | Keeps the classifier open-ended; new types require no code change. |
| `TYPE_*` constants are descriptive, not prescriptive | Document the built-in starters without implying exhaustiveness. |
| `updateProcessingStatus` and `writeProcessedContentBridge` split | Forces callers to explicitly opt in to writing the temporary bridge column; pipeline state and content storage are separate concerns. |
| `writeProcessedContentBridge` named with "Bridge" | Method name communicates migration intent; any future caller knows this path is temporary. |
| Soft-delete sets `status = archived` | Archived resources excluded from agent reads without needing a separate deleted_at check in every query. |

---

## 12. What Phase 4.3 Can Immediately Build On

- `findActiveBySite()` — all agent-usable resources for a site
- `findActiveByGroup()` — scoped agent reads by content category
- `updateProcessingStatus()` — pure pipeline state update
- `writeProcessedContentBridge()` — temporary content store; to be deprecated when `knowledge_chunks` is introduced
- `content_group` column + index — ready for group-scoped queries without schema change
- `processing_status` constants — `queued` / `done` / `failed` states fully supported
- `knowledge_chunks` table can be added alongside this table without touching the resource schema

---

*Generated: 2026-03-22 — Phase 4.2 refined for retrieval and reporting readiness. Patched: resource_type open classifier, processed_content bridge separation.*
