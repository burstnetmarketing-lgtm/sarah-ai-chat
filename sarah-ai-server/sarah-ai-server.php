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
require_once SARAH_AI_SERVER_PATH . 'includes/Core/Helpers.php';

// DB Tables
require_once SARAH_AI_SERVER_PATH . 'includes/DB/MenuTable.php';
require_once SARAH_AI_SERVER_PATH . 'includes/DB/SettingsTable.php';
require_once SARAH_AI_SERVER_PATH . 'includes/DB/TenantTable.php';
require_once SARAH_AI_SERVER_PATH . 'includes/DB/UserTenantTable.php';
require_once SARAH_AI_SERVER_PATH . 'includes/DB/SiteTable.php';
require_once SARAH_AI_SERVER_PATH . 'includes/DB/SiteTokenTable.php';
require_once SARAH_AI_SERVER_PATH . 'includes/DB/AgentTable.php';
require_once SARAH_AI_SERVER_PATH . 'includes/DB/SiteAgentTable.php';
require_once SARAH_AI_SERVER_PATH . 'includes/DB/PlanTable.php';
require_once SARAH_AI_SERVER_PATH . 'includes/DB/EmailTemplateTable.php';
require_once SARAH_AI_SERVER_PATH . 'includes/DB/UsageLogTable.php';
require_once SARAH_AI_SERVER_PATH . 'includes/DB/KnowledgeResourceTable.php';
require_once SARAH_AI_SERVER_PATH . 'includes/DB/AccountKeyTable.php';
require_once SARAH_AI_SERVER_PATH . 'includes/DB/PlanAgentTable.php';
require_once SARAH_AI_SERVER_PATH . 'includes/DB/ChatSessionTable.php';
require_once SARAH_AI_SERVER_PATH . 'includes/DB/ChatMessageTable.php';
require_once SARAH_AI_SERVER_PATH . 'includes/DB/SiteApiKeyTable.php';
require_once SARAH_AI_SERVER_PATH . 'includes/DB/KnowledgeChunksTable.php';
require_once SARAH_AI_SERVER_PATH . 'includes/DB/KnowledgeResourceTypeTable.php';

// Infrastructure
require_once SARAH_AI_SERVER_PATH . 'includes/Infrastructure/MenuRepository.php';
require_once SARAH_AI_SERVER_PATH . 'includes/Infrastructure/SettingsRepository.php';
require_once SARAH_AI_SERVER_PATH . 'includes/Infrastructure/TenantRepository.php';
require_once SARAH_AI_SERVER_PATH . 'includes/Infrastructure/UserTenantRepository.php';
require_once SARAH_AI_SERVER_PATH . 'includes/Infrastructure/SiteRepository.php';
require_once SARAH_AI_SERVER_PATH . 'includes/Infrastructure/SiteTokenRepository.php';
require_once SARAH_AI_SERVER_PATH . 'includes/Infrastructure/AgentRepository.php';
require_once SARAH_AI_SERVER_PATH . 'includes/Infrastructure/PlanRepository.php';
require_once SARAH_AI_SERVER_PATH . 'includes/Infrastructure/EmailTemplateRepository.php';
require_once SARAH_AI_SERVER_PATH . 'includes/Infrastructure/KnowledgeResourceRepository.php';
require_once SARAH_AI_SERVER_PATH . 'includes/Infrastructure/AccountKeyRepository.php';
require_once SARAH_AI_SERVER_PATH . 'includes/Infrastructure/SiteAgentRepository.php';
require_once SARAH_AI_SERVER_PATH . 'includes/Infrastructure/WhmcsLicenseService.php';
require_once SARAH_AI_SERVER_PATH . 'includes/Infrastructure/LicenseValidator.php';
require_once SARAH_AI_SERVER_PATH . 'includes/Infrastructure/CredentialValidator.php';
require_once SARAH_AI_SERVER_PATH . 'includes/Infrastructure/PlanAgentRepository.php';
require_once SARAH_AI_SERVER_PATH . 'includes/Infrastructure/ChatSessionRepository.php';
require_once SARAH_AI_SERVER_PATH . 'includes/Infrastructure/ChatMessageRepository.php';
require_once SARAH_AI_SERVER_PATH . 'includes/Infrastructure/UsageLogRepository.php';
require_once SARAH_AI_SERVER_PATH . 'includes/Infrastructure/SiteApiKeyRepository.php';
require_once SARAH_AI_SERVER_PATH . 'includes/Infrastructure/KnowledgeChunkRepository.php';
require_once SARAH_AI_SERVER_PATH . 'includes/Infrastructure/KnowledgeResourceTypeRepository.php';

