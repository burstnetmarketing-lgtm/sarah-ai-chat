<?php

declare(strict_types=1);

namespace SarahAiServer\Api;

use SarahAiServer\Infrastructure\ChatMessageRepository;
use SarahAiServer\Infrastructure\ChatSessionRepository;
use SarahAiServer\Infrastructure\CredentialValidator;
use SarahAiServer\Runtime\ChatRuntime;

/**
 * Public chat endpoints — no WordPress authentication required.
 * Authentication is credential-based (account key + site key).
 *
 * POST /sarah-ai-server/v1/chat
 *   account_key  string  required
 *   site_key     string  required
 *   message      string  required
 *   session_uuid string  optional — continue existing session
 *   lead         object  optional — {name?, phone?, email?}
 *
 * GET /sarah-ai-server/v1/chat/history
 *   account_key  string  required
 *   site_key     string  required
 *   session_uuid string  required
 *   Returns ordered message history for the widget to restore on reload.
 */
class ChatController
{
    private ChatRuntime            $runtime;
    private CredentialValidator    $credentials;
    private ChatSessionRepository  $sessions;
    private ChatMessageRepository  $chatMessages;

    public function __construct()
    {
        $this->runtime      = new ChatRuntime();
        $this->credentials  = new CredentialValidator();
        $this->sessions     = new ChatSessionRepository();
        $this->chatMessages = new ChatMessageRepository();
    }

    public function registerRoutes(): void
    {
        register_rest_route('sarah-ai-server/v1', '/chat', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handle'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('sarah-ai-server/v1', '/chat/history', [
            'methods'             => 'GET',
            'callback'            => [$this, 'history'],
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

    /**
     * GET /chat/history?account_key=&site_key=&session_uuid=
     *
     * Returns the ordered message history for a session so the widget can
     * restore the conversation after a page reload.
     * Auth: account_key + site_key (same as POST /chat — no platform key needed).
     */
    public function history(\WP_REST_Request $request): \WP_REST_Response
    {
        $accountKey  = trim((string) ($request->get_param('account_key')  ?? ''));
        $siteKey     = trim((string) ($request->get_param('site_key')     ?? ''));
        $sessionUuid = trim((string) ($request->get_param('session_uuid') ?? ''));

        if (! $accountKey || ! $siteKey || ! $sessionUuid) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'account_key, site_key, and session_uuid are required.',
            ], 400);
        }

        $context = $this->credentials->resolveContext($accountKey, $siteKey);
        if (! $context) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Invalid credentials.'], 401);
        }

        $session = $this->sessions->findByUuid($sessionUuid);
        if (! $session || (int) $session['site_id'] !== (int) $context['site']['id']) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Session not found.'], 404);
        }

        $rows     = $this->chatMessages->findBySession((int) $session['id']);
        $messages = array_map(fn($msg) => [
            'role'    => (string) $msg['role'],    // 'customer' or 'assistant'
            'content' => (string) $msg['content'],
        ], $rows);

        return new \WP_REST_Response([
            'success'      => true,
            'session_uuid' => $sessionUuid,
            'messages'     => $messages,
        ]);
    }
}
