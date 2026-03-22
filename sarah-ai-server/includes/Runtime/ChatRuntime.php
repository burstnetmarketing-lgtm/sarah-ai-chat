<?php

declare(strict_types=1);

namespace SarahAiServer\Runtime;

use SarahAiServer\DB\ChatMessageTable;
use SarahAiServer\Infrastructure\ChatMessageRepository;
use SarahAiServer\Infrastructure\ChatSessionRepository;
use SarahAiServer\Infrastructure\CredentialValidator;
use SarahAiServer\Infrastructure\KnowledgeResourceRepository;
use SarahAiServer\Infrastructure\UsageLogRepository;

/**
 * Orchestrates the full chat request pipeline.
 *
 * Pipeline:
 *   1. Validate credentials → resolve tenant + site context
 *   2. Check operational eligibility (tenant active, site active, subscription, agent allowed)
 *   3. Resolve or create chat session
 *   4. Persist customer message
 *   5. Load active knowledge for the site
 *   6. Load prior message history for context
 *   7. Execute agent via provider-agnostic executor
 *   8. Persist assistant response
 *   9. Log usage
 *  10. Return structured response
 *
 * This class must not contain provider-specific logic. Provider dispatch
 * lives in the executor implementations (OpenAiAgentExecutor, etc.).
 */
class ChatRuntime
{
    private CredentialValidator $credentials;
    private RuntimeEligibilityChecker $eligibility;
    private ChatSessionRepository $sessions;
    private ChatMessageRepository $messages;
    private KnowledgeResourceRepository $knowledge;
    private UsageLogRepository $usageLog;

    public function __construct()
    {
        $this->credentials = new CredentialValidator();
        $this->eligibility = new RuntimeEligibilityChecker();
        $this->sessions    = new ChatSessionRepository();
        $this->messages    = new ChatMessageRepository();
        $this->knowledge   = new KnowledgeResourceRepository();
        $this->usageLog    = new UsageLogRepository();
    }

    /**
     * Handles a chat request and returns a structured response.
     *
     * @param string      $accountKey   Account-level tenant credential
     * @param string      $siteKey      Site-level credential
     * @param string      $message      The customer's message
     * @param string|null $sessionUuid  Existing session UUID for conversation continuation
     * @param array       $leadInfo     Optional: ['name', 'phone', 'email'] to attach to session
     * @return array{success: bool, session_uuid?: string, message?: string, agent?: string, error?: string}
     */
    public function handle(
        string $accountKey,
        string $siteKey,
        string $message,
        ?string $sessionUuid = null,
        array $leadInfo = []
    ): array {
        // ── Step 1: Credential validation ────────────────────────────────────
        $context = $this->credentials->resolveContext($accountKey, $siteKey);
        if (! $context) {
            return $this->error('invalid_credentials', 'Authentication failed.', 401);
        }

        $tenant = $context['tenant'];
        $site   = $context['site'];

        // ── Step 2: Operational eligibility ──────────────────────────────────
        $eligible = $this->eligibility->check($tenant, $site);
        if (! $eligible) {
            return $this->error('not_eligible', 'Service is not currently available.', 403);
        }

        $subscription = $eligible['subscription'];
        $agent        = $eligible['agent'];

        // ── Step 3: Session resolution ────────────────────────────────────────
        $session = null;

        if ($sessionUuid) {
            $session = $this->sessions->findByUuid($sessionUuid);

            // Verify session ownership — must belong to this tenant AND site
            if (
                ! $session ||
                (int) $session['tenant_id'] !== (int) $tenant['id'] ||
                (int) $session['site_id']   !== (int) $site['id']
            ) {
                return $this->error('invalid_session', 'Session not found or access denied.', 403);
            }
        }

        if (! $session) {
            $sessionId = $this->sessions->create(
                (int) $tenant['id'],
                (int) $site['id'],
                (int) $agent['id'],
                (int) ($subscription['id'] ?? 0)
            );
            $session = $this->sessions->findById($sessionId);
        }

        if (! $session) {
            return $this->error('session_error', 'Failed to initialise session.', 500);
        }

        // ── Step 3b: Attach lead info if provided ─────────────────────────────
        if (! empty($leadInfo)) {
            $this->sessions->updateLeadInfo(
                (int) $session['id'],
                $leadInfo['name']  ?? null,
                $leadInfo['phone'] ?? null,
                $leadInfo['email'] ?? null
            );
        }

        // ── Step 4: Persist customer message ──────────────────────────────────
        $this->messages->add((int) $session['id'], ChatMessageTable::ROLE_CUSTOMER, $message);

        // ── Step 5: Load site knowledge ───────────────────────────────────────
        $knowledge = $this->knowledge->findActiveBySite((int) $site['id']);

        // ── Step 6: Load prior history (last 20 messages for context window) ──
        $allHistory = $this->messages->findBySession((int) $session['id']);
        // Exclude the message we just inserted (last item); pass the rest as history
        $history = array_slice($allHistory, 0, -1);
        if (count($history) > 20) {
            $history = array_slice($history, -20);
        }

        // ── Step 7: Execute agent ─────────────────────────────────────────────
        $agentConfig = json_decode($agent['config'] ?? '{}', true) ?? [];
        $agent['config'] = $agentConfig;

        $executor = $this->resolveExecutor($agent);

        $result = $executor->execute([
            'agent'     => $agent,
            'tenant'    => $tenant,
            'site'      => $site,
            'session'   => $session,
            'message'   => $message,
            'history'   => $history,
            'knowledge' => $knowledge,
        ]);

        // ── Step 8: Persist assistant response ───────────────────────────────
        $this->messages->add(
            (int) $session['id'],
            ChatMessageTable::ROLE_ASSISTANT,
            $result['content'],
            [
                'provider'   => $result['provider']   ?? null,
                'model'      => $result['model']      ?? null,
                'tokens_in'  => $result['tokens_in']  ?? null,
                'tokens_out' => $result['tokens_out'] ?? null,
            ]
        );

        // ── Step 9: Log usage ─────────────────────────────────────────────────
        $this->usageLog->log(
            (int) $tenant['id'],
            (int) $site['id'],
            (int) $agent['id'],
            (int) ($subscription['id'] ?? 0),
            (int) $session['id'],
            'chat_message',
            $result['tokens_in']  ?? null,
            $result['tokens_out'] ?? null,
            ['model' => $result['model'] ?? null]
        );

        // ── Step 10: Return response ──────────────────────────────────────────
        return [
            'success'      => true,
            'session_uuid' => $session['uuid'],
            'message'      => $result['content'],
            'agent'        => $agent['name'],
        ];
    }

    private function resolveExecutor(array $agent): AgentExecutorInterface
    {
        $type = $agent['type'] ?? 'openai';

        return match ($type) {
            'openai' => new OpenAiAgentExecutor(),
            default  => new OpenAiAgentExecutor(),
        };
    }

    private function error(string $code, string $message, int $status): array
    {
        return [
            'success' => false,
            'error'   => $code,
            'message' => $message,
            'status'  => $status,
        ];
    }
}
