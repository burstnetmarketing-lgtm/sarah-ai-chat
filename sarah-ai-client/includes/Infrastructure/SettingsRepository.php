<?php

declare(strict_types=1);

namespace SarahAiClient\Infrastructure;

use SarahAiClient\DB\SettingsTable;

class SettingsRepository
{
    public const APPEARANCE_KEYS = [
        // General / Layout
        'widget_width', 'widget_height', 'widget_position',
        // Launcher Button
        'launcher_bg_color', 'launcher_icon', 'launcher_image',
        // Header
        'header_bg_color', 'header_text', 'header_text_color',
        'header_font_family', 'close_btn_color', 'close_btn_size',
        // Messages / Welcome
        'welcome_message', 'msg_area_bg',
        'bubble_user_bg', 'bubble_user_text',
        'bubble_ai_bg',  'bubble_ai_text',
        // Input & Send
        'send_bg_color',
        // Quick Questions
        'qq_border_color', 'qq_text_color', 'qq_hover_bg', 'qq_border_radius',
    ];

    public const APPEARANCE_DEFAULTS = [
        'widget_width'       => '360',
        'widget_height'      => '500',
        'widget_position'    => 'right',
        'launcher_bg_color'  => '#2563eb',
        'launcher_icon'      => 'bubble',
        'launcher_image'     => '',
        'header_bg_color'    => '#2563eb',
        'header_text'        => 'Sarah Assistant',
        'header_text_color'  => '#ffffff',
        'header_font_family' => 'inherit',
        'close_btn_color'    => '#ffffff',
        'close_btn_size'     => '16',
        'welcome_message'    => 'Hi 👋 How can I help you today?',
        'msg_area_bg'        => '#f8fafc',
        'bubble_user_bg'     => '#2563eb',
        'bubble_user_text'   => '#ffffff',
        'bubble_ai_bg'       => '#ffffff',
        'bubble_ai_text'     => '#1e293b',
        'send_bg_color'      => '#2563eb',
        'qq_border_color'    => '#2563eb',
        'qq_text_color'      => '#2563eb',
        'qq_hover_bg'        => '#2563eb',
        'qq_border_radius'   => '20',
    ];

    /* ── Generic get/set (used for widget_enabled etc.) ───────── */

    public function get(string $key, string $default = ''): string
    {
        global $wpdb;
        $table = $wpdb->prefix . SettingsTable::TABLE;
        $value = $wpdb->get_var($wpdb->prepare("SELECT setting_value FROM {$table} WHERE setting_key = %s", $key));
        return is_string($value) ? $value : $default;
    }

    public function set(string $key, string $value): void
    {
        global $wpdb;
        $table = $wpdb->prefix . SettingsTable::TABLE;
        $now   = current_time('mysql');
        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$table} (setting_key, setting_value, created_at, updated_at)
             VALUES (%s, %s, %s, %s)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = VALUES(updated_at)",
            $key, $value, $now, $now
        ));
    }

    /* ── Appearance (draft / publish) ─────────────────────────── */

    public function ensureAppearanceDefaults(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . SettingsTable::TABLE;
        $now   = current_time('mysql');
        foreach (self::APPEARANCE_KEYS as $key) {
            $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE setting_key = %s", $key));
            if ($exists) {
                continue;
            }
            $wpdb->insert($table, [
                'setting_key'     => $key,
                'setting_value'   => self::APPEARANCE_DEFAULTS[$key],
                'published_at'    => $now,
                'draft_value'     => null,
                'draft_updated_at'=> null,
                'created_at'      => $now,
                'updated_at'      => $now,
            ]);
        }
    }

    public function getAllAppearance(): array
    {
        global $wpdb;
        $table      = $wpdb->prefix . SettingsTable::TABLE;
        $draft      = [];
        $published  = [];
        $canPublish = false;

        foreach (self::APPEARANCE_KEYS as $key) {
            $row = $wpdb->get_row(
                $wpdb->prepare("SELECT setting_value, published_at, draft_value, draft_updated_at FROM {$table} WHERE setting_key = %s", $key),
                ARRAY_A
            );
            $pub              = ($row && $row['setting_value'] !== null) ? $row['setting_value'] : self::APPEARANCE_DEFAULTS[$key];
            $dft              = ($row && $row['draft_value'] !== null) ? $row['draft_value'] : $pub;
            $published[$key]  = $pub;
            $draft[$key]      = $dft;

            if ($row && $row['draft_updated_at'] !== null) {
                if ($row['published_at'] === null || $row['draft_updated_at'] > $row['published_at']) {
                    $canPublish = true;
                }
            }
        }

        return [
            'draft'       => $draft,
            'published'   => $published,
            'can_publish' => $canPublish,
        ];
    }

    public function saveDraft(array $values): void
    {
        global $wpdb;
        $table = $wpdb->prefix . SettingsTable::TABLE;
        $now   = current_time('mysql');

        foreach (self::APPEARANCE_KEYS as $key) {
            if (! array_key_exists($key, $values)) {
                continue;
            }
            $value   = (string) $values[$key];
            $exists  = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE setting_key = %s", $key));
            if ($exists) {
                $wpdb->update(
                    $table,
                    ['draft_value' => $value, 'draft_updated_at' => $now, 'updated_at' => $now],
                    ['setting_key' => $key]
                );
            } else {
                $wpdb->insert($table, [
                    'setting_key'      => $key,
                    'setting_value'    => self::APPEARANCE_DEFAULTS[$key],
                    'published_at'     => null,
                    'draft_value'      => $value,
                    'draft_updated_at' => $now,
                    'created_at'       => $now,
                    'updated_at'       => $now,
                ]);
            }
        }
    }

    public function publish(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . SettingsTable::TABLE;
        $now   = current_time('mysql');

        foreach (self::APPEARANCE_KEYS as $key) {
            $row = $wpdb->get_row(
                $wpdb->prepare("SELECT setting_value, draft_value FROM {$table} WHERE setting_key = %s", $key),
                ARRAY_A
            );
            if (! $row) {
                continue;
            }
            $newValue = ($row['draft_value'] !== null) ? $row['draft_value'] : $row['setting_value'];
            $wpdb->update(
                $table,
                ['setting_value' => $newValue, 'published_at' => $now, 'updated_at' => $now],
                ['setting_key' => $key]
            );
        }
    }

    public function discardDraft(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . SettingsTable::TABLE;
        $now   = current_time('mysql');

        foreach (self::APPEARANCE_KEYS as $key) {
            $row = $wpdb->get_row(
                $wpdb->prepare("SELECT setting_value, published_at FROM {$table} WHERE setting_key = %s", $key),
                ARRAY_A
            );
            if (! $row) {
                continue;
            }
            $wpdb->update(
                $table,
                [
                    'draft_value'      => $row['setting_value'],
                    'draft_updated_at' => $row['published_at'],
                    'updated_at'       => $now,
                ],
                ['setting_key' => $key]
            );
        }
    }

    public function getPublishedSettings(): array
    {
        $d       = self::APPEARANCE_DEFAULTS;
        $result  = [];
        foreach (self::APPEARANCE_KEYS as $key) {
            $result[$key] = $this->get($key, $d[$key]);
        }
        return $result;
    }
}
