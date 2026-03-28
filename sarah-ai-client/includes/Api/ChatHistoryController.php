<?php

declare(strict_types=1);

namespace SarahAiClient\Api;

use WP_REST_Request;
use WP_REST_Response;

/**
 * Chat History endpoints for the admin dashboard.
 *
 * Intentionally delegates to the public-api.php global functions
 * (sarah_chat_get_sessions, sarah_chat_get_session_history) rather than
 * calling the server directly, so that this page acts as a live integration
 * test of the public API — confirming the same API that third-party plugins
 * would use actually works end-to-end.
 *
 * Routes (all require manage_options capability):
 *   GET /sarah-ai-client/v1/sessions           — list sessions
 *   GET /sarah-ai-client/v1/sessions/{uuid}    — session detail + messages
 */
class ChatHistoryController
{
    public function registerRoutes(): void
    {
        register_rest_route('sarah-ai-client/v1', '/stats', [
            'methods'             => 'GET',
            'callback'            => [$this, 'stats'],
            'permission_callback' => [$this, 'can'],
        ]);

        register_rest_route('sarah-ai-client/v1', '/sessions', [
            'methods'             => 'GET',
            'callback'            => [$this, 'index'],
            'permission_callback' => [$this, 'can'],
        ]);

        register_rest_route('sarah-ai-client/v1', '/sessions/(?P<uuid>[0-9a-f-]{36})', [
            'methods'             => 'GET',
            'callback'            => [$this, 'show'],
            'permission_callback' => [$this, 'can'],
        ]);
    }

    public function can(): bool
    {
        return current_user_can('manage_options');
    }

    /**
     * GET /stats
     */
    public function stats(WP_REST_Request $request): WP_REST_Response
    {
        $result = sarah_chat_get_site_stats();

        if (! $result['success']) {
            return new WP_REST_Response([
                'success' => false,
                'message' => $result['error'] ?? 'Failed to fetch stats.',
            ], 500);
        }

        return new WP_REST_Response([
            'success' => true,
            'data'    => $result['data'],
        ], 200);
    }

    /**
     * GET /sessions?limit=20
     *
     * Calls sarah_chat_get_sessions() — the public API function.
     */
    public function index(WP_REST_Request $request): WP_REST_Response
    {
        $limit  = min((int) ($request->get_param('limit') ?? 20), 100);
        $result = sarah_chat_get_sessions(['limit' => $limit]);

        if (! $result['success']) {
            return new WP_REST_Response([
                'success' => false,
                'message' => $result['error'] ?? 'Failed to fetch sessions.',
            ], 500);
        }

        return new WP_REST_Response([
            'success' => true,
            'data'    => $result['data'],
        ], 200);
    }

    /**
     * GET /sessions/{uuid}
     *
     * Calls sarah_chat_get_session_history() — the public API function.
     */
    public function show(WP_REST_Request $request): WP_REST_Response
    {
        $uuid   = (string) $request->get_param('uuid');
        $result = sarah_chat_get_session_history($uuid);

        if (! $result['success']) {
            $status = (str_contains($result['error'] ?? '', 'not found')) ? 404 : 500;
            return new WP_REST_Response([
                'success' => false,
                'message' => $result['error'] ?? 'Failed to fetch session.',
            ], $status);
        }

        return new WP_REST_Response([
            'success'  => true,
            'session'  => $result['session'],
            'messages' => $result['messages'],
        ], 200);
    }
}
