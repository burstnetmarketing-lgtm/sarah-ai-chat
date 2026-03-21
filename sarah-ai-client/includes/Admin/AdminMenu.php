<?php

declare(strict_types=1);

namespace SarahAiClient\Admin;

class AdminMenu
{
    private SettingsPage $settings;

    public function __construct(SettingsPage $settings)
    {
        $this->settings = $settings;
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'registerMenu']);
    }

    public function registerMenu(): void
    {
        add_options_page(
            __('Sarah AI Client', 'sarah-ai-client'),
            __('Sarah AI Client', 'sarah-ai-client'),
            'manage_options',
            'sarah-ai-client',
            [$this->settings, 'render']
        );
    }
}
