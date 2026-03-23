# Task 0066 — Async KB Sync Endpoint (update-kb)

## Goal
Add a `POST /client/update-kb` endpoint that triggers background re-processing of all
active KB resources for a site. Integration partners call it once (e.g., after adding a
new machine/product) and the server responds immediately — processing happens via WP Cron.

## Flow
```
POST /client/update-kb
  → auth (Platform Key + account_key + site_key)
  → KbSyncJob::dispatch($siteId)
      → wp_schedule_single_event(time(), 'sarah_ai_kb_site_sync', [$siteId])
      → wp_remote_post(wp-cron.php, blocking: false)   ← fire-and-forget
  → response: { success: true, queued: N, message: "..." }

[background]
sarah_ai_kb_site_sync hook fires
  → KbSyncJob::run($siteId)
      → foreach active resource → KnowledgeProcessingService::process()
```

## New: `sarah-ai-server/includes/Core/KbSyncJob.php`
- `register()` — adds WP action hook (called on every `Plugin::boot()`)
- `dispatch(int $siteId): int` — schedules event + fires non-blocking cron ping; returns queued count
- `run(int $siteId)` — cron callback; processes each active resource; logs failures and continues

## Modified: `sarah-ai-server/includes/Api/ClientSiteController.php`
- New route: `POST /client/update-kb`
- New handler: `updateKb()` — auth + dispatch + immediate response
- Uses same auth as `/client/api-keys` (Platform Key + account_key + site_key)

## Modified: `sarah-ai-server/includes/Core/Plugin.php`
- Added `KbSyncJob::register()` call at top of `boot()` (outside version-gate)

## Modified: `sarah-ai-server/sarah-ai-server.php`
- Added `require_once` for `KbSyncJob.php` (before `Plugin.php`)

## API Reference
```
POST /sarah-ai-server/v1/client/update-kb
Headers:
  X-Sarah-Platform-Key: {platform_key}
Body:
  { account_key, site_key }

Response (immediate):
  { success: true, data: { queued: 3, message: "KB sync queued for 3 resource(s)..." } }
```

## Commit
0066
