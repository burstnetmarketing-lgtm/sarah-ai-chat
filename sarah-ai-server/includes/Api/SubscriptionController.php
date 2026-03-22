<?php

declare(strict_types=1);

namespace SarahAiServer\Api;

use SarahAiServer\Infrastructure\SubscriptionRepository;

class SubscriptionController
{
    private SubscriptionRepository $subscriptions;

    public function __construct()
    {
        $this->subscriptions = new SubscriptionRepository();
    }

    public function isAdmin(): bool
    {
        return current_user_can('manage_options');
    }

    public function registerRoutes(): void
    {
        register_rest_route('sarah-ai-server/v1', '/subscriptions', [
            'methods'             => 'GET',
            'callback'            => [$this, 'index'],
            'permission_callback' => [$this, 'isAdmin'],
        ]);

        register_rest_route('sarah-ai-server/v1', '/subscriptions/(?P<id>\d+)/status', [
            'methods'             => 'POST',
            'callback'            => [$this, 'updateStatus'],
            'permission_callback' => [$this, 'isAdmin'],
        ]);
    }

    public function index(\WP_REST_Request $request): \WP_REST_Response
    {
        $status = trim((string) ($request->get_param('status') ?? ''));
        $data   = $this->subscriptions->all($status);
        return new \WP_REST_Response(['success' => true, 'data' => $data], 200);
    }

    public function updateStatus(\WP_REST_Request $request): \WP_REST_Response
    {
        $id     = (int) $request->get_param('id');
        $status = trim((string) ($request->get_param('status') ?? ''));

        $allowed = ['trialing', 'active', 'expired', 'cancelled'];
        if (! in_array($status, $allowed, true)) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Invalid status'], 400);
        }

        $sub = $this->subscriptions->findById($id);
        if (! $sub) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Subscription not found'], 404);
        }

        $this->subscriptions->updateStatus($id, $status);
        return new \WP_REST_Response(['success' => true, 'message' => 'Status updated'], 200);
    }
}
