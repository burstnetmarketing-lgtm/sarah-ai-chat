<?php

declare(strict_types=1);

namespace SarahAiServer\Api;

use SarahAiServer\Infrastructure\AccountKeyRepository;
use SarahAiServer\Infrastructure\TenantRepository;

class AccountKeyController
{
    private AccountKeyRepository $accountKeys;
    private TenantRepository $tenants;

    public function __construct()
    {
        $this->accountKeys = new AccountKeyRepository();
        $this->tenants     = new TenantRepository();
    }

    public function isAdmin(): bool
    {
        return current_user_can('manage_options');
    }

    public function registerRoutes(): void
    {
        register_rest_route('sarah-ai-server/v1', '/tenants/(?P<uuid>[0-9a-f-]{36})/account-keys', [
            'methods'             => 'POST',
            'callback'            => [$this, 'issue'],
            'permission_callback' => [$this, 'isAdmin'],
        ]);

        register_rest_route('sarah-ai-server/v1', '/tenants/(?P<uuid>[0-9a-f-]{36})/account-keys', [
            'methods'             => 'GET',
            'callback'            => [$this, 'index'],
            'permission_callback' => [$this, 'isAdmin'],
        ]);

        register_rest_route('sarah-ai-server/v1', '/account-keys/(?P<uuid>[0-9a-f-]{36})', [
            'methods'             => 'DELETE',
            'callback'            => [$this, 'revoke'],
            'permission_callback' => [$this, 'isAdmin'],
        ]);
    }

    public function issue(\WP_REST_Request $request): \WP_REST_Response
    {
        $tenant = $this->tenants->findByUuid((string) $request->get_param('uuid'));
        if (! $tenant) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Tenant not found'], 404);
        }

        $label     = trim((string) ($request->get_param('label') ?? ''));
        $expiresAt = $request->get_param('expires_at');
        $rawKey    = bin2hex(random_bytes(32));
        $recordId  = $this->accountKeys->issue((int) $tenant['id'], $rawKey, $label, $expiresAt ?: null);

        $record = $this->accountKeys->findById($recordId);
        $safe   = $record ? array_diff_key($record, ['key_hash' => '']) : [];

        return new \WP_REST_Response([
            'success' => true,
            'data'    => array_merge($safe, [
                'raw_key' => $rawKey,
                '_note'   => 'Store this key now. It will not be shown again.',
            ]),
        ], 201);
    }

    public function index(\WP_REST_Request $request): \WP_REST_Response
    {
        $tenant = $this->tenants->findByUuid((string) $request->get_param('uuid'));
        if (! $tenant) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Tenant not found'], 404);
        }

        $keys = $this->accountKeys->findByTenant((int) $tenant['id']);
        $safe = array_map(fn($k) => array_diff_key($k, ['key_hash' => '']), $keys);

        return new \WP_REST_Response(['success' => true, 'data' => array_values($safe)], 200);
    }

    public function revoke(\WP_REST_Request $request): \WP_REST_Response
    {
        $record = $this->accountKeys->findByUuid((string) $request->get_param('uuid'));
        if (! $record) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Account key not found'], 404);
        }

        $this->accountKeys->revoke((int) $record['id']);
        return new \WP_REST_Response(['success' => true, 'message' => 'Account key revoked'], 200);
    }
}
