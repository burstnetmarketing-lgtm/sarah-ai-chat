<?php

declare(strict_types=1);

namespace SarahAiServer\Processing;

use SarahAiServer\DB\KnowledgeResourceTable;
use SarahAiServer\Infrastructure\KnowledgeChunkRepository;
use SarahAiServer\Infrastructure\KnowledgeResourceRepository;

/**
 * Orchestrates the full knowledge processing pipeline.
 *
 * PIPELINE STEPS
 * 1. Load resource — verify it exists and has source content
 * 2. Mark as processing (processing_status = queued → processing)
 * 3. Extract text — type-specific (text/link/pdf/docx/txt)
 * 4. Normalize text — remove noise, collapse whitespace
 * 5. Chunk text — sliding window with overlap
 * 6. Generate embeddings — OpenAI text-embedding-3-small
 * 7. Persist chunks — delete old, write new
 * 8. Mark done (processing_status = done)
 *
 * REPROCESSING
 * Calling process() on an already-processed resource is safe.
 * Old chunks are always deleted before new ones are written (step 7),
 * so there is no risk of orphaned or duplicate chunk data.
 *
 * FAILURE HANDLING
 * Any exception during steps 3–6 is caught here.
 * The resource is marked processing_status = failed and the error message
 * is stored in the `meta` JSON under the key `processing_error`.
 * Partial chunk writes do NOT happen — chunks are only written if all
 * embeddings succeed. The old chunks are not deleted until the new batch
 * is ready (fail-safe ordering).
 *
 * EMBEDDING SKIP
 * If the OpenAI API key is not configured, embeddings are skipped and
 * chunks are saved without vectors. This allows chunk data to be available
 * for keyword-based fallback retrieval while embeddings remain incomplete.
 * The resource is still marked done in this case (chunks exist even without vectors).
 */
class KnowledgeProcessingService
{
    private KnowledgeResourceRepository $resources;
    private KnowledgeChunkRepository    $chunks;
    private KnowledgeTextExtractor      $extractor;
    private KnowledgeChunker            $chunker;
    private EmbeddingService            $embedding;

    public function __construct()
    {
        $this->resources  = new KnowledgeResourceRepository();
        $this->chunks     = new KnowledgeChunkRepository();
        $this->extractor  = new KnowledgeTextExtractor();
        $this->chunker    = new KnowledgeChunker();
        $this->embedding  = new EmbeddingService();
    }

    /**
     * Process (or reprocess) a knowledge resource by its ID.
     *
     * @param  int  $resourceId
     * @return array{success: bool, chunks: int, message: string}
     */
    public function process(int $resourceId): array
    {
        $resource = $this->resources->findById($resourceId);
        if (! $resource) {
            return ['success' => false, 'chunks' => 0, 'message' => 'Resource not found.'];
        }

        if (empty(trim((string) ($resource['source_content'] ?? '')))) {
            $this->markFailed($resourceId, $resource, 'Resource has no source content to process.');
            return ['success' => false, 'chunks' => 0, 'message' => 'Resource has no source content to process.'];
        }

        // Mark as processing
        $this->resources->updateProcessingStatus($resourceId, KnowledgeResourceTable::PROCESSING_QUEUED);

        try {
            // Step 3: Extract
            $rawText = $this->extractor->extract($resource);

            // Step 4: Normalize
            $cleanText = $this->normalize($rawText);
            if (trim($cleanText) === '') {
                throw new \RuntimeException('Text extraction produced empty content after normalization.');
            }

            // Step 4b: For link resources, preserve the original URL in meta.source_url
            // before overwriting source_content with extracted text.
            if (($resource['resource_type'] ?? '') === 'link') {
                $this->preserveSourceUrl($resourceId, $resource);
            }

            // Save extracted text back to source_content so the keyword fallback
            // in buildSystemPrompt() works even without embeddings.
            $this->resources->updateSourceContent($resourceId, $cleanText);

            // Step 5: Chunk
            $chunks = $this->chunker->chunk($cleanText);
            if (empty($chunks)) {
                throw new \RuntimeException('Chunking produced no chunks from the extracted text.');
            }

            // Step 6: Embed (best-effort — skip if key missing, don't fail)
            $embeddings    = [];
            $embeddingModel = '';
            $embeddingSkipped = false;
            try {
                $chunkTexts     = array_column($chunks, 'text');
                $embeddings     = $this->embedding->embed($chunkTexts);
                $embeddingModel = $this->embedding->getDefaultModel();
            } catch (\RuntimeException $e) {
                if (str_contains($e->getMessage(), 'API key is not configured')) {
                    $embeddingSkipped = true;
                } else {
                    // Real API error — re-throw to mark resource as failed
                    throw $e;
                }
            }

            // Step 7: Persist — delete old chunks, write new ones atomically
            $this->chunks->deleteByResource($resourceId);
            $this->chunks->saveChunks(
                $resourceId,
                (int) $resource['site_id'],
                $chunks,
                $embeddings,
                $embeddingModel
            );

            // Step 8: Mark done, clear any previous error
            $this->resources->updateProcessingStatus($resourceId, KnowledgeResourceTable::PROCESSING_DONE);
            $this->clearProcessingError($resourceId, $resource);

            $note = $embeddingSkipped
                ? 'Chunks saved without embeddings (no API key configured).'
                : 'Chunks and embeddings saved.';

            return [
                'success' => true,
                'chunks'  => count($chunks),
                'message' => $note,
            ];
        } catch (\Throwable $e) {
            $this->markFailed($resourceId, $resource, $e->getMessage());
            return [
                'success' => false,
                'chunks'  => 0,
                'message' => $e->getMessage(),
            ];
        }
    }

