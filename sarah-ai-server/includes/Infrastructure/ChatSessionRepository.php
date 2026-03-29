<?php

declare(strict_types=1);

namespace SarahAiServer\Infrastructure;

use SarahAiServer\DB\ChatSessionTable;

class ChatSessionRepository
{
    public function create(
        int $tenantId,
        int $siteId,
        ?int $agentId = null,
        ?int $subscriptionId = null
    ): int {
        global $wpdb;
        $table = $wpdb->prefix . ChatSessionTable::TABLE;
        $now   = current_time('mysql');
        $wpdb->insert($table, [
            'uuid'            => sarah_ai_uuid(),
            'tenant_id'       => $tenantId,
            'site_id'         => $siteId,
            'agent_id'        => $agentId,
            'subscription_id' => $subscriptionId,
            'status'          => ChatSessionTable::STATUS_OPEN,
            'created_at'      => $now,
            'updated_at'      => $now,
        ]);
        return (int) $wpdb->insert_id;
    }

    public function findByUuid(string $uuid): ?array
    {
        global $wpdb;
        $table = $wpdb->prefix . ChatSessionTable::TABLE;
        $row   = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE uuid = %s", $uuid),
            ARRAY_A
        );
        return $row ?: null;
    }

    public function findById(int $id): ?array
    {
        global $wpdb;
        $table = $wpdb->prefix . ChatSessionTable::TABLE;
        $row   = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id),
            ARRAY_A
        );
        return $row ?: null;
    }

    public function updateLeadInfo(int $id, ?string $name, ?string $phone, ?string $email): void
    {
        global $wpdb;
        $data = ['updated_at' => current_time('mysql')];
        if ($name  !== null) $data['visitor_name']  = $name;
        if ($phone !== null) $data['visitor_phone'] = $phone;
        if ($email !== null) $data['visitor_email'] = $email;
        $wpdb->update($wpdb->prefix . ChatSessionTable::TABLE, $data, ['id' => $id]);
    }

    public function mergeCapturedData(int $id, array $data): void
    {
        global $wpdb;
        $table   = $wpdb->prefix . ChatSessionTable::TABLE;
        $session = $this->findById($id);
        $existing = [];
        if ($session && $session['captured_data']) {
            $existing = json_decode($session['captured_data'], true) ?? [];
        }
        $merged = array_merge($existing, $data);
        $wpdb->update($table,
            ['captured_data' => wp_json_encode($merged), 'updated_at' => current_time('mysql')],
            ['id' => $id]
        );
    }

    /** Returns total session count for a site. */
    public function countBySite(int $siteId): int
    {
        global $wpdb;
        $table = $wpdb->prefix . ChatSessionTable::TABLE;
        return (int) $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE site_id = %d", $siteId)
        );
    }

    /** Returns all sessions for a site ordered newest first. */
    public function findBySite(int $siteId, int $limit = 50, int $offset = 0): array
    {
        global $wpdb;
        $table = $wpdb->prefix . ChatSessionTable::TABLE;
        $rows  = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE site_id = %d ORDER BY created_at DESC LIMIT %d OFFSET %d",
                $siteId, $limit, $offset
            ),
            ARRAY_A
        );
        return is_array($rows) ? $rows : [];
    }

    /** Returns all sessions for a tenant ordered newest first. */
    public function findByTenant(int $tenantId, int $limit = 50, int $offset = 0): array
    {
        global $wpdb;
        $table = $wpdb->prefix . ChatSessionTable::TABLE;
        $rows  = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE tenant_id = %d ORDER BY created_at DESC LIMIT %d OFFSET %d",
                $tenantId, $limit, $offset
            ),
            ARRAY_A
        );
        return is_array($rows) ? $rows : [];
    }

    public function close(int $id): void
    {
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . ChatSessionTable::TABLE,
            ['status' => ChatSessionTable::STATUS_CLOSED, 'updated_at' => current_time('mysql')],
            ['id' => $id]
        );
    }

    public function updateLastMessageAt(int $id): void
    {
        global $wpdb;
        $now = current_time('mysql');
        $wpdb->update(
            $wpdb->prefix . ChatSessionTable::TABLE,
            ['last_message_at' => $now, 'updated_at' => $now],
            ['id' => $id]
        );
    }

    /**
     * Returns open sessions whose last message was at least $minutes ago
     * and have not yet been summarized (or have new messages since summarized_at).
     */
    public function findNeedingSummary(int $minutes = 30, int $limit = 5): array
    {
        global $wpdb;
        $table = $wpdb->prefix . ChatSessionTable::TABLE;
        $rows  = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table}
                 WHERE status = 'open'
                   AND last_message_at IS NOT NULL
                   AND last_message_at <= DATE_SUB(NOW(), INTERVAL %d MINUTE)
                   AND (summarized_at IS NULL OR summarized_at < last_message_at)
                 ORDER BY last_message_at ASC
                 LIMIT %d",
                $minutes,
                $limit
            ),
            ARRAY_A
        );
        return is_array($rows) ? $rows : [];
    }

    public function saveSummary(int $id, string $summary): void
    {
        global $wpdb;
        $now = current_time('mysql');
        $wpdb->update(
            $wpdb->prefix . ChatSessionTable::TABLE,
            ['summary' => $summary, 'summarized_at' => $now, 'updated_at' => $now],
            ['id' => $id]
        );
    }
}
