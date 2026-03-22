<?php

declare(strict_types=1);

namespace SarahAiServer\Infrastructure;

use SarahAiServer\DB\UserTenantTable;

class UserTenantRepository
{
    /** Associates a WordPress user with a tenant. Safe to call multiple times. */
    public function associate(int $wpUserId, int $tenantId, string $role = 'owner'): void
    {
        global $wpdb;
        $table = $wpdb->prefix . UserTenantTable::TABLE;
        $now   = current_time('mysql');
        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$table} (wp_user_id, tenant_id, role, status, created_at, updated_at)
             VALUES (%d, %d, %s, 'active', %s, %s)
             ON DUPLICATE KEY UPDATE role = VALUES(role), status = 'active', updated_at = VALUES(updated_at)",
            $wpUserId,
            $tenantId,
            $role,
            $now,
            $now
        ));
    }

    /** Returns all tenants a WP user belongs to. */
    public function findByUser(int $wpUserId): array
    {
        global $wpdb;
        $table = $wpdb->prefix . UserTenantTable::TABLE;
        $rows  = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE wp_user_id = %d AND status = 'active'",
                $wpUserId
            ),
            ARRAY_A
        );
        return is_array($rows) ? $rows : [];
    }

    /** Returns all user associations for a tenant. */
    public function findByTenant(int $tenantId): array
    {
        global $wpdb;
        $table = $wpdb->prefix . UserTenantTable::TABLE;
        $rows  = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE tenant_id = %d AND status = 'active'",
                $tenantId
            ),
            ARRAY_A
        );
        return is_array($rows) ? $rows : [];
    }

    /** Returns the Sarah-level role of a user within a tenant, or null if not associated. */
    public function getRole(int $wpUserId, int $tenantId): ?string
    {
        global $wpdb;
        $table = $wpdb->prefix . UserTenantTable::TABLE;
        $role  = $wpdb->get_var($wpdb->prepare(
            "SELECT role FROM {$table} WHERE wp_user_id = %d AND tenant_id = %d AND status = 'active'",
            $wpUserId,
            $tenantId
        ));
        return is_string($role) ? $role : null;
    }

    public function deactivate(int $wpUserId, int $tenantId): void
    {
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . UserTenantTable::TABLE,
            ['status' => 'inactive', 'updated_at' => current_time('mysql')],
            ['wp_user_id' => $wpUserId, 'tenant_id' => $tenantId],
            ['%s', '%s'],
            ['%d', '%d']
        );
    }
}
