<?php

declare(strict_types=1);

namespace SarahAiServer\DB;

class SubscriptionTable
{
    public const TABLE = 'sarah_ai_server_subscriptions';

    public static function create(): void
    {
        global $wpdb;
        $table   = $wpdb->prefix . self::TABLE;
        $charset = $wpdb->get_charset_collate();
        $sql     = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id BIGINT UNSIGNED NOT NULL,
            plan_id BIGINT UNSIGNED NOT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'trialing',
            starts_at DATETIME NOT NULL,
            ends_at DATETIME NULL DEFAULT NULL,
            meta LONGTEXT NULL DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY idx_tenant_id (tenant_id),
            KEY idx_plan_id (plan_id),
            KEY idx_status (status),
            KEY idx_ends_at (ends_at)
        ) {$charset};";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }
}
