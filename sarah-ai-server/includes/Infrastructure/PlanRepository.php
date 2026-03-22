<?php

declare(strict_types=1);

namespace SarahAiServer\Infrastructure;

use SarahAiServer\DB\PlanTable;

class PlanRepository
{
    public function findById(int $id): ?array
    {
        global $wpdb;
        $table = $wpdb->prefix . PlanTable::TABLE;
        $row   = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id),
            ARRAY_A
        );
        return $row ?: null;
    }

    public function findBySlug(string $slug): ?array
    {
        global $wpdb;
        $table = $wpdb->prefix . PlanTable::TABLE;
        $row   = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE slug = %s", $slug),
            ARRAY_A
        );
        return $row ?: null;
    }

    public function allActive(): array
    {
        global $wpdb;
        $table = $wpdb->prefix . PlanTable::TABLE;
        $rows  = $wpdb->get_results(
            "SELECT * FROM {$table} WHERE status = 'active' ORDER BY name ASC",
            ARRAY_A
        );
        return is_array($rows) ? $rows : [];
    }

    /** Inserts a plan only if the slug does not already exist. Idempotent. */
    public function insertIfMissing(string $name, string $slug, int $durationDays, array $features = []): void
    {
        global $wpdb;
        $table  = $wpdb->prefix . PlanTable::TABLE;
        $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE slug = %s", $slug));
        if ($exists) {
            return;
        }
        $now = current_time('mysql');
        $wpdb->insert($table, [
            'name'          => $name,
            'slug'          => $slug,
            'duration_days' => $durationDays,
            'features'      => $features ? wp_json_encode($features) : null,
            'status'        => 'active',
            'created_at'    => $now,
            'updated_at'    => $now,
        ]);
    }
}
