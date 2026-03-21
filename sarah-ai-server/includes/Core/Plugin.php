<?php

declare(strict_types=1);

namespace ProjectName\Core;

use ProjectName\Admin\AdminMenu;
use ProjectName\Admin\DashboardPage;
use ProjectName\Api\LogController;
use ProjectName\Api\MenuItemsController;
use ProjectName\DB\MenuTable;
use ProjectName\Infrastructure\MenuRepository;

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
