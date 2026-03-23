<?php

namespace SarahAiClient\Core;

use SarahAiClient\DB\MenuTable;
use SarahAiClient\DB\QuickQuestionsTable;
use SarahAiClient\DB\SettingsTable;

class Deactivator
{
    public static function deactivate(): void
    {
        global $wpdb;

        $tables = [
            $wpdb->prefix . SettingsTable::TABLE,
            $wpdb->prefix . MenuTable::TABLE,
            $wpdb->prefix . QuickQuestionsTable::TABLE,
        ];

        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS {$table}");
        }

        delete_option('sarah_ai_client_db_version');
    }
}
