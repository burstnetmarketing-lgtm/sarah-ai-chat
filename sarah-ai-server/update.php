<?php

if (! defined('ABSPATH')) {
    exit;
}

require_once SARAH_AI_SERVER_PATH . 'plugin-update-checker/load-v5p6.php';

YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
    'https://https://burstpartners.com.au/updates/?action=get_metadata&slug=sarah-ai-server',
    SARAH_AI_SERVER_FILE,
    'sarah-ai-server'
);
