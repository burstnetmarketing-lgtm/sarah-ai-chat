<?php

declare(strict_types=1);

namespace SarahAiClient\Core;

use SarahAiClient\Admin\AdminMenu;
use SarahAiClient\Admin\DashboardPage;
use SarahAiClient\Admin\SettingsPage;
use SarahAiClient\Api\LogController;
use SarahAiClient\Api\MenuItemsController;
use SarahAiClient\DB\MenuTable;
use SarahAiClient\Infrastructure\MenuRepository;
use SarahAiClient\Infrastructure\SettingsRepository;

class Plugin
{
    public static function boot(): void
    {
        MenuTable::create();
        $menuRepo = new MenuRepository();
        $menuRepo->ensureCoreItems();
        $controller = new MenuItemsController($menuRepo);
        add_action('rest_api_init', [$controller, 'registerRoutes']);
        add_action('rest_api_init', [(new LogController()), 'registerRoutes']);
        if (! is_admin()) {
            return;
        }
        (new AdminMenu(new DashboardPage()))->register();
        $settingsPage = new SettingsPage(new SettingsRepository());
        $settingsPage->register();
    }
}
