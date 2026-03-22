# Task 0033 — TenantDetail 10-Step Wizard

## Goal
Convert the TenantDetail page from showing all sections simultaneously into a proper 10-step wizard. Each step shows only its own relevant section. The stepper header is always visible and clickable.

## Steps Defined

| # | Label       | Sub-label  | Condition                                    |
|---|-------------|------------|----------------------------------------------|
| 0 | Tenant      | Active     | tenant exists & status === 'active'          |
| 1 | Subscription| Set        | subscription record exists                   |
| 2 | User        | Added      | users.length > 0                             |
| 3 | Site        | Registered | sites.length > 0                             |
| 4 | Site Status | Active     | firstSite?.status === 'active'               |
| 5 | Account Key | Issued     | accountKeys.length > 0                       |
| 6 | Site Key    | Issued     | firstSiteKeys.length > 0                     |
| 7 | Agent       | Assigned   | firstSiteAgentId is set                      |
| 8 | Knowledge   | Added      | firstSiteKnowledgeCount > 0                  |
| 9 | Launch      | Ready      | all 9 preceding steps complete               |

## Implementation

### State
- `activeStep` (0–9): which step is currently shown
- `firstSiteKeys`: updated via `onKeysChange` callback from SiteKeysSection
- `firstSiteAgentId`: updated via `onAgentChange` callback from AgentSection
- `firstSiteKnowledgeCount`: updated via `onItemsChange` callback from KnowledgeSection
- `stepInitialized` (`useRef`): ensures auto-init runs only once after first data load

### Auto-Init
After the first successful data load, `activeStep` is set to the index of the first incomplete step (or 9 if all complete).

### renderStepContent() switch
Each case renders its dedicated section component. Steps 4–8 show a `PrereqCard` if no first site exists yet.

### New Components Added
- `TenantInfoPanel` — displays tenant metadata (step 0)
- `SubscriptionPanel` — displays subscription dates/status (step 1)
- `SiteCreateSection` — create form + site list without configure button (step 3)
- `SiteStatusPanel` — activate/deactivate site (step 4)
- `PrereqCard` — shown when a prerequisite step is not yet complete
- `LaunchPanel` — shows ready/not-ready summary (step 9)

### ReadinessCheck Props Changed
`steps` array is now computed in the parent and passed as a prop. `activeStep` and `onStepClick` are also passed in so the stepper can highlight the active node with an amber ring.

## Files Changed
- `sarah-ai-server/assets/src/pages/TenantDetail.jsx` — full rewrite

## Build
`npm run build` — ✓ 373 modules, app.js 248 KB, built in 3.17s
