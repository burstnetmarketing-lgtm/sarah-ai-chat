<?php

declare(strict_types=1);

namespace SarahAiServer\Core;

use SarahAiServer\Infrastructure\KnowledgeResourceRepository;
use SarahAiServer\Processing\KnowledgeProcessingService;

/**
 * Async background job: re-processes all active KB resources for a site.
 *
 * Triggered via WP Cron (single event). The REST endpoint dispatches the job
 * and returns immediately — processing happens in a separate non-blocking request.
 *
 * Hook:  sarah_ai_kb_site_sync
 * Arg:   int $siteId
 */
class KbSyncJob
{
    private const HOOK = 'sarah_ai_kb_site_sync';

    /**
     * Register the WP action hook.
     * Must be called on every boot() so cron can find the callback.
     */
    public static function register(): void
    {
        add_action(self::HOOK, [self::class, 'run']);
    }

    /**
     * Schedule the job for the given site and trigger WP cron immediately.
     * Returns the number of resources queued (0 = nothing to process).
     */
    public static function dispatch(int $siteId): int
    {
        $resources = (new KnowledgeResourceRepository())->findActiveBySite($siteId);
        if (empty($resources)) {
            return 0;
        }

        // Schedule only if not already pending for this site.
        if (! wp_next_scheduled(self::HOOK, [$siteId])) {
            wp_schedule_single_event(time(), self::HOOK, [$siteId]);
        }

        // Kick WP cron immediately — non-blocking, fire-and-forget.
        wp_remote_post(site_url('wp-cron.php'), [
            'blocking'  => false,
            'sslverify' => false,
            'timeout'   => 0.01,
        ]);

        return count($resources);
    }

    /**
     * Cron callback — runs in the background.
     * Processes every active resource for the site sequentially.
     */
    public static function run(int $siteId): void
    {
        $resources = (new KnowledgeResourceRepository())->findActiveBySite($siteId);
        $processor = new KnowledgeProcessingService();

        foreach ($resources as $resource) {
            try {
                $processor->process((int) $resource['id']);
            } catch (\Throwable $e) {
                // Log and continue with remaining resources.
                Logger::error('KbSyncJob: resource ' . ($resource['uuid'] ?? '?') . ' failed: ' . $e->getMessage());
            }
        }
    }
}
