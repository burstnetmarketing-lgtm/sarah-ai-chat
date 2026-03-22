# SCENARIO — Phase 4.1
## Core Infrastructure, Database & System Foundations

### Purpose

This phase establishes the foundational system layer for Sarah as a multi-tenant platform. The purpose of this phase is not to build the final user experience, complete workflows, or production-ready response logic. The purpose is to create a durable and extensible core that later phases can safely build upon.

The implementation produced in this phase must support the core concepts of the product from the beginning, even when some behaviors are not yet fully activated. The data model and supporting infrastructure must be designed so that later additions such as richer onboarding, real AI agents, billing, reporting, tenant-specific customization, and more advanced permissions can be introduced with minimal refactoring and minimal migration pressure.

This phase should be approached as system groundwork, not as MVP shortcutting. Decisions made here must reduce future structural rework.

---

### Product Context

Sarah is a centralized multi-tenant system. One central server-side application must support multiple customers, where each customer may have its own users, websites, access rules, subscriptions, and future service behavior.

The system must distinguish clearly between platform-wide concerns and tenant-specific concerns. It must also preserve a clean separation between identity, ownership, assignment, configuration, and runtime behavior.

WordPress may act as the identity layer for user accounts, but Sarah must maintain its own application-level model for tenant association, access scope, ownership, and feature-level behavior.

---

### Outcome of This Phase

At the end of this phase, the system must be capable of storing and relating the core entities required for later phases. It must provide a clean foundation for:

- admin-created tenants
- admin-created customer users
- tenant-to-user association
- tenant-owned sites
- secure site identification using token-based access
- pre-registered agents that can later be assigned to sites
- plan and subscription lifecycle representation
- reusable email template infrastructure
- configurable system settings
- future usage tracking and reporting expansion

This phase does not require polished UI, full end-to-end client communication, or real agent execution. It does require a complete and intentional foundation that later phases can rely on without structural redesign.

---

### Non-Goals for This Phase

The implementation in this phase must not try to prematurely solve later product layers. The following are explicitly outside scope for this phase:

- self-service customer registration
- advanced onboarding flows
- full dashboard design
- rich reporting UI
- real AI provider integration
- payment processing
- billing engine
- finalized runtime response engine
- production-grade client widget behavior
- final email sending workflows
- complex analytics aggregation

Basic admin-facing utilities may exist for testing or validation, but this phase is not primarily a UI phase.

---

### General Design Expectations

The coding agent must make implementation decisions that favor long-term extensibility. The design must be robust enough to survive later expansion without repeated structural rewrites.

The implementation should assume that future changes are likely in the following areas:

- a user may be associated with more than one tenant
- a tenant may own more than one site
- a site may eventually support more than one token or rotated credentials
- an agent catalog may grow and include multiple implementation types
- agent configuration may expand in structure and complexity
- subscription models may evolve beyond a single trial plan
- email templates may later support tenant-specific overrides
- settings may grow beyond a small fixed set
- permissions may become more granular over time
- usage records may later require richer dimensions and aggregation strategies

For that reason, the implementation must not be overly optimized for a temporary simplified assumption if that assumption is likely to break in later phases.

---

### Core Capabilities the System Must Be Able to Support

#### 1. Tenant capability

The system must be able to represent a customer as a first-class application entity. A tenant must be treated as an isolated business context that can own sites, have associated users, carry subscription state, and hold tenant-level configuration in later phases.

A tenant must be able to move through lifecycle states such as active, inactive, suspended, trialing, or archived-equivalent states, even if the exact storage model is chosen by the coding agent. The important requirement is that the system can distinguish whether a tenant is operational, restricted, disabled, or no longer in normal service.

The design must preserve tenant continuity. Deactivation or archival-like behavior must not require destructive deletion of tenant records.

#### 2. User-to-tenant association capability

The system must support application-level association between WordPress users and Sarah tenants. This association must not rely only on native WordPress roles.

