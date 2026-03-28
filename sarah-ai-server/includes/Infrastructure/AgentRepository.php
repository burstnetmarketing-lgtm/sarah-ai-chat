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

    /** Inserts or updates an agent by slug. Idempotent.
     *
     * On UPDATE: technical fields (name, description, status, type) and system
     * config keys (model, max_tokens, temperature) are refreshed from the seeder.
     * Behavior fields edited by the admin (role, tone, tone_custom, system_prompt,
     * allow_general_knowledge, no_closing_question, handle_vague_queries,
     * custom_rules, knowledge_instruction, knowledge_fallback, restricted_response)
     * are preserved — admin changes are never overwritten by the seeder.
     */
    public function upsertBySlug(string $name, string $slug, string $type, string $description, array $config = [], string $status = 'active'): void
    {
        global $wpdb;
        $table = $wpdb->prefix . AgentTable::TABLE;
        $now   = current_time('mysql');

        $existingRow = $wpdb->get_row($wpdb->prepare("SELECT id, config FROM {$table} WHERE slug = %s", $slug), ARRAY_A);

        if ($existingRow) {
            // Merge: seed supplies defaults for everything; admin-edited behavior fields take priority.
            $existingConfig = $existingRow['config'] ? (json_decode($existingRow['config'], true) ?? []) : [];

            $behaviorFields = [
                'role', 'tone', 'tone_custom',
                'allow_general_knowledge', 'no_closing_question', 'handle_vague_queries',
                'custom_rules', 'knowledge_instruction', 'knowledge_fallback', 'restricted_response',
                'max_tokens', 'temperature',
            ];

            // Start from seed config (provides defaults + technical fields)
            $merged = $config;
            // Overlay any behavior fields the admin has already saved (non-empty wins)
            foreach ($behaviorFields as $key) {
                $adminValue = $existingConfig[$key] ?? null;
                if ($adminValue !== null && $adminValue !== '' && $adminValue !== []) {
                    $merged[$key] = $adminValue;
                }
            }

            $wpdb->update(
                $table,
                ['name' => $name, 'type' => $type, 'description' => $description, 'status' => $status, 'config' => $merged ? wp_json_encode($merged) : null, 'updated_at' => $now],
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

    /**
     * Merges behavior fields into the agent's config JSON.
     * Existing config keys (model, max_tokens, temperature) are preserved.
     *
     * Accepted keys:
     *   role, tone, tone_custom, system_prompt,
     *   allow_general_knowledge, no_closing_question, handle_vague_queries,
     *   custom_rules, knowledge_instruction, knowledge_fallback, restricted_response
     */
    public function updateBehavior(int $id, array $fields): void
    {
        global $wpdb;
        $table = $wpdb->prefix . AgentTable::TABLE;

        $existing = $wpdb->get_var($wpdb->prepare("SELECT config FROM {$table} WHERE id = %d", $id));
        $config   = $existing ? (json_decode($existing, true) ?? []) : [];

        $allowed = [
            'role', 'tone', 'tone_custom',
            'allow_general_knowledge', 'no_closing_question', 'handle_vague_queries',
            'custom_rules', 'knowledge_instruction', 'knowledge_fallback', 'restricted_response',
            'max_tokens', 'temperature',
        ];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $fields)) {
                $config[$key] = $fields[$key];
            }
        }

        $wpdb->update(
            $table,
            ['config' => wp_json_encode($config), 'updated_at' => current_time('mysql')],
            ['id'     => $id],
            ['%s', '%s'],
            ['%d']
        );
    }
}
