<?php

if (! defined('ABSPATH')) {
    exit;
}

require_once PROJECT_NAME_PATH . 'plugin-update-checker/load-v5p6.php';

YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
    'https://example.com/updates/?action=get_metadata&slug=project-name',
    PROJECT_NAME_FILE,
    'project-name'
);
