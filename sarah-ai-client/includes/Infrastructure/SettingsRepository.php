<?php

namespace SarahAiClient\Infrastructure;

use SarahAiClient\DB\SettingsTable;

class SettingsRepository
{
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
        $now = current_time('mysql');
        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$table} (setting_key, setting_value, created_at, updated_at)
             VALUES (%s, %s, %s, %s)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = VALUES(updated_at)",
            $key,
            $value,
            $now,
            $now
        ));
    }
}
