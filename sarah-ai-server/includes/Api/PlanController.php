<?php

declare(strict_types=1);

namespace SarahAiServer\Api;

use SarahAiServer\Infrastructure\AgentRepository;
use SarahAiServer\Infrastructure\PlanAgentRepository;
use SarahAiServer\Infrastructure\PlanRepository;

class PlanController
{
    private PlanRepository      $plans;
    private AgentRepository     $agents;
    private PlanAgentRepository $planAgents;

    public function __construct()
    {
        $this->plans      = new PlanRepository();
        $this->agents     = new AgentRepository();
        $this->planAgents = new PlanAgentRepository();
    }

    public function isAdmin(): bool
    {
        return current_user_can('manage_options');
    }

    public function registerRoutes(): void
    {
        // List all plans (with their allowed agents)
        register_rest_route('sarah-ai-server/v1', '/plans', [
            'methods'             => 'GET',
            'callback'            => [$this, 'index'],
            'permission_callback' => [$this, 'isAdmin'],
        ]);

        // Get agents allowed for a specific plan
        register_rest_route('sarah-ai-server/v1', '/plans/(?P<id>\d+)/agents', [
            'methods'             => 'GET',
            'callback'            => [$this, 'getAgents'],
            'permission_callback' => [$this, 'isAdmin'],
        ]);

        // Sync (replace) agents for a plan
        register_rest_route('sarah-ai-server/v1', '/plans/(?P<id>\d+)/agents', [
            'methods'             => 'POST',
            'callback'            => [$this, 'syncAgents'],
            'permission_callback' => [$this, 'isAdmin'],
        ]);

        // All active agents (used by setup wizard for agent assignment)
        register_rest_route('sarah-ai-server/v1', '/tenants/(?P<uuid>[0-9a-f-]{36})/available-agents', [
            'methods'             => 'GET',
            'callback'            => [$this, 'availableAgents'],
            'permission_callback' => [$this, 'isAdmin'],
        ]);
    }

    /** List all active plans, each with their allowed agent IDs and names. */
    public function index(\WP_REST_Request $request): \WP_REST_Response
    {
        $allAgents = $this->agents->allActive();

        $plans = array_map(function (array $plan) use ($allAgents) {
            $agentIds     = $this->planAgents->getAgentIdsForPlan((int) $plan['id']);
            $plan['agents'] = array_values(array_filter($allAgents, fn($a) => in_array((int) $a['id'], $agentIds, true)));
            return $plan;
        }, $this->plans->allActive());

        return new \WP_REST_Response(['success' => true, 'data' => $plans], 200);
    }

    /** Get agents allowed for a plan. */
    public function getAgents(\WP_REST_Request $request): \WP_REST_Response
    {
        $planId   = (int) $request->get_param('id');
        $plan     = $this->plans->findById($planId);
        if (! $plan) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Plan not found'], 404);
        }

        $agentIds = $this->planAgents->getAgentIdsForPlan($planId);
        $agents   = array_values(array_filter($this->agents->allActive(), fn($a) => in_array((int) $a['id'], $agentIds, true)));

        return new \WP_REST_Response(['success' => true, 'data' => $agents], 200);
    }

    /** Replace the agent list for a plan. Body: { agent_ids: [1,2,3] } */
    public function syncAgents(\WP_REST_Request $request): \WP_REST_Response
    {
        $planId = (int) $request->get_param('id');
        $plan   = $this->plans->findById($planId);
        if (! $plan) {
            return new \WP_REST_Response(['success' => false, 'message' => 'Plan not found'], 404);
        }

        $agentIds = (array) ($request->get_param('agent_ids') ?? []);
        $this->planAgents->syncAgents($planId, $agentIds);

        return new \WP_REST_Response(['success' => true, 'message' => 'Agents updated'], 200);
    }

    /** Returns all active agents — used by the setup wizard for agent assignment. */
    public function availableAgents(\WP_REST_Request $request): \WP_REST_Response
    {
        return new \WP_REST_Response(['success' => true, 'data' => $this->agents->allActive()], 200);
    }
}
