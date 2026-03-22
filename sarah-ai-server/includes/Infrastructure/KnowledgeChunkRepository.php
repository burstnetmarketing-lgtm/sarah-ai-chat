<?php

declare(strict_types=1);

namespace SarahAiServer\Infrastructure;

use SarahAiServer\DB\KnowledgeChunksTable;

class KnowledgeChunkRepository
{
    /**
     * Persist a batch of chunks for a resource.
     * Each entry in $chunks must have: chunk_index, chunk_text.
     * Each entry in $embeddings (if provided) must be a float array at the same index.
     *
     * @param int    $resourceId
     * @param int    $siteId
     * @param array  $chunks         [['index' => int, 'text' => string], ...]
     * @param array  $embeddings     Parallel array of float[] vectors, or empty to skip
     * @param string $embeddingModel Model name used, e.g. 'text-embedding-3-small'
     */
    public function saveChunks(
        int $resourceId,
        int $siteId,
        array $chunks,
        array $embeddings = [],
        string $embeddingModel = ''
    ): void {
        global $wpdb;
        $table = $wpdb->prefix . KnowledgeChunksTable::TABLE;
        $now   = current_time('mysql');

        foreach ($chunks as $i => $chunk) {
            $embedding = $embeddings[$i] ?? null;
            $wpdb->insert($table, [
                'uuid'            => sarah_ai_uuid(),
                'resource_id'     => $resourceId,
                'site_id'         => $siteId,
                'chunk_index'     => (int) $chunk['index'],
                'chunk_text'      => (string) $chunk['text'],
                'embedding'       => $embedding !== null ? wp_json_encode($embedding) : null,
                'embedding_model' => $embedding !== null ? ($embeddingModel ?: null) : null,
                'token_count'     => $chunk['token_count'] ?? null,
                'created_at'      => $now,
                'updated_at'      => $now,
            ]);
        }
    }

    /**
     * Delete all chunks belonging to a resource.
     * Must be called before reprocessing to avoid stale data.
     */
    public function deleteByResource(int $resourceId): void
    {
        global $wpdb;
        $wpdb->delete(
            $wpdb->prefix . KnowledgeChunksTable::TABLE,
            ['resource_id' => $resourceId],
            ['%d']
        );
    }

    /** Return all chunks for a resource, ordered by chunk_index. */
    public function findByResource(int $resourceId): array
    {
        global $wpdb;
        $table = $wpdb->prefix . KnowledgeChunksTable::TABLE;
        $rows  = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, uuid, resource_id, site_id, chunk_index, chunk_text, embedding_model, token_count, created_at
                 FROM {$table}
                 WHERE resource_id = %d
                 ORDER BY chunk_index ASC",
                $resourceId
            ),
            ARRAY_A
        );
        return is_array($rows) ? $rows : [];
    }

    /** Return all chunks for a resource including embedding vectors. */
    public function findByResourceWithEmbeddings(int $resourceId): array
    {
        global $wpdb;
        $table = $wpdb->prefix . KnowledgeChunksTable::TABLE;
        $rows  = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE resource_id = %d ORDER BY chunk_index ASC",
                $resourceId
            ),
            ARRAY_A
        );
        return is_array($rows) ? $rows : [];
    }

    /** Return chunk count for a resource. */
    public function countByResource(int $resourceId): int
    {
        global $wpdb;
        $table = $wpdb->prefix . KnowledgeChunksTable::TABLE;
        return (int) $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE resource_id = %d", $resourceId)
        );
    }
}
