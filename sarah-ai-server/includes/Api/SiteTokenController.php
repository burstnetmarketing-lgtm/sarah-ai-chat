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

    public function registerRoutes(): void
    {
        // POST /sites/{id}/site-keys
        register_rest_route('sarah-ai-server/v1', '/sites/(?P<id>\d+)/site-keys', [
            'methods'             => 'POST',
            'callback'            => [$this, 'issue'],
            'permission_callback' => '__return_true',
        ]);

        // GET /sites/{id}/site-keys
        register_rest_route('sarah-ai-server/v1', '/sites/(?P<id>\d+)/site-keys', [
            'methods'             => 'GET',
            'callback'            => [$this, 'index'],
            'permission_callback' => '__return_true',
        ]);

        // DELETE /site-keys/{id}
        register_rest_route('sarah-ai-server/v1', '/site-keys/(?P<id>\d+)', [
            'methods'             => 'DELETE',
            'callback'            => [$this, 'revoke'],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * Issue a new site key for a site.
     * The raw key is returned ONCE in this response and is never recoverable after this point.
     *
     * Body: label (optional), expires_at (optional)
     */
    public function issue(\WP_REST_Request $request): \WP_REST_Response
    {
        $siteId    = (int) $request->get_param('id');
        $label     = trim((string) ($request->get_param('label') ?? ''));
        $expiresAt = $request->get_param('expires_at');

        if (! $this->sites->findById($siteId)) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Site not found'], 404);
        }

        $rawToken = bin2hex(random_bytes(32));
        $recordId = $this->siteTokens->issue($siteId, $rawToken, $label, $expiresAt ?: null);

        return new \WP_REST_Response([
            'success' => true,
            'data'    => [
                'id'         => $recordId,
                'site_id'    => $siteId,
                'label'      => $label ?: null,
                'expires_at' => $expiresAt ?: null,
                'raw_key'    => $rawToken,
                '_note'      => 'Store this key now. It will not be shown again.',
            ],
        ], 201);
    }

    /** List all site keys for a site. Raw keys are never included in list responses. */
    public function index(\WP_REST_Request $request): \WP_REST_Response
    {
        $siteId = (int) $request->get_param('id');

        if (! $this->sites->findById($siteId)) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Site not found'], 404);
        }

        $tokens = $this->siteTokens->findBySite($siteId);

        // Strip token_hash from all records — never expose hashes via API
        $tokens = array_map(function (array $token) {
            unset($token['token_hash']);
            return $token;
        }, $tokens);

        return new \WP_REST_Response(['success' => true, 'data' => $tokens], 200);
    }

    /** Revoke a site key. This is permanent and cannot be undone. */
    public function revoke(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $this->siteTokens->revoke($id);
        return new \WP_REST_Response(['success' => true, 'message' => 'Site key revoked'], 200);
    }
}
