# KB Access Control — Implementation Summary

**Tasks completed:** 0054–0057
**Source spec:** `kb-access-control-task.md`

---

## What was implemented

### Backend (PHP)

| Component | File | What it does |
|-----------|------|--------------|
| Visibility column | `KnowledgeResourceTable.php` | `visibility VARCHAR(20) DEFAULT 'public'` added to schema |
| Public filter | `KnowledgeResourceRepository::findPublicActiveBySite()` | Agent/widget read path — private excluded |
| Visibility update | `KnowledgeResourceRepository::updateVisibility()` | Persists toggle |
| Chunk filter | `KnowledgeChunkRepository::findWithEmbeddingsBySite()` | `AND kr.visibility = 'public'` — private chunks excluded from RAG |
| Policy filter | `KnowledgePolicyFilter.php` | `publicOnly()`, `restrictedDataSafeResponse()`, `isValidVisibility()` |
| Field schema | `KnowledgeFieldSchema.php` | Canonical key constants + `extractFromResource()` |
| Fields API | `KnowledgeFieldsController.php` | `GET /sites/{uuid}/knowledge-fields` (account_key + site_key auth) |
| Visibility toggle API | `KnowledgeFieldsController::updateVisibility()` | `POST /knowledge-resources/{uuid}/visibility` |
| Safe response | `OpenAiAgentExecutor` | System prompt instructs AI to use pre-defined response for missing data |

### Frontend — Widget (sarah-ai-client)

| Component | File | What it does |
|-----------|------|--------------|
| Field schema | `knowledgeFieldSchema.js` | JS canonical key constants, `isCanonicalKey()`, `filterCanonicalFields()` |
| Business provider | `businessProvider.js` | Real API call (was mock); session cache; async functions |
| ContactCard | `ContactCard.jsx` | Validates fields against canonical schema before rendering |

### Frontend — Admin (sarah-ai-server)

| Component | File | What it does |
|-----------|------|--------------|
| Visibility API | `provisioning.js` | `updateKnowledgeVisibility(uuid, visibility)` |
| Visibility UI | `TenantDetail.jsx` | Visibility column + 🌐/🔒 toggle button in knowledge table |

---

## Security layers (defence in depth)

```
1. DB query      → findPublicActiveBySite() + findWithEmbeddingsBySite()
                   excludes private at the query level

2. Prompt filter → KnowledgePolicyFilter::publicOnly()
                   private resources never injected into AI system prompt

3. RAG corpus    → findWithEmbeddingsBySite() WHERE visibility = 'public'
                   private chunks excluded from semantic search

4. API response  → /knowledge-fields returns public resources only
                   validated by CredentialValidator + UUID check

5. Widget render → ContactCard validates canonical keys before rendering
                   non-canonical keys from AI output silently dropped
```

---

## Spec tasks status

| # | Task | Status |
|---|------|--------|
| 1 | KB Visibility Model | ✅ Done |
| 2 | Canonical KB Field Schema | ✅ Done |
| 3 | Tenant Isolation with site_token | ✅ Done (existing + new endpoint) |
| 4 | KB Filtering Before Prompt Injection | ✅ Done |
| 5 | SemanticRetriever Visibility-Aware | ✅ Done |
| 6 | Safe Response for Restricted Data | ✅ Done (system prompt instruction) |
| 7 | Intent Detection as UX Helper Only | ✅ Documented in KnowledgePolicyFilter.php |
| 8 | Knowledge Fields API | ✅ Done |
| 9 | Connect businessProvider to API | ✅ Done |
| 10 | Update ContactCard Rendering | ✅ Done |
| 11 | Policy Validation Layer | ✅ Done (KnowledgePolicyFilter + schema validation) |
| 12 | End-to-End Tests | ⏳ Not implemented — no test infrastructure in project |
| 13 | Admin UI for Visibility | ✅ Done |
| 14 | Document KB Access Control | ✅ This file |

---

## Structured fields convention

To expose structured contact/business data via the widget API, add `meta.structured_fields` to any active public knowledge resource:

```json
{
  "structured_fields": {
    "contact.phone_admin": "04XX XXX XXX",
    "contact.website": "https://example.com",
    "business.address": "123 Main St, Sydney NSW 2000",
    "business.hours": "Mon–Fri 9am–5pm"
  }
}
```

The `/knowledge-fields` endpoint merges all public resources' structured_fields and returns them. The widget's `businessProvider.js` fetches and caches this on first use.
