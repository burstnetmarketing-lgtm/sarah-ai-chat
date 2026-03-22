# Phase 4.4.1 — System Design Summary
## Server Runtime, Session Foundation & Request Handling

---

## 1. Overview

Phase 4.4.1 introduces the first real server-side runtime behavior of the Sarah platform.

Up to this point, the system has a stable data model, tenant provisioning flow, site registration, dual credentials, agent assignment, subscription structure, and site-owned knowledge resources. However, the platform is still largely passive. This phase turns that foundation into an executable runtime pipeline that can accept a chat request, validate credentials, resolve the tenant and site context, create or continue a chat session, execute the assigned agent, and return a server-generated response.

This phase is strictly server-side. It does not depend on the admin provisioning UI from Phase 4.3.2, and it does not include client adaptation work. As long as the provisioning data exists — whether created by REST endpoints or through admin screens — this phase must work.

The outcome of this phase must be a working server runtime that is ready for end-to-end testing. A client should be able to send a message with valid credentials and receive a server-backed response. Even if the first implementation uses a dummy agent or an initial OpenAI-backed agent, the runtime path must already reflect the long-term structure of the platform.

---

## 2. Primary Objective

The goal of this phase is to make the server capable of handling a real chat request in a secure, state-aware, and extensible way.

At the end of this phase, the server must be able to:

- receive a chat request from a configured site
- validate account-level and site-level credentials
- resolve the correct tenant and site context
- verify that the tenant, site, subscription, and assigned agent are all eligible for runtime use
- create a new chat session when a conversation begins
- continue an existing chat session when the conversation is ongoing
- store both customer and assistant messages under that session
- allow lead or contact information to be attached to the session during the conversation
- make active site-owned knowledge available to the agent runtime
- execute the currently assigned agent
- return a structured response payload for client consumption
- prepare the system for future analysis, reporting, lead notification, and provider expansion

This phase should be treated as the point where the system stops being a collection of configured records and becomes a functioning conversation platform.

---

## 3. Runtime Scope

This phase includes:

- server-side chat endpoint design and runtime handling
- credential validation and context resolution
- operational eligibility checks
- session creation and session continuation
- message persistence
- session-level contact and lead data capture
- runtime loading of site-owned active knowledge
- agent execution
- structured response generation
- usage logging readiness

This phase does not include:

- client-side integration changes
- chat widget redesign
- final dashboard design
- advanced reporting UI
- embeddings, retrieval ranking, or vector search
- conversation analytics dashboards
- advanced quota enforcement
- full CRM or notification workflows

---

## 4. Runtime Request Model

The server must accept a request that includes, at minimum:

- Account Key
- Site Key
- User message

The request may also include additional non-authoritative metadata if helpful for debugging, correlation, future analytics, or client behavior. However, the server must not trust client-supplied identifiers such as `tenant_id`, `site_id`, or `agent_id` as authority. Those must always be derived from the validated server-side credential and ownership model.

The server should also support a way to indicate session continuity. The client may send an existing session identifier when continuing a conversation, but the server must verify that the session belongs to the resolved tenant and site context before using it.

The request model should be simple enough for immediate integration, but structured enough that later fields such as locale, page URL, visitor metadata, or message source can be introduced without breaking the contract.

---

## 5. Authentication and Context Resolution

This phase must use the dual-credential model established in Phase 4.3.

The server must validate:

- Account Key → resolves to tenant
- Site Key → resolves to site
- Site → resolves to tenant

The request is valid only if both credentials are valid and both resolve to the same tenant context.

Requests must be rejected if:

- the account key does not exist
- the account key is revoked
- the account key is expired
- the site key does not exist
- the site key is revoked
- the site key is expired
- the resolved site does not belong to the tenant identified by the account key

The server must not expose security-sensitive distinctions that allow callers to infer which part of validation failed. Failure responses should remain controlled and non-enumerative.

`CredentialValidator` or an equivalent dedicated runtime service should remain the primary entry point for this logic. Authentication and resolution should not be scattered across unrelated controllers.

---

## 6. Operational Eligibility Checks

Credential validity alone is not enough. After the runtime context is resolved, the server must confirm that the context is currently operationally eligible.

At minimum, the runtime must be able to reject or block execution when:

- the tenant is not in an operational state
- the site is not in an operational state
- the tenant has no valid subscription context if subscription existence is required
- the assigned agent is missing or inactive
- the assigned agent is not allowed by the tenant's current plan
- the resolved session, when provided, does not belong to the resolved site and tenant context

