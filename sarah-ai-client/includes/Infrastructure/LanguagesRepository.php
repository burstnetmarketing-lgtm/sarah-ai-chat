<?php

declare(strict_types=1);

namespace SarahAiClient\Infrastructure;

use SarahAiClient\DB\LanguagesTable;

class LanguagesRepository
{
    private const DEFAULTS = [
        ['code' => 'en', 'label' => 'English',  'message' => 'Hi! Please respond to me in English.',            'sort_order' => 1],
        ['code' => 'fa', 'label' => 'فارسی',    'message' => 'سلام! لطفاً از این لحظه فقط به فارسی پاسخ بده.', 'sort_order' => 2],
        ['code' => 'ar', 'label' => 'العربية',  'message' => 'مرحباً! تحدث معي باللغة العربية من فضلك.',       'sort_order' => 3],
        ['code' => 'zh', 'label' => '中文',     'message' => 'Hi! Please respond to me in Mandarin Chinese.',   'sort_order' => 4],
    ];

    private function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . LanguagesTable::TABLE;
    }

    public function seedDefaults(): void
    {
        global $wpdb;
        $now = current_time('mysql');

        foreach (self::DEFAULTS as $row) {
            $exists = $wpdb->get_var(
                $wpdb->prepare("SELECT id FROM {$this->table()} WHERE code = %s", $row['code'])
            );
            if ($exists) {
                continue;
            }
            $wpdb->insert($this->table(), [
                'code'       => $row['code'],
                'label'      => $row['label'],
                'message'    => $row['message'],
                'sort_order' => $row['sort_order'],
                'is_enabled' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function allEnabled(): array
    {
        global $wpdb;
        $rows = $wpdb->get_results(
            "SELECT code, label, message FROM {$this->table()} WHERE is_enabled = 1 ORDER BY sort_order ASC, id ASC",
            ARRAY_A
        );
        return is_array($rows) ? $rows : [];
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
}
