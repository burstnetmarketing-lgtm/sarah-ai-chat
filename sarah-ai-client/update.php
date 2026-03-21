<?php

if (! defined('ABSPATH')) {
    exit;
}

require_once SARAH_AI_CLIENT_PATH . 'plugin-update-checker/load-v5p6.php';

YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
    'https://example.com/updates/?action=get_metadata&slug=sarah-ai-client',
    SARAH_AI_CLIENT_FILE,
    'sarah-ai-client'
);
