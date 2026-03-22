# Task 0039 — Site-Level Agent Identity & Greeting Configuration

## Goal
Allow each site to define how the agent presents itself independently of the agent's core behavior. The same agent can have a different name and greeting on each site.

## Separation Rule
- **Agent** = behavior (role, tone, guardrails, model)
- **Site** = identity (name, greeting, branding)

## Fields Added to `sarah_ai_server_sites`
| Column | Type | Purpose |
|--------|------|---------|
| `agent_display_name` | VARCHAR(190) NULL | Name injected into system prompt |
| `greeting_message` | TEXT NULL | Initial UI message — not in prompt |
| `intro_message` | TEXT NULL | Agent self-introduction — injected in prompt |

## sarah-ai-server Changes

### DB / Repository
- `SiteTable.php` — added 3 columns (dbDelta handles migration)
- `SiteRepository.php` — added `updateAgentIdentity(siteId, data)` and `getAgentIdentity(siteId)`

### API
- `SiteController.php` — added `GET /sites/{uuid}/agent-identity` and `POST /sites/{uuid}/agent-identity`
- `provisioning.js` — added `getAgentIdentity()` and `updateAgentIdentity()`

### Runtime
- `ChatRuntime.php` — extracts `agent_display_name` and `intro_message` from site row → passes as `site_identity` in executor context
- `OpenAiAgentExecutor.php` — `buildSystemPrompt()` now accepts `$siteIdentity` and injects:
  - `"Your name is {agent_display_name}."` if set
  - `{intro_message}` if set
  - Applied in both custom-override and composed-prompt modes

### Admin UI (TenantDetail)
- Added `AgentIdentitySection` component in step 7 (below AgentSection)
- Site selector dropdown for multi-site tenants
- Fields: Agent Display Name, Intro Message, Greeting Message
- Note on each field explaining how it's used

## sarah-ai-client Changes

### PHP
- `SettingsController.php` — GET/POST `/widget-settings` now includes `greeting_message`
- `Plugin.php` — `greeting_message` passed in `window.SarahAiWidget.connection`

### React
- `Settings.jsx` — added Greeting Message field in Server Connection section
- `ChatWindow.jsx` — on mount, reads `window.SarahAiWidget.connection.greeting_message` and shows it instantly as first AI bubble (no server round-trip)

## Commit
0030
