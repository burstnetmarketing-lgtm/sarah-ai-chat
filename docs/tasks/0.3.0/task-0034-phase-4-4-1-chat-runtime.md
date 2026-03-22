# Task 0034 — Phase 4.4.1: Chat Runtime & Session Foundation

## Goal
Transform the Sarah server from a passive provisioning system into a live, session-aware conversation runtime. Add POST /chat endpoint, session + message persistence, OpenAI executor, usage logging, and public session inspection endpoints.

## Scope

### DB
- `ChatSessionTable` — sessions (tenant, site, agent, subscription, lead info, status, captured_data)
- `ChatMessageTable` — messages (session, role, content, meta)
- `UsageLogTable` — added `session_id` column

### Infrastructure
- `ChatSessionRepository` — create, findByUuid, findById, findBySite, updateLeadInfo, close
- `ChatMessageRepository` — add, findBySession
- `UsageLogRepository` — log per-request usage event

### Runtime
- `AgentExecutorInterface` — provider-agnostic contract
- `OpenAiAgentExecutor` — OpenAI Chat Completions via wp_remote_post; mock when no API key
- `RuntimeEligibilityChecker` — 6-step eligibility chain (tenant → site → subscription → agent assigned → agent active → agent in plan)
- `ChatRuntime` — 10-step pipeline (credentials → eligibility → session → persist message → knowledge → history → execute → persist reply → log usage → return)

### API
- `ChatController` — `POST /chat` — public, account_key + site_key auth
- `SessionController` — `GET /sessions`, `GET /sessions/{uuid}`, `GET /sessions/{uuid}/messages` — triple auth (account_key + site_key + X-Sarah-Platform-Key header)

### Settings
- `platform_api_key` seeded as `www.BurstNET.com.au` — mandatory for session endpoints

### Admin UI (TenantDetail)
- AccountKeysSection: fixed rawKey display bug (onKeysChange pattern replaces onReload)
- SiteKeysSection: added site selector dropdown
- AgentSection: rewritten as table — shows all sites with agent badge, inline assign/change/remove
- SiteCreateSection: added Agent column
- AgentController: added `DELETE /sites/{uuid}/agent` unassign endpoint

## Files Changed
- `includes/DB/ChatSessionTable.php` (new)
- `includes/DB/ChatMessageTable.php` (new)
- `includes/DB/UsageLogTable.php` (updated — session_id)
- `includes/Infrastructure/ChatSessionRepository.php` (new)
- `includes/Infrastructure/ChatMessageRepository.php` (new)
- `includes/Infrastructure/UsageLogRepository.php` (updated)
- `includes/Runtime/AgentExecutorInterface.php` (new)
- `includes/Runtime/OpenAiAgentExecutor.php` (new)
- `includes/Runtime/RuntimeEligibilityChecker.php` (new)
- `includes/Runtime/ChatRuntime.php` (new)
- `includes/Api/ChatController.php` (new)
- `includes/Api/SessionController.php` (new)
- `includes/Api/AgentController.php` (updated — unassign endpoint)
- `includes/Core/Seeder.php` (updated — platform_api_key)
- `assets/src/pages/TenantDetail.jsx` (updated — UI fixes)
- `assets/src/api/provisioning.js` (updated — unassignAgent)
- `sarah-ai-server.php` (updated — new requires)
- `includes/Core/Plugin.php` (updated — new controller registrations)
- `docs/technical/test-chat.bat` (new)

## Commit
0025
