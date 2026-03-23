<?php

declare(strict_types=1);

namespace SarahAiServer\Runtime;

use SarahAiServer\Infrastructure\AgentRepository;
use SarahAiServer\Infrastructure\PlanAgentRepository;

/**
 * Verifies that a resolved tenant+site context is operationally eligible.
 *
 * Credential validity and license check are handled upstream by CredentialValidator.
 * This checker verifies: tenant/site active, agent assigned and allowed by plan.
 *
 * @return array{agent: array}|null
 */
class RuntimeEligibilityChecker
{
    private AgentRepository     $agents;
    private PlanAgentRepository $planAgents;

    public function __construct()
    {
        $this->agents     = new AgentRepository();
        $this->planAgents = new PlanAgentRepository();
    }

    /**
     * @param array $tenant Resolved tenant record
     * @param array $site   Resolved site record (must include plan_id, active_agent_id)
     * @return array{agent: array}|null
     */
    public function check(array $tenant, array $site): ?array
    {
        // 1. Tenant must be active
        if (($tenant['status'] ?? '') !== 'active') {
            return null;
        }

        // 2. Site must be active
        if (($site['status'] ?? '') !== 'active') {
            return null;
        }

        // 3. Site must have an agent assigned
        $agentId = (int) ($site['active_agent_id'] ?? 0);
        if (! $agentId) {
            return null;
        }

        // 4. Agent must exist and be active
        $agent = $this->agents->findById($agentId);
        if (! $agent || ($agent['status'] ?? '') !== 'active') {
            return null;
        }

        // 5. Agent must be allowed by the site's plan (if plan has restrictions)
        $planId        = (int) ($site['plan_id'] ?? 0);
        $allowedAgents = $this->planAgents->getAgentIdsForPlan($planId);
        if (! empty($allowedAgents) && ! in_array($agentId, $allowedAgents, true)) {
            return null;
        }

        return ['agent' => $agent];
    }
}
