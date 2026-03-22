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
use SarahAiServer\DB\KnowledgeResourceTable;
use SarahAiServer\DB\UsageLogTable;
use SarahAiServer\DB\UserTenantTable;
use SarahAiServer\Api\KnowledgeController;
use SarahAiServer\Infrastructure\MenuRepository;

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

        // Seed baseline data (idempotent)
        Seeder::run();

        $menuRepo = new MenuRepository();
        $menuRepo->ensureCoreItems();

        $controller = new MenuItemsController($menuRepo);
        add_action('rest_api_init', [$controller, 'registerRoutes']);
        add_action('rest_api_init', [(new LogController()), 'registerRoutes']);
        add_action('rest_api_init', [(new KnowledgeController()), 'registerRoutes']);

        if (! is_admin()) {
            return;
        }
        (new AdminMenu(new DashboardPage()))->register();
    }
}
