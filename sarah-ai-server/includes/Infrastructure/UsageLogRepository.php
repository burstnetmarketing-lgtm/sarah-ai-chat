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
}
