<?php

declare(strict_types=1);

namespace SarahAiServer\Infrastructure;

use SarahAiServer\DB\TenantTable;

class TenantRepository
{
    /** Creates a new tenant and returns its ID. */
    public function create(string $name, string $slug, string $status = 'active', array $meta = [], string $whmcsKey = ''): int
    {
        global $wpdb;
        $table = $wpdb->prefix . TenantTable::TABLE;
        $now   = current_time('mysql');
        $wpdb->insert($table, [
            'uuid'      => sarah_ai_uuid(),
            'name'      => $name,
            'slug'      => $slug,
            'status'    => $status,
            'whmcs_key' => $whmcsKey !== '' ? $whmcsKey : null,
            'meta'      => $meta ? wp_json_encode($meta) : null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        return (int) $wpdb->insert_id;
    }

    /** Updates the WHMCS key for a tenant and resets lastcheck on all its sites. */
    public function updateWhmcsKey(int $id, string $whmcsKey): void
    {
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . TenantTable::TABLE,
            ['whmcs_key' => $whmcsKey !== '' ? $whmcsKey : null, 'updated_at' => current_time('mysql')],
            ['id'        => $id],
            ['%s', '%s'],
            ['%d']
        );
        // Reset whmcs_lastcheck for all sites of this tenant so they re-validate
        $siteTable = $wpdb->prefix . 'sarah_ai_server_sites';
        $wpdb->update(
            $siteTable,
            ['whmcs_lastcheck' => null],
            ['tenant_id'       => $id],
            ['NULL'],
            ['%d']
        );
    }

    public function findByUuid(string $uuid): ?array
    {
        global $wpdb;
        $table = $wpdb->prefix . TenantTable::TABLE;
        $row   = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE uuid = %s AND deleted_at IS NULL", $uuid),
            ARRAY_A
        );
        return $row ?: null;
    }

    public function findById(int $id): ?array
    {
        global $wpdb;
        $table = $wpdb->prefix . TenantTable::TABLE;
        $row   = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d AND deleted_at IS NULL", $id),
            ARRAY_A
        );
        return $row ?: null;
    }

    public function findBySlug(string $slug): ?array
    {
        global $wpdb;
        $table = $wpdb->prefix . TenantTable::TABLE;
        $row   = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE slug = %s AND deleted_at IS NULL", $slug),
            ARRAY_A
        );
        return $row ?: null;
    }

    public function all(): array
    {
        global $wpdb;
        $table = $wpdb->prefix . TenantTable::TABLE;
        $rows  = $wpdb->get_results(
            "SELECT * FROM {$table} WHERE deleted_at IS NULL ORDER BY created_at DESC",
            ARRAY_A
        );
        return is_array($rows) ? $rows : [];
    }

    public function markSetupComplete(int $id): void
    {
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . TenantTable::TABLE,
            ['setup_complete' => 1, 'updated_at' => current_time('mysql')],
            ['id'             => $id],
            ['%d', '%s'],
            ['%d']
        );
    }

    public function updateStatus(int $id, string $status): void
    {
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . TenantTable::TABLE,
            ['status' => $status, 'updated_at' => current_time('mysql')],
            ['id'     => $id],
            ['%s', '%s'],
            ['%d']
        );
    }

    /**
     * Hard-deletes a tenant and ALL related data:
     * chat messages → sessions → knowledge chunks → knowledge resources →
     * site tokens → site agents → site api keys → usage logs → account keys →
     * user-tenant mappings → sites → tenant row.
     */
    public function purge(int $id): void
    {
        global $wpdb;
        $p = $wpdb->prefix;

        // Collect site IDs for this tenant
        $siteIds = $wpdb->get_col(
            $wpdb->prepare("SELECT id FROM {$p}sarah_ai_server_sites WHERE tenant_id = %d", $id)
        );

        if (! empty($siteIds)) {
            $placeholders = implode(',', array_fill(0, count($siteIds), '%d'));

            // Collect session IDs for these sites
            $sessionIds = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT id FROM {$p}sarah_ai_server_chat_sessions WHERE site_id IN ($placeholders)",
                    ...$siteIds
                )
            );

            // Delete chat messages
            if (! empty($sessionIds)) {
                $sp = implode(',', array_fill(0, count($sessionIds), '%d'));
                $wpdb->query(
                    $wpdb->prepare(
                        "DELETE FROM {$p}sarah_ai_server_chat_messages WHERE session_id IN ($sp)",
                        ...$sessionIds
                    )
                );
            }

            // Delete chat sessions
            $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$p}sarah_ai_server_chat_sessions WHERE site_id IN ($placeholders)",
                    ...$siteIds
                )
            );

            // Collect knowledge resource IDs
            $resourceIds = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT id FROM {$p}sarah_ai_server_knowledge_resources WHERE site_id IN ($placeholders)",
                    ...$siteIds
                )
            );

            // Delete knowledge chunks
            if (! empty($resourceIds)) {
                $rp = implode(',', array_fill(0, count($resourceIds), '%d'));
                $wpdb->query(
                    $wpdb->prepare(
                        "DELETE FROM {$p}sarah_ai_server_knowledge_chunks WHERE resource_id IN ($rp)",
                        ...$resourceIds
                    )
                );
            }

            // Delete knowledge resources, site tokens, agents, api keys, usage logs
            foreach ([
                'sarah_ai_server_knowledge_resources',
                'sarah_ai_server_site_tokens',
                'sarah_ai_server_site_agents',
                'sarah_ai_server_site_api_keys',
                'sarah_ai_server_usage_logs',
            ] as $table) {
                $wpdb->query(
                    $wpdb->prepare(
                        "DELETE FROM {$p}{$table} WHERE site_id IN ($placeholders)",
                        ...$siteIds
                    )
                );
            }
        }

        // Delete account keys and user-tenant mappings
        $wpdb->delete("{$p}sarah_ai_server_account_keys", ['tenant_id' => $id], ['%d']);
        $wpdb->delete("{$p}sarah_ai_server_user_tenant",  ['tenant_id' => $id], ['%d']);

        // Delete sites
        $wpdb->delete("{$p}sarah_ai_server_sites", ['tenant_id' => $id], ['%d']);

        // Delete tenant row
        $wpdb->delete("{$p}sarah_ai_server_tenants", ['id' => $id], ['%d']);
    }

    /** Soft-deletes a tenant. Records remain in the database. */
    public function softDelete(int $id): void
    {
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . TenantTable::TABLE,
            ['deleted_at' => current_time('mysql'), 'updated_at' => current_time('mysql')],
            ['id'         => $id],
            ['%s', '%s'],
            ['%d']
        );
    }
}
