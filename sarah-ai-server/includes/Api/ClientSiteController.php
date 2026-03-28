<?php

declare(strict_types=1);

namespace SarahAiServer\Api;

use SarahAiServer\Core\KbSyncJob;
use SarahAiServer\Infrastructure\ChatMessageRepository;
use SarahAiServer\Infrastructure\ChatSessionRepository;
use SarahAiServer\Infrastructure\CredentialValidator;
use SarahAiServer\Infrastructure\SiteApiKeyRepository;
use SarahAiServer\Infrastructure\SettingsRepository;

/**
 * Client-side site configuration endpoints.
 *
 * Allows the sarah-ai-client plugin to manage site-level settings
 * (currently: provider API keys) without requiring a WordPress admin login.
 *
 * Authentication (all three required):
 *   1. X-Sarah-Platform-Key header  — static platform secret
 *   2. account_key (body/query)     — identifies tenant
 *   3. site_key    (body/query)     — identifies site
 *
 * Routes:
 *   GET  /client/api-keys          — list providers that have a key set (keys NOT returned)
 *   POST /client/api-key           — set or clear a provider key
 *   POST /client/update-kb         — trigger async re-processing of all KB resources for the site
 */
class ClientSiteController
{
    private CredentialValidator   $credentials;
    private SiteApiKeyRepository  $siteApiKeys;
    private SettingsRepository    $settings;
    private ChatSessionRepository $sessions;
    private ChatMessageRepository $messages;

    public function __construct()
    {
        $this->credentials = new CredentialValidator();
        $this->siteApiKeys = new SiteApiKeyRepository();
        $this->settings    = new SettingsRepository();
        $this->sessions    = new ChatSessionRepository();
        $this->messages    = new ChatMessageRepository();
    }

    public function registerRoutes(): void
    {
        add_filter('rest_allowed_cors_headers', function (array $headers): array {
            $headers[] = 'X-Sarah-Platform-Key';
            return $headers;
        });

        register_rest_route('sarah-ai-server/v1', '/client/api-keys', [
            'methods'             => 'GET',
            'callback'            => [$this, 'listKeys'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('sarah-ai-server/v1', '/client/api-key', [
            'methods'             => 'POST',
            'callback'            => [$this, 'setKey'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('sarah-ai-server/v1', '/client/stats', [
            'methods'             => 'GET',
            'callback'            => [$this, 'getStats'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('sarah-ai-server/v1', '/client/update-kb', [
            'methods'             => 'POST',
            'callback'            => [$this, 'updateKb'],
            'permission_callback' => '__return_true',
        ]);
    }

    // ─── Auth ─────────────────────────────────────────────────────────────────

    private function resolveAuth(\WP_REST_Request $request): ?array
    {
        $storedKey   = trim((string) $this->settings->get('platform_api_key', '', 'platform'));
        $platformKey = trim((string) $request->get_header('X-Sarah-Platform-Key'));

        if (! $storedKey || ! $platformKey || ! hash_equals($storedKey, $platformKey)) {
            return null;
        }

        $accountKey = trim((string) ($request->get_param('account_key') ?? ''));
        $siteKey    = trim((string) ($request->get_param('site_key')    ?? ''));

        return $this->credentials->resolveContext($accountKey, $siteKey);
    }

    // ─── Handlers ─────────────────────────────────────────────────────────────

    /**
     * GET /client/api-keys
     * Returns which providers have a key configured (keys are NOT returned).
     */
    public function listKeys(\WP_REST_Request $request): \WP_REST_Response
    {
        $ctx = $this->resolveAuth($request);
        if (! $ctx) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $siteId    = (int) $ctx['site']['id'];
        $providers = $this->siteApiKeys->listProviders($siteId);

        return new \WP_REST_Response(['success' => true, 'data' => ['providers' => $providers]], 200);
    }

    /**
     * POST /client/api-key
     * Body: { account_key, site_key, provider, api_key }
     * Pass empty api_key to clear the key for that provider.
     */
    public function setKey(\WP_REST_Request $request): \WP_REST_Response
    {
        $ctx = $this->resolveAuth($request);
        if (! $ctx) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $provider = trim((string) ($request->get_param('provider') ?? ''));
        $apiKey   = trim((string) ($request->get_param('api_key')  ?? ''));

        if ($provider === '') {
            return new \WP_REST_Response(['success' => false, 'message' => 'provider is required'], 400);
        }

        $siteId = (int) $ctx['site']['id'];
        $this->siteApiKeys->set($siteId, $provider, $apiKey);

        return new \WP_REST_Response([
            'success' => true,
            'data'    => ['providers' => $this->siteApiKeys->listProviders($siteId)],
        ], 200);
    }

    /**
     * GET /client/stats
     * Returns total session and message counts for the site.
     */
    public function getStats(\WP_REST_Request $request): \WP_REST_Response
    {
        $ctx = $this->resolveAuth($request);
        if (! $ctx) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $siteId = (int) $ctx['site']['id'];

        return new \WP_REST_Response([
            'success' => true,
            'data'    => [
                'total_sessions' => $this->sessions->countBySite($siteId),
                'total_messages' => $this->messages->countBySite($siteId),
            ],
        ], 200);
    }

    /**
     * POST /client/update-kb
     * Body: { account_key, site_key }
     *
     * Queues async re-processing of all active KB resources for the site.
     * Returns immediately — processing happens in background via WP Cron.
     */
    public function updateKb(\WP_REST_Request $request): \WP_REST_Response
    {
        $ctx = $this->resolveAuth($request);
        if (! $ctx) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $siteId = (int) $ctx['site']['id'];
        $queued = KbSyncJob::dispatch($siteId);

        return new \WP_REST_Response([
            'success' => true,
            'data'    => [
                'queued'  => $queued,
                'message' => $queued > 0
                    ? "KB sync queued for {$queued} resource(s). Processing in background."
                    : 'No active KB resources found for this site.',
            ],
        ], 200);
    }
}
