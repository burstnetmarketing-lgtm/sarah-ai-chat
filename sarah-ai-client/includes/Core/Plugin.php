<?php

declare(strict_types=1);

namespace SarahAiClient\Core;

use SarahAiClient\Admin\AdminMenu;
use SarahAiClient\Admin\DashboardPage;
use SarahAiClient\Api\AppearanceController;
use SarahAiClient\Api\ChatHistoryController;
use SarahAiClient\Api\ConnectController;
use SarahAiClient\Api\LogController;
use SarahAiClient\Api\MenuItemsController;
use SarahAiClient\Api\QuickQuestionsController;
use SarahAiClient\Api\SettingsController;
use SarahAiClient\DB\LanguagesTable;
use SarahAiClient\DB\MenuTable;
use SarahAiClient\DB\QuickQuestionsTable;
use SarahAiClient\DB\SettingsTable;
use SarahAiClient\Infrastructure\LanguagesRepository;
use SarahAiClient\Infrastructure\MenuRepository;
use SarahAiClient\Infrastructure\QuickQuestionsRepository;
use SarahAiClient\Infrastructure\SettingsRepository;

class Plugin
{
    public static function boot(): void
    {
        SettingsTable::create();
        MenuTable::create();
        QuickQuestionsTable::create();
        LanguagesTable::create();

        $menuRepo           = new MenuRepository();
        $settingsRepo       = new SettingsRepository();
        $quickQuestionsRepo = new QuickQuestionsRepository();
        $languagesRepo      = new LanguagesRepository();

        $languagesRepo->seedDefaults();

        $menuRepo->ensureCoreItems();
        $settingsRepo->ensureAppearanceDefaults();

        add_action('rest_api_init', [(new MenuItemsController($menuRepo)), 'registerRoutes']);
        add_action('rest_api_init', [(new LogController()), 'registerRoutes']);
        add_action('rest_api_init', [(new SettingsController($settingsRepo)), 'registerRoutes']);
        add_action('rest_api_init', [(new QuickQuestionsController($quickQuestionsRepo)), 'registerRoutes']);
        add_action('rest_api_init', [(new AppearanceController($settingsRepo)), 'registerRoutes']);
        add_action('rest_api_init', [(new ChatHistoryController()), 'registerRoutes']);
        add_action('rest_api_init', [(new ConnectController()), 'registerRoutes']);

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
        $cssFile = SARAH_AI_CLIENT_PATH . 'assets/dist/widget.css';
        $jsFile  = SARAH_AI_CLIENT_PATH . 'assets/dist/widget.js';
        $cssVer  = file_exists($cssFile) ? filemtime($cssFile) : SARAH_AI_CLIENT_VERSION;
        $jsVer   = file_exists($jsFile)  ? filemtime($jsFile)  : SARAH_AI_CLIENT_VERSION;

        wp_enqueue_style(
            'sarah-ai-client-widget',
            SARAH_AI_CLIENT_URL . 'assets/dist/widget.css',
            [],
            $cssVer
        );
        wp_enqueue_script(
            'sarah-ai-client-widget',
            SARAH_AI_CLIENT_URL . 'assets/dist/widget.js',
            [],
            $jsVer,
            true
        );
        add_filter('script_loader_tag', [self::class, 'addModuleType'], 10, 2);

        $quickQuestionsRepo = new QuickQuestionsRepository();
        $languagesRepo      = new LanguagesRepository();
        $settingsRepo       = new SettingsRepository();
        wp_localize_script('sarah-ai-client-widget', 'SarahAiWidget', [
            'quickQuestions' => $quickQuestionsRepo->allEnabled(),
            'languages'      => $languagesRepo->allEnabled(),
            'settings'       => $settingsRepo->getPublishedSettings(),
            'connection'     => [
                'server_url'       => $settingsRepo->get('server_url', ''),
                'account_key'      => $settingsRepo->get('account_key', ''),
                'site_key'         => $settingsRepo->get('site_key', ''),
                'platform_key'     => $settingsRepo->get('platform_key', ''),
                'greeting_message' => $settingsRepo->get('greeting_message', ''),
            ],
        ]);
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
