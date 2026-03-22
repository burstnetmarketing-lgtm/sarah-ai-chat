<?php

declare(strict_types=1);

namespace SarahAiServer\Api;

use SarahAiServer\Runtime\ChatRuntime;

/**
 * Public chat endpoint — no WordPress authentication required.
 * Authentication is credential-based (account key + site key).
 *
 * POST /sarah-ai-server/v1/chat
 *
 * Request body:
 *   account_key  string  required — tenant account key
 *   site_key     string  required — site key
 *   message      string  required — customer message
 *   session_uuid string  optional — continue an existing session
 *   lead         object  optional — {name?, phone?, email?}
 *
 * Response (success):
 *   {success: true, session_uuid: string, message: string, agent: string}
 *
 * Response (failure):
 *   {success: false, error: string, message: string}
 */
class ChatController
{
    private ChatRuntime $runtime;

    public function __construct()
    {
        $this->runtime = new ChatRuntime();
    }

    public function registerRoutes(): void
    {
        register_rest_route('sarah-ai-server/v1', '/chat', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handle'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function handle(\WP_REST_Request $request): \WP_REST_Response
    {
        $accountKey  = trim((string) ($request->get_param('account_key')  ?? ''));
        $siteKey     = trim((string) ($request->get_param('site_key')     ?? ''));
        $message     = trim((string) ($request->get_param('message')      ?? ''));
        $sessionUuid = trim((string) ($request->get_param('session_uuid') ?? '')) ?: null;
        $lead        = $request->get_param('lead');
        $leadInfo    = is_array($lead) ? $lead : [];

        if (! $accountKey || ! $siteKey) {
            return new \WP_REST_Response([
                'success' => false,
                'error'   => 'missing_credentials',
                'message' => 'account_key and site_key are required.',
            ], 400);
        }

        if (! $message) {
            return new \WP_REST_Response([
                'success' => false,
                'error'   => 'missing_message',
                'message' => 'message is required.',
            ], 400);
        }

        $result = $this->runtime->handle($accountKey, $siteKey, $message, $sessionUuid, $leadInfo);

        $status = $result['success'] ? 200 : ($result['status'] ?? 400);
        unset($result['status']);

        return new \WP_REST_Response($result, $status);
    }
}
