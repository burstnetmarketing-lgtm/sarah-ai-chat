<?php

namespace SarahAiClient\Core;

use SarahAiClient\DB\LanguagesTable;
use SarahAiClient\DB\MenuTable;
use SarahAiClient\DB\QuickQuestionsTable;
use SarahAiClient\DB\SettingsTable;
use SarahAiClient\Infrastructure\LanguagesRepository;
use SarahAiClient\Infrastructure\MenuRepository;

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
    }
}
