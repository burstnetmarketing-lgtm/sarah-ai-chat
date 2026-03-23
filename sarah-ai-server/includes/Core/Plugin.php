<?php

declare(strict_types=1);

namespace SarahAiServer\Core;

use SarahAiServer\Admin\AdminMenu;
use SarahAiServer\Admin\DashboardPage;
use SarahAiServer\Api\LogController;
use SarahAiServer\Api\MenuItemsController;
use SarahAiServer\DB\AgentTable;
use SarahAiServer\DB\MenuTable;
use SarahAiServer\DB\PlanTable;
use SarahAiServer\DB\SettingsTable;
use SarahAiServer\DB\SiteAgentTable;
use SarahAiServer\DB\SiteTable;
use SarahAiServer\DB\SiteTokenTable;
use SarahAiServer\DB\TenantTable;
use SarahAiServer\DB\AccountKeyTable;
use SarahAiServer\DB\KnowledgeResourceTable;
use SarahAiServer\DB\KnowledgeChunksTable;
use SarahAiServer\DB\KnowledgeResourceTypeTable;
use SarahAiServer\DB\PlanAgentTable;
use SarahAiServer\DB\UsageLogTable;
use SarahAiServer\DB\ChatSessionTable;
use SarahAiServer\DB\ChatMessageTable;
use SarahAiServer\Api\ChatController;
use SarahAiServer\DB\UserTenantTable;
use SarahAiServer\Api\AccountKeyController;
use SarahAiServer\Api\AgentController;
use SarahAiServer\Api\KnowledgeController;
use SarahAiServer\Api\PlanController;
use SarahAiServer\Api\QuickSetupController;
use SarahAiServer\Api\SiteController;
use SarahAiServer\Api\SiteTokenController;
use SarahAiServer\Api\TenantController;
use SarahAiServer\Api\UserTenantController;
use SarahAiServer\Api\UsageController;
use SarahAiServer\Api\PlatformSettingsController;
use SarahAiServer\Api\KnowledgeFieldsController;
use SarahAiServer\Api\KnowledgeProcessingController;
use SarahAiServer\Api\ClientKnowledgeController;
use SarahAiServer\Api\ClientSiteController;
use SarahAiServer\DB\SiteApiKeyTable;
use SarahAiServer\Core\KbSyncJob;
use SarahAiServer\Infrastructure\MenuRepository;
use SarahAiServer\Infrastructure\SettingsRepository;

class Plugin
{
    /** Bump this constant whenever a schema change or seed change is made. */
    private const DB_VERSION = '0.1.24';

    public static function boot(): void
    {
        // Schema, seeds, and migrations only run when DB_VERSION changes.
        // Avoids 20+ dbDelta() calls on every WordPress request.
        if (get_option('sarah_ai_server_db_version') !== self::DB_VERSION) {
            self::runSchemaUpgrade();
            Seeder::run();
            update_option('sarah_ai_server_db_version', self::DB_VERSION, false);
        }

        // Register background job hooks (must run on every boot so cron callbacks are found).
        KbSyncJob::register();

        // Wire logger on/off from DB setting and register PHP error hooks
        $settingsRepo   = new SettingsRepository();
        $loggingEnabled = $settingsRepo->get('logging_enabled', '1', 'platform') === '1';
        Logger::setEnabled($loggingEnabled);
        Logger::registerShutdownHandler();

        $menuRepo   = new MenuRepository();
        $controller = new MenuItemsController($menuRepo);
        add_action('rest_api_init', [$controller, 'registerRoutes']);
        add_action('rest_api_init', [(new LogController()), 'registerRoutes']);
        add_action('rest_api_init', [(new KnowledgeController()), 'registerRoutes']);
        add_action('rest_api_init', [(new KnowledgeProcessingController()), 'registerRoutes']);
        add_action('rest_api_init', [(new KnowledgeFieldsController()), 'registerRoutes']);
        add_action('rest_api_init', [(new TenantController()), 'registerRoutes']);
        add_action('rest_api_init', [(new UserTenantController()), 'registerRoutes']);
        add_action('rest_api_init', [(new SiteController()), 'registerRoutes']);
        add_action('rest_api_init', [(new AccountKeyController()), 'registerRoutes']);
        add_action('rest_api_init', [(new SiteTokenController()), 'registerRoutes']);
        add_action('rest_api_init', [(new AgentController()), 'registerRoutes']);
        add_action('rest_api_init', [(new PlanController()), 'registerRoutes']);
        add_action('rest_api_init', [(new QuickSetupController()), 'registerRoutes']);
        add_action('rest_api_init', [(new ChatController()), 'registerRoutes']);
        add_action('rest_api_init', [(new \SarahAiServer\Api\SessionController()), 'registerRoutes']);
        add_action('rest_api_init', [(new UsageController()), 'registerRoutes']);
        add_action('rest_api_init', [(new PlatformSettingsController()), 'registerRoutes']);
        add_action('rest_api_init', [(new ClientKnowledgeController()), 'registerRoutes']);
        add_action('rest_api_init', [(new ClientSiteController()), 'registerRoutes']);
        add_action('rest_api_init', [(new \SarahAiServer\Api\WhmcsTestController()), 'registerRoutes']);

        if (! is_admin()) {
            return;
        }
        (new AdminMenu(new DashboardPage()))->register();
    }

    /**
     * Creates or upgrades all database tables.
     * Called only when DB_VERSION has changed — not on every request.
     */
    private static function runSchemaUpgrade(): void
    {
        SettingsTable::create();
        MenuTable::create();
        TenantTable::create();
        UserTenantTable::create();
        SiteTable::create();
        SiteTokenTable::create();
        AgentTable::create();
        SiteAgentTable::create();
        PlanTable::create();
        UsageLogTable::create();
        KnowledgeResourceTable::create();
        KnowledgeChunksTable::create();
        KnowledgeResourceTypeTable::create();
        AccountKeyTable::create();
        PlanAgentTable::create();
        ChatSessionTable::create();
        ChatMessageTable::create();
        SiteApiKeyTable::create();

        (new MenuRepository())->ensureCoreItems();
    }

}
