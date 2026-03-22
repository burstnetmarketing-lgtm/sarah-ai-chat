<?php

declare(strict_types=1);

namespace SarahAiServer\Infrastructure;

use SarahAiServer\DB\SiteAgentTable;

/**
 * Manages the site-agent assignment audit log.
 *
 * This table records every agent assignment event per site.
 * It is NOT the fast read path for the current agent — that is
 * sites.active_agent_id (maintained by SiteRepository::updateActiveAgent).
 *
 * Use this repository to:
 *   - log a new assignment event
 *   - retrieve the full assignment history for a site
 */
class SiteAgentRepository
{
    /**
     * Logs an agent assignment event for a site.
     * Always call SiteRepository::updateActiveAgent() alongside this method
     * to keep the denormalized fast-path in sync.
     */
    public function log(int $siteId, int $agentId): void
    {
        global $wpdb;
        $table = $wpdb->prefix . SiteAgentTable::TABLE;
        $now   = current_time('mysql');
        $wpdb->insert($table, [
            'site_id'     => $siteId,
            'agent_id'    => $agentId,
            'status'      => 'active',
            'assigned_at' => $now,
        ]);
    }

    /** Returns the full assignment history for a site, newest first. */
    public function findBySite(int $siteId): array
    {
        global $wpdb;
        $table = $wpdb->prefix . SiteAgentTable::TABLE;
        $rows  = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE site_id = %d ORDER BY assigned_at DESC",
                $siteId
            ),
            ARRAY_A
        );
        return is_array($rows) ? $rows : [];
    }
}