// Processing
require_once SARAH_AI_SERVER_PATH . 'includes/Processing/KnowledgeFieldSchema.php';
require_once SARAH_AI_SERVER_PATH . 'includes/Processing/KnowledgePolicyFilter.php';
require_once SARAH_AI_SERVER_PATH . 'includes/Processing/KnowledgeTextExtractor.php';
require_once SARAH_AI_SERVER_PATH . 'includes/Processing/KnowledgeChunker.php';
require_once SARAH_AI_SERVER_PATH . 'includes/Processing/EmbeddingService.php';
require_once SARAH_AI_SERVER_PATH . 'includes/Processing/KnowledgeProcessingService.php';
require_once SARAH_AI_SERVER_PATH . 'includes/Processing/SemanticRetriever.php';

// Runtime
require_once SARAH_AI_SERVER_PATH . 'includes/Runtime/AgentExecutorInterface.php';
require_once SARAH_AI_SERVER_PATH . 'includes/Runtime/OpenAiAgentExecutor.php';
require_once SARAH_AI_SERVER_PATH . 'includes/Runtime/RuntimeEligibilityChecker.php';
require_once SARAH_AI_SERVER_PATH . 'includes/Runtime/ChatRuntime.php';

// Admin
require_once SARAH_AI_SERVER_PATH . 'includes/Admin/DashboardPage.php';
require_once SARAH_AI_SERVER_PATH . 'includes/Admin/AdminMenu.php';

// API
require_once SARAH_AI_SERVER_PATH . 'includes/Api/MenuItemsController.php';
require_once SARAH_AI_SERVER_PATH . 'includes/Api/LogController.php';
require_once SARAH_AI_SERVER_PATH . 'includes/Api/KnowledgeController.php';
require_once SARAH_AI_SERVER_PATH . 'includes/Api/TenantController.php';
require_once SARAH_AI_SERVER_PATH . 'includes/Api/UserTenantController.php';
require_once SARAH_AI_SERVER_PATH . 'includes/Api/SiteController.php';
require_once SARAH_AI_SERVER_PATH . 'includes/Api/AccountKeyController.php';
require_once SARAH_AI_SERVER_PATH . 'includes/Api/SiteTokenController.php';
require_once SARAH_AI_SERVER_PATH . 'includes/Api/AgentController.php';
require_once SARAH_AI_SERVER_PATH . 'includes/Api/PlanController.php';
require_once SARAH_AI_SERVER_PATH . 'includes/Api/QuickSetupController.php';
require_once SARAH_AI_SERVER_PATH . 'includes/Api/ChatController.php';
require_once SARAH_AI_SERVER_PATH . 'includes/Api/SessionController.php';
require_once SARAH_AI_SERVER_PATH . 'includes/Api/UsageController.php';
require_once SARAH_AI_SERVER_PATH . 'includes/Api/PlatformSettingsController.php';
require_once SARAH_AI_SERVER_PATH . 'includes/Api/KnowledgeProcessingController.php';
require_once SARAH_AI_SERVER_PATH . 'includes/Api/KnowledgeFieldsController.php';
require_once SARAH_AI_SERVER_PATH . 'includes/Api/ClientKnowledgeController.php';
require_once SARAH_AI_SERVER_PATH . 'includes/Api/ClientSiteController.php';

// Core
require_once SARAH_AI_SERVER_PATH . 'includes/Core/Logger.php';
require_once SARAH_AI_SERVER_PATH . 'includes/Core/Seeder.php';
require_once SARAH_AI_SERVER_PATH . 'includes/Core/Activator.php';
require_once SARAH_AI_SERVER_PATH . 'includes/Core/Plugin.php';

register_activation_hook(SARAH_AI_SERVER_FILE, ['SarahAiServer\\Core\\Activator', 'activate']);
add_action('plugins_loaded', ['SarahAiServer\\Core\\Plugin', 'boot']);
