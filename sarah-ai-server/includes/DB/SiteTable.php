<?php

declare(strict_types=1);

namespace SarahAiServer\DB;

class SiteTable
{
    public const TABLE = 'sarah_ai_server_sites';

    public static function create(): void
    {
        global $wpdb;
        $table   = $wpdb->prefix . self::TABLE;
        $charset = $wpdb->get_charset_collate();
        $sql     = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            uuid VARCHAR(36) NULL DEFAULT NULL,
            tenant_id BIGINT UNSIGNED NOT NULL,
            name VARCHAR(190) NOT NULL,
            url VARCHAR(500) NOT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'active',
            plan_id BIGINT UNSIGNED NULL DEFAULT NULL,
            whmcs_lastcheck DATETIME NULL DEFAULT NULL,
            active_agent_id BIGINT UNSIGNED NULL DEFAULT NULL,
            agent_display_name VARCHAR(190) NULL DEFAULT NULL,
            greeting_message TEXT NULL DEFAULT NULL,
            intro_message TEXT NULL DEFAULT NULL,
            agent_config LONGTEXT NULL DEFAULT NULL,
            meta LONGTEXT NULL DEFAULT NULL,
            deleted_at DATETIME NULL DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_uuid (uuid),
            KEY idx_tenant_id (tenant_id),
            KEY idx_status (status),
            KEY idx_active_agent_id (active_agent_id)
        ) {$charset};";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }
}
