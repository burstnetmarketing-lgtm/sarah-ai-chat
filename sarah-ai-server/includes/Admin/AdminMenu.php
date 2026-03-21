<?php

declare(strict_types=1);

namespace ProjectName\Admin;

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
            'Project Name',
            'Project Name',
            'manage_options',
            'project-name-shell',
            [$this->dashboard, 'render'],
            'dashicons-admin-generic',
            56
        );
    }
}
