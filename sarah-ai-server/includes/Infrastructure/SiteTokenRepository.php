<?php

declare(strict_types=1);

namespace SarahAiServer\Infrastructure;

use SarahAiServer\DB\SiteTokenTable;

class SiteTokenRepository
{
    /**
     * Issues a new token for a site.
     * The raw token is hashed with SHA-256 before storage.
     * Returns the new record ID.
     */
    public function issue(int $siteId, string $rawToken, string $label = '', ?string $expiresAt = null): int
    {
        global $wpdb;
        $table = $wpdb->prefix . SiteTokenTable::TABLE;
        $now   = current_time('mysql');
        $wpdb->insert($table, [
            'site_id'    => $siteId,
            'token_hash' => hash('sha256', $rawToken),
            'label'      => $label !== '' ? $label : null,
            'status'     => 'active',
            'expires_at' => $expiresAt,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        return (int) $wpdb->insert_id;
    }

    /**
     * Looks up a token record by raw token value.
     * Returns the record if found and active, null otherwise.
     */
    public function findByRawToken(string $rawToken): ?array
    {
        return $this->findByHash(hash('sha256', $rawToken));
    }

    /** Looks up a token record by its stored SHA-256 hash. */
    public function findByHash(string $tokenHash): ?array
    {
        global $wpdb;
        $table = $wpdb->prefix . SiteTokenTable::TABLE;
        $row   = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE token_hash = %s AND status = 'active'",
                $tokenHash
            ),
            ARRAY_A
        );
        return $row ?: null;
    }

    /** Returns all tokens for a site (active and revoked). */
    public function findBySite(int $siteId): array
    {
        global $wpdb;
        $table = $wpdb->prefix . SiteTokenTable::TABLE;
        $rows  = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE site_id = %d ORDER BY created_at DESC",
                $siteId
            ),
            ARRAY_A
        );
        return is_array($rows) ? $rows : [];
    }

    public function revoke(int $id): void
    {
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . SiteTokenTable::TABLE,
            ['status' => 'revoked', 'updated_at' => current_time('mysql')],
            ['id'     => $id],
            ['%s', '%s'],
            ['%d']
        );
    }
}
