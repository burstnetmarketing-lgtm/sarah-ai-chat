<?php

declare(strict_types=1);

namespace SarahAiServer\Api;

use SarahAiServer\Infrastructure\AgentRepository;
use SarahAiServer\Infrastructure\SiteRepository;
use SarahAiServer\Infrastructure\SiteAgentRepository;

class AgentController
{
    private AgentRepository $agents;
    private SiteRepository $sites;
    private SiteAgentRepository $siteAgents;

    public function __construct()
    {
        $this->agents     = new AgentRepository();
        $this->sites      = new SiteRepository();
        $this->siteAgents = new SiteAgentRepository();
    }

    public function registerRoutes(): void
    {
        // GET /agents
        register_rest_route('sarah-ai-server/v1', '/agents', [
            'methods'             => 'GET',
            'callback'            => [$this, 'index'],
            'permission_callback' => '__return_true',
        ]);

        // POST /sites/{id}/agent
        register_rest_route('sarah-ai-server/v1', '/sites/(?P<id>\d+)/agent', [
            'methods'             => 'POST',
            'callback'            => [$this, 'assign'],
            'permission_callback' => '__return_true',
        ]);
    }

    /** List all active agents available for assignment. */
    public function index(\WP_REST_Request $request): \WP_REST_Response
    {
        return new \WP_REST_Response(['success' => true, 'data' => $this->agents->allActive()], 200);
    }

    /**
     * Assign an agent to a site.
     * Updates the denormalized active_agent_id fast-path on the site row
     * and logs the event in the site_agents audit table.
     *
     * Body: agent_id (required)
     */
    public function assign(\WP_REST_Request $request): \WP_REST_Response
    {
        $siteId  = (int) $request->get_param('id');
        $agentId = (int) $request->get_param('agent_id');

        if (! $agentId) {
            return new \WP_REST_Response(['success' => false, 'message' => 'agent_id is required'], 400);
        }

        $site = $this->sites->findById($siteId);
        if (! $site) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Site not found'], 404);
        }

        $agent = $this->agents->findById($agentId);
        if (! $agent) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Agent not found'], 404);
        }

        // Update denormalized fast-path
        $this->sites->updateActiveAgent($siteId, $agentId);

        // Log to audit table
        $this->siteAgents->log($siteId, $agentId);

        return new \WP_REST_Response([
            'success' => true,
            'data'    => [
                'site'  => $this->sites->findById($siteId),
                'agent' => $agent,
            ],
        ], 200);
    }
}
