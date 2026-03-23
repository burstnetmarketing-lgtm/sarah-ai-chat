<?php

/**
 * Sarah AI Client — Deploy-time configuration.
 *
 * Fill in SARAH_AI_CLIENT_SERVER_URL before distributing the plugin.
 * When set, the Quick Setup wizard will not ask the user for a Server URL
 * and will use this value automatically.
 *
 * Leave empty ('') to let the administrator enter the URL manually.
 */

if (! defined('ABSPATH')) {
    exit;
}

if (! defined('SARAH_AI_CLIENT_SERVER_URL')) {
    define('SARAH_AI_CLIENT_SERVER_URL', 'http://sarah.burstpartners.com.au/wp-json');
}
