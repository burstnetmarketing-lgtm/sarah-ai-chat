<?php

declare(strict_types=1);

namespace SarahAiServer\DB;

class AgentTable
{
    public const TABLE = 'sarah_ai_server_agents';

    public static function create(): void
    {
        global $wpdb;
        $table   = $wpdb->prefix . self::TABLE;
        $charset = $wpdb->get_charset_collate();
        $sql     = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(190) NOT NULL,
            slug VARCHAR(120) NOT NULL,
            type VARCHAR(80) NOT NULL DEFAULT 'dummy',
            description TEXT NULL DEFAULT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'active',
            config LONGTEXT NULL DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_slug (slug),
            KEY idx_status (status)
        ) {$charset};";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }
}
