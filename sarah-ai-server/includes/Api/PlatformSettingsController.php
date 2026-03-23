<?php

declare(strict_types=1);

namespace SarahAiServer\Api;

use SarahAiServer\Infrastructure\SettingsRepository;

/**
 * Platform settings management — admin only.
 *
 * GET  /platform-settings  — returns all editable platform settings
 * POST /platform-settings  — saves one or more platform settings
 */
class PlatformSettingsController
{
    private const GROUP = 'platform';

    /** Keys that are readable and writable through this endpoint. */
    private const ALLOWED_KEYS = [
        'platform_name',
        'openai_api_key',
        'platform_api_key',
        'logging_enabled',
        'default_agent_slug',
        'whmcs_api_url',
    ];

    private SettingsRepository $repo;

    public function __construct()
    {
        $this->repo = new SettingsRepository();
    }

    public function registerRoutes(): void
    {
        register_rest_route('sarah-ai-server/v1', '/platform-settings', [
            ['methods' => 'GET',  'callback' => [$this, 'get'],    'permission_callback' => [$this, 'can']],
            ['methods' => 'POST', 'callback' => [$this, 'update'], 'permission_callback' => [$this, 'can']],
        ]);
    }

    public function can(): bool
    {
        return current_user_can('manage_options');
    }

    public function get(): \WP_REST_Response
    {
        $data = [];
        foreach (self::ALLOWED_KEYS as $key) {
            $data[$key] = $this->repo->get($key, '', self::GROUP);
        }

        // Mask the OpenAI key — return only whether it is set, not the value
        $data['openai_api_key_set'] = ! empty($data['openai_api_key']);
        $data['openai_api_key']     = $data['openai_api_key'] ? str_repeat('•', 8) . substr($data['openai_api_key'], -4) : '';

        return new \WP_REST_Response(['success' => true, 'data' => $data], 200);
    }

    public function update(\WP_REST_Request $request): \WP_REST_Response
    {
        $saved = [];

        foreach (self::ALLOWED_KEYS as $key) {
            if ($request->has_param($key)) {
                $value = trim((string) $request->get_param($key));

                // Do not overwrite the real key if the masked placeholder is submitted
                if ($key === 'openai_api_key' && str_contains($value, '•')) {
                    continue;
                }

                $this->repo->set($key, $value, self::GROUP);
                $saved[] = $key;
            }
        }

        return new \WP_REST_Response(['success' => true, 'saved' => $saved], 200);
    }
}
