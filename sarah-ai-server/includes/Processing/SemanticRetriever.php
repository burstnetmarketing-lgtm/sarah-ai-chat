<?php

declare(strict_types=1);

namespace SarahAiServer\Processing;

use SarahAiServer\Infrastructure\KnowledgeChunkRepository;

/**
 * Retrieves the most semantically relevant knowledge chunks for a user query.
 *
 * HOW IT WORKS
 * 1. Load all stored chunk embeddings for active, processed resources on the site.
 * 2. Embed the user's query using the same model (text-embedding-3-small).
 * 3. Score each chunk via cosine similarity against the query vector.
 * 4. Return the top-K highest-scoring chunks in descending order.
 *
 * FALLBACK
 * If the API key is not configured, or if no chunks with embeddings exist for the
 * site, the retriever returns an empty array. The caller (OpenAiAgentExecutor) then
 * falls back to injecting raw source_content as in pre-RAG behavior.
 *
 * SCALE NOTE
 * This implementation computes similarity in PHP for all chunks in memory.
 * This is intentionally simple and suitable for sites with up to a few thousand
 * chunks. Phase 6.3+ can introduce approximate vector search or a vector DB
 * without changing the interface — just this class.
 *
 * PROVIDER BOUNDARY
 * Query embedding goes through EmbeddingService, which is already the single
 * provider contact point. Switching providers only requires changing EmbeddingService.
 */
class SemanticRetriever
{
    private const DEFAULT_TOP_K = 5;

    private KnowledgeChunkRepository $chunks;
    private EmbeddingService         $embedding;

    public function __construct()
    {
        $this->chunks    = new KnowledgeChunkRepository();
        $this->embedding = new EmbeddingService();
    }

    /**
     * Retrieve the top-K most relevant chunks for a query.
     *
     * @param  int    $siteId    Site to search within (only active + processed resources)
     * @param  string $query     The user's question or message
     * @param  int    $topK      Maximum number of chunks to return
     * @return array             [['chunk_text' => string, 'score' => float, 'resource_title' => string|null], ...]
     *                           Empty array if retrieval is not possible (no embeddings, no key).
     */
    public function retrieve(int $siteId, string $query, int $topK = self::DEFAULT_TOP_K): array
    {
        $query = trim($query);
        if ($query === '' || $topK <= 0) {
            return [];
        }

        // Load all chunks with embeddings for this site
        $storedChunks = $this->chunks->findWithEmbeddingsBySite($siteId);
        if (empty($storedChunks)) {
            return [];
        }

        // Embed the query — gracefully return empty if API key is missing
        try {
            $queryVectors = $this->embedding->embed([$query]);
        } catch (\RuntimeException $e) {
            if (str_contains($e->getMessage(), 'API key is not configured')) {
                return [];
            }
            throw $e;
        }

        $queryVector = $queryVectors[0] ?? null;
        if (! $queryVector) {
            return [];
        }

        // Score each chunk
        $scored = [];
        foreach ($storedChunks as $chunk) {
            $embeddingJson = $chunk['embedding'] ?? null;
            if (! $embeddingJson) {
                continue;
            }
            $vector = json_decode($embeddingJson, true);
            if (! is_array($vector) || empty($vector)) {
                continue;
            }
            $score    = $this->cosineSimilarity($queryVector, $vector);
            $scored[] = [
                'chunk_text'     => (string) $chunk['chunk_text'],
                'resource_title' => $chunk['resource_title'] ?? null,
                'chunk_index'    => (int) $chunk['chunk_index'],
                'score'          => $score,
            ];
        }

        if (empty($scored)) {
            return [];
        }

        // Sort by score descending
        usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);

        // Return top-K, stripped of the score (internal detail)
        $results = array_slice($scored, 0, $topK);
        return array_map(fn($r) => [
            'chunk_text'     => $r['chunk_text'],
            'resource_title' => $r['resource_title'],
            'chunk_index'    => $r['chunk_index'],
            'score'          => $r['score'],
        ], $results);
    }

    // ─── Math ─────────────────────────────────────────────────────────────────

    /**
     * Cosine similarity between two equal-length float vectors.
     * Returns a value in [-1, 1]. Higher = more similar.
     */
    private function cosineSimilarity(array $a, array $b): float
    {
        $dot   = 0.0;
        $normA = 0.0;
        $normB = 0.0;
        $len   = min(count($a), count($b));

        for ($i = 0; $i < $len; $i++) {
            $dot   += $a[$i] * $b[$i];
            $normA += $a[$i] * $a[$i];
            $normB += $b[$i] * $b[$i];
        }

        if ($normA == 0.0 || $normB == 0.0) {
            return 0.0;
        }

        return (float) ($dot / (sqrt($normA) * sqrt($normB)));
    }
}
