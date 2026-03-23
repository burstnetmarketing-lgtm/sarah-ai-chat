<?php

declare(strict_types=1);

namespace SarahAiServer\Api;

use SarahAiServer\Infrastructure\ChatSessionRepository;
use SarahAiServer\Infrastructure\ChatMessageRepository;
use SarahAiServer\Infrastructure\CredentialValidator;
use SarahAiServer\Infrastructure\SettingsRepository;

/**
 * Session inspection endpoints — public, credential-authenticated.
 *
 * Authentication requires ALL THREE of:
 *   1. account_key   (query param or body) — identifies tenant
 *   2. site_key      (query param or body) — identifies site
 *   3. X-Sarah-Platform-Key header         — static platform secret
 *
 * All responses are scoped to the resolved tenant/site.
 * No WordPress login is required.
 *
 * Endpoints:
 *   GET /sessions                          — list sessions for the authenticated site
 *   GET /sessions/{session_uuid}           — session detail (must belong to resolved site)
 *   GET /sessions/{session_uuid}/messages  — ordered message history
 */
class SessionController
{
    private ChatSessionRepository $sessions;
    private ChatMessageRepository $messages;
    private CredentialValidator   $credentials;
    private SettingsRepository    $settings;

    public function __construct()
    {
        $this->sessions    = new ChatSessionRepository();
        $this->messages    = new ChatMessageRepository();
        $this->credentials = new CredentialValidator();
        $this->settings    = new SettingsRepository();
    }

    public function registerRoutes(): void
    {
        register_rest_route('sarah-ai-server/v1', '/sessions', [
            'methods'             => 'GET',
            'callback'            => [$this, 'index'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('sarah-ai-server/v1', '/sessions/(?P<uuid>[0-9a-f-]{36})', [
            'methods'             => 'GET',
            'callback'            => [$this, 'show'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('sarah-ai-server/v1', '/sessions/(?P<uuid>[0-9a-f-]{36})/messages', [
            'methods'             => 'GET',
            'callback'            => [$this, 'messages'],
            'permission_callback' => '__return_true',
        ]);

        // Client-scoped aliases — same handlers, grouped under /client/ for consistency
        register_rest_route('sarah-ai-server/v1', '/client/sessions', [
            'methods'             => 'GET',
            'callback'            => [$this, 'index'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('sarah-ai-server/v1', '/client/sessions/(?P<uuid>[0-9a-f-]{36})', [
            'methods'             => 'GET',
            'callback'            => [$this, 'show'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('sarah-ai-server/v1', '/client/sessions/(?P<uuid>[0-9a-f-]{36})/messages', [
            'methods'             => 'GET',
            'callback'            => [$this, 'messages'],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * Validates all three credentials and returns resolved context.
     * Returns null with a WP_REST_Response error if any check fails.
     *
     * @return array{tenant: array, site: array}|null
     */
    private function resolveAuth(\WP_REST_Request $request): ?array
    {
        // 1. Platform key header — always required
        $storedKey   = trim((string) $this->settings->get('platform_api_key', '', 'platform'));
        $platformKey = trim((string) $request->get_header('X-Sarah-Platform-Key'));
        if (! $storedKey || ! $platformKey || ! hash_equals($storedKey, $platformKey)) {
            return null;
        }

        // 2. account_key + site_key → tenant + site
        $accountKey = trim((string) ($request->get_param('account_key') ?? ''));
        $siteKey    = trim((string) ($request->get_param('site_key')    ?? ''));

        if (! $accountKey || ! $siteKey) {
            return null;
        }

        return $this->credentials->resolveContext($accountKey, $siteKey);
    }

    private function unauthorized(): \WP_REST_Response
    {
        return new \WP_REST_Response([
            'success' => false,
            'message' => 'Authentication failed.',
        ], 401);
    }

    /**
     * GET /sessions?account_key=&site_key=&limit=&offset=
     */
    public function index(\WP_REST_Request $request): \WP_REST_Response
    {
        $context = $this->resolveAuth($request);
        if (! $context) {
            return $this->unauthorized();
        }

        $siteId = (int) $context['site']['id'];
        $limit  = min((int) ($request->get_param('limit')  ?? 50), 200);
        $offset = max((int) ($request->get_param('offset') ?? 0), 0);

        $rows = $this->sessions->findBySite($siteId, $limit, $offset);
        $data = array_map([$this, 'formatSession'], $rows);

        return new \WP_REST_Response([
            'success' => true,
            'data'    => $data,
            'meta'    => ['limit' => $limit, 'offset' => $offset, 'count' => count($data)],
        ], 200);
    }

    /**
     * GET /sessions/{uuid}?account_key=&site_key=
     */
    public function show(\WP_REST_Request $request): \WP_REST_Response
    {
        $context = $this->resolveAuth($request);
        if (! $context) {
            return $this->unauthorized();
        }

        $session = $this->sessions->findByUuid((string) $request->get_param('uuid'));

        if (! $session || (int) $session['site_id'] !== (int) $context['site']['id']) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Session not found.'], 404);
        }

        return new \WP_REST_Response(['success' => true, 'data' => $this->formatSession($session)], 200);
    }

    /**
     * GET /sessions/{uuid}/messages?account_key=&site_key=
     */
    public function messages(\WP_REST_Request $request): \WP_REST_Response
    {
        $context = $this->resolveAuth($request);
        if (! $context) {
            return $this->unauthorized();
        }

        $session = $this->sessions->findByUuid((string) $request->get_param('uuid'));

        if (! $session || (int) $session['site_id'] !== (int) $context['site']['id']) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Session not found.'], 404);
        }

        $rows = $this->messages->findBySession((int) $session['id']);

        $data = array_map(function (array $msg): array {
            return [
                'id'         => (int) $msg['id'],
                'uuid'       => $msg['uuid'],
                'role'       => $msg['role'],
                'content'    => $msg['content'],
                'meta'       => $msg['meta'] ? json_decode($msg['meta'], true) : null,
                'created_at' => $msg['created_at'],
            ];
        }, $rows);

        return new \WP_REST_Response([
            'success'      => true,
            'session_uuid' => $session['uuid'],
            'data'         => $data,
        ], 200);
    }

    private function formatSession(array $session): array
    {
        return [
            'uuid'          => $session['uuid'],
            'tenant_id'     => (int) $session['tenant_id'],
            'site_id'       => (int) $session['site_id'],
            'agent_id'      => $session['agent_id'] ? (int) $session['agent_id'] : null,
            'status'        => $session['status'],
            'visitor_name'  => $session['visitor_name'],
            'visitor_phone' => $session['visitor_phone'],
            'visitor_email' => $session['visitor_email'],
            'captured_data' => $session['captured_data'] ? json_decode($session['captured_data'], true) : null,
            'created_at'    => $session['created_at'],
            'updated_at'    => $session['updated_at'],
        ];
    }
}
