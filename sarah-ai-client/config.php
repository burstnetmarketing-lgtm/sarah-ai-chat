<?php

/**
 * Sarah AI Client — Deploy-time configuration.
 *
 * Set SARAH_AI_CLIENT_SERVER_URL and SARAH_AI_CLIENT_PLATFORM_KEY before distributing.
 * When set, the Quick Setup wizard hides those fields and uses these values automatically.
 *
 * Leave empty ('') to let the administrator enter the value manually.
 */

if (! defined('ABSPATH')) {
    exit;
}

if (! defined('SARAH_AI_CLIENT_SERVER_URL')) {
    define('SARAH_AI_CLIENT_SERVER_URL', 'https://burstpartners.com.au/sarah/wp-json');
}

if (! defined('SARAH_AI_CLIENT_PLATFORM_KEY')) {
    define('SARAH_AI_CLIENT_PLATFORM_KEY', 'www.BurstNET.com.au');
}
