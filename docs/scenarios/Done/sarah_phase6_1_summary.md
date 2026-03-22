# Phase 6.1 — Implementation Summary
## Knowledge Processing Pipeline

---

## 1. What Was Built

Phase 6.1 introduces the full pipeline that transforms stored knowledge resources into retrieval-ready chunk + embedding data.

| File | Role |
|------|------|
| `includes/DB/KnowledgeChunksTable.php` | New DB table for chunk storage |
| `includes/Infrastructure/KnowledgeChunkRepository.php` | Chunk data access (save / delete / find) |
| `includes/Processing/KnowledgeTextExtractor.php` | Type-specific text extraction |
| `includes/Processing/KnowledgeChunker.php` | Paragraph-aware sliding window chunker |
| `includes/Processing/EmbeddingService.php` | OpenAI embeddings API wrapper |
| `includes/Processing/KnowledgeProcessingService.php` | Pipeline orchestrator |
| `includes/Api/KnowledgeProcessingController.php` | REST endpoints for trigger + inspection |
| `includes/Core/Plugin.php` | Registers table + controller on boot |
| `assets/src/api/provisioning.js` | `processKnowledge()`, `getKnowledgeChunks()` |
| `assets/src/pages/TenantDetail.jsx` | Processing status + Process button in Knowledge section |

---

## 2. New DB Table: `sarah_ai_server_knowledge_chunks`

| Column | Type | Purpose |
|--------|------|---------|
| `id` | BIGINT UNSIGNED | PK |
| `uuid` | VARCHAR(36) | External identifier |
| `resource_id` | BIGINT UNSIGNED | Owning knowledge resource |
| `site_id` | BIGINT UNSIGNED | Denormalized for bulk ops |
| `chunk_index` | INT | Position within resource |
| `chunk_text` | LONGTEXT | Extracted text slice |
| `embedding` | LONGTEXT | JSON float array — OpenAI vector (1 536 dims) |
| `embedding_model` | VARCHAR(80) | e.g. `text-embedding-3-small` |
| `token_count` | INT NULL | Estimated token count (~chars / 4) |

---

## 3. Pipeline Steps

```
source_content
    │
    ▼  1. KnowledgeTextExtractor  (type dispatch)
raw text
    │
    ▼  2. KnowledgeProcessingService::normalize()
clean text
    │
    ▼  3. KnowledgeChunker  (paragraph sliding window)
[ chunk_0, chunk_1, ..., chunk_N ]
    │
    ▼  4. EmbeddingService  (OpenAI text-embedding-3-small, batch 20)
[ embedding_0, embedding_1, ..., embedding_N ]
    │
    ▼  5. KnowledgeChunkRepository::saveChunks()
DB rows in sarah_ai_server_knowledge_chunks
    │
    ▼  6. processing_status = done
```

---

## 4. Supported Resource Types

| Type | Extraction Strategy |
|------|---------------------|
| `text` | `source_content` used directly |
| `txt` | `source_content` used directly, or fetched if URL |
| `link` | `wp_remote_get`, strip HTML noise, collapse whitespace |
| `pdf` | Fetch binary, decompress FlateDecode streams, extract BT/ET text blocks |
| `docx` | Fetch binary, temp file, `ZipArchive`, parse `word/document.xml` |
| other | `source_content` as-is if non-empty; otherwise throws |

---

## 5. Chunking Strategy

- **Chunk size:** 1 500 chars (≈ 375 tokens at 4 chars/token)
- **Overlap:** 200 chars — preserves sentence context at boundaries
- **Paragraph-first:** accumulates paragraphs until size limit, then slides
- **Oversized paragraph:** splits by sentence, then hard-splits by character
- **Result:** `[{ index, text, token_count }, ...]`

---

## 6. Embedding Service

- Provider: OpenAI `/v1/embeddings`
- Model: `text-embedding-3-small` (1 536 dimensions)
- Batch size: 20 texts per API call
- Reads `openai_api_key` from SettingsRepository (namespace: `platform`)
- **Graceful skip:** if API key is not configured, chunks are saved without vectors and resource is still marked `done`
- **API errors:** re-thrown and recorded as failure in `meta.processing_error`

---

## 7. Processing Lifecycle

| `processing_status` | Meaning |
|---|---|
| `none` | Resource not yet processed |
| `queued` | Processing has been triggered (set at pipeline start) |
| `done` | All chunks saved; embeddings saved or skipped (no key) |
| `failed` | Pipeline error; `meta.processing_error` contains the message |

Reprocessing is safe — old chunks are always deleted before new ones are written.

---

## 8. API Endpoints

### POST /wp-json/sarah-ai-server/v1/knowledge-resources/{uuid}/process
Triggers or re-triggers the full pipeline for one resource.
Auth: `manage_options`

Response:
```json
{ "success": true, "chunks": 12, "message": "Chunks and embeddings saved." }
```

### GET /wp-json/sarah-ai-server/v1/knowledge-resources/{uuid}/chunks
Returns all chunks for a resource (without embedding vectors — too large for UI).
Auth: `manage_options`

Response:
```json
{
  "success": true,
  "resource_uuid": "...",
  "processing_status": "done",
  "chunk_count": 12,
  "chunks": [{ "id": 1, "chunk_index": 0, "chunk_text": "...", "token_count": 375 }, ...]
}
```

---

## 9. Admin UI Changes

- Knowledge table in TenantDetail step 8 now shows a `Processing` column
- Badge colors: `none` = grey, `queued` = blue, `done` = green, `failed` = red
- `⚙ Process` button per row — triggers processing, disables while in-flight, reloads on complete

---

## 10. Phase Boundary

Phase 6.1 is complete when:
- Knowledge resources can be processed through the full pipeline
- Chunk and embedding data is persisted in `sarah_ai_server_knowledge_chunks`
- Processing lifecycle and failure states are explicit and inspectable

Phase 6.2 will use the stored chunks and embedding vectors during chat runtime to perform semantic search and inject relevant knowledge into the agent prompt.
