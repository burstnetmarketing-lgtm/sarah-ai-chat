# Task 0063 — Per-Site Provider API Keys

## Goal
Allow each client site to configure its own AI provider API key (e.g. OpenAI).
If a site key is set, it is used for chat; otherwise the platform's shared key is used as fallback.
Architecture is typed (provider column) so new providers (Amazon Bedrock, etc.) can be added later.

## Design
- New table `sarah_ai_server_site_api_keys`: `id | site_id | provider | api_key | created_at | updated_at`
- `provider` column allows future extensibility (currently only `openai` supported)
- Keys are never returned by any API endpoint — only a list of which providers have a key is returned

## Changes

### Created: `sarah-ai-server/includes/DB/SiteApiKeyTable.php`
- New table with unique constraint on `(site_id, provider)`

### Created: `sarah-ai-server/includes/Infrastructure/SiteApiKeyRepository.php`
- `get(int $siteId, string $provider): ?string` — returns raw key or null
- `set(int $siteId, string $provider, string $apiKey): void` — upsert; empty string = delete row
- `listProviders(int $siteId): string[]` — list providers with a key set (keys not exposed)

### Modified: `sarah-ai-server/includes/Api/SiteController.php`
- Added `GET /sites/{uuid}/api-key` → `getApiKeys()` — returns `{ providers: [...] }`
- Added `POST /sites/{uuid}/api-key` → `setApiKey()` — body: `{ provider, api_key }`

### Created: `sarah-ai-server/includes/Api/ClientSiteController.php`
- `GET  /client/api-keys` — list providers with a key (auth: X-Sarah-Platform-Key + account_key + site_key)
- `POST /client/api-key`  — set/clear a provider key (body: `{ account_key, site_key, provider, api_key }`)

### Modified: `sarah-ai-server/includes/Runtime/OpenAiAgentExecutor.php`
- Looks up `SiteApiKeyRepository::get($siteId, 'openai')` first
- Falls back to platform `openai_api_key` setting if site key is not set

### Modified: `sarah-ai-server/includes/Core/Activator.php`
### Modified: `sarah-ai-server/includes/Core/Plugin.php`
### Modified: `sarah-ai-server/sarah-ai-server.php`
- Added `SiteApiKeyTable`, `SiteApiKeyRepository`, `ClientSiteController`

### Modified: `sarah-ai-client/assets/src/pages/Settings.jsx`
- Added "AI Provider Keys" card section
- Provider dropdown (currently only OpenAI) + API key password field + Save Key button
- On load: fetches saved providers from server and shows as removable badges
- Clear button on each badge sends empty key to server (deletes the row)

## Commit
0063
