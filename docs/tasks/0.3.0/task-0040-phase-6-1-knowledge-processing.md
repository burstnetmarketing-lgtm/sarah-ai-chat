# Task 0040 — Phase 6.1: Knowledge Processing Pipeline

## Goal
Convert stored knowledge resources into retrieval-ready data by building a structured pipeline: extract text → normalize → chunk → embed → persist.

## Separation Rule
- **Phase 6.1** = processing pipeline (prepare for retrieval)
- **Phase 6.2** = retrieval runtime (use embeddings during chat)

## New DB Table: `sarah_ai_server_knowledge_chunks`
| Column | Type | Purpose |
|--------|------|---------|
| `id` | BIGINT UNSIGNED | PK |
| `uuid` | VARCHAR(36) | External identifier |
| `resource_id` | BIGINT UNSIGNED | Owning resource |
| `site_id` | BIGINT UNSIGNED | Owning site (denormalized for bulk ops) |
| `chunk_index` | INT | Position within resource |
| `chunk_text` | LONGTEXT | Extracted text slice |
| `embedding` | LONGTEXT | JSON float array (OpenAI vector) |
| `embedding_model` | VARCHAR(80) | e.g. `text-embedding-3-small` |
| `token_count` | INT NULL | Estimated token count (~chars/4) |
| `created_at` / `updated_at` | DATETIME | Timestamps |

## New PHP Files

### DB
- `KnowledgeChunksTable.php` — dbDelta schema, table constants

### Infrastructure
- `KnowledgeChunkRepository.php`
  - `saveChunks(resourceId, siteId, chunks, embeddings, model)` — batch insert
  - `deleteByResource(resourceId)` — called before reprocessing
  - `findByResource(resourceId)` — ordered by chunk_index, no embedding column
  - `findByResourceWithEmbeddings(resourceId)` — full rows (Phase 6.2 retrieval)
  - `countByResource(resourceId)` — quick count

### Processing
- `KnowledgeTextExtractor.php` — type dispatch:
  - `text` / `txt` — use source_content directly; fetch URL if source_content is http(s)
  - `link` — fetch URL, strip HTML noise, collapse whitespace
  - `pdf` — fetch binary, decompress FlateDecode streams, extract BT/ET text blocks
  - `docx` — fetch binary, temp file, ZipArchive, parse word/document.xml
  - unknown types — use source_content as-is or throw

- `KnowledgeChunker.php` — paragraph-aware sliding window
  - Default chunk size: 1 500 chars, overlap: 200 chars
  - Splits oversized paragraphs by sentence, then hard-splits if needed
  - Returns `[{index, text, token_count}, ...]`

- `EmbeddingService.php` — OpenAI text-embedding-3-small
  - Batch size: 20 texts per API call
  - Reads openai_api_key from SettingsRepository (namespace: platform)
  - Throws RuntimeException on API error or missing key

- `KnowledgeProcessingService.php` — pipeline orchestrator
  - Steps: extract → normalize → chunk → embed → delete old → save new → mark done
  - Embedding skipped gracefully if API key not set (chunks still saved)
  - Failures stored in `meta.processing_error`, processing_status = failed
  - Safe to call on already-processed resources (reprocessing)

### API
- `KnowledgeProcessingController.php`
  - `POST /knowledge-resources/{uuid}/process` — trigger/retrigger processing
  - `GET  /knowledge-resources/{uuid}/chunks` — list chunks (no embedding vectors)
  - Auth: manage_options

## Modified Files

### Plugin.php
- Added `KnowledgeChunksTable::create()` in boot
- Added `KnowledgeProcessingController` route registration

### provisioning.js
- `processKnowledge(uuid)` — POST .../process
- `getKnowledgeChunks(uuid)` — GET .../chunks

### TenantDetail.jsx
- Added `Processing` column to knowledge table showing processing_status badge
- Added `⚙ Process` button per row — triggers processKnowledge, reloads list
- Processing in-flight state per UUID (button disabled while running)
- Badge colors: none=secondary, queued=info, done=success, failed=danger

## Processing Lifecycle States
| processing_status | Meaning |
|---|---|
| `none` | Not yet processed |
| `queued` | Set at start of processing run |
| `done` | All chunks + embeddings saved successfully |
| `failed` | Error during extract/embed; error in meta.processing_error |

## Commit
0031
