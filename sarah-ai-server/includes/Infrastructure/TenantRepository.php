<?php

declare(strict_types=1);

namespace SarahAiServer\Infrastructure;

use SarahAiServer\DB\TenantTable;

class TenantRepository
{
    /** Creates a new tenant and returns its ID. */
    public function create(string $name, string $slug, string $status = 'active', array $meta = []): int
    {
        global $wpdb;
        $table = $wpdb->prefix . TenantTable::TABLE;
        $now   = current_time('mysql');
        $wpdb->insert($table, [
            'uuid'       => sarah_ai_uuid(),
            'name'       => $name,
            'slug'       => $slug,
            'status'     => $status,
            'meta'       => $meta ? wp_json_encode($meta) : null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        return (int) $wpdb->insert_id;
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
