<?php

declare(strict_types=1);

namespace SarahAiServer\Runtime;

use SarahAiServer\Infrastructure\SettingsRepository;
use SarahAiServer\Processing\KnowledgePolicyFilter;
use SarahAiServer\Processing\SemanticRetriever;

/**
 * OpenAI-backed agent executor.
 *
 * Uses wp_remote_post to call the OpenAI Chat Completions API.
 * The API key is read from SettingsRepository (key: openai_api_key, namespace: platform).
 *
 * Agent config fields (from agents.config JSON column):
 *   model       — OpenAI model ID (e.g. gpt-4o-mini, gpt-4o, o1)
 *   max_tokens  — Maximum completion tokens
 *   temperature — Sampling temperature (0.0–2.0)
 *
 * This class must not be referenced directly by controllers or other non-runtime code.
 * All calls go through ChatRuntime which resolves the correct executor per agent type.
 */
class OpenAiAgentExecutor implements AgentExecutorInterface
{
    private const API_URL = 'https://api.openai.com/v1/chat/completions';

    private SettingsRepository $settings;

    public function __construct()
    {
        $this->settings = new SettingsRepository();
    }

    public function execute(array $context): array
    {
        $agent     = $context['agent'];
        $message   = $context['message'];
        $history   = $context['history'] ?? [];
        $knowledge = $context['knowledge'] ?? [];
        $site      = $context['site']      ?? [];

        $config      = is_array($agent['config']) ? $agent['config'] : (json_decode($agent['config'] ?? '{}', true) ?? []);
        $model       = $config['model']       ?? $agent['slug'];
        $maxTokens   = (int)   ($config['max_tokens']  ?? 1024);
        $temperature = (float) ($config['temperature'] ?? 0.7);

        $apiKey = $this->settings->get('openai_api_key', '', 'platform');
        if (! $apiKey) {
            return [
                'content'    => '[TEST MODE] Hello! I am ' . ($agent['name'] ?? 'Sarah AI') . '. OpenAI API key is not set — this is a mock response.',
                'tokens_in'  => null,
                'tokens_out' => null,
                'provider'   => 'openai',
                'model'      => $model,
            ];
        }

        // ── RAG retrieval ──────────────────────────────────────────────────────
        // Retrieve the most relevant chunks for this message via semantic search.
        // SemanticRetriever returns [] when no embeddings exist or key is missing,
        // which causes buildSystemPrompt() to fall back to raw source_content injection.
        $siteId          = (int) ($site['id'] ?? 0);
        $retrievedChunks = [];
        if ($siteId > 0 && ! empty($knowledge)) {
            $retriever       = new SemanticRetriever();
            $retrievedChunks = $retriever->retrieve($siteId, $message);
        }

        $messages = [];

        // System prompt: use retrieved chunks if available, else raw knowledge
        $siteIdentity = $context['site_identity'] ?? [];
        $systemPrompt = $this->buildSystemPrompt($agent, $knowledge, $siteIdentity, $retrievedChunks);
        if ($systemPrompt) {
            $messages[] = ['role' => 'system', 'content' => $systemPrompt];
        }

        // Prior conversation history (customer = user, assistant = assistant)
        foreach ($history as $msg) {
            $role = ($msg['role'] === 'customer') ? 'user' : 'assistant';
            $messages[] = ['role' => $role, 'content' => (string) $msg['content']];
        }

        // Incoming customer message
        $messages[] = ['role' => 'user', 'content' => $message];

        $body = [
            'model'    => $model,
            'messages' => $messages,
        ];

        // o1 models do not support temperature or max_tokens in the same way
        if (strpos($model, 'o1') === false) {
            $body['temperature'] = $temperature;
            $body['max_tokens']  = $maxTokens;
        } else {
            $body['max_completion_tokens'] = $maxTokens;
        }

        $response = wp_remote_post(self::API_URL, [
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type'  => 'application/json',
            ],
            'body'    => wp_json_encode($body),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            return [
                'content'    => 'Unable to reach the AI service. Please try again.',
                'tokens_in'  => null,
                'tokens_out' => null,
                'provider'   => 'openai',
                'model'      => $model,
            ];
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);

        $content    = $data['choices'][0]['message']['content'] ?? 'No response received.';
        $tokensIn   = (int) ($data['usage']['prompt_tokens']     ?? 0) ?: null;
        $tokensOut  = (int) ($data['usage']['completion_tokens'] ?? 0) ?: null;

        return [
            'content'    => $content,
            'tokens_in'  => $tokensIn,
            'tokens_out' => $tokensOut,
            'provider'   => 'openai',
            'model'      => $model,
        ];
    }

