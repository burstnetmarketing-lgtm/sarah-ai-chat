<?php

declare(strict_types=1);

namespace SarahAiServer\Infrastructure;

use SarahAiServer\DB\AgentTable;

class AgentRepository
{
    public function findById(int $id): ?array
    {
        global $wpdb;
        $table = $wpdb->prefix . AgentTable::TABLE;
        $row   = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id),
            ARRAY_A
        );
        return $row ?: null;
    }

    public function findBySlug(string $slug): ?array
    {
        global $wpdb;
        $table = $wpdb->prefix . AgentTable::TABLE;
        $row   = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE slug = %s", $slug),
            ARRAY_A
        );
        return $row ?: null;
    }

    public function allActive(): array
    {
        global $wpdb;
        $table = $wpdb->prefix . AgentTable::TABLE;
        $rows  = $wpdb->get_results(
            "SELECT * FROM {$table} WHERE status = 'active' ORDER BY name ASC",
            ARRAY_A
        );
        return is_array($rows) ? $rows : [];
    }

    /** Inserts a new agent only if the slug does not already exist. Idempotent. */
    public function insertIfMissing(string $name, string $slug, string $type, string $description, array $config = []): void
    {
        global $wpdb;
        $table  = $wpdb->prefix . AgentTable::TABLE;
        $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE slug = %s", $slug));
        if ($exists) {
            return;
        }
        $now = current_time('mysql');
        $wpdb->insert($table, [
            'name'        => $name,
            'slug'        => $slug,
            'type'        => $type,
            'description' => $description,
            'status'      => 'active',
            'config'      => $config ? wp_json_encode($config) : null,
            'created_at'  => $now,
            'updated_at'  => $now,
        ]);
    }

    /** Inserts or updates an agent by slug. Idempotent. */
    public function upsertBySlug(string $name, string $slug, string $type, string $description, array $config = [], string $status = 'active'): void
    {
        global $wpdb;
        $table = $wpdb->prefix . AgentTable::TABLE;
        $now   = current_time('mysql');
        $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE slug = %s", $slug));
        if ($exists) {
            $wpdb->update(
                $table,
                ['name' => $name, 'type' => $type, 'description' => $description, 'status' => $status, 'config' => $config ? wp_json_encode($config) : null, 'updated_at' => $now],
                ['slug' => $slug],
                ['%s', '%s', '%s', '%s', '%s', '%s'],
                ['%s']
            );
        } else {
            $wpdb->insert($table, [
                'name'        => $name,
                'slug'        => $slug,
                'type'        => $type,
                'description' => $description,
                'status'      => $status,
                'config'      => $config ? wp_json_encode($config) : null,
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);
        }
    }

    public function updateStatus(int $id, string $status): void
    {
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . AgentTable::TABLE,
            ['status' => $status, 'updated_at' => current_time('mysql')],
            ['id'     => $id],
            ['%s', '%s'],
            ['%d']
        );
    }
}
