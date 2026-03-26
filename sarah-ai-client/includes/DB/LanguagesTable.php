<?php

declare(strict_types=1);

namespace SarahAiClient\DB;

class LanguagesTable
{
    public const TABLE = 'sarah_ai_client_languages';

    public static function create(): void
    {
        global $wpdb;
        $table   = $wpdb->prefix . self::TABLE;
        $charset = $wpdb->get_charset_collate();
        $sql     = "CREATE TABLE {$table} (
            id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
            code       VARCHAR(10)  NOT NULL,
            label      VARCHAR(100) NOT NULL,
            message    TEXT         NOT NULL,
            sort_order INT UNSIGNED NOT NULL DEFAULT 0,
            is_enabled TINYINT(1)   NOT NULL DEFAULT 1,
            created_at DATETIME     NOT NULL,
            updated_at DATETIME     NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY code (code)
        ) {$charset};";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }
}
