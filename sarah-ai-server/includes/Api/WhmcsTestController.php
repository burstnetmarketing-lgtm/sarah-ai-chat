<?php

declare(strict_types=1);

namespace SarahAiServer\Api;

use SarahAiServer\Infrastructure\SettingsRepository;
use SarahAiServer\Infrastructure\WhmcsLicenseService;

/**
 * Admin-only endpoint for testing WHMCS license key validation.
 *
 * POST /sarah-ai-server/v1/whmcs-test
 * Auth: WordPress admin (manage_options)
 * Body: { license_key: string }
 *
 * Bypasses the cache and hits the licensing server directly,
 * returning the full decoded response for diagnostic purposes.
 */
class WhmcsTestController
{
    public function registerRoutes(): void
    {
        register_rest_route('sarah-ai-server/v1', '/whmcs-test', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handle'],
            'permission_callback' => [$this, 'can'],
        ]);
    }

    public function can(): bool
    {
        return current_user_can('manage_options');
    }

    public function handle(\WP_REST_Request $request): \WP_REST_Response
    {
        $licenseKey = trim((string) ($request->get_param('license_key') ?? ''));

        if ($licenseKey === '') {
            return new \WP_REST_Response(['success' => false, 'message' => 'license_key is required'], 400);
        }

        $settings = new SettingsRepository();
        $apiUrl   = $settings->get('whmcs_api_url', '', 'platform');

        $service = new WhmcsLicenseService();
        $result  = $service->test($licenseKey);

        $status = strtolower(trim((string) ($result['status'] ?? '')));

        $raw   = $result['_raw']   ?? null;
        $error = $result['_error'] ?? null;
        unset($result['_raw'], $result['_error']);

        return new \WP_REST_Response([
            'success' => true,
            'data'    => [
                'is_active'      => ($status === 'active'),
                'whmcs_api_url'  => $apiUrl,
                'result'         => $result,
                'raw_response'   => $raw,
                'http_error'     => $error,
            ],
        ], 200);
    }
}
