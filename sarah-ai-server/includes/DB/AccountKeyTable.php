<?php

declare(strict_types=1);

namespace SarahAiServer\DB;

/**
 * Tenant-level authentication credentials.
 *
 * Account Keys are independent of Site Keys (site_tokens). They identify the
 * tenant during client authentication and must not carry any business data.
 * Ownership: AccountKey → tenant_id → Tenant.
 *
 * Runtime validation resolves:
 *   account key  → tenant
 *   site key     → site
 *   site.tenant_id must equal tenant.id
 *
 * Multiple keys per tenant are supported for key rotation and environment
 * separation (e.g. staging vs. production). Revocation does not affect
 * any other key belonging to the same tenant.
 */
class AccountKeyTable
{
    public const TABLE = 'sarah_ai_server_account_keys';

    public const STATUS_ACTIVE  = 'active';
    public const STATUS_REVOKED = 'revoked';

    public static function create(): void
    {
        global $wpdb;
        $table   = $wpdb->prefix . self::TABLE;
        $charset = $wpdb->get_charset_collate();
        $sql     = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            tenant_id BIGINT UNSIGNED NOT NULL,
            key_hash VARCHAR(64) NOT NULL,
            label VARCHAR(190) NULL DEFAULT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'active',
            expires_at DATETIME NULL DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_key_hash (key_hash),
            KEY idx_tenant_id (tenant_id),
            KEY idx_status (status)
        ) {$charset};";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }
}
