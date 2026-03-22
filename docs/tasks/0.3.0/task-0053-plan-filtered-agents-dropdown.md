# Task 0053 — Plan-Filtered Agent Dropdown in Tenant Setup

## Goal
Agent dropdown in Tenant Setup → Agents step must only show agents allowed by the tenant's active plan, not all agents.

## Problem
`TenantDetail.jsx` called `listAgents()` which returns all active agents regardless of plan.

## Solution
Switch to `listAvailableAgents(tenantUuid)` which hits `GET /tenants/{uuid}/available-agents` — already implemented in `PlanController::availableAgents()`. This endpoint filters agents by the plan's `PlanAgentTable` entries and falls back to all active agents if the plan has no restrictions.

## Changes

### Modified: `assets/src/pages/TenantDetail.jsx`
- Import: `listAgents` → `listAvailableAgents`
- `load()`: `listAgents()` → `listAvailableAgents(tenantUuid)`

## Related
- `PlanController::availableAgents()` — existing endpoint, no changes needed
- `Seeder::seedPlanAgents()` — extended in same session to link all 3 OpenAI agents (gpt-4o-mini, gpt-4o, o1) to the Trial plan

## Commit
0053
