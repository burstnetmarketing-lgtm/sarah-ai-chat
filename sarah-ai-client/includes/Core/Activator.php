<?php

namespace SarahAiClient\Core;

use SarahAiClient\DB\MenuTable;
use SarahAiClient\DB\SettingsTable;
use SarahAiClient\Infrastructure\MenuRepository;

class Activator
{
    public static function activate(): void
    {
        SettingsTable::create();
        MenuTable::create();
        (new MenuRepository())->seedDefaults();
    }
}
