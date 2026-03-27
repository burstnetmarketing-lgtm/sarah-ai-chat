<?php

declare(strict_types=1);

namespace SarahAiServer\Runtime;

use SarahAiServer\Infrastructure\SettingsRepository;
use SarahAiServer\Infrastructure\SiteApiKeyRepository;
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

    private SettingsRepository   $settings;
    private SiteApiKeyRepository $siteApiKeys;

    public function __construct()
    {
        $this->settings    = new SettingsRepository();
        $this->siteApiKeys = new SiteApiKeyRepository();
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

        // Use site's own OpenAI key if configured, otherwise fall back to platform key
        // (only when allow_platform_openai_key is enabled in platform settings).
        $siteId = (int) ($site['id'] ?? 0);
        $apiKey = ($siteId > 0) ? ($this->siteApiKeys->get($siteId, 'openai') ?? '') : '';
        if ($apiKey === '') {
            $allowFallback = $this->settings->get('allow_platform_openai_key', '0', 'platform') === '1';
            if ($allowFallback) {
                $apiKey = $this->settings->get('openai_api_key', '', 'platform');
            }
        }
        if (! $apiKey) {
            return [
                'content'    => '[TEST MODE] Hello! I am ' . ($agent['name'] ?? 'Sarah AI') . '. OpenAI API key is not set — this is a mock response.',
                'tokens_in'  => null,
                'tokens_out' => null,
                'provider'   => 'openai',
                'model'      => $model,
            ];
        }

        // ── Merge site-level agent config overrides ────────────────────────────
        $siteOverrides = $context['site_agent_config'] ?? [];
        if (! empty($siteOverrides)) {
            $base = is_array($agent['config']) ? $agent['config'] : [];
            $agent['config'] = $this->mergeAgentConfig($base, $siteOverrides);
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
        $language     = trim((string) ($context['language'] ?? ''));
        $systemPrompt = $this->buildSystemPrompt($agent, $knowledge, $siteIdentity, $retrievedChunks, $language);
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
        array $retrievedChunks = [],
        string $language = ''
    ): string {
        $config      = is_array($agent['config']) ? $agent['config'] : [];
        $customPrompt      = trim((string) ($config['system_prompt'] ?? ''));
        $role              = trim((string) ($config['role']          ?? ''));
        $tone              = trim((string) ($config['tone']          ?? ''));
        $toneCustom        = trim((string) ($config['tone_custom']   ?? ''));

        $allowGeneralKnowledge = (bool) ($config['allow_general_knowledge'] ?? true);
        $noClosingQuestion     = (bool) ($config['no_closing_question']     ?? true);
        $handleVagueQueries    = (bool) ($config['handle_vague_queries']    ?? true);
        $customRules           = trim((string) ($config['custom_rules']           ?? ''));
        $knowledgeInstruction  = trim((string) ($config['knowledge_instruction']  ?? ''));
        $knowledgeFallback     = trim((string) ($config['knowledge_fallback']     ?? ''));
        $restrictedResponse    = trim((string) ($config['restricted_response']    ?? ''));
        $description       = trim((string) ($agent['description']   ?? ''));
        $agentDisplayName  = trim((string) ($siteIdentity['agent_display_name'] ?? ''));
        $introMessage      = trim((string) ($siteIdentity['intro_message']      ?? ''));

        // ── Knowledge section (RAG or raw fallback) ────────────────────────
        $knowledgeSection = '';
        $kbInstruction = $knowledgeInstruction ?: 'Present this information in a clear, helpful, and organized way. Use it to answer questions accurately.';
        $kbFallback    = $knowledgeFallback    ?: 'No business-specific information has been provided. Do NOT use your training data or any external knowledge to answer questions about this business (products, prices, contact details, addresses, staff, or any specific facts). If asked about any such details, say you do not have that information and suggest the user contact the business directly.';

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
                $knowledgeSection = "\n\n## Knowledge Base\n\n{$kbInstruction}\n\n" . implode("\n\n", $parts);
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
                $knowledgeSection = "\n\n## Knowledge Base\n\n{$kbInstruction}\n\n" . implode("\n\n", $knowledgeParts);
            } else {
                $knowledgeSection = "\n\n## Knowledge Base\n\n{$kbFallback}";
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

        // ── Language rule ──────────────────────────────────────────────────
        $languageSection = '';
        if ($language) {
            $languageNames = [
                'en' => 'English',
                'fa' => 'Persian (Farsi)',
                'ar' => 'Arabic',
                'zh' => 'Mandarin Chinese',
                'fr' => 'French',
                'de' => 'German',
                'es' => 'Spanish',
                'tr' => 'Turkish',
            ];
            $langName = $languageNames[$language] ?? $language;
            $languageSection = "\n\n## Language — MANDATORY\nYou MUST respond exclusively in {$langName}. Never switch to any other language, even if the user writes in a different language.";
        }

        // ── Custom override ────────────────────────────────────────────────
        if ($customPrompt) {
            return $customPrompt . $identitySection . $languageSection . $knowledgeSection . $this->buildStructuredOutputInstruction($restrictedResponse);
        }

        // ── Composed prompt ────────────────────────────────────────────────
        $roleLabel = $role ?: 'helpful assistant';
        $lines     = ["You are a {$roleLabel}."];

        if ($description) {
            $lines[] = $description;
        }

        if ($tone) {
            if ($toneCustom) {
                $lines[] = $toneCustom;
            } else {
                $toneMap = [
                    'friendly'     => 'Be genuinely warm, caring, and enthusiastic. Show real interest in helping the user. Use a conversational and inviting tone, as if talking to a friend. Express positivity and encouragement naturally.',
                    'professional' => 'Maintain a professional and formal tone at all times.',
                    'concise'      => 'Be brief and to the point. Avoid unnecessary filler.',
                    'formal'       => 'Use formal language. Avoid contractions and casual expressions.',
                ];
                $lines[] = $toneMap[$tone] ?? "Tone: {$tone}.";
            }
        }

        $lines[] = '';
        $lines[] = '## Output Format — MANDATORY — NEVER VIOLATE';
        $lines[] = 'CRITICAL: Every single response you send MUST be formatted in HTML. No exceptions.';
        $lines[] = '- Use <p> for paragraphs, <ul>/<li> for bullet lists, <ol>/<li> for numbered lists, <strong> for bold, <em> for italic, <h3>/<h4> for headings.';
        $lines[] = '- NEVER use Markdown syntax. Not even partially. This means: no **bold**, no *italic*, no ## headings, no - bullet lines, no `code`, no [links](url).';
        $lines[] = '- Do NOT wrap output in <html>, <body>, or <head> — return only the inner content HTML.';
        $lines[] = '- Do NOT add inline style attributes. Use only the HTML tags listed above.';

        $lines[] = '';
        $lines[] = '## Behaviour Rules';
        if ($allowGeneralKnowledge) {
            $lines[] = '- Use your general knowledge freely to answer questions about products, brands, models, categories, or any publicly known information.';
            $lines[] = '- For business-specific details (prices, availability, stock, promotions, contact info), rely only on your Knowledge Base. Do not fabricate these.';
            $lines[] = '- If a business-specific detail is not in your Knowledge Base, acknowledge it and suggest the user contact the business directly.';
        } else {
            $lines[] = '- Answer only what you know from your Knowledge Base. Do not use general knowledge or training data.';
            $lines[] = '- If the answer is not in your Knowledge Base, say you do not have that information.';
        }
        if ($handleVagueQueries) {
            $lines[] = '- When a user sends a short or vague message (e.g. a brand name or single word), treat it as a broad question and provide a helpful, informative overview.';
        }
        if ($noClosingQuestion) {
            $lines[] = '- Do not end your response with closing phrases like "Is there anything else I can help you with?" or similar follow-up questions.';
        }
        $lines[] = '- Do not generate harmful, misleading, or offensive content.';

        if ($customRules) {
            foreach (array_filter(array_map('trim', explode("\n", $customRules))) as $rule) {
                $lines[] = '- ' . $rule;
            }
        }

        return implode("\n", $lines) . $identitySection . $languageSection . $knowledgeSection . $this->buildStructuredOutputInstruction($restrictedResponse);
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
    /**
     * Merges site-level overrides on top of agent defaults.
     * String fields: site value wins if non-null and non-empty.
     * Boolean fields: site value wins if non-null.
     */
    private function mergeAgentConfig(array $agentConfig, array $siteOverrides): array
    {
        $stringFields = ['tone', 'tone_custom', 'system_prompt', 'custom_rules', 'knowledge_instruction', 'knowledge_fallback', 'restricted_response'];
        $boolFields   = ['allow_general_knowledge', 'no_closing_question', 'handle_vague_queries'];

        $merged = $agentConfig;

        foreach ($stringFields as $key) {
            if (array_key_exists($key, $siteOverrides) && $siteOverrides[$key] !== null && $siteOverrides[$key] !== '') {
                $merged[$key] = $siteOverrides[$key];
            }
        }
        foreach ($boolFields as $key) {
            if (array_key_exists($key, $siteOverrides) && $siteOverrides[$key] !== null) {
                $merged[$key] = (bool) $siteOverrides[$key];
            }
        }

        return $merged;
    }

    private function buildStructuredOutputInstruction(string $restrictedResponse = ''): string
    {
        $safeResponse = $restrictedResponse ?: KnowledgePolicyFilter::restrictedDataSafeResponse();

        return <<<PROMPT


## Restricted Information Policy

If a user asks for information that is not present in your Knowledge Base, respond with:
"{$safeResponse}"
Do not guess, fabricate, or infer restricted details. This response is mandatory — do not improvise.

## Structured Contact Output

When your response mentions specific contact information (phone numbers, email addresses, website URLs, or physical addresses), append a structured data block using this exact format:

<sarah_card>{"type":"contact","fields":[{"key":"contact.phone_admin","label":"Phone","value":"0449948867"}]}</sarah_card>

Rules:
- Only include fields that are actually mentioned in your response text
- Do not include this block if your response contains no contact information
- Canonical key names: contact.phone_admin, contact.phone_marketing, contact.phone_sales, contact.website, contact.email_support, contact.email_sales, business.address, business.hours, business.name

## Language Metadata — MANDATORY in EVERY response

At the very end of EVERY response (after sarah_card if present), append this tag on its own line:

<sarah_meta>{"lang":"en","dir":"ltr"}</sarah_meta>

Rules:
- lang: ISO 639-1 code of the language you are responding in (e.g. "fa", "en", "ar", "zh", "tr")
- dir: "rtl" for right-to-left languages (Persian, Arabic, Hebrew, Urdu) — "ltr" for everything else
- This tag is MANDATORY. Include it in every single response, no exceptions.
- It must be the very last line of your response.
PROMPT;
    }
}
