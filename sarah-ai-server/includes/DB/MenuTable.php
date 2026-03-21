<?php

declare(strict_types=1);

namespace ProjectName\DB;

class MenuTable
{
    public const TABLE = 'project_name_menu_items';

    public static function create(): void
    {
        global $wpdb;
        $table   = $wpdb->prefix . self::TABLE;
        $charset = $wpdb->get_charset_collate();
        $sql     = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            parent_key VARCHAR(120) NULL DEFAULT NULL,
            item_key VARCHAR(120) NOT NULL,
            label VARCHAR(190) NOT NULL,
            view_key VARCHAR(120) NOT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            is_enabled TINYINT(1) NOT NULL DEFAULT 1,
            is_deletable TINYINT(1) NOT NULL DEFAULT 1,
            allow_children TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_item_key (item_key)
        ) {$charset};";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }
}
