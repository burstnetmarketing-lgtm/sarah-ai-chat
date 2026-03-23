<?php

declare(strict_types=1);

namespace SarahAiServer\Infrastructure;

use SarahAiServer\DB\KnowledgeResourceTable;
use SarahAiServer\DB\SiteTable;

class KnowledgeResourceRepository
{
    /**
     * Creates a knowledge resource owned by the given site.
     * Validates that the site exists and is not deleted before inserting.
     *
     * Ownership chain: resource → site_id → sites.tenant_id
     * Resources must never be attached to credentials (site tokens) or agent records.
     *
     * @param int    $siteId        Owning site ID.
     * @param string $resourceType  Extensible string classifier for the resource kind.
     *                              The TYPE_* constants on KnowledgeResourceTable document
     *                              the built-in starter values ('text', 'link', 'file').
     *                              Any additional lowercase-slug value is valid — the model
     *                              is open-ended by design. No schema change is required to
     *                              introduce a new type (e.g. 'faq-entry', 'markdown-page').
     * @param string $title         Human-readable label.
     * @param string $sourceContent Raw content, URL, or file path depending on type.
     * @param string $contentGroup  Optional logical category for grouping and filtering
     *                              (e.g. 'faq', 'policy', 'product', 'support', 'campaign').
     *                              This is a first-class column — do not push group into meta.
     * @param array  $meta          Non-authoritative overflow: file size, MIME type, source URL,
     *                              upload hints. Do not use for anything that will be WHERE-filtered
     *                              or aggregated in reporting.
     * @return int Inserted record ID, or 0 if the site is invalid.
     */
    public function create(
        int $siteId,
        string $resourceType,
        string $title = '',
        string $sourceContent = '',
        string $contentGroup = '',
        array $meta = []
    ): int {
        global $wpdb;

        // Validate site ownership — no FK in MySQL, enforce here.
        $siteTable = $wpdb->prefix . SiteTable::TABLE;
        $site      = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id FROM {$siteTable} WHERE id = %d AND deleted_at IS NULL",
                $siteId
            ),
            ARRAY_A
        );
        if (! $site) {
            return 0;
        }

        $table = $wpdb->prefix . KnowledgeResourceTable::TABLE;
        $now   = current_time('mysql');
        $wpdb->insert($table, [
            'uuid'              => sarah_ai_uuid(),
            'site_id'           => $siteId,
            'title'             => $title ?: null,
            'resource_type'     => $resourceType,
            'content_group'     => $contentGroup ?: null,
            'status'            => KnowledgeResourceTable::STATUS_ACTIVE,
            'source_content'    => $sourceContent ?: null,
            'processing_status' => KnowledgeResourceTable::PROCESSING_QUEUED,
            'meta'              => $meta ? wp_json_encode($meta) : null,
            'sort_order'        => 0,
            'created_at'        => $now,
            'updated_at'        => $now,
        ]);

        return (int) $wpdb->insert_id;
    }

    public function findByUuid(string $uuid): ?array
    {
        global $wpdb;
        $table = $wpdb->prefix . KnowledgeResourceTable::TABLE;
        $row   = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE uuid = %s AND deleted_at IS NULL",
                $uuid
            ),
            ARRAY_A
        );
        return $row ?: null;
    }

    /** Returns a single resource by ID, or null if not found or soft-deleted. */
    public function findById(int $id): ?array
    {
        global $wpdb;
        $table = $wpdb->prefix . KnowledgeResourceTable::TABLE;
        $row   = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE id = %d AND deleted_at IS NULL",
                $id
            ),
            ARRAY_A
        );
        return $row ?: null;
    }

    /** Returns all non-deleted resources for the given site, ordered by sort_order then created_at. */
    public function findBySite(int $siteId): array
    {
        global $wpdb;
        $table = $wpdb->prefix . KnowledgeResourceTable::TABLE;
        $rows  = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE site_id = %d AND deleted_at IS NULL ORDER BY sort_order ASC, created_at DESC",
                $siteId
            ),
            ARRAY_A
        );
        return is_array($rows) ? $rows : [];
    }

    /**
     * Returns only active resources for the given site.
     * This is the primary read path for agents — only STATUS_ACTIVE resources
     * are considered usable by agent execution.
     */
    public function findActiveBySite(int $siteId): array
    {
        global $wpdb;
        $table = $wpdb->prefix . KnowledgeResourceTable::TABLE;
        $rows  = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE site_id = %d AND status = %s AND deleted_at IS NULL ORDER BY sort_order ASC, created_at DESC",
                $siteId,
                KnowledgeResourceTable::STATUS_ACTIVE
            ),
            ARRAY_A
        );
        return is_array($rows) ? $rows : [];
    }

    /**
     * Returns only active PUBLIC resources for the given site.
     * This is the safe read path for AI prompt injection and the public widget API —
     * private resources are excluded so they are never exposed outside admin context.
     */
    public function findPublicActiveBySite(int $siteId): array
    {
        global $wpdb;
        $table = $wpdb->prefix . KnowledgeResourceTable::TABLE;
        $rows  = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE site_id = %d AND status = %s AND visibility = %s AND deleted_at IS NULL ORDER BY sort_order ASC, created_at DESC",
                $siteId,
                KnowledgeResourceTable::STATUS_ACTIVE,
                KnowledgeResourceTable::VISIBILITY_PUBLIC
            ),
            ARRAY_A
        );
        return is_array($rows) ? $rows : [];
    }

    /**
     * Updates the visibility of a resource (public / private).
     * Valid values: KnowledgeResourceTable::VISIBILITY_*
     */
    public function updateVisibility(int $id, string $visibility): void
    {
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . KnowledgeResourceTable::TABLE,
            ['visibility' => $visibility, 'updated_at' => current_time('mysql')],
            ['id'         => $id],
            ['%s', '%s'],
            ['%d']
        );
    }

    /**
     * Returns all non-deleted resources for a site filtered by content_group.
     * Use this for reporting, grouped admin views, or agent scoping by category.
     */
    public function findByGroup(int $siteId, string $contentGroup): array
    {
        global $wpdb;
        $table = $wpdb->prefix . KnowledgeResourceTable::TABLE;
        $rows  = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE site_id = %d AND content_group = %s AND deleted_at IS NULL ORDER BY sort_order ASC, created_at DESC",
                $siteId,
                $contentGroup
            ),
            ARRAY_A
        );
        return is_array($rows) ? $rows : [];
    }

    /**
     * Returns active resources for a site within a specific content group.
     * Intended for future agent scoping — e.g. "use only FAQ resources for this query".
     */
    public function findActiveByGroup(int $siteId, string $contentGroup): array
    {
        global $wpdb;
        $table = $wpdb->prefix . KnowledgeResourceTable::TABLE;
        $rows  = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE site_id = %d AND content_group = %s AND status = %s AND deleted_at IS NULL ORDER BY sort_order ASC, created_at DESC",
                $siteId,
                $contentGroup,
                KnowledgeResourceTable::STATUS_ACTIVE
            ),
            ARRAY_A
        );
        return is_array($rows) ? $rows : [];
    }

    /**
     * Updates the lifecycle status of a resource (admin intent).
     * Valid values: KnowledgeResourceTable::STATUS_*
     * Do not use this method to record pipeline outcomes — use updateProcessingStatus().
     */
    public function updateStatus(int $id, string $status): void
    {
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . KnowledgeResourceTable::TABLE,
            ['status' => $status, 'updated_at' => current_time('mysql')],
            ['id'     => $id],
            ['%s', '%s'],
            ['%d']
        );
    }

    /**
     * Records the outcome of a processing pipeline run (pipeline state only).
     * Valid values: KnowledgeResourceTable::PROCESSING_*
     *
     * This method updates processing_status exclusively. It does not touch
     * processed_content. Storing processed output is a separate concern handled
     * by writeProcessedContentBridge() below.
     *
     * Do not use this method to change admin lifecycle state — use updateStatus().
     */
    public function updateProcessingStatus(int $id, string $processingStatus): void
    {
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . KnowledgeResourceTable::TABLE,
            ['processing_status' => $processingStatus, 'updated_at' => current_time('mysql')],
            ['id'                => $id],
            ['%s', '%s'],
            ['%d']
        );
    }

    /**
     * Writes simple extracted or flattened text to the processed_content column.
     *
     * TEMPORARY BRIDGE — DO NOT BUILD RETRIEVAL LOGIC ON TOP OF THIS.
     *
     * processed_content is a single-row compatibility column introduced in Phase 4.2
     * as an interim store for simple pipeline output. It is not the long-term
     * processed-content model. Phase 4.3+ is expected to introduce a
     * knowledge_chunks table (resource_id, chunk_index, content, meta) for
     * chunk-based storage that supports semantic retrieval, per-chunk metadata,
     * and embedding references. When that table is introduced, callers of this
     * method should migrate to writing chunks instead, and this method should be
     * deprecated and eventually removed.
     *
     * Call updateProcessingStatus() separately to record the pipeline outcome state.
     */
    public function writeProcessedContentBridge(int $id, string $content): void
    {
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . KnowledgeResourceTable::TABLE,
            ['processed_content' => $content, 'updated_at' => current_time('mysql')],
            ['id'                => $id],
            ['%s', '%s'],
            ['%d']
        );
    }

    /**
     * Soft-deletes a resource. Record is retained but excluded from all standard queries.
     * Status is set to ARCHIVED to keep agent read paths consistent — archived resources
     * are never returned by findActiveBySite() or findActiveByGroup().
     */
    public function softDelete(int $id): void
    {
        global $wpdb;
        $now = current_time('mysql');
        $wpdb->update(
            $wpdb->prefix . KnowledgeResourceTable::TABLE,
            ['deleted_at' => $now, 'updated_at' => $now, 'status' => KnowledgeResourceTable::STATUS_ARCHIVED],
            ['id'         => $id],
            ['%s', '%s', '%s'],
            ['%d']
        );
    }
}
