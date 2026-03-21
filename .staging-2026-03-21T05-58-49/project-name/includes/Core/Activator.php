<?php

namespace ProjectName\Core;

use ProjectName\DB\MenuTable;
use ProjectName\DB\SettingsTable;
use ProjectName\Infrastructure\MenuRepository;

class Activator
{
    public static function activate(): void
    {
        SettingsTable::create();
        MenuTable::create();
        (new MenuRepository())->seedDefaults();
    }
}
