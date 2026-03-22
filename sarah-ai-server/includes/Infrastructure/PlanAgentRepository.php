<?php

declare(strict_types=1);

namespace SarahAiServer\Infrastructure;

use SarahAiServer\DB\PlanAgentTable;

class PlanAgentRepository
{
    /** Return all agent IDs allowed for a plan. */
    public function getAgentIdsForPlan(int $planId): array
    {
        global $wpdb;
        $table = $wpdb->prefix . PlanAgentTable::TABLE;
        $ids   = $wpdb->get_col($wpdb->prepare("SELECT agent_id FROM {$table} WHERE plan_id = %d", $planId));
        return array_map('intval', $ids ?: []);
    }

    /** Replace the full agent list for a plan (sync). */
    public function syncAgents(int $planId, array $agentIds): void
    {
        global $wpdb;
        $table = $wpdb->prefix . PlanAgentTable::TABLE;
        $wpdb->delete($table, ['plan_id' => $planId], ['%d']);
        $now = current_time('mysql');
        foreach (array_unique(array_map('intval', $agentIds)) as $agentId) {
            $wpdb->insert($table, ['plan_id' => $planId, 'agent_id' => $agentId, 'created_at' => $now]);
        }
    }

    /** Seed a plan→agent link if not already present. */
    public function insertIfMissing(int $planId, int $agentId): void
    {
        global $wpdb;
        $table  = $wpdb->prefix . PlanAgentTable::TABLE;
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE plan_id = %d AND agent_id = %d",
            $planId, $agentId
        ));
        if ($exists) {
            return;
        }
        $wpdb->insert($table, ['plan_id' => $planId, 'agent_id' => $agentId, 'created_at' => current_time('mysql')]);
    }
}
