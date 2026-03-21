<?php

declare(strict_types=1);

namespace SarahAiClient\Api;

use SarahAiClient\Infrastructure\SettingsRepository;
use WP_REST_Request;
use WP_REST_Response;

class AppearanceController
{
    private SettingsRepository $repo;

    public function __construct(SettingsRepository $repo)
    {
        $this->repo = $repo;
    }

    public function registerRoutes(): void
    {
        register_rest_route('sarah-ai-client/v1', '/appearance', [
            ['methods' => 'GET', 'callback' => [$this, 'index'], 'permission_callback' => [$this, 'can']],
        ]);
        register_rest_route('sarah-ai-client/v1', '/appearance/draft', [
            ['methods' => 'POST', 'callback' => [$this, 'saveDraft'], 'permission_callback' => [$this, 'can']],
        ]);
        register_rest_route('sarah-ai-client/v1', '/appearance/publish', [
            ['methods' => 'POST', 'callback' => [$this, 'publish'], 'permission_callback' => [$this, 'can']],
        ]);
        register_rest_route('sarah-ai-client/v1', '/appearance/discard', [
            ['methods' => 'POST', 'callback' => [$this, 'discard'], 'permission_callback' => [$this, 'can']],
        ]);
    }

    public function can(): bool
    {
        return current_user_can('manage_options');
    }

    public function index(): WP_REST_Response
    {
        return new WP_REST_Response(['success' => true, 'data' => $this->repo->getAllAppearance()], 200);
    }

    public function saveDraft(WP_REST_Request $request): WP_REST_Response
    {
        $values = [];
        foreach (SettingsRepository::APPEARANCE_KEYS as $key) {
            if ($request->has_param($key)) {
                $values[$key] = (string) $request[$key];
            }
        }
        if (empty($values)) {
            return new WP_REST_Response(['success' => false, 'message' => 'No values provided.'], 422);
        }
        $this->repo->saveDraft($values);
        return new WP_REST_Response(['success' => true, 'data' => $this->repo->getAllAppearance()], 200);
    }

    public function publish(WP_REST_Request $request): WP_REST_Response
    {
        $this->repo->publish();
        return new WP_REST_Response(['success' => true, 'data' => $this->repo->getAllAppearance()], 200);
    }

    public function discard(WP_REST_Request $request): WP_REST_Response
    {
        $this->repo->discardDraft();
        return new WP_REST_Response(['success' => true, 'data' => $this->repo->getAllAppearance()], 200);
    }
}
