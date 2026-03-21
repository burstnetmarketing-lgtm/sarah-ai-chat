<?php

namespace SarahAiServer\Core;

use SarahAiServer\DB\MenuTable;
use SarahAiServer\DB\SettingsTable;
use SarahAiServer\Infrastructure\MenuRepository;

class Activator
{
    public static function activate(): void
    {
        SettingsTable::create();
        MenuTable::create();
        (new MenuRepository())->seedDefaults();
    }
}
