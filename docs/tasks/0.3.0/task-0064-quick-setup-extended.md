# Task 0064 — Quick Setup Extended (OpenAI Key + KB Link)

## Goal
Extend the Quick Setup flow (server + client) to accept two optional fields:
- `openai_api_key` — store as site's own provider key (falls back to platform key if omitted)
- `kb_link` — seed the first knowledge base entry from a URL

Supports two usage patterns:
1. **Wizard UI** — customer fills the form in the sarah-ai-client dashboard
2. **API-first (headless)** — integration partner sends a single POST `/quick-setup` with all fields; no human interaction needed

## Changes

### Modified: `sarah-ai-server/includes/Api/QuickSetupController.php`
- Added `SiteApiKeyRepository` + `KnowledgeResourceRepository` dependencies
- New params: `openai_api_key` (optional), `kb_link` (optional)
- If `openai_api_key` present → `SiteApiKeyRepository::set($siteId, 'openai', $key)`
- If `kb_link` present and valid URL → `KnowledgeResourceRepository::create($siteId, 'link', $siteName, $kbLink)`
- Response now includes `has_openai_key: bool` and `has_kb: bool`

### Modified: `sarah-ai-client/assets/src/pages/QuickSetup.jsx`
- Added `openai_api_key` field (password, optional)
- Added `kb_link` field (URL, optional)
- Both sent to server `/quick-setup` call (undefined if blank)
- Success screen now shows badges for plan, agent, OpenAI key saved, KB entry created

## API Reference
```
POST /sarah-ai-server/v1/quick-setup
Header: X-Sarah-Platform-Key: {key}

Body:
  site_name      string  required
  site_url       string  required
  whmcs_key      string  optional — customer plan if provided
  openai_api_key string  optional — site-level OpenAI key
  kb_link        string  optional — valid URL for first KB entry

Response:
  {
    success: true,
    data: {
      account_key:    string,
      site_key:       string,
      agent_slug:     string,
      plan:           "trial"|"customer",
      site_uuid:      string,
      has_openai_key: bool,
      has_kb:         bool
    }
  }
```

## Commit
0064
