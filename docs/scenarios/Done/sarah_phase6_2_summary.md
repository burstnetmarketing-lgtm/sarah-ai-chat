# Phase 6.2 — Implementation Summary
## Retrieval & RAG Runtime

---

## 1. What Was Built

Phase 6.2 activates the processed knowledge at chat runtime. For each user message, the system embeds the query, finds the most semantically relevant chunks from the site's knowledge base, and injects them into the system prompt.

| File | Change |
|------|--------|
| `includes/Processing/SemanticRetriever.php` | New — query embedding + cosine similarity + top-K selection |
| `includes/Infrastructure/KnowledgeChunkRepository.php` | Added `findWithEmbeddingsBySite()` |
| `includes/Runtime/OpenAiAgentExecutor.php` | Integrated retrieval in `execute()`, updated `buildSystemPrompt()` |

---

## 2. Runtime Flow

```
User message arrives at ChatRuntime
  ↓
OpenAiAgentExecutor::execute()
  ↓
SemanticRetriever::retrieve(siteId, userMessage)
  │  1. Load all chunks with embeddings for active+done resources of the site
  │  2. Embed user message → query vector (text-embedding-3-small)
  │  3. Cosine similarity: query vector vs. each chunk vector
  │  4. Sort descending, take top-5
  ↓
Retrieved chunks: [{ chunk_text, resource_title, score }, ...]
  ↓
buildSystemPrompt() — injects retrieved chunks into ## Knowledge Base section
  ↓
OpenAI Chat Completions API
  ↓
Answer grounded in site knowledge
```

---

## 3. Knowledge Injection Mode

`buildSystemPrompt()` now accepts two knowledge sources and uses the first non-empty one:

| Condition | Injection Mode |
|---|---|
| `$retrievedChunks` non-empty | **RAG mode** — top-K chunk_text from semantic retrieval |
| `$retrievedChunks` empty | **Fallback mode** — raw source_content from all active resources |

The fallback ensures sites without processed knowledge (pre-Phase 6.1) continue to work unchanged.

---

## 4. SemanticRetriever

```
retrieve(siteId, query, topK = 5): array
```

- Calls `KnowledgeChunkRepository::findWithEmbeddingsBySite()` to load corpus
- Embeds query via `EmbeddingService` (same model used for chunks)
- Computes cosine similarity for each chunk in PHP
- Returns top-K results sorted by score descending
- Returns `[]` gracefully if: no embeddings in DB, API key missing, or query empty

**Cosine similarity:**
```
similarity = dot(a, b) / (|a| × |b|)
```
Range: [-1, 1]. Higher = more relevant.

---

## 5. findWithEmbeddingsBySite() Query

```sql
SELECT kc.chunk_text, kc.embedding, kc.chunk_index, kr.title AS resource_title
FROM sarah_ai_server_knowledge_chunks kc
INNER JOIN sarah_ai_server_knowledge_resources kr ON kr.id = kc.resource_id
WHERE kc.site_id = {siteId}
  AND kc.embedding IS NOT NULL
  AND kr.status = 'active'
  AND kr.processing_status = 'done'
  AND kr.deleted_at IS NULL
ORDER BY kc.resource_id ASC, kc.chunk_index ASC
```

Only returns chunks from resources that are both **active** and **fully processed**.

---

## 6. Backward Compatibility

- Sites without processed knowledge: `SemanticRetriever` returns `[]` → executor falls back to raw source_content injection (same as Phase ≤5 behavior)
- Sites with partial processing: only done resources contribute to retrieval; unprocessed ones are ignored by retrieval but still present in the fallback path
- No DB migration, no API changes, no admin UI changes required

---

## 7. System Prompt Structure (RAG mode)

```
You are a {role}.
{tone instruction}

## Behaviour Rules
...

{identity section if set}

## Knowledge Base

Use the following information to answer questions. Rely only on what is provided below — do not invent facts.

### {resource_title}
{chunk_text_0}

### {resource_title}
{chunk_text_1}

...up to top-5 chunks
```

---

## 8. Phase Boundary

Phase 6.2 is complete when:
- User questions trigger semantic retrieval over stored chunk embeddings
- Relevant chunks are injected into the system prompt
- Agents answer based on site knowledge, not general guesses
- Fallback to raw content works transparently for unprocessed resources

The system is now a full RAG pipeline. Future phases may add re-ranking, caching, analytics, and approximate vector search for larger knowledge bases.
