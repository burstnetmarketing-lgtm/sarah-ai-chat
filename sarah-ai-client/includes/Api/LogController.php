<?php

declare(strict_types=1);

namespace SarahAiClient\Api;

use SarahAiClient\Core\Logger;
use WP_REST_Request;
use WP_REST_Response;

class LogController
{
    public function registerRoutes(): void
    {
        register_rest_route('sarah-ai-client/v1', '/log', [
            ['methods' => 'POST', 'callback' => [$this, 'store'], 'permission_callback' => [$this, 'can']],
            ['methods' => 'GET',  'callback' => [$this, 'index'], 'permission_callback' => [$this, 'canRead']],
        ]);
    }

    public function can(): bool
    {
        return is_user_logged_in();
    }

    public function canRead(): bool
    {
        return current_user_can('manage_options');
    }

    public function index(): WP_REST_Response
    {
        $logFile = SARAH_AI_CLIENT_PATH . 'sarah-ai-client.log';
        if (! file_exists($logFile)) {
            return new WP_REST_Response(['success' => true, 'data' => ['lines' => []]], 200);
        }
        $lines = array_filter(explode("\n", file_get_contents($logFile)));
        $lines = array_values(array_slice(array_reverse($lines), 0, 500));
        return new WP_REST_Response(['success' => true, 'data' => ['lines' => $lines]], 200);
    }

    public function store(WP_REST_Request $request): WP_REST_Response
    {
        $level   = sanitize_key((string) ($request['level'] ?? 'error'));
        $context = sanitize_text_field((string) ($request['context'] ?? 'js'));
        $message = sanitize_text_field((string) ($request['message'] ?? ''));
        $data    = is_array($request['data']) ? $request['data'] : [];

        if (!in_array($level, ['error', 'warn', 'info'], true)) {
            $level = 'error';
        }

        Logger::write($level, "js:{$context}", $message, $data);

        return new WP_REST_Response(['success' => true], 200);
    }
}
