<?php

namespace SarahAiClient\DB;

class SettingsTable
{
    public const TABLE = 'sarah_ai_client_settings';

    public static function create(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;
        $charset = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            setting_key VARCHAR(120) NOT NULL,
            setting_value LONGTEXT NULL,
            published_at DATETIME NULL,
            draft_value LONGTEXT NULL,
            draft_updated_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_setting_key (setting_key)
        ) {$charset};";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }
}
