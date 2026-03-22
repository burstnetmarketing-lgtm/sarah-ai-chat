# Task 0041 — Phase 6.2: Retrieval & RAG Runtime

## Goal
Use the chunk embeddings produced in Phase 6.1 during chat runtime. For each user message, embed the query, compute cosine similarity against stored chunk vectors, inject the top-K most relevant chunks into the system prompt.

## Runtime Flow

```
User message
  ↓
SemanticRetriever::retrieve(siteId, message)
  ↓  embed query → cosine similarity over site chunks → top-5
Retrieved chunks
  ↓
OpenAiAgentExecutor::buildSystemPrompt() — injects retrieved chunks
  ↓
OpenAI Chat Completions API
  ↓
Answer grounded in site knowledge
```

## Backward Compatibility
- If no chunks with embeddings exist for the site → `retrievedChunks = []`
- `buildSystemPrompt()` falls back to raw `source_content` injection (pre-6.1 behavior)
- No migration required; existing sites without processed knowledge continue to work unchanged

## New Files

### `includes/Processing/SemanticRetriever.php`
- `retrieve(int $siteId, string $query, int $topK = 5): array`
- Loads all chunks with embeddings via `KnowledgeChunkRepository::findWithEmbeddingsBySite()`
- Embeds query via `EmbeddingService::embed()`
- Computes cosine similarity for each chunk
- Returns top-K scored chunks: `[{chunk_text, resource_title, chunk_index, score}, ...]`
- Returns `[]` if no embeddings exist or API key missing (graceful fallback)

## Modified Files

### `includes/Infrastructure/KnowledgeChunkRepository.php`
- Added `findWithEmbeddingsBySite(int $siteId): array`
- JOIN with `knowledge_resources` table
- Filters: `embedding IS NOT NULL`, `kr.status = active`, `kr.processing_status = done`, `kr.deleted_at IS NULL`
- Returns chunk_text, embedding (JSON), chunk_index, resource_title

### `includes/Runtime/OpenAiAgentExecutor.php`
- `execute()`: calls `SemanticRetriever::retrieve()` when site has active knowledge
- Passes `$retrievedChunks` to `buildSystemPrompt()`
- `buildSystemPrompt()` signature updated: `(agent, knowledge, siteIdentity, retrievedChunks = [])`
- When `$retrievedChunks` non-empty → injects chunk_text from top-K results
- When `$retrievedChunks` empty → falls back to raw source_content (unchanged)

## Retrieval Details
- **Similarity metric:** cosine similarity (dot product / norms)
- **Query embedding model:** same as chunk model (text-embedding-3-small)
- **Top-K:** 5 chunks per query
- **Filtering:** only active resources with processing_status = done
- **Scale:** in-memory PHP computation; suitable for thousands of chunks

## Commit
0032
