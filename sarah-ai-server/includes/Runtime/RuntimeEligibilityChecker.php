<?php

declare(strict_types=1);

namespace SarahAiServer\Runtime;

use SarahAiServer\Infrastructure\AgentRepository;
use SarahAiServer\Infrastructure\PlanAgentRepository;
use SarahAiServer\Infrastructure\SubscriptionRepository;

/**
 * Verifies that a resolved tenant+site context is operationally eligible.
 *
 * Credential validity (account key + site key) is a prerequisite — call
 * CredentialValidator::resolveContext() first. This checker answers the
 * additional question: even if credentials are valid, can this request execute?
 *
 * Returns a result array on success or null on any eligibility failure.
 * Callers must not expose which specific check failed to the client.
 */
class RuntimeEligibilityChecker
{
    private SubscriptionRepository $subscriptions;
    private AgentRepository $agents;
    private PlanAgentRepository $planAgents;

    public function __construct()
    {
        $this->subscriptions = new SubscriptionRepository();
        $this->agents        = new AgentRepository();
        $this->planAgents    = new PlanAgentRepository();
    }

    /**
     * Checks all eligibility conditions.
     *
     * @param array $tenant Resolved tenant record
     * @param array $site   Resolved site record
     * @return array{subscription: array, agent: array}|null
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

        // 3. Active subscription must exist in an operational state
        $subscription = $this->subscriptions->findActiveByTenant((int) $tenant['id']);
        if (! $subscription) {
            return null;
        }
        if (! in_array($subscription['status'], ['trialing', 'active'], true)) {
            return null;
        }

        // 4. Site must have an agent assigned
        $agentId = (int) ($site['active_agent_id'] ?? 0);
        if (! $agentId) {
            return null;
        }

        // 5. Agent must exist and be active
        $agent = $this->agents->findById($agentId);
        if (! $agent || ($agent['status'] ?? '') !== 'active') {
            return null;
        }

        // 6. Agent must be allowed by the plan (if plan has restrictions)
        $planId         = (int) ($subscription['plan_id'] ?? 0);
        $allowedAgents  = $this->planAgents->getAgentIdsForPlan($planId);
        if (! empty($allowedAgents) && ! in_array($agentId, $allowedAgents, true)) {
            return null;
        }

        return [
            'subscription' => $subscription,
            'agent'        => $agent,
        ];
    }
}
