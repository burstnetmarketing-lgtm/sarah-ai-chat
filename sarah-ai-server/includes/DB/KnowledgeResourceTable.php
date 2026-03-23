<?php

declare(strict_types=1);

namespace SarahAiServer\DB;

/**
 * Table definition for knowledge resources.
 *
 * LIFECYCLE STATUS vs. PROCESSING STATUS
 * These are two orthogonal state dimensions and must remain separate:
 *   - STATUS_*      = admin intent (is this resource available to agents?)
 *   - PROCESSING_*  = pipeline outcome (has this content been processed?)
 * Never conflate them. A resource can be STATUS_ACTIVE while PROCESSING_NONE
 * (usable as raw text), or STATUS_INACTIVE while PROCESSING_DONE (disabled by admin
 * after processing completed).
 *
 * PROCESSED CONTENT — TEMPORARY COMPATIBILITY COLUMN
 * The `processed_content` column is a single-row compatibility layer introduced
 * in Phase 4.2 to hold simple extracted or flattened content. It is NOT the
 * long-term processed-content model. Phase 4.3+ is expected to introduce a
 * `knowledge_chunks` table (resource_id, chunk_index, content, meta) for
 * chunk-based storage that supports semantic retrieval, embedding references,
 * and per-chunk metadata. When that table is introduced, `processed_content`
 * should be treated as deprecated and eventually dropped.
 * Nothing in this implementation should assume `processed_content` will remain
 * the retrieval source.
 *
 * META FIELD USAGE BOUNDARY
 * The `meta` JSON column is non-authoritative overflow storage for attributes
 * that are not query targets, filter dimensions, or reporting aggregates.
 * Appropriate meta contents: file size, MIME type, original filename, source URL
 * for crawled content, upload timestamp, encoding hints.
 * Do NOT push into meta: content_group, resource_type, status, processing_status,
 * or any field that will appear in a WHERE clause or aggregate in future phases.
 * If a new attribute is expected to be filtered or counted, add a first-class column.
 */
class KnowledgeResourceTable
{
    public const TABLE = 'sarah_ai_server_knowledge_resources';

    // -------------------------------------------------------------------------
    // Lifecycle status constants (admin intent — is this resource usable?)
    // -------------------------------------------------------------------------
    public const STATUS_ACTIVE     = 'active';
    public const STATUS_INACTIVE   = 'inactive';
    public const STATUS_PENDING    = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_FAILED     = 'failed';
    public const STATUS_ARCHIVED   = 'archived';

    // -------------------------------------------------------------------------
    // Processing status constants (pipeline outcome — has content been processed?)
    // -------------------------------------------------------------------------
    public const PROCESSING_NONE   = 'none';
    public const PROCESSING_QUEUED = 'queued';
    public const PROCESSING_DONE   = 'done';
    public const PROCESSING_FAILED = 'failed';

    // -------------------------------------------------------------------------
    // Built-in starter resource types — not an exhaustive or closed list.
    // These constants document the initial well-known types only.
    // resource_type is a VARCHAR(80) open classifier: any lowercase-slug value
    // is acceptable. Future types (faq-entry, markdown-page, structured-record,
    // imported-data, etc.) require no schema migration and no change to this file.
    // Do NOT use these constants as a validation allowlist.
    // -------------------------------------------------------------------------
    public const TYPE_TEXT = 'text';
    public const TYPE_LINK = 'link';
    public const TYPE_FILE = 'file';

    // -------------------------------------------------------------------------
    // Visibility constants (access control — who can see this resource?)
    // -------------------------------------------------------------------------
    // public  : injected into AI prompts AND returned via the public knowledge-fields API
    // private : injected into AI prompts ONLY when the site_token query is from the
    //           admin context; never returned to the widget or public API
    // -------------------------------------------------------------------------
    public const VISIBILITY_PUBLIC  = 'public';
    public const VISIBILITY_PRIVATE = 'private';

    public static function create(): void
    {
        global $wpdb;
        $table   = $wpdb->prefix . self::TABLE;
        $charset = $wpdb->get_charset_collate();
        $sql     = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            uuid VARCHAR(36) NULL DEFAULT NULL,
            site_id BIGINT UNSIGNED NOT NULL,
            title VARCHAR(190) NULL DEFAULT NULL,
            resource_type VARCHAR(80) NOT NULL,
            content_group VARCHAR(80) NULL DEFAULT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'pending',
            source_content LONGTEXT NULL DEFAULT NULL,
            processed_content LONGTEXT NULL DEFAULT NULL,
            processing_status VARCHAR(30) NOT NULL DEFAULT 'none',
            visibility VARCHAR(20) NOT NULL DEFAULT 'public',
            meta LONGTEXT NULL DEFAULT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            deleted_at DATETIME NULL DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_uuid (uuid),
            KEY idx_site_id (site_id),
            KEY idx_status (status),
            KEY idx_resource_type (resource_type),
            KEY idx_content_group (content_group)
        ) {$charset};";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }
}
