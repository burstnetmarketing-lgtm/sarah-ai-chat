<?php

declare(strict_types=1);

namespace SarahAiServer\Infrastructure;

use SarahAiServer\DB\SiteApiKeyTable;

class SiteApiKeyRepository
{
    /**
     * Returns the raw API key for a given site + provider, or null if not set.
     */
    public function get(int $siteId, string $provider): ?string
    {
        global $wpdb;
        $table = $wpdb->prefix . SiteApiKeyTable::TABLE;
        $row   = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT api_key FROM {$table} WHERE site_id = %d AND provider = %s",
                $siteId,
                $provider
            ),
            ARRAY_A
        );
        return $row ? (string) $row['api_key'] : null;
    }

    /**
     * Upserts a key for a site + provider. Pass empty string to clear (deletes the row).
     */
    public function set(int $siteId, string $provider, string $apiKey): void
    {
        global $wpdb;
        $table = $wpdb->prefix . SiteApiKeyTable::TABLE;

        if ($apiKey === '') {
            $wpdb->delete($table, ['site_id' => $siteId, 'provider' => $provider], ['%d', '%s']);
            return;
        }

        $now      = current_time('mysql');
        $existing = $wpdb->get_var(
            $wpdb->prepare("SELECT id FROM {$table} WHERE site_id = %d AND provider = %s", $siteId, $provider)
        );

        if ($existing) {
            $wpdb->update(
                $table,
                ['api_key' => $apiKey, 'updated_at' => $now],
                ['site_id' => $siteId, 'provider' => $provider],
                ['%s', '%s'],
                ['%d', '%s']
            );
        } else {
            $wpdb->insert($table, [
                'site_id'    => $siteId,
                'provider'   => $provider,
                'api_key'    => $apiKey,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * Returns a list of providers that have a key set for a site (keys are NOT returned).
     *
     * @return string[]
     */
    public function listProviders(int $siteId): array
    {
        global $wpdb;
        $table = $wpdb->prefix . SiteApiKeyTable::TABLE;
        $rows  = $wpdb->get_col(
            $wpdb->prepare("SELECT provider FROM {$table} WHERE site_id = %d ORDER BY provider", $siteId)
        );
        return is_array($rows) ? $rows : [];
    }
}
