# Phase 4.3.2 — Admin Provisioning UI (Operational Screens)

## Objective

Provide a minimal, functional admin interface that allows platform administrators to perform all provisioning operations defined in Phase 4.3 without using external tools such as Postman or direct API calls.

This phase is not about building the final product dashboard. It is about enabling fast, reliable, and repeatable setup of tenant environments for testing and operational use.

The UI must be simple, fast, and utilitarian — not polished.

---

## Scope

This phase introduces internal admin screens for:

- creating tenants
- associating users
- registering sites
- issuing account keys
- issuing site keys
- assigning agents
- attaching knowledge resources
- viewing basic configuration state

All UI actions must call the existing REST endpoints from Phase 4.3.  
No business logic should be reimplemented in the UI layer.

---

## Core Design Principles

- UI is a thin layer on top of REST APIs
- No duplication of validation logic in frontend
- No design or styling effort beyond usability
- Prefer speed and clarity over completeness
- All actions must be immediately testable

---

## Required Screens

### 1. Tenants List + Create

The system must provide a page that:

- lists all tenants
- shows basic info:
  - name
  - status
  - subscription state
- allows creating a new tenant

Creating a tenant must:
- call `POST /tenants`
- automatically create a trial subscription through the existing backend flow

This screen is the main entry point for provisioning.

---

### 2. Tenant Detail / Setup View

The system must provide a detail screen for a selected tenant.

This screen must act as the operational setup hub for that tenant and make it easy to complete the remaining provisioning steps.

It must display:

- tenant details
- current subscription state
- associated users
- registered sites

It should make the setup flow obvious and reduce the need to jump between unrelated pages.

---

### 3. User Association

The system must provide a way to:

- select an existing WordPress user or enter a user reference
- associate that user with the current tenant
- assign the Sarah-level role for that association

The screen does not need to become a full user-management system. Its purpose is only to support tenant onboarding and setup.

If welcome email triggering already exists in the backend, the UI should expose it in a simple and explicit way.

---

### 4. Site Registration

The system must allow creating one or more sites under a tenant.

The screen must allow the admin to:

- register a site
- see existing sites for that tenant
- inspect site state at a basic level

This screen should clearly reinforce that a site belongs to a tenant and acts as the boundary for:

- site keys
- agent assignment
- knowledge resources

---

### 5. Account Key Management

The system must provide a tenant-level section for issuing and viewing account keys.

The UI must allow:

- issuing a new account key
- viewing existing account key records
- revoking a key if that capability already exists in the API

Important behavior:

- raw keys are shown only once after issuance
- hashes must never be displayed
- the UI must clearly warn the admin to copy and store the raw key immediately

This screen must make it obvious that account keys are tenant-level credentials.

---

### 6. Site Key Management

The system must provide site-level key management for each site.

The UI must allow:

- issuing a new site key
- viewing existing site key records
- revoking a key if supported

Important behavior:

- raw site keys are shown only once after issuance
- hashes must never be displayed
- the UI must clearly distinguish site keys from account keys

This section should make the credential hierarchy clear:

- account key = tenant credential
- site key = site credential

---

### 7. Agent Assignment

The system must provide a way to assign an agent to a site.

The UI must:

- list available agents from the existing backend
- allow selecting one active agent for the current site
- display the currently assigned agent

This screen should not attempt to configure advanced runtime behavior. It only needs to support selecting which registered agent the site should use.

---

### 8. Knowledge Attachment

The system must provide a minimal way to create and view knowledge resources for a site.

It must support:

- adding a knowledge resource
- selecting resource type
- entering source content
- viewing existing resources for the site
- basic lifecycle updates if already exposed by the API

This is not a full knowledge management experience. It is only enough to make site setup complete before Phase 4.4.

---

### 9. Basic Configuration Visibility

The UI must make it easy for an admin to verify whether a tenant is ready for runtime testing.

At minimum, the admin should be able to confirm:

- tenant exists
- user association exists
- site exists
- account key has been issued
- site key has been issued
- agent has been assigned
- knowledge resources exist
- subscription is present

This can be shown as structured sections or a simple readiness summary. It does not need charts, cards, or polished visual design.

---

## Interaction Expectations

The interface should support a fast operational workflow.

An admin should be able to create a tenant and complete the basic setup in one session without switching to Postman, editing the database, or navigating through unrelated WordPress areas.

The UI should favor:

- clear grouping
- explicit labels
- direct action buttons
- immediate success/error feedback

The goal is operational speed, not presentation quality.

---

## Technical Constraints

- Use the existing backend endpoints from Phase 4.3
- Do not duplicate provisioning logic in the UI
- Do not move provisioning rules into JavaScript or page handlers
- Keep the interface internal/admin-only
- Do not expand this phase into final dashboard design
- Do not introduce customer-facing UI
- Do not treat this phase as the reporting layer

---

## Non-Goals

This phase must not include:

- polished dashboard design
- analytics widgets
- customer-facing panel
- advanced filtering/reporting
- visual design system work
- self-service onboarding
- runtime chat testing UI

Those belong to later phases.

---

## Success Criteria

This phase is complete when a platform administrator can perform the full setup flow through the admin UI and prepare a tenant environment for runtime testing in Phase 4.4 without relying on API tools or database access.

The result should be an operational provisioning interface, not a finished product dashboard.
