# Task 0036 — Phase 4.5: Runtime Observability & Usage Visibility

## Goal
Verify that token usage is truly captured during runtime, and expose usage data through a queryable API and admin-facing UI. Phase 4.5 must not remain only internal infrastructure — it must be operationally observable.

## Scope

### Verification
- Confirmed `OpenAiAgentExecutor` reads `usage.prompt_tokens` and `usage.completion_tokens` from OpenAI response
- Confirmed `ChatRuntime` step 9 passes token values into `UsageLogRepository::log()`
- Mock mode safely logs null tokens without breaking runtime

### Infrastructure
- `UsageLogRepository` — added `findByFilters()` (tenant, site, session, agent, date range, limit, offset) and `getSummary()` (aggregate totals)

### API
- `UsageController` (new) — `GET /usage` (filtered, paginated records) + `GET /usage/summary` (aggregate totals)
- Auth: `manage_options` (WordPress admin)
- Registered in `Plugin.php` and `sarah-ai-server.php`

### Admin UI
- `assets/src/pages/Usage.jsx` (new) — summary cards (total requests, tokens in/out), filter form (tenant/site/agent/session/date), paginated table with prev/next
- `assets/src/api/provisioning.js` — added `getUsage()` and `getUsageSummary()`
- `assets/src/App.jsx` — added `usage` route
- `MenuRepository` — added `usage` sidebar item

## Files Changed
- `includes/Infrastructure/UsageLogRepository.php` (updated)
- `includes/Api/UsageController.php` (new)
- `includes/Core/Plugin.php` (updated)
- `sarah-ai-server.php` (updated)
- `assets/src/pages/Usage.jsx` (new)
- `assets/src/api/provisioning.js` (updated)
- `assets/src/App.jsx` (updated)
- `includes/Infrastructure/MenuRepository.php` (updated)

## Commit
0027
