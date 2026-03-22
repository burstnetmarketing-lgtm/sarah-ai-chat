<?php

declare(strict_types=1);

namespace SarahAiServer\DB;

class UserTenantTable
{
    public const TABLE = 'sarah_ai_server_user_tenant';

    public static function create(): void
    {
        global $wpdb;
        $table   = $wpdb->prefix . self::TABLE;
        $charset = $wpdb->get_charset_collate();
        $sql     = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            wp_user_id BIGINT UNSIGNED NOT NULL,
            tenant_id BIGINT UNSIGNED NOT NULL,
            role VARCHAR(80) NOT NULL DEFAULT 'member',
            status VARCHAR(30) NOT NULL DEFAULT 'active',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_user_tenant (wp_user_id, tenant_id),
            KEY idx_tenant_id (tenant_id),
            KEY idx_wp_user_id (wp_user_id)
        ) {$charset};";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }
}
