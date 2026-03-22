<?php

declare(strict_types=1);

namespace SarahAiServer\DB;

class ChatMessageTable
{
    public const TABLE = 'sarah_ai_server_chat_messages';

    public const ROLE_CUSTOMER  = 'customer';
    public const ROLE_ASSISTANT = 'assistant';
    public const ROLE_SYSTEM    = 'system';

    public static function create(): void
    {
        global $wpdb;
        $table   = $wpdb->prefix . self::TABLE;
        $charset = $wpdb->get_charset_collate();
        $sql     = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            uuid VARCHAR(36) NULL DEFAULT NULL,
            session_id BIGINT UNSIGNED NOT NULL,
            role VARCHAR(30) NOT NULL,
            content LONGTEXT NOT NULL,
            meta LONGTEXT NULL DEFAULT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_uuid (uuid),
            KEY idx_session_id (session_id),
            KEY idx_role (role),
            KEY idx_created_at (created_at)
        ) {$charset};";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }
}
