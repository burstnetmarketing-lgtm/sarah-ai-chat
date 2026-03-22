# Sarah AI — Security Backlog
## Deferred Security & Hardening Tasks

---

## Overview

This document lists security-related items that were intentionally deferred during Phases 4–6 to prioritize system delivery and testing.

These items must be revisited and implemented before production-scale deployment.

---

## Task 1 — Remove Server URL from Client Settings

### Issue
The server URL is currently configurable via client (WordPress) settings.

### Risk
- Misconfiguration
- Credential leakage to malicious endpoints
- Loss of control over environment routing

### Required Fix
- Remove `server_url` from admin UI
- Inject server base URL via build-time configuration (Vite env)
- Ensure client cannot override server endpoint

---

## Task 2 — Implement Proper CORS Policy

### Issue
CORS is not explicitly controlled for cross-domain requests.

### Risk
- Unauthorized domains may access API
- Potential abuse of `/chat` endpoint

### Required Fix
- Restrict allowed origins
- Support dynamic allowlist if needed
- Apply consistent headers across all public endpoints

---

## Task 3 — Enforce Guardrails Even with Custom Prompt Override

### Issue
Custom `system_prompt` fully replaces composed prompt, including safety rules.

### Risk
- Users may disable critical guardrails
- Increased hallucination or unsafe responses

### Required Fix
- Ensure core rules are always appended or enforced
- Separate:
  - system behavior rules (non-removable)
  - user-defined prompt content

---

## Task 4 — Strengthen Credential Validation

### Issue
Account key + site key validation is functional but minimal.

### Risk
- Potential misuse or cross-tenant abuse
- Weak enforcement of ownership relationships

### Required Fix
- Ensure strict mapping:
  - account key → tenant
  - site key → site
  - site → tenant must match
- Add logging for invalid credential attempts
- Consider rate limiting or abuse detection

---

## Task 5 — Harden Session & Access Control

### Issue
Session and message APIs are currently admin-focused and not fully scoped.

### Risk
- Overexposure of session data
- Weak tenant isolation in future expansion

### Required Fix
- Introduce tenant-scoped access control
- Ensure users can only access sessions within their tenant
- Prepare for role-based access (admin vs. operator)

---

## Task 6 — Secure Usage & Internal APIs

### Issue
Usage endpoints and internal data are exposed to admin without further segmentation.

### Risk
- Data leakage in multi-tenant environments
- Lack of fine-grained permissions

### Required Fix
- Add tenant-level filtering enforcement
- Validate all query inputs
- Prevent cross-tenant data access

---

## Task 7 — Input Validation & Sanitization

### Issue
User inputs (messages, knowledge content, links) are accepted with minimal validation.

### Risk
- Injection attacks
- malformed data breaking pipeline

### Required Fix
- Sanitize all inputs at API boundary
- Validate content types strictly
- Normalize unsafe inputs before processing

---

## Task 8 — Rate Limiting / Abuse Protection

### Issue
No rate limiting is currently applied to chat or embedding endpoints.

### Risk
- API abuse
- excessive cost (OpenAI usage)
- denial of service scenarios

### Required Fix
- Add per-site and/or per-account request limits
- Implement throttling strategy
- Log excessive usage patterns

---

## Task 9 — Logging & Monitoring for Security Events

### Issue
Runtime logging exists but is not focused on security events.

### Risk
- Suspicious activity goes unnoticed

### Required Fix
- Log:
  - failed auth attempts
  - invalid keys
  - abnormal usage spikes
- Prepare for alerting (future phase)

---

## Summary

These tasks are not blocking current functionality but are critical for:

- production readiness
- multi-tenant safety
- cost control
- system integrity

They should be implemented before scaling the platform to real users.

---

*Generated: Security Backlog*
