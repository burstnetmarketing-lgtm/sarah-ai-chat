<?php

declare(strict_types=1);

namespace SarahAiServer\Infrastructure;

use SarahAiServer\DB\SiteTable;

class SiteRepository
{
    /** Creates a new site under a tenant and returns its ID. */
    public function create(int $tenantId, string $name, string $url, array $meta = [], ?int $planId = null): int
    {
        global $wpdb;
        $table = $wpdb->prefix . SiteTable::TABLE;
        $now   = current_time('mysql');
        $wpdb->insert($table, [
            'uuid'       => sarah_ai_uuid(),
            'tenant_id'  => $tenantId,
            'name'       => $name,
            'url'        => $url,
            'status'     => 'active',
            'plan_id'    => $planId,
            'meta'       => $meta ? wp_json_encode($meta) : null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        return (int) $wpdb->insert_id;
    }

    /** Updates the plan for a site. */
    public function updatePlan(int $siteId, int $planId): void
    {
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . SiteTable::TABLE,
            ['plan_id' => $planId, 'updated_at' => current_time('mysql')],
            ['id'      => $siteId],
            ['%d', '%s'],
            ['%d']
        );
    }

    /** Stamps whmcs_lastcheck to now (marks WHMCS key as recently validated). */
    public function updateWhmcsLastcheck(int $siteId): void
    {
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . SiteTable::TABLE,
            ['whmcs_lastcheck' => current_time('mysql'), 'updated_at' => current_time('mysql')],
            ['id'              => $siteId],
            ['%s', '%s'],
            ['%d']
        );
    }

    public function findByUuid(string $uuid): ?array
    {
        global $wpdb;
        $table = $wpdb->prefix . SiteTable::TABLE;
        $row   = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE uuid = %s AND deleted_at IS NULL", $uuid),
            ARRAY_A
        );
        return $row ?: null;
    }

    /** Returns any (non-deleted) site whose url matches, for uniqueness enforcement. */
    public function findByUrl(string $url): ?array
    {
        global $wpdb;
        $table = $wpdb->prefix . SiteTable::TABLE;
        $row   = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE url = %s AND deleted_at IS NULL LIMIT 1", $url),
            ARRAY_A
        );
        return $row ?: null;
    }

    public function findById(int $id): ?array
    {
        global $wpdb;
        $table = $wpdb->prefix . SiteTable::TABLE;
        $row   = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d AND deleted_at IS NULL", $id),
            ARRAY_A
        );
        return $row ?: null;
    }

    public function findByTenant(int $tenantId): array
    {
        global $wpdb;
        $table = $wpdb->prefix . SiteTable::TABLE;
        $rows  = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE tenant_id = %d AND deleted_at IS NULL ORDER BY created_at DESC",
                $tenantId
            ),
            ARRAY_A
        );
        return is_array($rows) ? $rows : [];
    }

    /** Sets the active agent for a site. Pass null to clear the assignment. */
    public function updateActiveAgent(int $siteId, ?int $agentId): void
    {
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . SiteTable::TABLE,
            ['active_agent_id' => $agentId, 'updated_at' => current_time('mysql')],
            ['id'              => $siteId],
            [$agentId !== null ? '%d' : 'NULL', '%s'],
            ['%d']
        );
    }

    public function updateStatus(int $id, string $status): void
    {
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . SiteTable::TABLE,
            ['status' => $status, 'updated_at' => current_time('mysql')],
            ['id'     => $id],
            ['%s', '%s'],
            ['%d']
        );
    }

    /**
     * Saves agent identity fields for a site.
     * Only non-null values in $data are written.
     */
    public function updateAgentIdentity(int $siteId, array $data): void
    {
        global $wpdb;
        $allowed = ['agent_display_name', 'greeting_message', 'intro_message'];
        $update  = ['updated_at' => current_time('mysql')];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $update[$field] = $data[$field] !== '' ? (string) $data[$field] : null;
            }
        }

        $wpdb->update($wpdb->prefix . SiteTable::TABLE, $update, ['id' => $siteId]);
    }

    /**
     * Returns agent identity fields for a site.
     *
     * @return array{agent_display_name: string|null, greeting_message: string|null, intro_message: string|null}
     */
    public function getAgentIdentity(int $siteId): array
    {
        global $wpdb;
        $table = $wpdb->prefix . SiteTable::TABLE;
        $row   = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT agent_display_name, greeting_message, intro_message FROM {$table} WHERE id = %d",
                $siteId
            ),
            ARRAY_A
        );
        return [
            'agent_display_name' => $row['agent_display_name'] ?? null,
            'greeting_message'   => $row['greeting_message']   ?? null,
            'intro_message'      => $row['intro_message']      ?? null,
        ];
    }

    /**
     * Saves per-site agent behavior overrides.
     * Only fields with non-null values in $data are written.
     * Pass null for any field to clear its override (revert to agent default).
     */
    public function updateAgentConfig(int $siteId, array $data): void
    {
        global $wpdb;
        $table = $wpdb->prefix . SiteTable::TABLE;

        $existing = $wpdb->get_var($wpdb->prepare("SELECT agent_config FROM {$table} WHERE id = %d", $siteId));
        $config   = $existing ? (json_decode($existing, true) ?? []) : [];

        $allowed = [
            'tone', 'tone_custom', 'system_prompt',
            'allow_general_knowledge', 'no_closing_question', 'handle_vague_queries',
            'custom_rules', 'knowledge_instruction', 'knowledge_fallback', 'restricted_response',
        ];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $data)) {
                if ($data[$key] === null) {
                    unset($config[$key]);
                } else {
                    $config[$key] = $data[$key];
                }
            }
        }

        $wpdb->update(
            $table,
            ['agent_config' => empty($config) ? null : wp_json_encode($config), 'updated_at' => current_time('mysql')],
            ['id'           => $siteId],
            ['%s', '%s'],
            ['%d']
        );
    }

    /**
     * Returns per-site agent behavior overrides.
     * Returns an empty array if no overrides are set.
     */
    public function getAgentConfig(int $siteId): array
    {
        global $wpdb;
        $table = $wpdb->prefix . SiteTable::TABLE;
        $raw   = $wpdb->get_var($wpdb->prepare("SELECT agent_config FROM {$table} WHERE id = %d", $siteId));
        return $raw ? (json_decode($raw, true) ?? []) : [];
    }

    /** Soft-deletes a site. Records remain in the database. */
    public function softDelete(int $id): void
    {
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . SiteTable::TABLE,
            ['deleted_at' => current_time('mysql'), 'updated_at' => current_time('mysql')],
            ['id'         => $id],
            ['%s', '%s'],
            ['%d']
        );
    }
}
