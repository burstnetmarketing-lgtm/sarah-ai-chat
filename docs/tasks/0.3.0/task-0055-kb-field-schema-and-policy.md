# Task 0055 — Canonical KB Field Schema + Policy Layer

## Goal
Define a canonical, versioned schema for structured KB fields shared across PHP backend and JS widget. Add a policy validation layer that filters fields before rendering.

## Changes

### `includes/Processing/KnowledgeFieldSchema.php` (new)
- Constants for all canonical field keys: `contact.phone_admin`, `contact.phone_marketing`, `contact.phone_sales`, `contact.email_support`, `contact.email_sales`, `contact.website`, `business.address`, `business.hours`, `business.name`, `business.description`
- `allKeys()` — returns all canonical keys
- `extractFromResource(array $resource)` — reads `meta.structured_fields` JSON from a resource row, filters to canonical keys only

### `assets/src/widget/knowledgeFieldSchema.js` (new, sarah-ai-client)
- JS mirror of the PHP schema: same canonical key constants + `ALL_KEYS` Set
- `isCanonicalKey(key)` — validates a key
- `filterCanonicalFields(fields)` — strips non-canonical keys + empty values from a fields object

### `assets/src/widget/ContactCard.jsx` (modified)
- Imports `isCanonicalKey` from `knowledgeFieldSchema.js`
- Added policy validation before `formatField()`: only fields with canonical keys and non-empty string values are rendered
- Removes TODOs — policy enforcement is now implemented

### `includes/Runtime/OpenAiAgentExecutor.php`
- `buildStructuredOutputInstruction()` now includes safe response instruction (from `KnowledgePolicyFilter::restrictedDataSafeResponse()`) and expanded canonical key list

## Structured Fields Convention
Admins can attach structured fields to any knowledge resource by adding `meta.structured_fields` JSON:
```json
{"structured_fields": {"contact.phone_admin": "04XX XXX XXX", "contact.website": "https://..."}}
```
These are merged and returned by the `/knowledge-fields` API (Task 0056).

## Commit
0055
