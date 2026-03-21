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
            ],
        ], 200);
    }

    public function update(WP_REST_Request $request): WP_REST_Response
    {
        $enabled = filter_var($request['widget_enabled'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $this->repo->set('widget_enabled', $enabled ? '1' : '0');
        return new WP_REST_Response(['success' => true], 200);
    }
}
