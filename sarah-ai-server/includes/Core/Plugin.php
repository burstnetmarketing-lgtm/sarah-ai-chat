<?php

declare(strict_types=1);

namespace SarahAiServer\Core;

use SarahAiServer\Admin\AdminMenu;
use SarahAiServer\Admin\DashboardPage;
use SarahAiServer\Api\LogController;
use SarahAiServer\Api\MenuItemsController;
use SarahAiServer\DB\MenuTable;
use SarahAiServer\Infrastructure\MenuRepository;

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
    }
}