This phase does not need to implement final quota accounting or rate limiting, but it must leave clear insertion points for those checks in future phases.

---

## 7. Agent Entitlement by Plan

The runtime must not assume that any assigned agent may execute for any tenant.

By this point, the subscription and plan system already supports agent availability as part of the plan model. Therefore, before agent execution begins, the runtime must verify that the assigned agent is permitted under the active plan or subscription context of the tenant.

This means the runtime flow must be able to answer:

- what is the active subscription for this tenant?
- what plan is attached to that subscription?
- is the assigned agent allowed by that plan?

If the assigned agent is not permitted, the request must be rejected in a controlled way. The runtime must not silently substitute another agent unless a deliberate fallback policy is added in a later phase.

This check is important because it connects runtime execution to the commercial and entitlement model of the platform.

---

## 8. Chat Session Foundation

This phase must introduce chat sessions as first-class runtime entities.

A chat session represents a single ongoing conversation between a visitor and the platform under a specific tenant, site, and agent context. The purpose of the session is to preserve continuity across multiple messages, allow later analysis, and provide a place where operational metadata and captured lead information can accumulate over time.

The system must be able to:

- create a new session when a conversation begins
- continue an existing session when a valid session reference is supplied
- associate the session with the resolved tenant, site, and agent context
- preserve session state over time
- make the session available for later reporting, notification, and operational review

A session should not be treated as just a temporary request container. It is the core ownership object for runtime conversations.

The system should support lifecycle-aware session states such as open, closed, archived, abandoned, or equivalent operational states if needed later. The exact storage design is up to the coding agent, but the system must clearly support the idea that a session has state and history.

---

## 9. Message Persistence

Each customer message and each assistant response must be persisted under the session.

The platform must support storing at least:

- customer-originated messages
- assistant-generated messages

The design should remain open to future message roles such as system messages, moderation messages, notifications, or tool-generated content.

Messages must belong to a session, and the system must be able to retrieve the ordered message history for that session later. This is important not only for chat continuity, but also for future auditing, quality review, and lead analysis.

The runtime should not assume that every request is stateless. Even if the first client integration uses a simple request-response flow, the message model must already be conversation-aware.

---

## 10. Session-Level Lead and Contact Capture

During the life of a session, the platform must be able to attach contact or lead information to that session.

At minimum, the design must support the capture of:

- name
- phone
- email

These values may be unknown at session creation time and may remain null for many sessions. The system must allow them to be filled later as the conversation progresses.

These fields are important enough that they should be treated as first-class session-level data, not only as generic metadata. They are likely to be used later for:

- lead review
- notification
- reporting
- filtering
- operational follow-up

At the same time, the session model must also support flexible key/value-style captured data for additional information that may appear during a conversation, such as:

- suburb
- service type
- budget
- preferred time
- property type
- other business-specific intake values

This means the design should support a hybrid approach:

- first-class session fields for high-value common attributes
- a flexible structured extension for additional captured values

The runtime must not force all captured information into one unstructured metadata blob if future filtering or reporting is likely.

---

## 11. Session Continuity Rules

The runtime must support continuing an existing conversation in a safe way.

If the client sends a session reference, the server must verify that:

- the session exists
- the session belongs to the resolved tenant
- the session belongs to the resolved site
- the session is in a state that still permits continuation, if lifecycle state is enforced

If these checks fail, the server must not attach the new message to the wrong session. It must either reject the request or deliberately create a new session according to the platform policy chosen by the coding agent.

The important requirement is that session continuity must be safe and explicit. The server must not trust arbitrary client-supplied session identifiers without ownership validation.

---

## 12. Knowledge Usage During Runtime

This phase is the first point where site-owned knowledge becomes part of runtime execution.

The runtime must resolve the active knowledge resources associated with the resolved site and make them available to the agent execution layer. The runtime does not yet need to perform semantic retrieval, chunk ranking, or vector search. However, it must follow the established ownership model:

- knowledge belongs to the site
- the runtime resolves site knowledge
- the agent consumes that knowledge
- credentials only establish access context

The runtime must not bypass this model by attaching knowledge behavior directly to credentials or directly to the tenant without going through site ownership.

The first implementation may pass the full active knowledge set or a simplified representation into the agent context. Later phases may replace that with smarter retrieval.

---

## 13. Agent Execution Layer

This phase must execute the site's assigned agent through a dedicated runtime abstraction.

The request handler must not hardcode all response behavior directly in the endpoint controller. Instead, the runtime should resolve the assigned agent and hand execution to an agent-aware service, executor, or adapter layer.

