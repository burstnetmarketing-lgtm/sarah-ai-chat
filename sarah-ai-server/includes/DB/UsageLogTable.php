<?php

declare(strict_types=1);

namespace SarahAiServer\DB;

class UsageLogTable
{
    public const TABLE = 'sarah_ai_server_usage_logs';

    public static function create(): void
    {
        global $wpdb;
        $table   = $wpdb->prefix . self::TABLE;
        $charset = $wpdb->get_charset_collate();
        $sql     = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id BIGINT UNSIGNED NULL DEFAULT NULL,
            site_id BIGINT UNSIGNED NULL DEFAULT NULL,
            agent_id BIGINT UNSIGNED NULL DEFAULT NULL,
            subscription_id BIGINT UNSIGNED NULL DEFAULT NULL,
            event_type VARCHAR(80) NOT NULL,
            tokens_in INT UNSIGNED NULL DEFAULT NULL,
            tokens_out INT UNSIGNED NULL DEFAULT NULL,
            meta LONGTEXT NULL DEFAULT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY idx_tenant_id (tenant_id),
            KEY idx_site_id (site_id),
            KEY idx_agent_id (agent_id),
            KEY idx_event_type (event_type),
            KEY idx_created_at (created_at)
        ) {$charset};";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }
}
