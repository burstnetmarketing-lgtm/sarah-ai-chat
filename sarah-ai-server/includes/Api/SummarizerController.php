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
 *   GET /sarah-ai-server/v1/run-summarizer?secret=SECRET_KEY
 *   GET /sarah-ai-server/v1/purge-summarizer-log?secret=SECRET_KEY&days=7
 *
 * Authentication: static secret key (hardcoded). No WordPress session required.
 */
class SummarizerController
{
    private const SECRET = 'f2514efeda3d963dff17e8cdb2ae2a6c735bd003aa9d3e87';
    private const IDLE_MINUTES = 30;

    public function registerRoutes(): void
    {
        register_rest_route('sarah-ai-server/v1', '/run-summarizer', [
            'methods'             => 'GET',
            'callback'            => [$this, 'run'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('sarah-ai-server/v1', '/purge-summarizer-log', [
            'methods'             => 'GET',
            'callback'            => [$this, 'purge'],
            'permission_callback' => '__return_true',
        ]);
    }

    // ── GET /run-summarizer ────────────────────────────────────────────────────

    public function run(WP_REST_Request $request): WP_REST_Response
    {
        if (! $this->auth($request)) {
            return new WP_REST_Response(['success' => false, 'message' => 'Unauthorized.'], 401);
        }

        $sessions   = new ChatSessionRepository();
        $log        = new SummarizerLogRepository();
        $summarizer = new SessionSummarizer();

        $pending = $sessions->findNeedingSummary(self::IDLE_MINUTES);
        $count   = count($pending);

        $log->log("run-summarizer started — found {$count} session(s) to summarize");

        $done   = 0;
        $failed = 0;

        foreach ($pending as $session) {
            $uuid   = (string) ($session['uuid'] ?? $session['id']);
            $result = $summarizer->summarize($session);

            if ($result['success']) {
                $log->log("{$uuid} summarized");
                $done++;
            } else {
                $log->log("{$uuid} failed — " . ($result['error'] ?? 'unknown error'));
                $failed++;
            }
        }

        $log->log("run-summarizer finished — {$done} done, {$failed} failed");

        return new WP_REST_Response([
            'success'  => true,
            'found'    => $count,
            'done'     => $done,
            'failed'   => $failed,
        ], 200);
    }

    // ── GET /purge-summarizer-log ──────────────────────────────────────────────

    public function purge(WP_REST_Request $request): WP_REST_Response
    {
        if (! $this->auth($request)) {
            return new WP_REST_Response(['success' => false, 'message' => 'Unauthorized.'], 401);
        }

        $days = max(1, (int) ($request->get_param('days') ?? 7));
        $log  = new SummarizerLogRepository();

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
