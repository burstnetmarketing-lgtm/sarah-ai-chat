# Task 0049 — Knowledge Resource File Upload

## Goal
Allow admins to upload PDF and DOCX files directly as knowledge resources instead of relying on external URLs.

## Changes

### `includes/Api/KnowledgeController.php`
- New route: `POST /knowledge-resources/upload`
- Accepts: `multipart/form-data` with `file` + `site_uuid` + optional `title`
- Validates: only `.pdf` and `.docx` extensions accepted
- Stores file at: `{wp-uploads}/sarah-ai/{site_uuid}/{resource_uuid}.{ext}`
  - One subfolder per site — no flat media library dump
  - Filename = UUID → no collision on duplicate original names
- Stores `original_filename` and `file_uuid` in resource `meta` JSON
- `title` defaults to the filename stem if left blank
- Creates knowledge resource with `resource_type = pdf|docx`, `source_content = absolute_path`

### `includes/Processing/KnowledgeTextExtractor.php`
- `extractPdf()`: if source_content is an existing file path (not a URL) → `file_get_contents()` instead of HTTP fetch
- `extractDocx()`: if source_content is an existing file path → pass directly to `parseDocxText()` (no temp file needed)
- Both methods now throw a clear error if source_content is neither a valid URL nor an existing path

### `assets/src/api/provisioning.js`
- New export: `uploadKnowledgeFile(siteUuid, file, title)` — uses `FormData` + `fetch` directly (bypasses `apiFetch` which sends JSON)

### `assets/src/pages/TenantDetail.jsx` — KnowledgeSection
- `resource_type` field: plain text input → **dropdown** with four options:
  - `text` — paste content (textarea)
  - `link` — URL to scrape (URL input)
  - `pdf` — upload file (file input, accept `.pdf`)
  - `docx` — upload file (file input, accept `.docx`)
- Source content field is now **context-sensitive** — renders the right input for each type
- File input shows filename + size preview after selection
- Title is optional for file types (auto-filled from filename if blank)
- On submit: file types call `uploadKnowledgeFile`, text/link types call `createKnowledge`
- Table: title column shows `title (original_filename)` for file resources
- Changing type resets `source_content` and `file` to avoid stale state

## File Storage Layout

```
{wp-uploads}/
    sarah-ai/
        {site_uuid}/
            a1b2c3d4-...-.pdf      ← UUID filename
            e5f6a7b8-...-.docx
```

## Notes
- WordPress media library not used — custom directory gives per-site organization
- `source_content` for uploaded files = absolute server path (not a URL)
- Extractor detects path vs. URL via `file_exists()` / `preg_match(https?://)`
- Reprocessing an uploaded file works identically to text/link resources

## Commit
0034
