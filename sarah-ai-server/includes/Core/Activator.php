<?php

namespace SarahAiServer\Core;

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
use SarahAiServer\DB\PlanAgentTable;
use SarahAiServer\DB\UsageLogTable;
use SarahAiServer\DB\ChatSessionTable;
use SarahAiServer\DB\ChatMessageTable;
use SarahAiServer\DB\SiteApiKeyTable;
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
        UsageLogTable::create();
        KnowledgeResourceTable::create();
        AccountKeyTable::create();
        PlanAgentTable::create();
        ChatSessionTable::create();
        ChatMessageTable::create();
        SiteApiKeyTable::create();

        // Seed core menu items and baseline data
        (new MenuRepository())->ensureCoreItems();
        Seeder::run();

        // Inject CORS rules into WordPress root .htaccess
        self::insertHtaccessRules();
    }

    public static function insertHtaccessRules(): void
    {
        $htaccess = get_home_path() . '.htaccess';
        if (! file_exists($htaccess) || ! is_writable($htaccess)) {
            return;
        }

        $rules = [
            '<IfModule mod_headers.c>',
            '    Header always set Access-Control-Allow-Origin "*"',
            '    Header always set Access-Control-Allow-Methods "GET, POST, PUT, PATCH, DELETE, OPTIONS"',
            '    Header always set Access-Control-Allow-Headers "Content-Type, Authorization, X-WP-Nonce, X-Sarah-Platform-Key"',
            '</IfModule>',
            '<IfModule mod_rewrite.c>',
            '    RewriteEngine On',
            '    RewriteCond %{REQUEST_METHOD} OPTIONS',
            '    RewriteRule .* - [R=200,L]',
            '</IfModule>',
        ];

        insert_with_markers($htaccess, 'Sarah AI Server CORS', $rules);
    }
}
