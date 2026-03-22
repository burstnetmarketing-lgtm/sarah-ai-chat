<?php

declare(strict_types=1);

namespace SarahAiClient\Api;

use SarahAiClient\Infrastructure\SettingsRepository;
use WP_REST_Request;
use WP_REST_Response;

class SettingsController
{
    private SettingsRepository $repo;

    public function __construct(SettingsRepository $repo)
    {
        $this->repo = $repo;
    }

    public function registerRoutes(): void
    {
        register_rest_route('sarah-ai-client/v1', '/widget-settings', [
            ['methods' => 'GET',  'callback' => [$this, 'get'],    'permission_callback' => [$this, 'can']],
            ['methods' => 'POST', 'callback' => [$this, 'update'], 'permission_callback' => [$this, 'can']],
        ]);
    }

    public function can(): bool
    {
        return current_user_can('manage_options');
    }

    public function get(): WP_REST_Response
    {
        return new WP_REST_Response([
            'success' => true,
            'data'    => [
                'widget_enabled' => $this->repo->get('widget_enabled', '1') === '1',
                'server_url'     => $this->repo->get('server_url', ''),
                'account_key'    => $this->repo->get('account_key', ''),
                'site_key'       => $this->repo->get('site_key', ''),
            ],
        ], 200);
    }

    public function update(WP_REST_Request $request): WP_REST_Response
    {
        if (isset($request['widget_enabled'])) {
            $enabled = filter_var($request['widget_enabled'], FILTER_VALIDATE_BOOLEAN);
            $this->repo->set('widget_enabled', $enabled ? '1' : '0');
        }
        if (isset($request['server_url'])) {
            $this->repo->set('server_url', trim((string) $request['server_url']));
        }
        if (isset($request['account_key'])) {
            $this->repo->set('account_key', trim((string) $request['account_key']));
        }
        if (isset($request['site_key'])) {
            $this->repo->set('site_key', trim((string) $request['site_key']));
        }
        return new WP_REST_Response(['success' => true], 200);
    }
}
