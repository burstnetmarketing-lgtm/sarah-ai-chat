Phase 4.3 — Admin Provisioning & Tenant Setup

Objective

This phase introduces the first operational layer of the system, where the foundational data structures created in previous phases become actively usable through controlled administrative actions.

The primary goal is to enable the system to create and configure a complete tenant environment that is ready for real usage in subsequent phases, specifically for client integration and agent execution.

At the end of this phase, an administrator must be able to construct a fully functional tenant setup without interacting directly with the database, and without requiring any unfinished or implicit system behavior.

This phase does not aim to deliver a polished user interface or a self-service onboarding experience. Instead, it focuses on correctness, completeness, and clarity of system behavior under admin control.

⸻

System Context

At this point in the system:
	•	Tenants, sites, tokens, agents, subscriptions, and knowledge resources already exist as data structures.
	•	The system has no real operational flow yet — everything is still passive.

This phase transforms the system from a data model into a configurable platform.

The admin becomes the actor that initializes and wires together all core entities into a working configuration.

⸻

Tenant Provisioning

The system must allow an administrator to create a tenant that represents a customer or business.

A tenant must act as the root ownership boundary of the system. All other entities (sites, subscriptions, users, and indirectly knowledge resources) must be traceable back to a tenant.

The system must ensure that:
	•	A tenant can exist independently of sites or users at creation time.
	•	A tenant has a lifecycle state that can affect its operational eligibility in future phases (e.g. active vs suspended).
	•	No destructive deletion is required to deactivate or disable a tenant.

The design must assume that tenants will later be used for:
	•	subscription enforcement
	•	usage tracking
	•	reporting
	•	access control

Therefore, the tenant model must remain stable and unambiguous.

⸻

User Provisioning and Association

The system must allow an administrator to associate users with tenants.

Users are created and authenticated via WordPress, but Sarah must maintain its own application-level relationship between users and tenants.

This relationship must support:
	•	assigning a role within the tenant context
	•	enabling or disabling a user within that tenant
	•	future support for a user being associated with multiple tenants

The system must not rely solely on WordPress roles to determine access or ownership.

Instead, WordPress should be treated as an identity provider, while Sarah defines the business relationship.

The system should be capable of supporting future differentiation such as:
	•	tenant owner
	•	tenant admin
	•	tenant member

without requiring structural redesign.

Optionally, the system may trigger a welcome email when a user is created or associated, using the email template system defined earlier.

⸻

Site Registration

The system must allow an administrator to create one or more sites under a tenant.

A site represents a real-world integration point, typically a website that will use the Sarah client.

The system must enforce that:
	•	Each site belongs to exactly one tenant.
	•	A tenant can have multiple sites.
	•	A site has its own lifecycle state independent of the tenant.

The site must be treated as a critical boundary because:
	•	knowledge resources are attached to sites
	•	agent execution is scoped per site
	•	client requests resolve to a site

The system must make it easy to determine:
	•	which tenant owns a given site
	•	whether that site is currently active and usable

⸻

Credential Model (Account Key + Site Key)

The system must implement a dual-credential model for client authentication.
	•	Account Key identifies the tenant
	•	Site Key identifies the site

The system must validate that:
	•	the provided site key belongs to a site
	•	that site belongs to the tenant identified by the account key

This ensures that requests cannot mix credentials from different tenants or sites.

The system must not require the client to send any additional identifiers such as tenant_id or site_id explicitly. The keys must be sufficient to resolve the full context.

The design must allow:
	•	multiple site keys per site (for future rotation or environment separation)
	•	revocation or replacement of keys without breaking the ownership model

Credentials must be treated as authentication artifacts, not ownership containers. No business data (such as knowledge) should be attached to keys.

⸻

Agent Assignment

The system must allow an administrator to assign an agent to a site.

The assignment must define which agent is responsible for handling requests coming from that site in future phases.

The system must ensure that:
	•	agents are selectable from the registered agent list
	•	changing an agent does not require modifying knowledge resources
	•	agent assignment is independent from knowledge ownership

This separation is critical because:
	•	knowledge belongs to the site
	•	agents consume knowledge, but do not own it

The design must support future cases where:
	•	multiple agent types exist
	•	agent configuration becomes more complex
	•	agent behavior varies significantly

⸻

Subscription Initialization

When a tenant is created, the system must ensure that a subscription is assigned.

At this stage, the system uses a default trial plan (e.g. 14 days), but the structure must support future plan types.

The system must be able to represent:
	•	the current subscription state of a tenant
	•	the time boundaries of the subscription
	•	the ability to transition between states (trialing → active → expired)

The design must not assume that the current single-plan model is permanent.

Future expansion may include:
	•	multiple plans
	•	usage-based billing
	•	plan upgrades or downgrades

Therefore, the subscription model must remain flexible.

⸻

Knowledge Integration Readiness

The system must allow knowledge resources (from Phase 4.2) to be associated with sites and be retrievable in a consistent way.

At this phase, the system does not need to:
	•	process knowledge
	•	index knowledge
	•	perform retrieval

However, it must ensure that:
	•	knowledge can be created and attached to a site
	•	active knowledge for a site can be retrieved cleanly
	•	no assumptions are made about how knowledge will be used by agents

The system must preserve the principle that:

knowledge belongs to the site, not to the agent and not to the credential.

⸻

Administrative Visibility

By the end of this phase, the system must provide enough visibility for an administrator to understand and verify the configuration.

This includes the ability to inspect:
	•	tenants and their states
	•	users associated with each tenant
	•	sites under each tenant
	•	credentials associated with each site
	•	assigned agents per site
	•	knowledge resources per site
	•	subscription state per tenant

This visibility does not need to be presented as a polished dashboard. Basic administrative screens or structured views are sufficient.

The goal is not visual quality, but operational clarity.

⸻

Constraints

The implementation must respect the following constraints:
	•	Do not embed business logic directly into the database schema
	•	Do not bind knowledge to credentials
	•	Do not assume a tenant has only one site
	•	Do not assume a fixed set of agent types
	•	Do not enforce subscription limits yet (only prepare for them)
	•	Do not introduce dependencies on future phases such as retrieval or AI providers

⸻

Success Criteria

This phase is complete when:
	•	an administrator can create a tenant and fully configure it
	•	all core entities (user, site, keys, agent, subscription, knowledge) are properly connected
	•	the system can represent a valid and usable tenant environment
	•	the system is ready to receive client requests in Phase 4.4 without structural changes

⸻

