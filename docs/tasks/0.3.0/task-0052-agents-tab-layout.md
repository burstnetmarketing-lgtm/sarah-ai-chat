# Task 0052 — Agents Page Tab Layout

## Goal
Replace the stacked card layout on the Agents admin page with Bootstrap tabs — one tab per agent.

## Changes

### Modified: `assets/src/pages/Agents.jsx`
- Replaced `AgentCard` (standalone card per agent) with `AgentPanel` (tab content pane)
- `Agents` component now renders a single card with Bootstrap `.nav-tabs` header
- Active tab index tracked via `activeTab` state (default: 0)
- Agent status badge rendered inside each tab button
- `key={agents[activeTab].id}` on `AgentPanel` resets form state when switching tabs

## Commit
0052
