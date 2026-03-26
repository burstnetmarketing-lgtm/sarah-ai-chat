<?php

namespace SarahAiClient\Core;

class Deactivator
{
    public static function deactivate(): void
    {
        // Data and tables are preserved on deactivation.
        // Full cleanup (DROP TABLE, delete options) happens in uninstall.php.
    }
}
