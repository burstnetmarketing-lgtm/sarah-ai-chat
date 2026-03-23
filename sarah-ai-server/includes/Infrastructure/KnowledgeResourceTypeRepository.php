<?php

declare(strict_types=1);

namespace SarahAiServer\Infrastructure;

use SarahAiServer\DB\KnowledgeResourceTypeTable;

class KnowledgeResourceTypeRepository
{
    private string $table;

    public function __construct()
    {
        global $wpdb;
        $this->table = $wpdb->prefix . KnowledgeResourceTypeTable::TABLE;
    }

    /**
     * Returns all enabled resource types ordered by sort_order.
     *
     * @return array<array{type_key: string, label: string}>
     */
    public function findEnabled(): array
    {
        global $wpdb;
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return $wpdb->get_results(
            "SELECT type_key, label FROM {$this->table} WHERE enabled = 1 ORDER BY sort_order ASC, id ASC",
            ARRAY_A
        ) ?: [];
    }

    /**
     * Seed a type row — INSERT IGNORE so it is idempotent on re-runs.
     */
    public function seed(string $typeKey, string $label, int $enabled, int $sortOrder): void
    {
        global $wpdb;
        $wpdb->query(
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                "INSERT INTO {$this->table} (type_key, label, enabled, sort_order)
                 VALUES (%s, %s, %d, %d)
                 ON DUPLICATE KEY UPDATE label = VALUES(label), enabled = VALUES(enabled), sort_order = VALUES(sort_order)",
                $typeKey,
                $label,
                $enabled,
                $sortOrder
            )
        );
    }
}
