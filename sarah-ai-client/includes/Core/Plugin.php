<?php

declare(strict_types=1);

namespace SarahAiClient\Core;

use SarahAiClient\Admin\AdminMenu;
use SarahAiClient\Admin\DashboardPage;
use SarahAiClient\Api\LogController;
use SarahAiClient\Api\MenuItemsController;
use SarahAiClient\Api\SettingsController;
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

        $settingsRepo = new SettingsRepository();

        add_action('rest_api_init', [(new MenuItemsController($menuRepo)), 'registerRoutes']);
        add_action('rest_api_init', [(new LogController()), 'registerRoutes']);
        add_action('rest_api_init', [(new SettingsController($settingsRepo)), 'registerRoutes']);

        if ($settingsRepo->get('widget_enabled', '1') === '1') {
            add_action('wp_enqueue_scripts', [self::class, 'enqueueWidget']);
            add_action('wp_footer', [self::class, 'renderRoot']);
        }

        if (! is_admin()) {
            return;
        }

        (new AdminMenu(new DashboardPage()))->register();
    }

    public static function enqueueWidget(): void
    {
        wp_enqueue_style(
            'sarah-ai-client-widget',
            SARAH_AI_CLIENT_URL . 'assets/dist/widget.css',
            [],
            SARAH_AI_CLIENT_VERSION
        );
        wp_enqueue_script(
            'sarah-ai-client-widget',
            SARAH_AI_CLIENT_URL . 'assets/dist/widget.js',
            [],
            SARAH_AI_CLIENT_VERSION,
            true
        );
        add_filter('script_loader_tag', [self::class, 'addModuleType'], 10, 2);
    }

    public static function addModuleType(string $tag, string $handle): string
    {
        if ($handle === 'sarah-ai-client-widget') {
            return str_replace('<script ', '<script type="module" ', $tag);
        }
        return $tag;
    }

    public static function renderRoot(): void
    {
        echo '<div id="sarah-chat-root"></div>';
    }
}
