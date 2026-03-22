<?php

declare(strict_types=1);

namespace SarahAiServer\Api;

use SarahAiServer\Infrastructure\ChatSessionRepository;
use SarahAiServer\Infrastructure\ChatMessageRepository;
use SarahAiServer\Infrastructure\SiteRepository;

/**
 * Read-only admin endpoints for session inspection and reporting.
 *
 * These endpoints do not mutate data and do not affect the runtime chat pipeline.
 * Authentication is WordPress admin (manage_options) — compatible with future
 * tenant-scoped or role-based restriction without structural changes.
 *
 * Endpoints:
 *   GET /sessions/{session_uuid}           — session detail
 *   GET /sessions/{session_uuid}/messages  — ordered message history
 *   GET /sessions?site_id=&tenant_id=      — list sessions (pagination-ready)
 */
class SessionController
{
    private ChatSessionRepository $sessions;
    private ChatMessageRepository $messages;
    private SiteRepository $sites;

    public function __construct()
    {
        $this->sessions = new ChatSessionRepository();
        $this->messages = new ChatMessageRepository();
        $this->sites    = new SiteRepository();
    }

    public function isAdmin(): bool
    {
        return current_user_can('manage_options');
    }

    public function registerRoutes(): void
    {
        register_rest_route('sarah-ai-server/v1', '/sessions', [
            'methods'             => 'GET',
            'callback'            => [$this, 'index'],
            'permission_callback' => [$this, 'isAdmin'],
        ]);

        register_rest_route('sarah-ai-server/v1', '/sessions/(?P<uuid>[0-9a-f-]{36})', [
            'methods'             => 'GET',
            'callback'            => [$this, 'show'],
            'permission_callback' => [$this, 'isAdmin'],
        ]);

        register_rest_route('sarah-ai-server/v1', '/sessions/(?P<uuid>[0-9a-f-]{36})/messages', [
            'methods'             => 'GET',
            'callback'            => [$this, 'messages'],
            'permission_callback' => [$this, 'isAdmin'],
        ]);
    }

    /**
     * GET /sessions?site_id=&tenant_id=&limit=&offset=
     * Lists sessions for a site or tenant. Pagination-ready via limit/offset.
     */
    public function index(\WP_REST_Request $request): \WP_REST_Response
    {
        $siteId   = (int) ($request->get_param('site_id')   ?? 0);
        $tenantId = (int) ($request->get_param('tenant_id') ?? 0);
        $limit    = min((int) ($request->get_param('limit')  ?? 50), 200);
        $offset   = max((int) ($request->get_param('offset') ?? 0), 0);

        if (! $siteId && ! $tenantId) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'site_id or tenant_id is required.',
            ], 400);
        }

        $rows = $siteId
            ? $this->sessions->findBySite($siteId, $limit, $offset)
            : $this->sessions->findByTenant($tenantId, $limit, $offset);

        $data = array_map([$this, 'formatSession'], $rows);

        return new \WP_REST_Response([
            'success' => true,
            'data'    => $data,
            'meta'    => ['limit' => $limit, 'offset' => $offset, 'count' => count($data)],
        ], 200);
    }

    /**
     * GET /sessions/{uuid}
     * Returns full session detail (no credential or hash fields).
     */
    public function show(\WP_REST_Request $request): \WP_REST_Response
    {
        $session = $this->sessions->findByUuid((string) $request->get_param('uuid'));

        if (! $session) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Session not found.'], 404);
        }

        return new \WP_REST_Response(['success' => true, 'data' => $this->formatSession($session)], 200);
    }

    /**
     * GET /sessions/{uuid}/messages
     * Returns full ordered message history for a session.
     */
    public function messages(\WP_REST_Request $request): \WP_REST_Response
    {
        $session = $this->sessions->findByUuid((string) $request->get_param('uuid'));

        if (! $session) {
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

    /** Formats a session record for API output — strips internal IDs, keeps UUIDs. */
    private function formatSession(array $session): array
    {
        return [
            'uuid'           => $session['uuid'],
            'tenant_id'      => (int) $session['tenant_id'],
            'site_id'        => (int) $session['site_id'],
            'agent_id'       => $session['agent_id'] ? (int) $session['agent_id'] : null,
            'status'         => $session['status'],
            'visitor_name'   => $session['visitor_name'],
            'visitor_phone'  => $session['visitor_phone'],
            'visitor_email'  => $session['visitor_email'],
            'captured_data'  => $session['captured_data'] ? json_decode($session['captured_data'], true) : null,
            'created_at'     => $session['created_at'],
            'updated_at'     => $session['updated_at'],
        ];
    }
}
