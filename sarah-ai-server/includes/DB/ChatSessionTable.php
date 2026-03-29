<?php

declare(strict_types=1);

namespace SarahAiServer\DB;

class ChatSessionTable
{
    public const TABLE = 'sarah_ai_server_chat_sessions';

    public const STATUS_OPEN      = 'open';
    public const STATUS_CLOSED    = 'closed';
    public const STATUS_ARCHIVED  = 'archived';
    public const STATUS_ABANDONED = 'abandoned';

    public static function create(): void
    {
        global $wpdb;
        $table   = $wpdb->prefix . self::TABLE;
        $charset = $wpdb->get_charset_collate();
        $sql     = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            uuid VARCHAR(36) NULL DEFAULT NULL,
            tenant_id BIGINT UNSIGNED NOT NULL,
            site_id BIGINT UNSIGNED NOT NULL,
            agent_id BIGINT UNSIGNED NULL DEFAULT NULL,
            subscription_id BIGINT UNSIGNED NULL DEFAULT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'open',
            visitor_name VARCHAR(190) NULL DEFAULT NULL,
            visitor_phone VARCHAR(60) NULL DEFAULT NULL,
            visitor_email VARCHAR(190) NULL DEFAULT NULL,
            captured_data LONGTEXT NULL DEFAULT NULL,
            meta LONGTEXT NULL DEFAULT NULL,
            last_message_at DATETIME NULL DEFAULT NULL,
            summary LONGTEXT NULL DEFAULT NULL,
            summarized_at DATETIME NULL DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_uuid (uuid),
            KEY idx_tenant_id (tenant_id),
            KEY idx_site_id (site_id),
            KEY idx_status (status),
            KEY idx_created_at (created_at),
            KEY idx_last_message_at (last_message_at)
        ) {$charset};";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }
}
