<?php

declare(strict_types=1);

namespace SarahAiServer\Processing;

use SarahAiServer\Infrastructure\SettingsRepository;

/**
 * Generates embeddings for text chunks using the OpenAI Embeddings API.
 *
 * PROVIDER BOUNDARY
 * This class is the single point of contact between the processing pipeline
 * and the OpenAI embeddings endpoint. Future provider changes (different model,
 * different vendor, self-hosted) only require changes here.
 *
 * MODEL
 * Default: text-embedding-3-small (1 536 dimensions, cost-effective for RAG).
 * Can be overridden per call.
 *
 * BATCH STRATEGY
 * Texts are sent in batches of up to 20 per API request to avoid hitting
 * the per-request token limit while reducing total HTTP round-trips.
 *
 * FAILURE HANDLING
 * Throws \RuntimeException on API error or missing API key.
 * The caller must catch and record failures — this service does not write to the DB.
 */
class EmbeddingService
{
    private const API_URL      = 'https://api.openai.com/v1/embeddings';
    private const DEFAULT_MODEL = 'text-embedding-3-small';
    private const BATCH_SIZE   = 20;

    private SettingsRepository $settings;

    public function __construct()
    {
        $this->settings = new SettingsRepository();
    }

    /**
     * Generate embeddings for an array of text strings.
     *
     * @param  string[] $texts  Texts to embed (parallel to returned vectors)
     * @param  string   $model  OpenAI embedding model ID
     * @return float[][]        Parallel array of embedding vectors
     * @throws \RuntimeException on API error or missing key
     */
    public function embed(array $texts, string $model = self::DEFAULT_MODEL): array
    {
        if (empty($texts)) {
            return [];
        }

        $apiKey = $this->settings->get('openai_api_key', '', 'platform');
        if (! $apiKey) {
            throw new \RuntimeException('OpenAI API key is not configured — cannot generate embeddings.');
        }

        $batches    = array_chunk($texts, self::BATCH_SIZE, true);
        $embeddings = [];

        foreach ($batches as $batch) {
            $batchTexts = array_values($batch);
            $vectors    = $this->callApi($batchTexts, $model, $apiKey);

            foreach ($vectors as $i => $vector) {
                // Restore original keys so the result array aligns with input
                $originalKey             = array_keys($batch)[$i];
                $embeddings[$originalKey] = $vector;
            }
        }

        // Sort by original index to ensure alignment
        ksort($embeddings);
        return array_values($embeddings);
    }

    /** Return the default embedding model name. */
    public function getDefaultModel(): string
    {
        return self::DEFAULT_MODEL;
    }

    // ─── Private ─────────────────────────────────────────────────────────────

    /**
     * Call the OpenAI /v1/embeddings endpoint for a batch.
     *
     * @param  string[] $texts
     * @return float[][]
     */
    private function callApi(array $texts, string $model, string $apiKey): array
    {
        $body = wp_json_encode([
            'input' => count($texts) === 1 ? $texts[0] : $texts,
            'model' => $model,
        ]);

        $response = wp_remote_post(self::API_URL, [
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type'  => 'application/json',
            ],
            'body'    => $body,
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            throw new \RuntimeException('Embedding API request failed: ' . $response->get_error_message());
        }

        $code = wp_remote_retrieve_response_code($response);
        $data = json_decode(wp_remote_retrieve_body($response), true);

        if ($code !== 200 || ! isset($data['data'])) {
            $error = $data['error']['message'] ?? "HTTP {$code}";
            throw new \RuntimeException("Embedding API error: {$error}");
        }

        // Sort response by index to match input order
        $items = $data['data'];
        usort($items, fn($a, $b) => $a['index'] <=> $b['index']);

        return array_map(fn($item) => $item['embedding'], $items);
    }
}
