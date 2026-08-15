## ADDED Requirements

### Requirement: Single CapabilityKernel dispatch path
The system SHALL expose one non-bypassable `CapabilityKernel` that HTTP controllers (and later Nexi) use to invoke registered capabilities, creating trusted principal/provenance context and running schema validation, authorization, handler invocation, and channel projection before returning a typed result.

#### Scenario: Setup readiness goes through the Kernel
- **WHEN** an authorized staff actor opens the Setup hub
- **THEN** readiness data is produced by invoking `setup.readiness.read@1` through the CapabilityKernel rather than a parallel ad-hoc production query path

#### Scenario: Duplicate capability registration fails closed
- **WHEN** two definitions register the same `key@version`
- **THEN** boot/build fails rather than silently picking one

### Requirement: Capability Registry is the only tool authority
Every callable capability SHALL have one immutable code-owned registry definition including `(key, version)`, kind, strict input/result schemas, application capability, handler binding, and channel projections. Only registry `read` entries are authorized in this change; `propose` and `command` SHALL NOT be registered.

#### Scenario: Reference read capability exists
- **WHEN** the registry boots
- **THEN** it contains `setup.readiness.read@1` bound to `ComputeSetupReadinessQuery` with kind `read`

#### Scenario: Unknown fields rejected
- **WHEN** an invoke supplies undeclared input properties
- **THEN** the Kernel rejects the invocation without calling the handler

### Requirement: GF-21 authorization on invoke
The Kernel SHALL authorize each invoke using the application CapabilityAuthorizer / GF-21 decision authority (Spatie helpers only), intersecting surface/mode policy. PageCapabilityPack membership SHALL NOT be treated as a permission grant.

#### Scenario: Staff without setup capability denied
- **WHEN** a staff actor lacking setup capability invokes `setup.readiness.read@1`
- **THEN** the Kernel denies and does not return readiness categories

#### Scenario: Applicant denied staff readiness
- **WHEN** an applicant principal attempts `setup.readiness.read@1`
- **THEN** the Kernel denies

#### Scenario: Pack absence does not cage an authorized actor
- **WHEN** an authorized staff actor invokes `setup.readiness.read@1` while `page_pack` is null on a mountable staff surface
- **THEN** pack absence alone does not cause denial (authorization follows actor ceiling ∩ registry ∩ GF-21)

### Requirement: HTTP result parity for readiness
Migrating Setup readiness through the Kernel SHALL preserve the authoritative readiness result shape used by the Setup hub (`overall_percent`, `passed`, `total`, `categories` with checks).

#### Scenario: Authorized staff parity
- **WHEN** readiness is loaded for an authorized staff actor after migration
- **THEN** the payload matches the golden fixture shape for the same seed state

### Requirement: Kernel non-goals enforced
This change SHALL NOT install a provider SDK, persist conversation/run/proposal/usage tables, register propose/command tools, expose model tool loops, or redefine PageCapabilityPack as an allowlist cage that hides Nexi on unregistered routes.

#### Scenario: No mutation tools registered
- **WHEN** the registry is inspected after this change
- **THEN** no `propose` or `command` capability kinds are present for Nexi Apply
