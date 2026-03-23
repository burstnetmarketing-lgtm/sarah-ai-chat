<?php

declare(strict_types=1);

namespace SarahAiClient\Core;

use SarahAiClient\Infrastructure\SettingsRepository;

/**
 * Internal service powering the sarah_chat_* public API.
 *
 * All public-api.php global functions delegate to this class.
 * Never call this class directly from outside the plugin — use the global functions.
 */
class PublicApiService
{
    /** Config keys that external callers are allowed to write. */
    private const WRITABLE_KEYS = [
        'widget_enabled',
        'greeting_message',
        'server_url',
        'account_key',
        'site_key',
        'platform_key',
    ];

    private SettingsRepository $settings;

    public function __construct()
    {
        $this->settings = new SettingsRepository();
    }

    // ─── Status ───────────────────────────────────────────────────────────

    /**
     * Returns true when all four connection credentials are present.
     */
    public function isReady(): bool
    {
        return $this->settings->get('server_url',   '') !== ''
            && $this->settings->get('account_key',  '') !== ''
            && $this->settings->get('site_key',     '') !== ''
            && $this->settings->get('platform_key', '') !== '';
    }

    // ─── Setup ────────────────────────────────────────────────────────────

    /**
     * Runs Quick Setup: calls the server's /quick-setup endpoint, then persists credentials.
     *
     * @param array $payload {
     *   @type string $server_url      Required. Base WP REST API URL (no trailing slash).
     *   @type string $platform_key    Required. Platform authentication key.
     *   @type string $site_name       Optional. Defaults to blogname.
     *   @type string $site_url        Optional. Defaults to home_url().
     *   @type string $whmcs_key       Optional.
     *   @type string $openai_api_key  Optional.
     * }
     * @return array { success: bool, ready: bool, error: string|null }
     */
    public function setup(array $payload): array
    {
        $serverUrl   = trim((string) ($payload['server_url']     ?? ''));
        $platformKey = trim((string) ($payload['platform_key']   ?? ''));
        $siteName    = trim((string) ($payload['site_name']      ?? '')) ?: (get_bloginfo('name') ?: 'My Site');
        $siteUrl     = trim((string) ($payload['site_url']       ?? '')) ?: (string) home_url('/');
        $whmcsKey    = trim((string) ($payload['whmcs_key']      ?? ''));
        $openAiKey   = trim((string) ($payload['openai_api_key'] ?? ''));

        if ($serverUrl === '' || $platformKey === '') {
            return ['success' => false, 'ready' => false, 'error' => 'server_url and platform_key are required'];
        }
        if ($whmcsKey === '') {
            return ['success' => false, 'ready' => false, 'error' => 'whmcs_key is required'];
        }
        if ($openAiKey === '') {
            return ['success' => false, 'ready' => false, 'error' => 'openai_api_key is required'];
        }

        $serverBase = rtrim($serverUrl, '/');
        $endpoint   = $serverBase . '/sarah-ai-server/v1/quick-setup';

        $body = ['site_name' => $siteName, 'site_url' => $siteUrl];
        if ($whmcsKey !== '')  $body['whmcs_key']      = $whmcsKey;
        if ($openAiKey !== '') $body['openai_api_key'] = $openAiKey;

        $response = wp_remote_post($endpoint, [
            'timeout' => 20,
            'headers' => [
                'Content-Type'          => 'application/json',
                'X-Sarah-Platform-Key'  => $platformKey,
            ],
            'body' => wp_json_encode($body),
        ]);

        if (is_wp_error($response)) {
            return ['success' => false, 'ready' => false, 'error' => $response->get_error_message()];
        }

        $data = json_decode((string) wp_remote_retrieve_body($response), true);

        if (! is_array($data) || empty($data['success'])) {
            $msg = is_array($data) ? (string) ($data['message'] ?? 'Setup failed') : 'Invalid server response';
            return ['success' => false, 'ready' => false, 'error' => $msg];
        }

        // Persist credentials — server_url stored with namespace so all API calls work directly.
        $this->settings->set('server_url',   $serverBase . '/sarah-ai-server/v1');
        $this->settings->set('account_key',  (string) ($data['data']['account_key'] ?? ''));
        $this->settings->set('site_key',     (string) ($data['data']['site_key']    ?? ''));
        $this->settings->set('platform_key', $platformKey);

        return ['success' => true, 'ready' => $this->isReady(), 'error' => null];
    }

    // ─── Sessions ─────────────────────────────────────────────────────────

