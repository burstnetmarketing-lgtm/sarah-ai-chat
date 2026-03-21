<?php

declare(strict_types=1);

namespace SarahAiClient\Core;

use SarahAiClient\Admin\AdminMenu;
use SarahAiClient\Admin\SettingsPage;
use SarahAiClient\Infrastructure\SettingsRepository;

class Plugin
{
    public static function boot(): void
    {
        add_action('wp_enqueue_scripts', [self::class, 'enqueueAssets']);
        add_action('wp_footer', [self::class, 'renderRoot']);

        if (is_admin()) {
            $settingsPage = new SettingsPage(new SettingsRepository());
            $settingsPage->register();
            (new AdminMenu($settingsPage))->register();
        }
    }

    public static function enqueueAssets(): void
    {
        wp_enqueue_style(
            'sarah-ai-client',
            SARAH_AI_CLIENT_URL . 'assets/dist/app.css',
            [],
            SARAH_AI_CLIENT_VERSION
        );
        wp_enqueue_script(
            'sarah-ai-client',
            SARAH_AI_CLIENT_URL . 'assets/dist/app.js',
            [],
            SARAH_AI_CLIENT_VERSION,
            true
        );
    }

    public static function renderRoot(): void
    {
        echo '<div id="sarah-chat-root"></div>';
    }
}