The runtime contract should provide the agent execution layer with enough context to operate meaningfully, including:

- tenant context
- site context
- session context
- incoming user message
- available site-owned active knowledge
- assigned agent identity and configuration
- optional prior message history if included at this phase

The first agent may be a dummy implementation or an initial OpenAI-backed implementation. The important requirement is that the runtime path must already be provider-agnostic enough that later provider changes do not require redesigning the request contract.

This phase must not let the temporary first agent implementation become the permanent architecture by accident.

---

## 14. OpenAI-First but Provider-Agnostic Runtime

If the first real agent implementation uses OpenAI, that is acceptable and practical for this phase. However, the runtime must still preserve a provider-agnostic structure.

This means:

- the endpoint should not call OpenAI directly in controller code
- provider-specific logic should live behind an agent execution abstraction
- the runtime should treat OpenAI as one implementation path, not as the architecture itself

This requirement matters because future phases may add:

- a self-hosted agent server
- another provider such as Bedrock
- a different OpenAI model
- plan-based provider restrictions
- fallback or multi-provider strategies

The runtime built in this phase should make such changes incremental rather than structural.

---

## 15. Response Contract

The server response must be structured, stable, and ready for client integration.

At minimum, the response should provide:

- success or failure outcome
- session reference or session identity usable for continuation
- assistant response content
- enough metadata for the client to know that the response came from the server runtime
- controlled error information when the request fails

The response contract must be treated as an evolving platform contract, not as a disposable temporary payload. The client adaptation work in Phase 4.4.2 should be able to build against this response without expecting another redesign immediately after.

It is acceptable for the first response content to be simple, but the response shape should remain extensible for future additions such as:

- message identifiers
- response source
- agent metadata
- token usage
- latency
- warning flags
- structured message formats

---

## 16. Error Handling

This phase must establish reliable runtime error behavior.

The server must be able to handle and return controlled failure responses for conditions such as:

- missing credentials
- invalid credentials
- mismatched account/site context
- inactive or ineligible tenant
- inactive or ineligible site
- missing or inactive agent
- disallowed agent under the current plan
- invalid or foreign session identifier
- malformed request payload
- upstream provider failure
- internal runtime exceptions

Security-sensitive failures should remain generic where appropriate. Operationally useful failures should still be clear enough that client integration and testing are practical.

The platform does not need a final public error taxonomy yet, but it must avoid ambiguity, silent failure, or brittle behavior.

---

## 17. Usage Logging and Runtime Visibility

This phase should begin turning runtime activity into persistent operational records.

At minimum, the runtime should be able to record that a request occurred and associate that activity with:

- tenant
- site
- agent
- session
- subscription context when available

The first implementation does not need to capture every future metric or billing dimension. However, runtime activity should no longer be invisible.

This phase should also leave the platform ready for future session-triggered operational behavior such as:

- owner notification that a new chat session opened
- lead notification when contact information is captured
- session-based reporting
- conversation review

The notification behavior itself does not need to be implemented now, but the session and message model must make it possible later.

---

## 18. Security and Guardrails

The server must never trust raw IDs from the client as the source of truth for tenant, site, session, or agent identity.

The server must not allow cross-tenant or cross-site session attachment.

The server must not expose key hashes, internal credential records, or storage-level details in responses.

The server must not execute an agent unless the full runtime context is valid and entitled.

The server must not make the initial agent implementation the permanent de facto contract.

The server must not treat session contact data as a reason to bypass proper ownership or lifecycle validation.

The server must not collapse all captured lead information into an opaque structure if some parts are expected to be queried or reported later.

---

## 19. Non-Goals

This phase must not include:

- client-side integration changes
- chat widget UI redesign
- admin dashboard polish
- vector search or semantic retrieval
- final conversation analytics dashboards
- advanced rate limiting
- final quota enforcement
- CRM workflow automation
- notification delivery rules
- polished lead-management UI
- full observability stack

Those belong to later phases.

---

## 20. Success Criteria

This phase is complete when the server can accept a chat request using account key and site key, resolve the correct tenant and site, validate runtime eligibility, create or continue a chat session, store both customer and assistant messages, attach or update session-level lead information, resolve the assigned agent, make active site-owned knowledge available to that agent, execute the agent, and return a structured response payload that a real client can consume.

At that point, the server is no longer passive. It becomes a functioning, session-aware conversation runtime.

---

## 21. Phase Boundary

This phase ends when the server-side runtime path is fully operational and session-aware.

The next phase, Phase 4.4.2, is responsible for adapting the already-built client so that it uses this real server runtime path instead of mock responses.
