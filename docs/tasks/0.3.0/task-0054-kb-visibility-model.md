# Task 0054 — KB Visibility Model (Public / Private)

## Goal
Add a `visibility` column to knowledge resources so each resource can be independently marked public or private, enforced at every data access point.

## Changes

### `includes/DB/KnowledgeResourceTable.php`
- Added `VISIBILITY_PUBLIC = 'public'` and `VISIBILITY_PRIVATE = 'private'` constants
- Added `visibility VARCHAR(20) NOT NULL DEFAULT 'public'` column to schema (dbDelta-safe)

### `includes/Infrastructure/KnowledgeResourceRepository.php`
- Added `findPublicActiveBySite(int $siteId)` — active resources filtered to visibility=public; used by all agent/widget read paths
- Added `updateVisibility(int $id, string $visibility)` — persists a visibility change

### `includes/Infrastructure/KnowledgeChunkRepository.php`
- Updated `findWithEmbeddingsBySite()` JOIN query: added `AND kr.visibility = 'public'` — private chunks are excluded from the semantic search corpus

### `includes/Processing/KnowledgePolicyFilter.php` (new)
- `publicOnly(array $resources)` — filters resource arrays to public only
- `restrictedDataSafeResponse()` — canonical safe response string for restricted info requests
- `isValidVisibility(string $v)` — validates visibility values

### `includes/Runtime/OpenAiAgentExecutor.php`
- Raw fallback knowledge injection now passes through `KnowledgePolicyFilter::publicOnly()` before building the system prompt — private resources never reach the AI

## Commit
0054
