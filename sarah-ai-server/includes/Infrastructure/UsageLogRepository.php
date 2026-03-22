<?php

declare(strict_types=1);

namespace SarahAiServer\Infrastructure;

use SarahAiServer\DB\UsageLogTable;

class UsageLogRepository
{
    public function log(
        ?int $tenantId,
        ?int $siteId,
        ?int $agentId,
        ?int $subscriptionId,
        ?int $sessionId,
        string $eventType,
        ?int $tokensIn = null,
        ?int $tokensOut = null,
        array $meta = []
    ): void {
        global $wpdb;
        $wpdb->insert($wpdb->prefix . UsageLogTable::TABLE, [
            'tenant_id'       => $tenantId,
            'site_id'         => $siteId,
            'agent_id'        => $agentId,
            'subscription_id' => $subscriptionId,
            'session_id'      => $sessionId,
            'event_type'      => $eventType,
            'tokens_in'       => $tokensIn,
            'tokens_out'      => $tokensOut,
            'meta'            => $meta ? wp_json_encode($meta) : null,
            'created_at'      => current_time('mysql'),
        ]);
    }

    /**
     * Query usage records with optional filters and pagination.
     *
     * @return array[]
     */
    public function findByFilters(
        ?int $tenantId  = null,
        ?int $siteId    = null,
        ?int $sessionId = null,
        ?int $agentId   = null,
        ?string $dateFrom = null,
        ?string $dateTo   = null,
        int $limit  = 50,
        int $offset = 0
    ): array {
        global $wpdb;
        $table  = $wpdb->prefix . UsageLogTable::TABLE;
        $wheres = [];
        $params = [];

        if ($tenantId  !== null) { $wheres[] = 'tenant_id = %d';  $params[] = $tenantId; }
        if ($siteId    !== null) { $wheres[] = 'site_id = %d';    $params[] = $siteId; }
        if ($sessionId !== null) { $wheres[] = 'session_id = %d'; $params[] = $sessionId; }
        if ($agentId   !== null) { $wheres[] = 'agent_id = %d';   $params[] = $agentId; }
        if ($dateFrom)           { $wheres[] = 'created_at >= %s'; $params[] = $dateFrom . ' 00:00:00'; }
        if ($dateTo)             { $wheres[] = 'created_at <= %s'; $params[] = $dateTo   . ' 23:59:59'; }

        $where = $wheres ? 'WHERE ' . implode(' AND ', $wheres) : '';
        $params[] = max(1, min($limit, 200));
        $params[] = max(0, $offset);

        return $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$table} {$where} ORDER BY id DESC LIMIT %d OFFSET %d", $params),
            ARRAY_A
        ) ?: [];
    }

    /**
     * Aggregate usage summary for the given filter scope.
     *
     * @return array{total_requests: int, total_tokens_in: int, total_tokens_out: int}
     */
    public function getSummary(
        ?int $tenantId  = null,
        ?int $siteId    = null,
        ?string $dateFrom = null,
        ?string $dateTo   = null
    ): array {
        global $wpdb;
        $table  = $wpdb->prefix . UsageLogTable::TABLE;
        $wheres = [];
        $params = [];

        if ($tenantId !== null) { $wheres[] = 'tenant_id = %d'; $params[] = $tenantId; }
        if ($siteId   !== null) { $wheres[] = 'site_id = %d';   $params[] = $siteId; }
        if ($dateFrom)          { $wheres[] = 'created_at >= %s'; $params[] = $dateFrom . ' 00:00:00'; }
        if ($dateTo)            { $wheres[] = 'created_at <= %s'; $params[] = $dateTo   . ' 23:59:59'; }

        $where = $wheres ? 'WHERE ' . implode(' AND ', $wheres) : '';
        $sql   = "SELECT COUNT(*) AS total_requests,
                         COALESCE(SUM(tokens_in), 0)  AS total_tokens_in,
                         COALESCE(SUM(tokens_out), 0) AS total_tokens_out
                  FROM {$table} {$where}";

        $row = $params
            ? $wpdb->get_row($wpdb->prepare($sql, $params), ARRAY_A)
            : $wpdb->get_row($sql, ARRAY_A);

        return [
            'total_requests'   => (int) ($row['total_requests']   ?? 0),
            'total_tokens_in'  => (int) ($row['total_tokens_in']  ?? 0),
            'total_tokens_out' => (int) ($row['total_tokens_out'] ?? 0),
        ];
    }
}
