<?php

declare(strict_types=1);

namespace SarahAiServer\Api;

use SarahAiServer\Infrastructure\ChatSessionRepository;
use SarahAiServer\Infrastructure\SummarizerLogRepository;
use SarahAiServer\Runtime\SessionSummarizer;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Summarizer endpoints — intended for external cron triggers (cron-job.org, etc.)
 * or internal calls from other plugins.
 *
 * Routes:
 *   GET /sarah-ai-server/v1/run-summarizer?secret=SECRET_KEY[&batch=5]
 *       Finds sessions idle 30+ min, fires up to `batch` non-blocking requests
 *       to /summarize-session/{uuid}. Returns immediately.
 *
 *   GET /sarah-ai-server/v1/summarize-session/{uuid}?secret=SECRET_KEY
 *       Processes a single session. Called by run-summarizer (non-blocking).
 *       Can also be called manually.
 *
 *   GET /sarah-ai-server/v1/purge-summarizer-log?secret=SECRET_KEY&days=7
 *       Deletes log entries older than N days.
 *
 * Authentication: static secret key (hardcoded). No WordPress session required.
 */
class SummarizerController
{
    private const SECRET       = 'f2514efeda3d963dff17e8cdb2ae2a6c735bd003aa9d3e87';
    private const IDLE_MINUTES = 30;
    private const DEFAULT_BATCH = 5;

    public function registerRoutes(): void
    {
        register_rest_route('sarah-ai-server/v1', '/run-summarizer', [
            'methods'             => 'GET',
            'callback'            => [$this, 'run'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('sarah-ai-server/v1', '/summarize-session/(?P<uuid>[0-9a-f-]{36})', [
            'methods'             => 'GET',
            'callback'            => [$this, 'summarizeOne'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('sarah-ai-server/v1', '/purge-summarizer-log', [
            'methods'             => 'GET',
            'callback'            => [$this, 'purge'],
            'permission_callback' => '__return_true',
        ]);
    }

    // ── GET /run-summarizer?secret=...&batch=5 ────────────────────────────────
    // Finds pending sessions and fires one non-blocking request per session.
    // Returns immediately — actual summarization happens in background.

    public function run(WP_REST_Request $request): WP_REST_Response
    {
        if (! $this->auth($request)) {
            return new WP_REST_Response(['success' => false, 'message' => 'Unauthorized.'], 401);
        }

        $batch    = max(1, min(50, (int) ($request->get_param('batch') ?? self::DEFAULT_BATCH)));
        $sessions = new ChatSessionRepository();
        $log      = new SummarizerLogRepository();

        $pending = $sessions->findNeedingSummary(self::IDLE_MINUTES, $batch);
        $count   = count($pending);

        $log->log("run-summarizer — found {$count} session(s) (batch={$batch})");

        foreach ($pending as $session) {
            $uuid = (string) ($session['uuid'] ?? '');
            if ($uuid === '') continue;

            $url = rest_url('sarah-ai-server/v1/summarize-session/' . $uuid)
                   . '?secret=' . self::SECRET;

            wp_remote_get($url, [
                'blocking'  => false,
                'timeout'   => 1,
                'sslverify' => apply_filters('https_local_ssl_verify', false),
            ]);

            $log->log("{$uuid} dispatched");
        }

        return new WP_REST_Response([
            'success'    => true,
            'dispatched' => $count,
            'batch'      => $batch,
        ], 200);
    }

    // ── GET /summarize-session/{uuid}?secret=... ──────────────────────────────
    // Processes a single session independently. Each call is self-contained.

    public function summarizeOne(WP_REST_Request $request): WP_REST_Response
    {
        if (! $this->auth($request)) {
            return new WP_REST_Response(['success' => false, 'message' => 'Unauthorized.'], 401);
        }

        $uuid     = (string) $request->get_param('uuid');
        $sessions = new ChatSessionRepository();
        $log      = new SummarizerLogRepository();

        $session = $sessions->findByUuid($uuid);
        if (! $session) {
            $log->log("{$uuid} failed — session not found");
            return new WP_REST_Response(['success' => false, 'message' => 'Session not found.'], 404);
        }

        $summarizer = new SessionSummarizer();
        $result     = $summarizer->summarize($session);

        if ($result['success']) {
            $log->log("{$uuid} summarized");
        } else {
            $log->log("{$uuid} failed — " . ($result['error'] ?? 'unknown error'));
        }

        return new WP_REST_Response([
            'success' => $result['success'],
            'uuid'    => $uuid,
            'error'   => $result['error'],
        ], $result['success'] ? 200 : 500);
    }

    // ── GET /purge-summarizer-log?secret=...&days=7 ───────────────────────────

    public function purge(WP_REST_Request $request): WP_REST_Response
    {
        if (! $this->auth($request)) {
            return new WP_REST_Response(['success' => false, 'message' => 'Unauthorized.'], 401);
        }

        $days    = max(1, (int) ($request->get_param('days') ?? 7));
        $log     = new SummarizerLogRepository();
        $deleted = $log->purgeOlderThan($days);

        return new WP_REST_Response([
            'success' => true,
            'deleted' => $deleted,
            'days'    => $days,
        ], 200);
    }

    // ── Auth ───────────────────────────────────────────────────────────────────

    private function auth(WP_REST_Request $request): bool
    {
        return $request->get_param('secret') === self::SECRET;
    }
}
