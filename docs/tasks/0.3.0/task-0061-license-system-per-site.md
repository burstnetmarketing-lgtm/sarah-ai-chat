# Task 0061 — License System Per Site (WHMCS Integration)

## Goal
Replace tenant-level subscription system with a per-site plan + WHMCS license key model.
Each site independently holds its plan (trial/customer) and inherits the WHMCS key from its tenant.

## Architecture

### Two Plans
- `trial` — 30-day free access from site creation date, basic agent only
- `customer` — permanent access, all agents, requires valid WHMCS key on tenant

### License Check Flow (every API call)
1. Credentials validated (account_key + site_key) → resolve tenant + site
2. If `site.plan = customer` → validate tenant's WHMCS key via WHMCS API (24h cache via `site.whmcs_lastcheck`)
3. If `site.plan = trial` → check `site.created_at + plan.duration_days` vs now
4. If neither passes → return null (401 to caller)

### WHMCS key stored at tenant level (one key covers all sites of that tenant)
- When tenant's `whmcs_key` changes → all sites' `whmcs_lastcheck` reset to NULL → forces re-validation

## Changes

### DB Tables Modified
- `TenantTable` — added `whmcs_key VARCHAR(255) NULL`
- `SiteTable` — added `plan_id BIGINT UNSIGNED NULL`, `whmcs_lastcheck DATETIME NULL`

### Files Deleted
- `includes/DB/SubscriptionTable.php`
- `includes/Infrastructure/SubscriptionRepository.php`
- `includes/Api/SubscriptionController.php`
- `assets/src/pages/Subscriptions.jsx`

### Files Created
- `includes/Infrastructure/WhmcsLicenseService.php` — WHMCS API call + 24h cache
- `includes/Infrastructure/LicenseValidator.php` — trial expiry OR WHMCS validity check
- `includes/Api/QuickSetupController.php` — one-call provisioning endpoint for client Quick Setup

### Files Modified
- `includes/Infrastructure/CredentialValidator.php` — step 4: `LicenseValidator::isActive()` after context resolve
- `includes/Infrastructure/TenantRepository.php` — `create()` accepts `whmcs_key`; added `updateWhmcsKey()` (also resets site lastchecks)
- `includes/Infrastructure/SiteRepository.php` — `create()` accepts `plan_id`; added `updatePlan()`, `updateWhmcsLastcheck()`
- `includes/Api/TenantController.php` — removed subscription logic; added `whmcs_key` to store; added `POST /tenants/{uuid}/whmcs-key`
- `includes/Api/SiteController.php` — auto-assigns plan on site create (customer if tenant has whmcs_key, else trial); added `POST /sites/{uuid}/plan`
- `includes/Api/PlanController.php` — removed subscription dependency; `availableAgents` now returns all active agents
- `includes/Api/PlatformSettingsController.php` — replaced `trial_duration_days` with `whmcs_api_url`
- `includes/Core/Seeder.php` — added `customer` plan (duration_days=0); trial now 30 days; seedPlanAgents seeds both plans; removed `trial_duration_days` default setting; added `whmcs_api_url`
- `includes/Core/Plugin.php` — removed SubscriptionTable/SubscriptionController; added QuickSetupController
- `sarah-ai-server.php` — removed subscription requires; added WhmcsLicenseService, LicenseValidator, QuickSetupController requires
- `includes/Infrastructure/MenuRepository.php` — removed `subscriptions` menu item (removeIfExists)
- `assets/src/App.jsx` — removed Subscriptions import and VIEWS/LABELS entries
- `assets/src/pages/TenantDetail.jsx` — replaced SubscriptionPanel with LicensePanel (WHMCS key set/update); replaced subscription badge with trial/customer badge in header; removed subscription state

## New API Endpoints
- `POST /tenants/{uuid}/whmcs-key` — set/update WHMCS key (admin)
- `POST /sites/{uuid}/plan` — change site plan (admin)
- `POST /quick-setup` — one-call provisioning: creates tenant+site+keys+agent (auth: X-Sarah-Platform-Key)

## Commit
0061
