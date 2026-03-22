<?php

declare(strict_types=1);

namespace SarahAiServer\DB;

class PlanAgentTable
{
    public const TABLE = 'sarah_ai_server_plan_agents';

    public static function create(): void
    {
        global $wpdb;
        $table   = $wpdb->prefix . self::TABLE;
        $charset = $wpdb->get_charset_collate();
        $sql     = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            plan_id BIGINT UNSIGNED NOT NULL,
            agent_id BIGINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_plan_agent (plan_id, agent_id),
            KEY idx_plan_id (plan_id),
            KEY idx_agent_id (agent_id)
        ) {$charset};";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }
}
