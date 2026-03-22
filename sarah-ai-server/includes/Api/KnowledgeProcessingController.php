<?php

declare(strict_types=1);

namespace SarahAiServer\Api;

use SarahAiServer\Infrastructure\KnowledgeChunkRepository;
use SarahAiServer\Infrastructure\KnowledgeResourceRepository;
use SarahAiServer\Processing\KnowledgeProcessingService;

/**
 * REST endpoints for the knowledge processing pipeline.
 *
 * Routes:
 *   POST /knowledge-resources/{uuid}/process  — trigger processing or reprocessing
 *   GET  /knowledge-resources/{uuid}/chunks   — list chunks produced by processing
 */
class KnowledgeProcessingController
{
    private KnowledgeResourceRepository $resources;
    private KnowledgeChunkRepository    $chunks;

    public function __construct()
    {
        $this->resources = new KnowledgeResourceRepository();
        $this->chunks    = new KnowledgeChunkRepository();
    }

    public function registerRoutes(): void
    {
        register_rest_route('sarah-ai-server/v1', '/knowledge-resources/(?P<uuid>[a-f0-9\-]{36})/process', [
            'methods'             => 'POST',
            'callback'            => [$this, 'process'],
            'permission_callback' => fn() => current_user_can('manage_options'),
        ]);

        register_rest_route('sarah-ai-server/v1', '/knowledge-resources/(?P<uuid>[a-f0-9\-]{36})/chunks', [
            'methods'             => 'GET',
            'callback'            => [$this, 'listChunks'],
            'permission_callback' => fn() => current_user_can('manage_options'),
        ]);
    }

    /**
     * POST /knowledge-resources/{uuid}/process
     *
     * Triggers (or re-triggers) the full processing pipeline for the given resource.
     * Safe to call multiple times — old chunks are replaced.
     */
    public function process(\WP_REST_Request $request): \WP_REST_Response
    {
        $uuid     = (string) $request->get_param('uuid');
        $resource = $this->resources->findByUuid($uuid);

        if (! $resource) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Resource not found.'], 404);
        }

        $service = new KnowledgeProcessingService();
        $result  = $service->process((int) $resource['id']);

        return new \WP_REST_Response([
            'success' => $result['success'],
            'chunks'  => $result['chunks'],
            'message' => $result['message'],
        ], 200);
    }

    /**
     * GET /knowledge-resources/{uuid}/chunks
     *
     * Returns all chunks for the resource (without embedding vectors — too large for UI).
     */
    public function listChunks(\WP_REST_Request $request): \WP_REST_Response
    {
        $uuid     = (string) $request->get_param('uuid');
        $resource = $this->resources->findByUuid($uuid);

        if (! $resource) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Resource not found.'], 404);
        }

        $chunks = $this->chunks->findByResource((int) $resource['id']);

        return new \WP_REST_Response([
            'success'            => true,
            'resource_uuid'      => $uuid,
            'processing_status'  => $resource['processing_status'],
            'chunk_count'        => count($chunks),
            'chunks'             => $chunks,
        ]);
    }
}
