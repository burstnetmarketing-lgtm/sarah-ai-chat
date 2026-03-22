<?php

declare(strict_types=1);

namespace SarahAiServer\DB;

class SiteTokenTable
{
    public const TABLE = 'sarah_ai_server_site_tokens';

    public static function create(): void
    {
        global $wpdb;
        $table   = $wpdb->prefix . self::TABLE;
        $charset = $wpdb->get_charset_collate();
        $sql     = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            site_id BIGINT UNSIGNED NOT NULL,
            token_hash VARCHAR(64) NOT NULL,
            label VARCHAR(190) NULL DEFAULT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'active',
            expires_at DATETIME NULL DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_token_hash (token_hash),
            KEY idx_site_id (site_id),
            KEY idx_status (status)
        ) {$charset};";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }
}
