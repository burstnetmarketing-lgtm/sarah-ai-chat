<?php

namespace SarahAiServer\Infrastructure;

use SarahAiServer\DB\SettingsTable;

class SettingsRepository
{
    public function get(string $key, string $default = '', string $group = 'general'): string
    {
        global $wpdb;
        $table = $wpdb->prefix . SettingsTable::TABLE;
        $value = $wpdb->get_var($wpdb->prepare(
            "SELECT setting_value FROM {$table} WHERE setting_key = %s AND setting_group = %s",
            $key,
            $group
        ));
        return is_string($value) ? $value : $default;
    }

    public function set(string $key, string $value, string $group = 'general'): void
    {
        global $wpdb;
        $table = $wpdb->prefix . SettingsTable::TABLE;
        $now   = current_time('mysql');
        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$table} (setting_group, setting_key, setting_value, created_at, updated_at)
             VALUES (%s, %s, %s, %s, %s)
             ON DUPLICATE KEY UPDATE setting_group = VALUES(setting_group), setting_value = VALUES(setting_value), updated_at = VALUES(updated_at)",
            $group,
            $key,
            $value,
            $now,
            $now
        ));
    }

    /** Returns all settings within a group as key => value pairs. */
    public function getGroup(string $group): array
    {
        global $wpdb;
        $table = $wpdb->prefix . SettingsTable::TABLE;
        $rows  = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT setting_key, setting_value FROM {$table} WHERE setting_group = %s",
                $group
            ),
            ARRAY_A
        );
        if (! is_array($rows)) {
            return [];
        }
        $result = [];
        foreach ($rows as $row) {
            $result[$row['setting_key']] = $row['setting_value'];
        }
        return $result;
    }
}