The system must support the idea that a user belongs to a tenant in Sarah, and that their effective application role is defined within the Sarah context. The design must also leave room for a user to potentially relate to more than one tenant in the future, even if the initial workflow only uses one association.

The system must be able to express differences between platform administrators and customer-side users. It must also allow future expansion into more granular role or capability models without forcing a redesign of the user-tenant relationship.

#### 3. Site ownership capability

The system must support the registration of one or more sites under a tenant. A site represents a client integration endpoint such as a website using Sarah.

A site must be uniquely identifiable, associated with a single tenant, and able to carry its own operational state. The design must support later additions such as site-level configuration, environment flags, connection metadata, or provider-specific settings.

The system must make it possible to determine which tenant owns a given site and whether that site is currently eligible to communicate with the platform.

#### 4. Site authentication capability

The system must support token-based or key-based identification for site-to-server communication. This capability is required because the client integration will later communicate without relying on a logged-in panel user session.

The design must support secure identification of a site and must allow the platform to determine whether an incoming request is associated with a valid and usable site credential. The design must also leave room for future credential rotation, expiration, revocation, replacement, or multiple credentials per site if needed later.

This phase does not need to finalize runtime validation logic, but it must create the storage and relation model needed to support it.

#### 5. Agent catalog and assignment capability

The system must support agents as configurable application entities rather than hardcoded response branches. An agent must be something the platform can identify, register, describe, activate or deactivate, and assign to a site.

In this phase, at least two dummy agents must be present in the system as seed or initial records. They do not need full message logic yet. Their purpose is to establish the future agent model and to allow later phases to assign a selected agent to a site and test end-to-end behavior.

The implementation must allow a site to have an active agent association. The design should not assume that all agent types are structurally identical forever. It should leave room for future variations such as AI-backed agents, provider-based agents, rule-based agents, or internally implemented responders.

The agent model must also allow future agent-specific configuration without forcing constant schema changes.

#### 6. Subscription and plan capability

The system must support the idea that tenants operate under a plan and an active subscription state. In this phase, the product only requires a basic default trial model, but the structure must support later growth.

The system must be able to represent:

- what plan a tenant is on
- whether the tenant is in trial or another state
- when a subscription begins
- when it ends or becomes invalid
- whether access should later be considered restricted, expired, cancelled, or otherwise non-active

The implementation must include the ability to represent a default 14-day trial plan from the beginning. Even if there is only one plan now, the design must not assume there will only ever be one plan.

This phase does not require invoices, payment methods, recurring billing logic, or payment provider integration. It does require a foundation that later billing work can build on without reshaping the core model.

#### 7. Email template infrastructure capability

The system must include a reusable email template foundation. The purpose is not simply to send one welcome email in a hardcoded way, but to establish an email infrastructure that later phases can use consistently.

The platform must be able to define template-based email content that can later be rendered with dynamic variables. The system must be ready to support transactional messages such as welcome emails, credential delivery, activation-related messages, and future notifications.

The implementation should support both the concept of reusable template definitions and the future ability to inject runtime data. The exact storage strategy is up to the coding agent, but the result must be flexible and maintainable.

The sending mechanism should be compatible with WordPress mailing behavior, because later phases are expected to send outbound emails through the WordPress environment.

#### 8. System configuration capability

The system must provide a way to store application-level settings without relying on hardcoded constants for everything. These settings may later control email defaults, login URLs, behavior toggles, branding data, or integration-specific values.

The design should support grouped or categorized settings and should allow future additions without requiring repeated structure changes.

#### 9. Usage and reporting readiness capability

This phase does not need full reporting or analytics logic, but the data foundation must be ready for later usage tracking. The design should leave room for recording request-related activity tied to tenant, site, agent, subscription, or other relevant contexts.

Even if the implementation only introduces a minimal placeholder or structural readiness, it must avoid decisions that would make later logging and reporting unnecessarily difficult.

