<?php

declare(strict_types=1);

namespace SarahAiServer\Admin;

class AdminMenu
{
    private DashboardPage $dashboard;

    public function __construct(DashboardPage $dashboard)
    {
        $this->dashboard = $dashboard;
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'registerMenu']);
        add_action('admin_init', [$this->dashboard, 'maybeRenderStandaloneShell'], 1);
    }

    public function registerMenu(): void
    {
        add_menu_page(
            'Sarah AI Server',
            'Sarah AI Server',
            'manage_options',
            'sarah-ai-server-shell',
            [$this->dashboard, 'render'],
            'dashicons-admin-generic',
            56
        );
    }
}
