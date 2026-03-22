<?php

declare(strict_types=1);

namespace SarahAiServer\DB;

/**
 * Lookup table for knowledge resource types.
 *
 * Each row declares one supported resource type, its display label, whether it
 * is currently enabled, and its display order.  The plugin reads this table at
 * boot time so that disabling a type (setting enabled = 0) automatically
 * removes it from the Admin UI without a code deployment.
 *
 * The table is seeded by Seeder::seedKnowledgeResourceTypes() on every boot
 * (idempotent INSERT IGNORE).  To disable a type just UPDATE the row:
 *   UPDATE {prefix}sarah_ai_server_knowledge_resource_types SET enabled = 0 WHERE type_key = 'pdf';
 */
class KnowledgeResourceTypeTable
{
    public const TABLE = 'sarah_ai_server_knowledge_resource_types';

    public static function create(): void
    {
        global $wpdb;
        $table   = $wpdb->prefix . self::TABLE;
        $charset = $wpdb->get_charset_collate();
        $sql     = "CREATE TABLE {$table} (
            id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
            type_key   VARCHAR(80)  NOT NULL,
            label      VARCHAR(120) NOT NULL,
            enabled    TINYINT(1)   NOT NULL DEFAULT 1,
            sort_order INT          NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_type_key (type_key),
            KEY idx_enabled (enabled)
        ) {$charset};";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }
}
