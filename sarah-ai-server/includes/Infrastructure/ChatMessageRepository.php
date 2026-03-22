<?php

declare(strict_types=1);

namespace SarahAiServer\Infrastructure;

use SarahAiServer\DB\ChatMessageTable;

class ChatMessageRepository
{
    public function add(int $sessionId, string $role, string $content, array $meta = []): int
    {
        global $wpdb;
        $table = $wpdb->prefix . ChatMessageTable::TABLE;
        $wpdb->insert($table, [
            'uuid'       => sarah_ai_uuid(),
            'session_id' => $sessionId,
            'role'       => $role,
            'content'    => $content,
            'meta'       => $meta ? wp_json_encode($meta) : null,
            'created_at' => current_time('mysql'),
        ]);
        return (int) $wpdb->insert_id;
    }

    /** Returns all messages for a session ordered oldest first. */
    public function findBySession(int $sessionId): array
    {
        global $wpdb;
        $table = $wpdb->prefix . ChatMessageTable::TABLE;
        $rows  = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE session_id = %d ORDER BY created_at ASC, id ASC",
                $sessionId
            ),
            ARRAY_A
        );
        return is_array($rows) ? $rows : [];
    }
}
