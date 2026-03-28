<?php

namespace SarahAiClient\Core;

use SarahAiClient\DB\LanguagesTable;
use SarahAiClient\DB\MenuTable;
use SarahAiClient\DB\QuickQuestionsTable;
use SarahAiClient\DB\SettingsTable;
use SarahAiClient\Infrastructure\LanguagesRepository;
use SarahAiClient\Infrastructure\MenuRepository;
use SarahAiClient\Infrastructure\SettingsRepository;

class Activator
{
    public static function activate(): void
    {
        SettingsTable::create();
        MenuTable::create();
        QuickQuestionsTable::create();
        LanguagesTable::create();
        (new MenuRepository())->seedDefaults();
        (new LanguagesRepository())->seedDefaults();
        self::seedConnectionDefaults();
    }

    private static function seedConnectionDefaults(): void
    {
        $settings = new SettingsRepository();

        if ($settings->get('server_url', '') === '' && defined('SARAH_AI_CLIENT_SERVER_URL') && SARAH_AI_CLIENT_SERVER_URL !== '') {
            $base = rtrim((string) SARAH_AI_CLIENT_SERVER_URL, '/');
            $settings->set('server_url', $base . '/sarah-ai-server/v1');
        }

        if ($settings->get('platform_key', '') === '' && defined('SARAH_AI_CLIENT_PLATFORM_KEY') && SARAH_AI_CLIENT_PLATFORM_KEY !== '') {
            $settings->set('platform_key', (string) SARAH_AI_CLIENT_PLATFORM_KEY);
        }
    }
}
