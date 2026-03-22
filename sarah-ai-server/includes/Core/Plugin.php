<?php

declare(strict_types=1);

namespace SarahAiServer\Core;

use SarahAiServer\Admin\AdminMenu;
use SarahAiServer\Admin\DashboardPage;
use SarahAiServer\Api\LogController;
use SarahAiServer\Api\MenuItemsController;
use SarahAiServer\DB\AgentTable;
use SarahAiServer\DB\EmailTemplateTable;
use SarahAiServer\DB\MenuTable;
use SarahAiServer\DB\PlanTable;
use SarahAiServer\DB\SettingsTable;
use SarahAiServer\DB\SiteAgentTable;
use SarahAiServer\DB\SiteTable;
use SarahAiServer\DB\SiteTokenTable;
use SarahAiServer\DB\SubscriptionTable;
use SarahAiServer\DB\TenantTable;
use SarahAiServer\DB\AccountKeyTable;
use SarahAiServer\DB\KnowledgeResourceTable;
use SarahAiServer\DB\UsageLogTable;
use SarahAiServer\DB\UserTenantTable;
use SarahAiServer\Api\AccountKeyController;
use SarahAiServer\Api\AgentController;
use SarahAiServer\Api\KnowledgeController;
use SarahAiServer\Api\SiteController;
use SarahAiServer\Api\SiteTokenController;
use SarahAiServer\Api\TenantController;
use SarahAiServer\Api\UserTenantController;
use SarahAiServer\Infrastructure\MenuRepository;
use SarahAiServer\Infrastructure\SettingsRepository;

class Plugin
{
    public static function boot(): void
    {
        // Ensure all tables exist (safe to run on every boot via dbDelta)
        SettingsTable::create();
        MenuTable::create();
        TenantTable::create();
        UserTenantTable::create();
        SiteTable::create();
        SiteTokenTable::create();
        AgentTable::create();
        SiteAgentTable::create();
        PlanTable::create();
        SubscriptionTable::create();
        EmailTemplateTable::create();
        UsageLogTable::create();
        KnowledgeResourceTable::create();
        AccountKeyTable::create();

        // Seed baseline data (idempotent)
        Seeder::run();

        // Wire logger on/off from DB setting and register PHP error hooks
        $settingsRepo   = new SettingsRepository();
        $loggingEnabled = $settingsRepo->get('logging_enabled', '1', 'platform') === '1';
        Logger::setEnabled($loggingEnabled);
        Logger::registerShutdownHandler();

        $menuRepo = new MenuRepository();
        $menuRepo->ensureCoreItems();

        $controller = new MenuItemsController($menuRepo);
        add_action('rest_api_init', [$controller, 'registerRoutes']);
        add_action('rest_api_init', [(new LogController()), 'registerRoutes']);
        add_action('rest_api_init', [(new KnowledgeController()), 'registerRoutes']);
        add_action('rest_api_init', [(new TenantController()), 'registerRoutes']);
        add_action('rest_api_init', [(new UserTenantController()), 'registerRoutes']);
        add_action('rest_api_init', [(new SiteController()), 'registerRoutes']);
        add_action('rest_api_init', [(new AccountKeyController()), 'registerRoutes']);
        add_action('rest_api_init', [(new SiteTokenController()), 'registerRoutes']);
        add_action('rest_api_init', [(new AgentController()), 'registerRoutes']);

        if (! is_admin()) {
            return;
        }
        (new AdminMenu(new DashboardPage()))->register();
    }
}
