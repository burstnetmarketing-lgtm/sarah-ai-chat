<?php

declare(strict_types=1);

namespace SarahAiServer\Infrastructure;

use SarahAiServer\DB\SummarizerLogTable;

class SummarizerLogRepository
{
    public function log(string $message): void
    {
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . SummarizerLogTable::TABLE,
            ['logged_at' => current_time('mysql'), 'message' => $message],
            ['%s', '%s']
        );
    }

    /**
     * Delete log entries older than $days days.
     * Returns number of deleted rows.
     */
    public function purgeOlderThan(int $days): int
    {
        global $wpdb;
        $table = $wpdb->prefix . SummarizerLogTable::TABLE;
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table} WHERE logged_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                $days
            )
        );
        return (int) $wpdb->rows_affected;
    }
}