    /**
     * Builds the system prompt from agent config + site identity + knowledge context.
     *
     * Priority:
     *   1. If config.system_prompt is set — use it as the full prompt, append identity + knowledge below.
     *   2. Otherwise — compose from role, tone, description, and standard guardrails.
     *
     * Knowledge injection (mutually exclusive, retrieved takes priority):
     *   - $retrievedChunks non-empty → Phase 6.2 RAG: inject top-K semantically matched chunks
     *   - $retrievedChunks empty     → Phase ≤6.1 fallback: inject raw source_content from all active resources
     *
     * Site identity fields (site-level, override agent defaults):
     *   agent_display_name — name the agent uses when introducing itself
     *   intro_message      — how the agent introduces itself
     *
     * @param array  $agent           Agent row (including parsed config array)
     * @param array  $knowledge       Active knowledge resources for the site (fallback)
     * @param array  $siteIdentity    Site-level identity fields
     * @param array  $retrievedChunks Top-K chunks from SemanticRetriever (preferred)
     */
    private function buildSystemPrompt(
        array $agent,
        array $knowledge,
        array $siteIdentity = [],
        array $retrievedChunks = []
    ): string {
        $config      = is_array($agent['config']) ? $agent['config'] : [];
        $customPrompt      = trim((string) ($config['system_prompt'] ?? ''));
        $role              = trim((string) ($config['role']          ?? ''));
        $tone              = trim((string) ($config['tone']          ?? ''));
        $description       = trim((string) ($agent['description']   ?? ''));
        $agentDisplayName  = trim((string) ($siteIdentity['agent_display_name'] ?? ''));
        $introMessage      = trim((string) ($siteIdentity['intro_message']      ?? ''));

        // ── Knowledge section (RAG or raw fallback) ────────────────────────
        $knowledgeSection = '';
        if (! empty($retrievedChunks)) {
            // Phase 6.2: inject semantically retrieved chunks
            $parts = [];
            foreach ($retrievedChunks as $item) {
                $text  = trim((string) ($item['chunk_text'] ?? ''));
                $title = trim((string) ($item['resource_title'] ?? ''));
                if (! $text) {
                    continue;
                }
                $parts[] = $title ? "### {$title}\n{$text}" : $text;
            }
            if ($parts) {
                $knowledgeSection = "\n\n## Knowledge Base\n\nUse the following information to answer questions. Rely only on what is provided below — do not invent facts.\n\n" . implode("\n\n", $parts);
            }
        } else {
            // Fallback: raw source_content — public resources only (visibility enforcement)
            $publicKnowledge = KnowledgePolicyFilter::publicOnly($knowledge);
            $knowledgeParts  = [];
            foreach ($publicKnowledge as $resource) {
                $content = trim((string) ($resource['source_content'] ?? ''));
                if (! $content) {
                    continue;
                }
                $title = trim((string) ($resource['title'] ?? ''));
                $knowledgeParts[] = $title ? "### {$title}\n{$content}" : $content;
            }
            if ($knowledgeParts) {
                $knowledgeSection = "\n\n## Knowledge Base\n\nUse the following information to answer questions. Rely only on what is provided below — do not invent facts.\n\n" . implode("\n\n", $knowledgeParts);
            }
        }

        // ── Identity section (appended to both modes) ─────────────────────
        $identitySection = '';
        if ($agentDisplayName) {
            $identitySection .= "\nYour name is {$agentDisplayName}.";
        }
        if ($introMessage) {
            $identitySection .= "\n{$introMessage}";
        }

        // ── Custom override ────────────────────────────────────────────────
        if ($customPrompt) {
            return $customPrompt . $identitySection . $knowledgeSection . $this->buildStructuredOutputInstruction();
        }

        // ── Composed prompt ────────────────────────────────────────────────
        $roleLabel = $role ?: 'helpful assistant';
        $lines     = ["You are a {$roleLabel}."];

        if ($description) {
            $lines[] = $description;
        }

        if ($tone) {
            $toneMap = [
                'friendly'     => 'Be warm, approachable, and friendly in your responses.',
                'professional' => 'Maintain a professional and formal tone at all times.',
                'concise'      => 'Be brief and to the point. Avoid unnecessary filler.',
                'formal'       => 'Use formal language. Avoid contractions and casual expressions.',
            ];
            $lines[] = $toneMap[$tone] ?? "Tone: {$tone}.";
        }

        $lines[] = '';
        $lines[] = '## Behaviour Rules';
        $lines[] = '- Answer only what you know. If you are unsure, say so clearly rather than guessing.';
        $lines[] = '- Do not make up facts, names, prices, dates, or any information not provided to you.';
        $lines[] = '- Stay within your defined role and domain. Do not provide advice outside your area.';
        $lines[] = '- If a question is outside your scope, politely say you cannot help with that.';
        $lines[] = '- Do not generate harmful, misleading, or offensive content.';

        return implode("\n", $lines) . $identitySection . $knowledgeSection . $this->buildStructuredOutputInstruction();
    }

    /**
     * Returns the structured output instruction appended to every system prompt.
     *
     * When the AI response contains contact information, it should append a
     * <sarah_card> JSON block so the widget can render a formatted contact card
     * instead of displaying raw text.
     *
     * The widget strips the tag from the displayed bubble text and renders
     * ContactCard alongside it.
     *
     * Canonical key names:
     *   contact.phone_admin, contact.phone_marketing,
     *   contact.website, contact.email_support,
     *   business.address, business.hours
     */
    private function buildStructuredOutputInstruction(): string
    {
        $safeResponse = KnowledgePolicyFilter::restrictedDataSafeResponse();

        return <<<PROMPT


## Restricted Information Policy

If a user asks for information that is not present in your Knowledge Base, respond with:
"{$safeResponse}"
Do not guess, fabricate, or infer restricted details. This response is mandatory — do not improvise.

## Structured Contact Output

When your response mentions specific contact information (phone numbers, email addresses, website URLs, or physical addresses), append a structured data block at the VERY END of your response using this exact format:

<sarah_card>{"type":"contact","fields":[{"key":"contact.phone_admin","label":"Phone","value":"0449948867"}]}</sarah_card>

Rules:
- Only include fields that are actually mentioned in your response text
- Do not include this block if your response contains no contact information
- The block must appear on its own line at the very end of your response
- Canonical key names: contact.phone_admin, contact.phone_marketing, contact.phone_sales, contact.website, contact.email_support, contact.email_sales, business.address, business.hours, business.name
PROMPT;
    }
}
