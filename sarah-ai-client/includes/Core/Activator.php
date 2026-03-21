<?php

namespace SarahAiClient\Core;

use SarahAiClient\DB\SettingsTable;

class Activator
{
    public static function activate(): void
    {
        SettingsTable::create();
    }
}
