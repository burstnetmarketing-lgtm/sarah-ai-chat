<?php

declare(strict_types=1);

namespace SarahAiServer\Infrastructure;

use SarahAiServer\DB\AccountKeyTable;

class AccountKeyRepository
{
    /**
     * Issues a new account key for a tenant.
     * The raw key is hashed with SHA-256 before storage.
     * The raw key is NOT stored and cannot be recovered after this call.
     *
     * @param int         $tenantId  Owning tenant ID.
     * @param string      $rawKey    Plaintext key to hash and store.
     * @param string      $label     Human-readable label (e.g. 'production', 'staging').
     * @param string|null $expiresAt Optional expiry datetime. Null = no expiry.
     * @return int Inserted record ID.
     */
    public function issue(int $tenantId, string $rawKey, string $label = '', ?string $expiresAt = null): int
    {
        global $wpdb;
        $table = $wpdb->prefix . AccountKeyTable::TABLE;
        $now   = current_time('mysql');
        $wpdb->insert($table, [
            'uuid'       => sarah_ai_uuid(),
            'tenant_id'  => $tenantId,
            'key_hash'   => hash('sha256', $rawKey),
            'label'      => $label !== '' ? $label : null,
            'status'     => AccountKeyTable::STATUS_ACTIVE,
            'expires_at' => $expiresAt,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        return (int) $wpdb->insert_id;
    }

    public function findById(int $id): ?array
    {
        global $wpdb;
        $table = $wpdb->prefix . AccountKeyTable::TABLE;
        $row   = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id),
            ARRAY_A
        );
        return $row ?: null;
    }

    public function findByUuid(string $uuid): ?array
    {
        global $wpdb;
        $table = $wpdb->prefix . AccountKeyTable::TABLE;
        $row   = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE uuid = %s", $uuid),
            ARRAY_A
        );
        return $row ?: null;
    }

    /**
     * Looks up an account key record by the raw plaintext key.
     * Returns the record only if it is active.
     * The hash is computed internally — the raw key is never compared directly.
     */
    public function findByRawKey(string $rawKey): ?array
    {
        return $this->findByHash(hash('sha256', $rawKey));
    }

    /** Looks up an account key record by its stored SHA-256 hash. */
    public function findByHash(string $keyHash): ?array
    {
        global $wpdb;
        $table = $wpdb->prefix . AccountKeyTable::TABLE;
        $row   = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE key_hash = %s AND status = %s",
                $keyHash,
                AccountKeyTable::STATUS_ACTIVE
            ),
            ARRAY_A
        );
        return $row ?: null;
    }

    /** Returns all account keys for a tenant (active and revoked). */
    public function findByTenant(int $tenantId): array
    {
        global $wpdb;
        $table = $wpdb->prefix . AccountKeyTable::TABLE;
        $rows  = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE tenant_id = %d ORDER BY created_at DESC",
                $tenantId
            ),
            ARRAY_A
        );
        return is_array($rows) ? $rows : [];
    }

    /** Revokes an account key. Revocation is permanent and does not affect other keys for the same tenant. */
    public function revoke(int $id): void
    {
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . AccountKeyTable::TABLE,
            ['status' => AccountKeyTable::STATUS_REVOKED, 'updated_at' => current_time('mysql')],
            ['id'     => $id],
            ['%s', '%s'],
            ['%d']
        );
    }
}
