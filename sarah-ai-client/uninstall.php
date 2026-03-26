<?php
if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

$tables = [
    $wpdb->prefix . 'sarah_ai_client_settings',
    $wpdb->prefix . 'sarah_ai_client_menu_items',
    $wpdb->prefix . 'sarah_ai_client_quick_questions',
    $wpdb->prefix . 'sarah_ai_client_languages',
];

foreach ($tables as $table) {
    $wpdb->query("DROP TABLE IF EXISTS {$table}");
}

delete_option('sarah_ai_client_db_version');
