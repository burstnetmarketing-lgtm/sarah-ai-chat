<?php

declare(strict_types=1);

namespace SarahAiClient\Infrastructure;

use SarahAiClient\DB\QuickQuestionsTable;

class QuickQuestionsRepository
{
    private function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . QuickQuestionsTable::TABLE;
    }

    public function all(): array
    {
        global $wpdb;
        $rows = $wpdb->get_results(
            "SELECT * FROM {$this->table()} ORDER BY sort_order ASC, id ASC",
            ARRAY_A
        );
        return is_array($rows) ? $rows : [];
    }

    public function allEnabled(): array
    {
        global $wpdb;
        $rows = $wpdb->get_results(
            "SELECT id, question FROM {$this->table()} WHERE is_enabled = 1 ORDER BY sort_order ASC, id ASC",
            ARRAY_A
        );
        return is_array($rows) ? $rows : [];
    }

    public function find(int $id): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table()} WHERE id = %d", $id),
            ARRAY_A
        );
        return $row ?: null;
    }

    public function create(string $question): int
    {
        global $wpdb;
        $now = current_time('mysql');
        $wpdb->insert($this->table(), [
            'question'   => $question,
            'sort_order' => 0,
            'is_enabled' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $id = (int) $wpdb->insert_id;
        if ($id > 0) {
            $wpdb->update($this->table(), ['sort_order' => $id], ['id' => $id]);
        }
        return $id;
    }

    public function update(int $id, string $question, bool $enabled): void
    {
        global $wpdb;
        $wpdb->update(
            $this->table(),
            [
                'question'   => $question,
                'is_enabled' => $enabled ? 1 : 0,
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $id],
            ['%s', '%d', '%s'],
            ['%d']
        );
    }

    public function delete(int $id): void
    {
        global $wpdb;
        $wpdb->delete($this->table(), ['id' => $id], ['%d']);
    }
}
