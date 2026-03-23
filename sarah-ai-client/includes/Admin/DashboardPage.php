<?php

declare(strict_types=1);

namespace SarahAiClient\Admin;

class DashboardPage
{
    public function maybeRenderStandaloneShell(): void
    {
        $page = sanitize_key((string) ($_GET['page'] ?? ''));
        if ($page !== 'sarah-ai-client-shell') {
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
        $userName     = $this->currentUserName();
        $settingsRepo = new \SarahAiClient\Infrastructure\SettingsRepository();
        $serverUrl  = $settingsRepo->get('server_url',   '');
        $accountKey = $settingsRepo->get('account_key',  '');
        $siteKey    = $settingsRepo->get('site_key',     '');

        // Deploy-time server URL takes precedence over DB-stored value.
        $deployServerUrl = defined('SARAH_AI_CLIENT_SERVER_URL') ? (string) SARAH_AI_CLIENT_SERVER_URL : '';

        $config = [
            'apiUrl'         => rest_url('sarah-ai-client/v1'),
            'nonce'          => wp_create_nonce('wp_rest'),
            'adminUrl'       => admin_url(),
            'userName'       => $userName,
            'initials'       => $this->userInitials($userName),
            'canManageMenus' => current_user_can('manage_options'),
            'siteName'       => get_bloginfo('name'),
            'siteUrl'        => get_bloginfo('url'),
            'isConfigured'   => ($serverUrl !== '' && $accountKey !== '' && $siteKey !== ''),
            'serverUrl'      => $deployServerUrl !== '' ? $deployServerUrl : $serverUrl,
            'connection'     => [
                'server_url'   => $serverUrl,
                'account_key'  => $accountKey,
                'site_key'     => $siteKey,
                'platform_key' => $settingsRepo->get('platform_key', ''),
            ],
        ];
        $appCss = esc_url(SARAH_AI_CLIENT_URL . 'assets/dist/app.css?ver=' . SARAH_AI_CLIENT_VERSION);
        $appJs  = esc_url(SARAH_AI_CLIENT_URL . 'assets/dist/app.js?ver=' . SARAH_AI_CLIENT_VERSION);
        header('Content-Type: text/html; charset=' . get_bloginfo('charset'));
        ?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr(get_user_locale()); ?>">
<head>
    <meta charset="<?php echo esc_attr(get_bloginfo('charset')); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo esc_html(get_bloginfo('name')); ?> — Sarah AI Client</title>
    <link rel="stylesheet" href="<?php echo $appCss; ?>">
</head>
<body>
<div id="app"></div>
<script>window.SarahAiClientConfig = <?php echo wp_json_encode($config); ?>;</script>
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
