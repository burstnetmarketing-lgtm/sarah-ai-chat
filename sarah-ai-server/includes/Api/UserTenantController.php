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

    public function isAdmin(): bool
    {
        return current_user_can('manage_options');
    }

    public function registerRoutes(): void
    {
        register_rest_route('sarah-ai-server/v1', '/tenants/(?P<uuid>[0-9a-f-]{36})/users', [
            'methods'             => 'POST',
            'callback'            => [$this, 'associate'],
            'permission_callback' => [$this, 'isAdmin'],
        ]);

        register_rest_route('sarah-ai-server/v1', '/tenants/(?P<uuid>[0-9a-f-]{36})/users', [
            'methods'             => 'GET',
            'callback'            => [$this, 'index'],
            'permission_callback' => [$this, 'isAdmin'],
        ]);

        register_rest_route('sarah-ai-server/v1', '/tenants/(?P<uuid>[0-9a-f-]{36})/users/(?P<wp_user_id>\d+)', [
            'methods'             => 'DELETE',
            'callback'            => [$this, 'deactivate'],
            'permission_callback' => [$this, 'isAdmin'],
        ]);
    }

    public function associate(\WP_REST_Request $request): \WP_REST_Response
    {
        $tenant = $this->tenants->findByUuid((string) $request->get_param('uuid'));
        if (! $tenant) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Tenant not found'], 404);
        }
        $tenantId = (int) $tenant['id'];

        $username    = trim((string) ($request->get_param('username') ?? ''));
        $email       = trim((string) ($request->get_param('email') ?? ''));
        $password    = (string) ($request->get_param('password') ?? '');
        $sendWelcome = $request->get_param('send_welcome') === true || $request->get_param('send_welcome') === '1';

        if (! $username || ! $email || ! $password) {
            return new \WP_REST_Response(['success' => false, 'message' => 'username, email, and password are required'], 400);
        }

        if (username_exists($username)) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Username already exists'], 409);
        }

        if (email_exists($email)) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Email already in use'], 409);
        }

        $wpUserId = wp_create_user($username, $password, $email);
        if (is_wp_error($wpUserId)) {
            return new \WP_REST_Response(['success' => false, 'message' => $wpUserId->get_error_message()], 500);
        }

        // Always assign the minimal WordPress role
        $wpUser = new \WP_User($wpUserId);
        $wpUser->set_role('subscriber');

        // Sarah tenant role is always 'member' for end-customers
        $this->userTenants->associate($wpUserId, $tenantId, 'member');

        if ($sendWelcome) {
            $this->sendWelcomeEmail($wpUser, $tenantId);
        }

        return new \WP_REST_Response(['success' => true, 'data' => [
            'wp_user_id' => $wpUserId,
            'username'   => $username,
            'email'      => $email,
            'tenant_id'  => $tenantId,
            'role'       => 'member',
        ]], 200);
    }

    public function index(\WP_REST_Request $request): \WP_REST_Response
    {
        $tenant = $this->tenants->findByUuid((string) $request->get_param('uuid'));
        if (! $tenant) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Tenant not found'], 404);
        }

        $associations = $this->userTenants->findByTenant((int) $tenant['id']);

        $result = array_map(function (array $assoc) {
            $wpUser = get_userdata((int) $assoc['wp_user_id']);
            return array_merge($assoc, [
                'user_login' => $wpUser ? $wpUser->user_login : null,
                'user_email' => $wpUser ? $wpUser->user_email : null,
            ]);
        }, $associations);

        return new \WP_REST_Response(['success' => true, 'data' => $result], 200);
    }

    public function deactivate(\WP_REST_Request $request): \WP_REST_Response
    {
        $tenant = $this->tenants->findByUuid((string) $request->get_param('uuid'));
        if (! $tenant) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Tenant not found'], 404);
        }

        $wpUserId = (int) $request->get_param('wp_user_id');
        $this->userTenants->deactivate($wpUserId, (int) $tenant['id']);
        return new \WP_REST_Response(['success' => true, 'message' => 'Association deactivated'], 200);
    }

    private function sendWelcomeEmail(\WP_User $wpUser, int $tenantId): void
    {
        $rendered = $this->emailTemplates->render('welcome', [
            'name'       => $wpUser->display_name ?: $wpUser->user_login,
            'username'   => $wpUser->user_login,
            'site_url'   => get_site_url(),
            'trial_days' => '14',
        ]);

        if ($rendered && $wpUser->user_email) {
            wp_mail($wpUser->user_email, $rendered['subject'], $rendered['body']);
        }
    }
}
