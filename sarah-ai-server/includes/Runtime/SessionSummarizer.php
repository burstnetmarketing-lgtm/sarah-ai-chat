<?php

declare(strict_types=1);

namespace SarahAiServer\Runtime;

use SarahAiServer\Infrastructure\AgentRepository;
use SarahAiServer\Infrastructure\ChatMessageRepository;
use SarahAiServer\Infrastructure\ChatSessionRepository;
use SarahAiServer\Infrastructure\SettingsRepository;
use SarahAiServer\Infrastructure\SiteApiKeyRepository;
use SarahAiServer\Infrastructure\SiteRepository;

/**
 * Generates a plain-text summary for a single chat session using the
 * site's own agent and API key.
 *
 * Each session is summarized independently so failures are isolated.
 */
class SessionSummarizer
{
    private ChatSessionRepository $sessions;
    private ChatMessageRepository $messages;
    private AgentRepository       $agents;
    private SiteRepository        $sites;
    private SiteApiKeyRepository  $siteApiKeys;
    private SettingsRepository    $settings;

    public function __construct()
    {
        $this->sessions    = new ChatSessionRepository();
        $this->messages    = new ChatMessageRepository();
        $this->agents      = new AgentRepository();
        $this->sites       = new SiteRepository();
        $this->siteApiKeys = new SiteApiKeyRepository();
        $this->settings    = new SettingsRepository();
    }

    /**
     * Summarize a single session.
     *
     * @param  array  $session  Row from chat_sessions table.
     * @return array{ success: bool, summary: string|null, error: string|null }
     */
    public function summarize(array $session): array
    {
        $sessionId = (int) $session['id'];
        $siteId    = (int) $session['site_id'];
        $agentId   = (int) ($session['agent_id'] ?? 0);

        // ── Resolve API key ────────────────────────────────────────────────────
        $apiKey = ($siteId > 0) ? ($this->siteApiKeys->get($siteId, 'openai') ?? '') : '';
        if ($apiKey === '') {
            $allowFallback = $this->settings->get('allow_platform_openai_key', '0', 'platform') === '1';
            if ($allowFallback) {
                $apiKey = $this->settings->get('openai_api_key', '', 'platform');
            }
        }
        if (! $apiKey) {
            return ['success' => false, 'summary' => null, 'error' => 'No OpenAI API key available for this session.'];
        }

        // ── Resolve model from agent config ────────────────────────────────────
        $model = 'gpt-4o-mini';
        if ($agentId > 0) {
            $agent = $this->agents->findById($agentId);
            if ($agent) {
                $config = json_decode($agent['config'] ?? '{}', true) ?? [];
                $model  = (string) ($config['model'] ?? $model);
            }
        }

        // ── Load messages ──────────────────────────────────────────────────────
        $messages = $this->messages->findBySession($sessionId);
        if (empty($messages)) {
            return ['success' => false, 'summary' => null, 'error' => 'No messages found.'];
        }

        // ── Build conversation transcript ──────────────────────────────────────
        $lines = [];
        foreach ($messages as $msg) {
            $role    = ($msg['role'] === 'customer') ? 'Customer' : 'Assistant';
            $content = strip_tags((string) ($msg['content'] ?? ''));
            $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if (trim($content) === '') continue;
            $lines[] = $role . ': ' . trim($content);
        }

        if (empty($lines)) {
            return ['success' => false, 'summary' => null, 'error' => 'Transcript is empty after processing.'];
        }

        $transcript = implode("\n", $lines);

        // ── Call OpenAI ────────────────────────────────────────────────────────
        $prompt = "You are summarizing a customer support chat. Write a concise summary (3-5 sentences) covering:\n"
                . "- What the customer asked or needed\n"
                . "- Key information provided by the assistant\n"
                . "- Any lead data collected (name, email, phone)\n"
                . "- The outcome or next steps\n\n"
                . "Respond in the same language as the conversation.\n\n"
                . "Conversation:\n" . $transcript;

        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode([
                'model'       => $model,
                'messages'    => [
                    ['role' => 'user', 'content' => $prompt],
                ],
                'max_tokens'  => 300,
                'temperature' => 0.3,
            ]),
        ]);

        if (is_wp_error($response)) {
            return ['success' => false, 'summary' => null, 'error' => $response->get_error_message()];
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $summary = trim((string) ($body['choices'][0]['message']['content'] ?? ''));

        if ($summary === '') {
            return ['success' => false, 'summary' => null, 'error' => 'OpenAI returned empty summary.'];
        }

        // ── Persist ────────────────────────────────────────────────────────────
        $this->sessions->saveSummary($sessionId, $summary);

        return ['success' => true, 'summary' => $summary, 'error' => null];
    }
}
