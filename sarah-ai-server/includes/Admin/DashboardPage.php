<?php

declare(strict_types=1);

namespace ProjectName\Admin;

class DashboardPage
{
    public function maybeRenderStandaloneShell(): void
    {
        $page = sanitize_key((string) ($_GET['page'] ?? ''));
        if ($page !== 'project-name-shell') {
            return;
        }
        if (! is_admin() || ! current_user_can('manage_options')) {
            return;
        }
        if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST') {
            return;
        }
        $this->renderPage();
        exit;
    }

    public function render(): void
    {
        // Handled via admin_init intercept
    }

    private function renderPage(): void
    {
        $userName = $this->currentUserName();
        $config   = [
            'apiUrl'          => rest_url('project-name/v1'),
            'nonce'           => wp_create_nonce('wp_rest'),
            'adminUrl'        => admin_url(),
            'logoutUrl'       => wp_logout_url(admin_url()),
            'userName'        => $userName,
            'initials'        => $this->userInitials($userName),
            'canManageMenus'  => current_user_can('manage_options'),
        ];
        $appCss = esc_url(PROJECT_NAME_URL . 'assets/dist/app.css?ver=' . PROJECT_NAME_VERSION);
        $appJs  = esc_url(PROJECT_NAME_URL . 'assets/dist/app.js?ver=' . PROJECT_NAME_VERSION);
        header('Content-Type: text/html; charset=' . get_bloginfo('charset'));
        ?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr(get_user_locale()); ?>">
<head>
    <meta charset="<?php echo esc_attr(get_bloginfo('charset')); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo esc_html(get_bloginfo('name')); ?> — Project Name</title>
    <link rel="stylesheet" href="<?php echo $appCss; ?>">
</head>
<body>
<div id="app"></div>
<script>window.ProjectNameConfig = <?php echo wp_json_encode($config); ?>;</script>
<script type="module" src="<?php echo $appJs; ?>"></script>
</body>
</html>
        <?php
    }

    private function currentUserName(): string
    {
        $user = wp_get_current_user();
        if (! $user || ! $user->exists()) {
            return 'Admin';
        }
        return (string) ($user->display_name ?: $user->user_login);
    }

    private function userInitials(string $name): string
    {
        $parts = array_filter(explode(' ', $name));
        if (count($parts) >= 2) {
            return strtoupper(mb_substr($parts[0], 0, 1) . mb_substr(end($parts), 0, 1));
        }
        return strtoupper(mb_substr($name, 0, 2));
    }
}
