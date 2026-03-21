<?php
/**
 * Plugin Name: Sarah AI Client
 * Description: AI-powered chat widget for client websites.
 * Version: 0.1.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Sarah AI
 * Text Domain: sarah-ai-client
 */

if (! defined('ABSPATH')) {
    exit;
}

define('SARAH_AI_CLIENT_VERSION', '0.1.0');
define('SARAH_AI_CLIENT_FILE', __FILE__);
define('SARAH_AI_CLIENT_PATH', plugin_dir_path(__FILE__));
define('SARAH_AI_CLIENT_URL', plugin_dir_url(__FILE__));

require_once SARAH_AI_CLIENT_PATH . 'update.php';

require_once SARAH_AI_CLIENT_PATH . 'includes/DB/MenuTable.php';
require_once SARAH_AI_CLIENT_PATH . 'includes/DB/SettingsTable.php';
require_once SARAH_AI_CLIENT_PATH . 'includes/DB/QuickQuestionsTable.php';
require_once SARAH_AI_CLIENT_PATH . 'includes/Infrastructure/MenuRepository.php';
require_once SARAH_AI_CLIENT_PATH . 'includes/Infrastructure/SettingsRepository.php';
require_once SARAH_AI_CLIENT_PATH . 'includes/Infrastructure/QuickQuestionsRepository.php';
require_once SARAH_AI_CLIENT_PATH . 'includes/Admin/DashboardPage.php';
require_once SARAH_AI_CLIENT_PATH . 'includes/Admin/AdminMenu.php';
require_once SARAH_AI_CLIENT_PATH . 'includes/Api/MenuItemsController.php';
require_once SARAH_AI_CLIENT_PATH . 'includes/Api/LogController.php';
require_once SARAH_AI_CLIENT_PATH . 'includes/Api/SettingsController.php';
require_once SARAH_AI_CLIENT_PATH . 'includes/Api/QuickQuestionsController.php';
require_once SARAH_AI_CLIENT_PATH . 'includes/Api/AppearanceController.php';
require_once SARAH_AI_CLIENT_PATH . 'includes/Core/Logger.php';
require_once SARAH_AI_CLIENT_PATH . 'includes/Core/Activator.php';
require_once SARAH_AI_CLIENT_PATH . 'includes/Core/Plugin.php';

register_activation_hook(SARAH_AI_CLIENT_FILE, ['SarahAiClient\\Core\\Activator', 'activate']);
add_action('plugins_loaded', ['SarahAiClient\\Core\\Plugin', 'boot']);