    // ─── Text normalization ───────────────────────────────────────────────────

    /**
     * Clean up extracted text before chunking.
     * Removes noise without destroying meaningful content.
     */
    private function normalize(string $text): string
    {
        // Normalize line endings
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        // Remove null bytes and non-printable control characters (except newlines and tabs)
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);

        // Collapse lines with only whitespace into empty lines
        $text = preg_replace('/^[ \t]+$/m', '', $text);

        // Collapse runs of spaces/tabs within lines to a single space
        $text = preg_replace('/[ \t]{2,}/', ' ', $text);

        // Collapse 3+ consecutive blank lines to 2 (preserve paragraph boundaries)
        $text = preg_replace('/\n{3,}/', "\n\n", $text);

        return trim($text);
    }

    // ─── Failure helpers ──────────────────────────────────────────────────────

    /**
     * Persist the original URL from source_content into meta.source_url before
     * source_content is overwritten with extracted text.
     * Only writes if meta.source_url is not already set (idempotent on reprocess).
     */
    private function preserveSourceUrl(int $resourceId, array $resource): void
    {
        global $wpdb;
        $meta = json_decode((string) ($resource['meta'] ?? '{}'), true) ?: [];
        if (isset($meta['source_url'])) {
            return;
        }
        $url = trim((string) ($resource['source_content'] ?? ''));
        if ($url === '') {
            return;
        }
        $meta['source_url'] = $url;
        $wpdb->update(
            $wpdb->prefix . KnowledgeResourceTable::TABLE,
            ['meta' => wp_json_encode($meta), 'updated_at' => current_time('mysql')],
            ['id'   => $resourceId],
            ['%s',  '%s'],
            ['%d']
        );
    }

    private function markFailed(int $resourceId, array $resource, string $error): void
    {
        $this->resources->updateProcessingStatus($resourceId, KnowledgeResourceTable::PROCESSING_FAILED);
        $this->writeProcessingError($resourceId, $resource, $error);
    }

    private function writeProcessingError(int $resourceId, array $resource, string $error): void
    {
        global $wpdb;
        $meta  = json_decode((string) ($resource['meta'] ?? '{}'), true) ?: [];
        $meta['processing_error'] = $error;
        $wpdb->update(
            $wpdb->prefix . KnowledgeResourceTable::TABLE,
            ['meta' => wp_json_encode($meta), 'updated_at' => current_time('mysql')],
            ['id'   => $resourceId],
            ['%s', '%s'],
            ['%d']
        );
    }

    private function clearProcessingError(int $resourceId, array $resource): void
    {
        global $wpdb;
        $meta = json_decode((string) ($resource['meta'] ?? '{}'), true) ?: [];
        if (isset($meta['processing_error'])) {
            unset($meta['processing_error']);
            $wpdb->update(
                $wpdb->prefix . KnowledgeResourceTable::TABLE,
                ['meta' => empty($meta) ? null : wp_json_encode($meta), 'updated_at' => current_time('mysql')],
                ['id'   => $resourceId],
                ['%s', '%s'],
                ['%d']
            );
        }
    }
}
