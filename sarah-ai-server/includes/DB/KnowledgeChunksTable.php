<?php

declare(strict_types=1);

namespace SarahAiServer\DB;

/**
 * Table definition for knowledge chunks.
 *
 * Each row represents one text chunk produced by the Phase 6.1 processing pipeline.
 * A single knowledge resource may produce many chunks.
 *
 * EMBEDDING STORAGE
 * The `embedding` column stores a JSON-encoded float array (vector).
 * For OpenAI text-embedding-3-small the vector has 1536 dimensions (~30KB per chunk as JSON).
 * LONGTEXT handles this safely. This is an intentional interim storage strategy —
 * future phases may migrate to a vector database or dedicated extension.
 *
 * CHUNK LIFECYCLE
 * When a resource is reprocessed, all existing chunks for that resource must be deleted
 * before new ones are written. This prevents orphaned or stale chunk data.
 *
 * INDEX NOTES
 * - idx_resource_id: primary read path — chunks are almost always queried by resource
 * - idx_site_id: used for bulk cleanup and future cross-resource queries
 */
class KnowledgeChunksTable
{
    public const TABLE = 'sarah_ai_server_knowledge_chunks';

    public static function create(): void
    {
        global $wpdb;
        $table   = $wpdb->prefix . self::TABLE;
        $charset = $wpdb->get_charset_collate();
        $sql     = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            uuid VARCHAR(36) NULL DEFAULT NULL,
            resource_id BIGINT UNSIGNED NOT NULL,
            site_id BIGINT UNSIGNED NOT NULL,
            chunk_index INT NOT NULL DEFAULT 0,
            chunk_text LONGTEXT NOT NULL,
            embedding LONGTEXT NULL DEFAULT NULL,
            embedding_model VARCHAR(80) NULL DEFAULT NULL,
            token_count INT NULL DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_uuid (uuid),
            KEY idx_resource_id (resource_id),
            KEY idx_site_id (site_id)
        ) {$charset};";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }
}
