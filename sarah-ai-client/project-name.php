<?php
/**
 * Plugin Name: Project Name
 * Description: Reusable admin boilerplate with Bootstrap 5 placeholder.
 * Version: 0.1.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Project Team
 * Text Domain: project-name
 */

if (! defined('ABSPATH')) {
    exit;
}

define('PROJECT_NAME_VERSION', '0.1.0');
define('PROJECT_NAME_FILE', __FILE__);
define('PROJECT_NAME_PATH', plugin_dir_path(__FILE__));
define('PROJECT_NAME_URL', plugin_dir_url(__FILE__));

require_once PROJECT_NAME_PATH . 'update.php';

require_once PROJECT_NAME_PATH . 'includes/DB/MenuTable.php';
require_once PROJECT_NAME_PATH . 'includes/DB/SettingsTable.php';
require_once PROJECT_NAME_PATH . 'includes/Infrastructure/MenuRepository.php';
require_once PROJECT_NAME_PATH . 'includes/Infrastructure/SettingsRepository.php';
require_once PROJECT_NAME_PATH . 'includes/Admin/DashboardPage.php';
require_once PROJECT_NAME_PATH . 'includes/Admin/AdminMenu.php';
require_once PROJECT_NAME_PATH . 'includes/Api/MenuItemsController.php';
require_once PROJECT_NAME_PATH . 'includes/Api/LogController.php';
require_once PROJECT_NAME_PATH . 'includes/Core/Logger.php';
require_once PROJECT_NAME_PATH . 'includes/Core/Activator.php';
require_once PROJECT_NAME_PATH . 'includes/Core/Plugin.php';

register_activation_hook(PROJECT_NAME_FILE, ['ProjectName\\Core\\Activator', 'activate']);
add_action('plugins_loaded', ['ProjectName\\Core\\Plugin', 'boot']);
