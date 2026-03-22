<?php

declare(strict_types=1);

namespace SarahAiServer\DB;

class EmailTemplateTable
{
    public const TABLE = 'sarah_ai_server_email_templates';

    public static function create(): void
    {
        global $wpdb;
        $table   = $wpdb->prefix . self::TABLE;
        $charset = $wpdb->get_charset_collate();
        $sql     = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            slug VARCHAR(120) NOT NULL,
            type VARCHAR(80) NOT NULL DEFAULT 'transactional',
            subject VARCHAR(500) NOT NULL,
            body LONGTEXT NOT NULL,
            variables LONGTEXT NULL DEFAULT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'active',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_slug (slug),
            KEY idx_type (type),
            KEY idx_status (status)
        ) {$charset};";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }
}
