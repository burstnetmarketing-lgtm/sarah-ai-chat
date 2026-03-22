# Task 0037 — Phase 5: AI Agent Behavior & Control

## Goal
Make the AI agent controllable and reliable. Phase 5.1 (basic agent runtime) was already complete in Phase 4.4.1. Phase 5.2 adds configurable role, tone, structured guardrails, and custom system prompt override per agent.

## Scope

### System Prompt Rewrite (`OpenAiAgentExecutor::buildSystemPrompt()`)
Two modes:
1. `config.system_prompt` set → used as full prompt (custom override), knowledge appended below
2. Otherwise → composed from: role + description + tone + Behaviour Rules guardrails + knowledge

Guardrails injected in composed mode:
- Do not guess or make up facts
- Handle uncertainty by saying so clearly
- Stay within defined role/domain
- Politely decline out-of-scope questions
- No harmful or misleading content

Tone options: `friendly`, `professional`, `concise`, `formal`

### Agent Config Fields Added
- `role` — agent purpose in plain language (e.g. "customer support assistant")
- `tone` — communication style
- `system_prompt` — full custom override (empty = use composed prompt)

### Infrastructure
- `AgentRepository::updateBehavior(id, role, tone, systemPrompt)` — merges behavior fields into config JSON, preserves model/max_tokens/temperature

### API
- `PUT /agents/{id}/behavior` — saves role, tone, system_prompt into agent config (admin only)

### Seeder
- All 3 OpenAI agents updated with `role: "customer support assistant"`, tone defaults (friendly for Mini, professional for 4o and o1), `system_prompt: ""`

### Admin UI
- `assets/src/pages/Agents.jsx` (new) — per-agent card: role input, tone dropdown, custom system prompt textarea, model info display, save with feedback
- `assets/src/api/provisioning.js` — added `updateAgentBehavior()`
- `assets/src/App.jsx` — added `agents` route
- `MenuRepository` — added `agents` sidebar item

## Files Changed
- `includes/Runtime/OpenAiAgentExecutor.php` (updated — buildSystemPrompt rewrite)
- `includes/Infrastructure/AgentRepository.php` (updated — updateBehavior)
- `includes/Api/AgentController.php` (updated — PUT /agents/{id}/behavior)
- `includes/Core/Seeder.php` (updated — role/tone/system_prompt in agent configs)
- `assets/src/pages/Agents.jsx` (new)
- `assets/src/api/provisioning.js` (updated)
- `assets/src/App.jsx` (updated)
- `includes/Infrastructure/MenuRepository.php` (updated)

## Commit
0028
