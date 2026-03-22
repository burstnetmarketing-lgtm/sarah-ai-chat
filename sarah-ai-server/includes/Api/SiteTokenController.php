<?php

declare(strict_types=1);

namespace SarahAiServer\Api;

use SarahAiServer\Infrastructure\SiteTokenRepository;
use SarahAiServer\Infrastructure\SiteRepository;

class SiteTokenController
{
    private SiteTokenRepository $siteTokens;
    private SiteRepository $sites;

    public function __construct()
    {
        $this->siteTokens = new SiteTokenRepository();
        $this->sites      = new SiteRepository();
    }

    public function isAdmin(): bool
    {
        return current_user_can('manage_options');
    }

    public function registerRoutes(): void
    {
        register_rest_route('sarah-ai-server/v1', '/sites/(?P<uuid>[0-9a-f-]{36})/site-keys', [
            'methods'             => 'POST',
            'callback'            => [$this, 'issue'],
            'permission_callback' => [$this, 'isAdmin'],
        ]);

        register_rest_route('sarah-ai-server/v1', '/sites/(?P<uuid>[0-9a-f-]{36})/site-keys', [
            'methods'             => 'GET',
            'callback'            => [$this, 'index'],
            'permission_callback' => [$this, 'isAdmin'],
        ]);

        register_rest_route('sarah-ai-server/v1', '/site-keys/(?P<uuid>[0-9a-f-]{36})', [
            'methods'             => 'DELETE',
            'callback'            => [$this, 'revoke'],
            'permission_callback' => [$this, 'isAdmin'],
        ]);
    }

    public function issue(\WP_REST_Request $request): \WP_REST_Response
    {
        $site = $this->sites->findByUuid((string) $request->get_param('uuid'));
        if (! $site) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Site not found'], 404);
        }

        $label     = trim((string) ($request->get_param('label') ?? ''));
        $expiresAt = $request->get_param('expires_at');
        $rawToken  = bin2hex(random_bytes(32));
        $recordId  = $this->siteTokens->issue((int) $site['id'], $rawToken, $label, $expiresAt ?: null);

        $record = $this->siteTokens->findById($recordId);
        $safe   = $record ? array_diff_key($record, ['token_hash' => '']) : [];

        return new \WP_REST_Response([
            'success' => true,
            'data'    => array_merge($safe, [
                'raw_key' => $rawToken,
                '_note'   => 'Store this key now. It will not be shown again.',
            ]),
        ], 201);
    }

    public function index(\WP_REST_Request $request): \WP_REST_Response
    {
        $site = $this->sites->findByUuid((string) $request->get_param('uuid'));
        if (! $site) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Site not found'], 404);
        }

        $tokens = $this->siteTokens->findBySite((int) $site['id']);
        $safe   = array_map(fn($t) => array_diff_key($t, ['token_hash' => '']), $tokens);

        return new \WP_REST_Response(['success' => true, 'data' => array_values($safe)], 200);
    }

    public function revoke(\WP_REST_Request $request): \WP_REST_Response
    {
        $record = $this->siteTokens->findByUuid((string) $request->get_param('uuid'));
        if (! $record) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Site key not found'], 404);
        }

        $this->siteTokens->revoke((int) $record['id']);
        return new \WP_REST_Response(['success' => true, 'message' => 'Site key revoked'], 200);
    }
}
