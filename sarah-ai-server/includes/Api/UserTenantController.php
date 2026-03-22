<?php

declare(strict_types=1);

namespace SarahAiServer\Api;

use SarahAiServer\Infrastructure\TenantRepository;
use SarahAiServer\Infrastructure\UserTenantRepository;
use SarahAiServer\Infrastructure\EmailTemplateRepository;

class UserTenantController
{
    private UserTenantRepository $userTenants;
    private TenantRepository $tenants;
    private EmailTemplateRepository $emailTemplates;

    public function __construct()
    {
        $this->userTenants    = new UserTenantRepository();
        $this->tenants        = new TenantRepository();
        $this->emailTemplates = new EmailTemplateRepository();
    }

    public function registerRoutes(): void
    {
        // POST /tenants/{id}/users
        register_rest_route('sarah-ai-server/v1', '/tenants/(?P<id>\d+)/users', [
            'methods'             => 'POST',
            'callback'            => [$this, 'associate'],
            'permission_callback' => '__return_true',
        ]);

        // GET /tenants/{id}/users
        register_rest_route('sarah-ai-server/v1', '/tenants/(?P<id>\d+)/users', [
            'methods'             => 'GET',
            'callback'            => [$this, 'index'],
            'permission_callback' => '__return_true',
        ]);

        // DELETE /tenants/{id}/users/{wpUserId}
        register_rest_route('sarah-ai-server/v1', '/tenants/(?P<id>\d+)/users/(?P<wp_user_id>\d+)', [
            'methods'             => 'DELETE',
            'callback'            => [$this, 'deactivate'],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * Associate a WordPress user with a tenant.
     *
     * Body:
     *   wp_user_id     (required) — WordPress user ID
     *   role           (optional) — 'owner' | 'admin' | 'member' (default: 'member')
     *   send_welcome   (optional) — '1' to trigger welcome email
     */
    public function associate(\WP_REST_Request $request): \WP_REST_Response
    {
        $tenantId   = (int) $request->get_param('id');
        $wpUserId   = (int) $request->get_param('wp_user_id');
        $role       = trim((string) ($request->get_param('role') ?? 'member'));
        $sendWelcome = $request->get_param('send_welcome') === '1';

        if (! $wpUserId) {
            return new \WP_REST_Response(['success' => false, 'message' => 'wp_user_id is required'], 400);
        }

        $allowedRoles = ['owner', 'admin', 'member'];
        if (! in_array($role, $allowedRoles, true)) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'role must be one of: ' . implode(', ', $allowedRoles),
            ], 400);
        }

        if (! $this->tenants->findById($tenantId)) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Tenant not found'], 404);
        }

        $wpUser = get_userdata($wpUserId);
        if (! $wpUser) {
            return new \WP_REST_Response(['success' => false, 'message' => 'WordPress user not found'], 404);
        }

        $this->userTenants->associate($wpUserId, $tenantId, $role);

        if ($sendWelcome) {
            $this->sendWelcomeEmail($wpUser, $tenantId);
        }

        return new \WP_REST_Response([
            'success' => true,
            'data'    => [
                'wp_user_id' => $wpUserId,
                'tenant_id'  => $tenantId,
                'role'       => $role,
            ],
        ], 200);
    }

    /** List all user associations for a tenant. */
    public function index(\WP_REST_Request $request): \WP_REST_Response
    {
        $tenantId = (int) $request->get_param('id');

        if (! $this->tenants->findById($tenantId)) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Tenant not found'], 404);
        }

        $associations = $this->userTenants->findByTenant($tenantId);

        // Enrich with basic WP user info
        $result = array_map(function (array $assoc) {
            $wpUser = get_userdata((int) $assoc['wp_user_id']);
            return array_merge($assoc, [
                'user_login' => $wpUser ? $wpUser->user_login : null,
                'user_email' => $wpUser ? $wpUser->user_email : null,
            ]);
        }, $associations);

        return new \WP_REST_Response(['success' => true, 'data' => $result], 200);
    }

    /** Deactivate a user-tenant association. */
    public function deactivate(\WP_REST_Request $request): \WP_REST_Response
    {
        $tenantId = (int) $request->get_param('id');
        $wpUserId = (int) $request->get_param('wp_user_id');

        $this->userTenants->deactivate($wpUserId, $tenantId);
        return new \WP_REST_Response(['success' => true, 'message' => 'Association deactivated'], 200);
    }

    private function sendWelcomeEmail(\WP_User $wpUser, int $tenantId): void
    {
        $rendered = $this->emailTemplates->render('welcome', [
            'name'        => $wpUser->display_name ?: $wpUser->user_login,
            'username'    => $wpUser->user_login,
            'site_url'    => get_site_url(),
            'trial_days'  => '14',
        ]);

        if ($rendered && $wpUser->user_email) {
            wp_mail($wpUser->user_email, $rendered['subject'], $rendered['body']);
        }
    }
}
