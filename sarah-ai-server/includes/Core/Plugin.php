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
use SarahAiServer\Api\SubscriptionController;
use SarahAiServer\Api\SiteController;
use SarahAiServer\Api\SiteTokenController;
use SarahAiServer\Api\TenantController;
use SarahAiServer\Api\UserTenantController;
use SarahAiServer\Api\UsageController;
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
        PlanAgentTable::create();
        ChatSessionTable::create();
        ChatMessageTable::create();

        // Seed baseline data (idempotent)
        Seeder::run();

        // Fill UUIDs for any existing rows that predate the uuid column
        self::migrateUuids();

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
        add_action('rest_api_init', [(new PlanController()), 'registerRoutes']);
        add_action('rest_api_init', [(new SubscriptionController()), 'registerRoutes']);
        add_action('rest_api_init', [(new ChatController()), 'registerRoutes']);
        add_action('rest_api_init', [(new \SarahAiServer\Api\SessionController()), 'registerRoutes']);
        add_action('rest_api_init', [(new UsageController()), 'registerRoutes']);

        if (! is_admin()) {
            return;
        }
        (new AdminMenu(new DashboardPage()))->register();
    }

    /**
     * Backfill UUID for any rows created before the uuid column was added.
     * Safe to call on every boot — only touches rows where uuid IS NULL.
     */
    private static function migrateUuids(): void
    {
        global $wpdb;

        $tables = [
            'sarah_ai_server_tenants',
            'sarah_ai_server_sites',
            'sarah_ai_server_account_keys',
            'sarah_ai_server_site_tokens',
            'sarah_ai_server_knowledge_resources',
        ];

        foreach ($tables as $tableName) {
            $table = $wpdb->prefix . $tableName;
            // Check column exists first (safe for fresh installs)
            $col = $wpdb->get_var("SHOW COLUMNS FROM {$table} LIKE 'uuid'");
            if (! $col) {
                continue;
            }
            $ids = $wpdb->get_col("SELECT id FROM {$table} WHERE uuid IS NULL OR uuid = '' LIMIT 100");
            foreach ($ids as $id) {
                $wpdb->update($table, ['uuid' => sarah_ai_uuid()], ['id' => (int) $id]);
            }
        }
    }
}
