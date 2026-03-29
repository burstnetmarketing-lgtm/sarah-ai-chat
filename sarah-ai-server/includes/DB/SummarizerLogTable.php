<?php

declare(strict_types=1);

namespace SarahAiServer\DB;

class SummarizerLogTable
{
    public const TABLE = 'sarah_ai_server_summarizer_log';

    public static function create(): void
    {
        global $wpdb;
        $table   = $wpdb->prefix . self::TABLE;
        $charset = $wpdb->get_charset_collate();
        $sql     = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            logged_at DATETIME NOT NULL,
            message TEXT NOT NULL,
            PRIMARY KEY (id),
            KEY idx_logged_at (logged_at)
        ) {$charset};";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }
}