    /**
     * Returns a list of sessions for this site from the server.
     *
     * @param array $args { limit: int (default 20, max 100) }
     * @return array { success: bool, data: array, error: string|null }
     */
    public function getSessions(array $args = []): array
    {
        $conn = $this->connection();
        if ($conn === null) {
            return ['success' => false, 'data' => [], 'error' => 'Plugin not configured'];
        }

        [$serverUrl, $accountKey, $siteKey, $platformKey] = $conn;
        $limit = min((int) ($args['limit'] ?? 20), 100);

        $url = $serverUrl . '/sessions?' . http_build_query([
            'account_key' => $accountKey,
            'site_key'    => $siteKey,
            'limit'       => $limit,
        ]);

        $response = wp_remote_get($url, [
            'timeout' => 15,
            'headers' => ['X-Sarah-Platform-Key' => $platformKey],
        ]);

        if (is_wp_error($response)) {
            return ['success' => false, 'data' => [], 'error' => $response->get_error_message()];
        }

        $data = json_decode((string) wp_remote_retrieve_body($response), true);

        if (! is_array($data) || empty($data['success'])) {
            $msg = is_array($data) ? (string) ($data['message'] ?? 'Request failed') : 'Invalid response';
            return ['success' => false, 'data' => [], 'error' => $msg];
        }

        return ['success' => true, 'data' => $data['data'] ?? [], 'error' => null];
    }

    // ─── Session History ──────────────────────────────────────────────────

    /**
     * Returns session metadata + full ordered message list.
     *
     * @param string $sessionUuid Session UUID.
     * @return array { success: bool, session: array|null, messages: array, error: string|null }
     */
    public function getSessionHistory(string $sessionUuid): array
    {
        $empty = ['success' => false, 'session' => null, 'messages' => [], 'error' => ''];

        if ($sessionUuid === '') {
            return array_merge($empty, ['error' => 'session_uuid is required']);
        }

        $conn = $this->connection();
        if ($conn === null) {
            return array_merge($empty, ['error' => 'Plugin not configured']);
        }

        [$serverUrl, $accountKey, $siteKey, $platformKey] = $conn;
        $qs      = http_build_query(['account_key' => $accountKey, 'site_key' => $siteKey]);
        $headers = ['X-Sarah-Platform-Key' => $platformKey];
        $opts    = ['timeout' => 15, 'headers' => $headers];

        $sessionRes  = wp_remote_get($serverUrl . '/sessions/' . $sessionUuid . '?' . $qs, $opts);
        $messagesRes = wp_remote_get($serverUrl . '/sessions/' . $sessionUuid . '/messages?' . $qs, $opts);

        if (is_wp_error($sessionRes)) {
            return array_merge($empty, ['error' => $sessionRes->get_error_message()]);
        }

        $sessionData  = json_decode((string) wp_remote_retrieve_body($sessionRes),  true);
        $messagesData = json_decode((string) wp_remote_retrieve_body($messagesRes), true);

        if (! is_array($sessionData) || empty($sessionData['success'])) {
            $msg = is_array($sessionData) ? (string) ($sessionData['message'] ?? 'Session not found') : 'Session not found';
            return array_merge($empty, ['error' => $msg]);
        }

        return [
            'success'  => true,
            'session'  => $sessionData['data'] ?? null,
            'messages' => is_array($messagesData) ? ($messagesData['data'] ?? []) : [],
            'error'    => null,
        ];
    }

    // ─── Config ───────────────────────────────────────────────────────────

    /**
     * Sets one or more plugin configuration values.
     *
     * @param array $values Map of key → value. Only keys in WRITABLE_KEYS are accepted.
     * @return array { success: bool, saved: string[], errors: string[], error: string|null }
     */
    public function setConfig(array $values): array
    {
        $saved  = [];
        $errors = [];

        foreach ($values as $key => $value) {
            if (! in_array($key, self::WRITABLE_KEYS, true)) {
                $errors[] = "Key '{$key}' is not allowed";
                continue;
            }
            $this->settings->set((string) $key, (string) $value);
            $saved[] = (string) $key;
        }

        return [
            'success' => empty($errors),
            'saved'   => $saved,
            'errors'  => $errors,
            'error'   => empty($errors) ? null : implode('; ', $errors),
        ];
    }

    // ─── Helpers ──────────────────────────────────────────────────────────

    /**
     * Returns [server_url, account_key, site_key, platform_key] or null if not configured.
     */
    private function connection(): ?array
    {
        $serverUrl   = $this->settings->get('server_url',   '');
        $accountKey  = $this->settings->get('account_key',  '');
        $siteKey     = $this->settings->get('site_key',     '');
        $platformKey = $this->settings->get('platform_key', '');

        if ($serverUrl === '' || $accountKey === '' || $siteKey === '' || $platformKey === '') {
            return null;
        }

        return [$serverUrl, $accountKey, $siteKey, $platformKey];
    }
}
