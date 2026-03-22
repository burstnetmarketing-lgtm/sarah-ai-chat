# Task 0026: Phase 4.2 — Knowledge Base Foundation

- **Task Number:** 0026
- **Title:** Phase 4.2 — Knowledge Base Foundation with Extensibility Patches
- **Version:** 0.3.0
- **Date:** 2026-03-22

---

## User Request

Implement the knowledge base layer (Phase 4.2), then apply two extensibility patches: treat `processed_content` as a temporary bridge and keep `resource_type` as an open classifier rather than a closed enum.

---

## Implementation Summary

Introduced `sarah_ai_server_knowledge_resources` as a first-class site-owned entity. Ownership chain: `resource → site_id → sites.tenant_id`. Knowledge belongs to the site, not to the agent and not to the credential.

### Table: `sarah_ai_server_knowledge_resources`

Key columns: `site_id`, `title`, `resource_type` (open VARCHAR), `content_group` (indexed), `status` (lifecycle), `source_content`, `processed_content` (temporary bridge), `processing_status` (pipeline state), `meta` (non-authoritative overflow), `sort_order`, `deleted_at`.

### Extensibility Patches Applied

**`resource_type` — open classifier, not a closed enum:**
- API validates format only (`^[a-z0-9][a-z0-9_-]{0,79}$`), not against a fixed allowlist
- `TYPE_TEXT/LINK/FILE` constants are descriptive starters, not a validation list
- New types (`faq-entry`, `markdown-page`, etc.) require no code change

**`processed_content` — explicit temporary bridge:**
- `updateProcessingStatus(id, status)` — pure pipeline state, does not touch `processed_content`
- `writeProcessedContentBridge(id, content)` — separate named method; "Bridge" signals future deprecation
- Column will be superseded by a `knowledge_chunks` table in Phase 4.3+

**`content_group` — first-class indexed column:**
- First-class `VARCHAR(80)` column with index (not meta) — will be a WHERE filter
- `findByGroup()` and `findActiveByGroup()` added to repository

### State Dimensions

Two orthogonal state fields kept strictly separate:
- `status` — admin intent (active/inactive/pending/archived...)
- `processing_status` — pipeline outcome (none/queued/done/failed)

All values exposed as class constants on `KnowledgeResourceTable`.

---

## Affected Files

- `sarah-ai-server/includes/DB/KnowledgeResourceTable.php` — new
- `sarah-ai-server/includes/Infrastructure/KnowledgeResourceRepository.php` — new
- `sarah-ai-server/includes/Api/KnowledgeController.php` — new
- `sarah-ai-server/sarah-ai-server.php` — updated
- `sarah-ai-server/includes/Core/Plugin.php` — updated
- `sarah-ai-server/includes/Core/Activator.php` — updated
- `docs/scenarios/sarah_phase4_2_summary.md` — system design summary
