<?php
/**
 * Plugin Name: Sarah AI Server
 * Description: Server-side management for Sarah AI — plans, access control, and client usage accounting.
 * Version: 0.1.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Sarah AI
 * Text Domain: sarah-ai-server
 */

if (! defined('ABSPATH')) {
    exit;
}

define('SARAH_AI_SERVER_VERSION', '0.1.0');
define('SARAH_AI_SERVER_FILE', __FILE__);
define('SARAH_AI_SERVER_PATH', plugin_dir_path(__FILE__));
define('SARAH_AI_SERVER_URL', plugin_dir_url(__FILE__));

require_once SARAH_AI_SERVER_PATH . 'update.php';

require_once SARAH_AI_SERVER_PATH . 'includes/DB/MenuTable.php';
require_once SARAH_AI_SERVER_PATH . 'includes/DB/SettingsTable.php';
require_once SARAH_AI_SERVER_PATH . 'includes/Infrastructure/MenuRepository.php';
require_once SARAH_AI_SERVER_PATH . 'includes/Infrastructure/SettingsRepository.php';
require_once SARAH_AI_SERVER_PATH . 'includes/Admin/DashboardPage.php';
require_once SARAH_AI_SERVER_PATH . 'includes/Admin/AdminMenu.php';
require_once SARAH_AI_SERVER_PATH . 'includes/Api/MenuItemsController.php';
require_once SARAH_AI_SERVER_PATH . 'includes/Api/LogController.php';
require_once SARAH_AI_SERVER_PATH . 'includes/Core/Logger.php';
require_once SARAH_AI_SERVER_PATH . 'includes/Core/Activator.php';
require_once SARAH_AI_SERVER_PATH . 'includes/Core/Plugin.php';

register_activation_hook(SARAH_AI_SERVER_FILE, ['SarahAiServer\\Core\\Activator', 'activate']);
add_action('plugins_loaded', ['SarahAiServer\\Core\\Plugin', 'boot']);
