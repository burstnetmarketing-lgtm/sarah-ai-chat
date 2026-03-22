<?php

declare(strict_types=1);

namespace SarahAiServer\Infrastructure;

use SarahAiServer\DB\SubscriptionTable;

class SubscriptionRepository
{
    /**
     * List all subscriptions with joined tenant and plan names.
     * Optional $status filter: 'trialing'|'active'|'expired'|'cancelled'|'' for all.
     */
    public function all(string $status = ''): array
    {
        global $wpdb;
        $sub    = $wpdb->prefix . SubscriptionTable::TABLE;
        $tenant = $wpdb->prefix . 'sarah_ai_server_tenants';
        $plan   = $wpdb->prefix . 'sarah_ai_server_plans';

        $where = $status ? $wpdb->prepare('WHERE s.status = %s', $status) : '';

        $rows = $wpdb->get_results(
            "SELECT s.*, t.name AS tenant_name, t.slug AS tenant_slug, t.uuid AS tenant_uuid, p.name AS plan_name, p.slug AS plan_slug
             FROM {$sub} s
             LEFT JOIN {$tenant} t ON t.id = s.tenant_id
             LEFT JOIN {$plan}   p ON p.id = s.plan_id
             {$where}
             ORDER BY s.created_at DESC",
            ARRAY_A
        );
        return is_array($rows) ? $rows : [];
    }

    /** Creates a subscription and returns its ID. */
    public function create(int $tenantId, int $planId, string $status, string $startsAt, ?string $endsAt = null, array $meta = []): int
    {
        global $wpdb;
        $table = $wpdb->prefix . SubscriptionTable::TABLE;
        $now   = current_time('mysql');
        $wpdb->insert($table, [
            'tenant_id'  => $tenantId,
            'plan_id'    => $planId,
            'status'     => $status,
            'starts_at'  => $startsAt,
            'ends_at'    => $endsAt,
            'meta'       => $meta ? wp_json_encode($meta) : null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        return (int) $wpdb->insert_id;
    }

    /** Returns the most recent non-cancelled subscription for a tenant, or null. */
    public function findActiveByTenant(int $tenantId): ?array
    {
        global $wpdb;
        $table = $wpdb->prefix . SubscriptionTable::TABLE;
        $row   = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table}
                 WHERE tenant_id = %d
                   AND status NOT IN ('cancelled', 'expired')
                 ORDER BY created_at DESC
                 LIMIT 1",
                $tenantId
            ),
            ARRAY_A
        );
        return $row ?: null;
    }

    public function findById(int $id): ?array
    {
        global $wpdb;
        $table = $wpdb->prefix . SubscriptionTable::TABLE;
        $row   = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id),
            ARRAY_A
        );
        return $row ?: null;
    }

    public function updateStatus(int $id, string $status): void
    {
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . SubscriptionTable::TABLE,
            ['status' => $status, 'updated_at' => current_time('mysql')],
            ['id'     => $id],
            ['%s', '%s'],
            ['%d']
        );
    }
}