---

### Seed / Initial Data Expectations

The coding agent must prepare the system so that it starts with meaningful baseline records where appropriate.

At minimum, the system should be ready to include:

- two dummy agents, clearly distinguishable from one another
- one default trial plan representing a 14-day evaluation period
- one welcome-style email template or equivalent baseline template definition
- any essential baseline configuration required for later phases to function cleanly

The purpose of this seed layer is not to fake production readiness, but to ensure that later phases can begin integration and testing immediately without retrofitting the foundation.

---

### Behavioral Expectations for Future Compatibility

Even though this phase does not implement all workflows, the structure created here must make the following future flows easy to implement:

- an admin creates a tenant
- an admin creates a user and associates that user with the tenant
- an admin registers a site for that tenant
- the system assigns or stores a site credential
- an admin selects which agent that site should use
- a default subscription is attached to that tenant
- a welcome email template can later be rendered and sent
- the client widget can later authenticate using the stored site credential
- usage can later be recorded against site, tenant, and agent context
- reporting can later read from a coherent ownership model

The coding agent should constantly evaluate whether the chosen implementation helps or harms these future flows.

---

### Architectural Guardrails

The implementation must follow the spirit of these guardrails:

The coding agent must not overfit the model to the current dummy-agent stage. Dummy agents exist only to validate the architecture, not to define its long-term limits.

The coding agent must not collapse unrelated concepts into one data object if that makes future ownership, reporting, or lifecycle handling unclear.

The coding agent must not treat WordPress roles as the complete permission model of Sarah. WordPress can support authentication and identity, but Sarah requires its own application-level ownership and access structure.

The coding agent must not build the system in a way that assumes destructive deletion is the primary path for lifecycle changes. Lifecycle-aware behavior is preferred over deletion-first modeling.

The coding agent must not hide important application concepts inside opaque structures when those concepts are likely to need querying, filtering, assignment, or reporting later.

At the same time, the coding agent should avoid needless overengineering. The target is not theoretical perfection. The target is a practical, extensible, and stable foundation.

---

### Implementation Expectations

The deliverable for this phase should include the infrastructure necessary to persist the required entities and relationships in a WordPress-compatible environment.

The coding agent is expected to decide the exact schema, indexing strategy, constraints, and representation details. Those choices should be guided by the requirements above, not by convenience shortcuts.

The implementation should include:

- creation of the foundational storage structures required by the phase
- initialization or seed support for the baseline records described above
- a maintainable way to evolve the foundation in later phases
- enough internal clarity that the next phases can build features without redefining core relationships

Basic test utilities, validation helpers, or lightweight admin support are acceptable if they help verify correctness, but they are not the primary outcome.

---

### Acceptance Criteria

This phase should be considered complete when all of the following are true:

The system can represent tenants as durable first-class entities.

The system can represent the association between WordPress users and Sarah tenant contexts in a way that is independent from native WordPress roles alone.

The system can represent tenant-owned sites and determine their ownership clearly.

The system can represent site-level credentials or tokens for future client-server authentication.

The system can store and identify multiple agents, including at least two seeded dummy agents, and can support assignment of an agent to a site.

The system can represent plans and tenant subscriptions, including a default 14-day trial structure.

The system can define and store reusable email templates for later rendering and sending through WordPress-compatible mail behavior.

The system can store general application settings without relying only on hardcoded values.

The resulting structure is coherent enough that later phases can implement onboarding, login, dummy-agent testing, runtime logic, and reporting without redesigning the data foundation.

---

### Final Instruction to the Coding Agent

When making design decisions in this phase, prioritize future stability over short-term convenience. If a modeling choice would likely force a migration or redesign in the next few phases, choose the more extensible path now.

Do not wait for later phases to fix foundational ambiguity. This phase exists specifically to remove that ambiguity and to establish a reliable base for all subsequent work.
