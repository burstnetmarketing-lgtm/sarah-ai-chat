# KB Access Control & Integration - Task Specification

## Overview

This document defines the full implementation plan for Knowledge Base
(KB) access control, secure data handling, and frontend integration in
the Sarah AI system.

The goal is to ensure: - No private data leaks into AI prompts or
frontend - Structured KB data is accessible deterministically - Full
end-to-end security and tenant isolation

------------------------------------------------------------------------

## Tasks

### 1. Add KB Visibility Model (Public / Private)

Add a visibility field to KB resources and/or chunks.

Requirements: - Values: `public`, `private` - Default: `public` - Must
be enforced at query level

------------------------------------------------------------------------

### 2. Define Canonical KB Field Schema

Create a consistent schema for structured fields.

Example: - contact.phone_admin - contact.phone_sales - contact.website -
business.address

------------------------------------------------------------------------

### 3. Add Tenant Isolation with site_token

Ensure all KB access is scoped per site.

Requirements: - All queries must filter by `site_id` - API access must
validate `site_token`

------------------------------------------------------------------------

### 4. Apply KB Filtering Before Prompt Injection

Ensure private data never reaches AI prompts.

Requirements: - Filter KB data before system prompt creation - No
exception

------------------------------------------------------------------------

### 5. Make SemanticRetriever Visibility-Aware

Modify retrieval logic:

Requirements: - Exclude private chunks - Only return allowed data

------------------------------------------------------------------------

### 6. Add Safe Response for Restricted Data

If user requests private info:

-   Return predefined safe response
-   Do not rely on AI improvisation

------------------------------------------------------------------------

### 7. Use Intent Detection Only as UX Helper

Intent detection can assist UX but must not be used as primary security.

------------------------------------------------------------------------

### 8. Implement Knowledge Fields API

Endpoint: GET /sarah-ai-server/v1/sites/{uuid}/knowledge-fields

Response: { "fields": { "contact.phone_admin": "...", "contact.website":
"...", "business.address": "..." } }

Requirements: - Only public data - Protected with site_token

------------------------------------------------------------------------

### 9. Connect businessProvider to API

Replace mock logic with real API call.

------------------------------------------------------------------------

### 10. Update ContactCard Rendering

Render only validated fields.

------------------------------------------------------------------------

### 11. Add Policy Validation Layer

Ensure: - Field-level filtering - Response validation

------------------------------------------------------------------------

### 12. Add End-to-End Tests

Validate: - Private data not exposed - Public data accessible - Prompt
safety

------------------------------------------------------------------------

### 13. Add Admin UI for Visibility

Allow admin to: - Set visibility - Manage KB entries

------------------------------------------------------------------------

### 14. Document KB Access Control

Document: - Architecture - Data flow - Security rules

------------------------------------------------------------------------

## Deliverables

-   Backend implementation
-   API endpoints
-   Frontend integration
-   Tests
-   Documentation
