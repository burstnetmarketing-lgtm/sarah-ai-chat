<?php

declare(strict_types=1);

namespace SarahAiServer\Runtime;

/**
 * Provider-agnostic contract for agent execution.
 *
 * Implementations must accept a structured execution context and return
 * a response array. The runtime pipeline must not assume any specific
 * provider — OpenAI is the first implementation, not the permanent architecture.
 */
interface AgentExecutorInterface
{
    /**
     * Executes the agent and returns a response.
     *
     * @param array $context {
     *   agent:     array   — agent record (slug, type, config JSON-decoded)
     *   tenant:    array   — resolved tenant
     *   site:      array   — resolved site
     *   session:   array   — current chat session
     *   message:   string  — the incoming customer message
     *   history:   array   — prior messages [{role, content}, ...]
     *   knowledge: array   — active site knowledge resources
     * }
     * @return array{
     *   content:    string,
     *   tokens_in:  int|null,
     *   tokens_out: int|null,
     *   provider:   string,
     *   model:      string|null
     * }
     */
    public function execute(array $context): array;
}
