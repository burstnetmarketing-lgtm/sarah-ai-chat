<?php

declare(strict_types=1);

namespace SarahAiClient\Api;

use WP_REST_Request;
use WP_REST_Response;

/**
 * Proxy endpoint for the Quick Setup wizard.
 *
 * POST /sarah-ai-client/v1/connect
 * Auth: WordPress admin (manage_options)
 *
 * Accepts the same body as the server's /quick-setup endpoint and forwards
 * it via wp_remote_post — server-to-server, no browser CORS restrictions.
 *
 * Body:
 *   server_url      string  required — base wp-json URL of the sarah-ai-server
 *   platform_key    string  required — X-Sarah-Platform-Key header value
 *   site_name       string  required
 *   site_url        string  required
 *   whmcs_key       string  optional
 *   openai_api_key  string  optional
 */
class ConnectController
{
    public function registerRoutes(): void
    {
        register_rest_route('sarah-ai-client/v1', '/connect', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handle'],
            'permission_callback' => [$this, 'can'],
        ]);
    }

    public function can(): bool
    {
        return current_user_can('manage_options');
    }

    public function handle(WP_REST_Request $request): WP_REST_Response
    {
        $serverUrl   = trim((string) ($request->get_param('server_url')     ?? ''));
        $platformKey = trim((string) ($request->get_param('platform_key')   ?? ''));
        $siteName    = trim((string) ($request->get_param('site_name')      ?? ''));
        $siteUrl     = trim((string) ($request->get_param('site_url')       ?? ''));
        $whmcsKey    = trim((string) ($request->get_param('whmcs_key')      ?? ''));
        $openAiKey   = trim((string) ($request->get_param('openai_api_key') ?? ''));

        if ($serverUrl === '' || $platformKey === '') {
            return new WP_REST_Response(['success' => false, 'message' => 'server_url and platform_key are required.'], 400);
        }

        $endpoint = rtrim($serverUrl, '/') . '/sarah-ai-server/v1/quick-setup';

        $body = ['site_name' => $siteName, 'site_url' => $siteUrl];
        if ($whmcsKey !== '')   $body['whmcs_key']      = $whmcsKey;
        if ($openAiKey !== '')  $body['openai_api_key'] = $openAiKey;

        $response = wp_remote_post($endpoint, [
            'timeout' => 30,
            'headers' => [
                'Content-Type'          => 'application/json',
                'X-Sarah-Platform-Key'  => $platformKey,
            ],
            'body' => wp_json_encode($body),
        ]);

        if (is_wp_error($response)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Could not reach the server: ' . $response->get_error_message(),
            ], 200);
        }

        $raw  = wp_remote_retrieve_body($response);
        $data = json_decode($raw, true);

        if (! is_array($data)) {
            $code = (int) wp_remote_retrieve_response_code($response);
            return new WP_REST_Response([
                'success' => false,
                'message' => 'HTTP ' . $code . ' from: ' . $endpoint . ' — ' . substr(strip_tags($raw), 0, 200),
            ], 200);
        }

        return new WP_REST_Response($data, 200);
    }
}
