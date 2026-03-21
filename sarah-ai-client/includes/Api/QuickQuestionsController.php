<?php

declare(strict_types=1);

namespace SarahAiClient\Api;

use SarahAiClient\Infrastructure\QuickQuestionsRepository;
use WP_REST_Request;
use WP_REST_Response;

class QuickQuestionsController
{
    private QuickQuestionsRepository $repo;

    public function __construct(QuickQuestionsRepository $repo)
    {
        $this->repo = $repo;
    }

    public function registerRoutes(): void
    {
        register_rest_route('sarah-ai-client/v1', '/quick-questions', [
            ['methods' => 'GET',  'callback' => [$this, 'index'], 'permission_callback' => [$this, 'can']],
            ['methods' => 'POST', 'callback' => [$this, 'store'], 'permission_callback' => [$this, 'can']],
        ]);
        register_rest_route('sarah-ai-client/v1', '/quick-questions/(?P<id>\d+)', [
            ['methods' => 'PUT',    'callback' => [$this, 'update'],  'permission_callback' => [$this, 'can']],
            ['methods' => 'DELETE', 'callback' => [$this, 'destroy'], 'permission_callback' => [$this, 'can']],
        ]);
    }

    public function can(): bool
    {
        return current_user_can('manage_options');
    }

    public function index(): WP_REST_Response
    {
        return new WP_REST_Response(['success' => true, 'data' => $this->repo->all()], 200);
    }

    public function store(WP_REST_Request $request): WP_REST_Response
    {
        $question = trim((string) ($request['question'] ?? ''));
        if ($question === '') {
            return new WP_REST_Response(['success' => false, 'message' => 'Question is required.'], 422);
        }
        $id = $this->repo->create($question);
        return new WP_REST_Response(['success' => true, 'data' => $this->repo->find($id)], 201);
    }

    public function update(WP_REST_Request $request): WP_REST_Response
    {
        $id       = (int) $request['id'];
        $question = trim((string) ($request['question'] ?? ''));
        $enabled  = filter_var($request['is_enabled'] ?? true, FILTER_VALIDATE_BOOLEAN);
        if ($question === '') {
            return new WP_REST_Response(['success' => false, 'message' => 'Question is required.'], 422);
        }
        $this->repo->update($id, $question, $enabled);
        return new WP_REST_Response(['success' => true, 'data' => $this->repo->find($id)], 200);
    }

    public function destroy(WP_REST_Request $request): WP_REST_Response
    {
        $this->repo->delete((int) $request['id']);
        return new WP_REST_Response(['success' => true], 200);
    }
}
