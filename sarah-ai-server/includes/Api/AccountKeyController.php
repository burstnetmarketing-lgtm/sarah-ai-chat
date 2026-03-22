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

    public function registerRoutes(): void
    {
        // POST /tenants/{id}/account-keys
        register_rest_route('sarah-ai-server/v1', '/tenants/(?P<id>\d+)/account-keys', [
            'methods'             => 'POST',
            'callback'            => [$this, 'issue'],
            'permission_callback' => '__return_true',
        ]);

        // GET /tenants/{id}/account-keys
        register_rest_route('sarah-ai-server/v1', '/tenants/(?P<id>\d+)/account-keys', [
            'methods'             => 'GET',
            'callback'            => [$this, 'index'],
            'permission_callback' => '__return_true',
        ]);

        // DELETE /account-keys/{id}
        register_rest_route('sarah-ai-server/v1', '/account-keys/(?P<id>\d+)', [
            'methods'             => 'DELETE',
            'callback'            => [$this, 'revoke'],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * Issue a new account key for a tenant.
     * The raw key is returned ONCE in this response and is never recoverable after this point.
     *
     * Body: label (optional), expires_at (optional)
     */
    public function issue(\WP_REST_Request $request): \WP_REST_Response
    {
        $tenantId  = (int) $request->get_param('id');
        $label     = trim((string) ($request->get_param('label') ?? ''));
        $expiresAt = $request->get_param('expires_at');

        if (! $this->tenants->findById($tenantId)) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Tenant not found'], 404);
        }

        $rawKey   = bin2hex(random_bytes(32));
        $recordId = $this->accountKeys->issue($tenantId, $rawKey, $label, $expiresAt ?: null);

        return new \WP_REST_Response([
            'success' => true,
            'data'    => [
                'id'         => $recordId,
                'tenant_id'  => $tenantId,
                'label'      => $label ?: null,
                'expires_at' => $expiresAt ?: null,
                'raw_key'    => $rawKey,
                '_note'      => 'Store this key now. It will not be shown again.',
            ],
        ], 201);
    }

    /** List all account keys for a tenant. Raw keys are never included in list responses. */
    public function index(\WP_REST_Request $request): \WP_REST_Response
    {
        $tenantId = (int) $request->get_param('id');

        if (! $this->tenants->findById($tenantId)) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Tenant not found'], 404);
        }

        $keys = $this->accountKeys->findByTenant($tenantId);

        // Strip key_hash from all records — never expose hashes via API
        $keys = array_map(function (array $key) {
            unset($key['key_hash']);
            return $key;
        }, $keys);

        return new \WP_REST_Response(['success' => true, 'data' => $keys], 200);
    }

    /** Revoke an account key. This is permanent and cannot be undone. */
    public function revoke(\WP_REST_Request $request): \WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $this->accountKeys->revoke($id);
        return new \WP_REST_Response(['success' => true, 'message' => 'Account key revoked'], 200);
    }
}
