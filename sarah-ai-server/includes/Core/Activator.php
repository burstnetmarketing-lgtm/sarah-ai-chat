<?php

namespace SarahAiServer\Core;

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
use SarahAiServer\DB\UserTenantTable;
use SarahAiServer\Infrastructure\MenuRepository;

class Activator
{
    public static function activate(): void
    {
        // Infrastructure tables
        SettingsTable::create();
        MenuTable::create();

        // Domain tables
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

        // Seed core menu items and baseline data
        (new MenuRepository())->ensureCoreItems();
        Seeder::run();
    }
}
