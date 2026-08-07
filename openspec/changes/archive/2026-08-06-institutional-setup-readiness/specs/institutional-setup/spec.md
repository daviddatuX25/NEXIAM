## ADDED Requirements

### Requirement: Shared institutional setup hub
The system SHALL provide one administrative Setup readiness hub that reports target catalog prerequisites (cycles/windows, programs, rooms, aptitude/privacy/grades, quotas) with critical/important/optional severity, and SHALL deep-link to configuration surfaces.

#### Scenario: Shared setup holders can open hub
- **WHEN** a user with `registrar.setup.manage` opens `/setup`
- **THEN** the hub renders readiness categories and does not use role middleware as authorization

#### Scenario: Support staff denied
- **WHEN** a registrar_support_staff or guidance_support_staff user without explicit setup grant opens `/setup`
- **THEN** the request is denied

### Requirement: Shared catalog mutation
Registrar Administrator, Registrar Clerk, Guidance Counselor, and Guidance Clerk SHALL be allowed to create/update/deactivate academic cycles, application windows, programs/courses, and exam rooms under `registrar.setup.manage` (D-087 shared setup).

#### Scenario: Guidance can upsert a room
- **WHEN** a guidance_clerk with setup capability saves a new active room
- **THEN** the room is persisted and audited

### Requirement: Program quota authority
Program Head SHALL modify quotas only for assigned programs. Guidance Counselor (Test Administrator) SHALL also modify quotas with audit and a Program Head communication/visibility seam. Quotas are operational before result release.

#### Scenario: Program Head denied cross-program
- **WHEN** a Program Head attempts to mutate another program’s quota
- **THEN** the command is denied

#### Scenario: Registrar denied quota by default
- **WHEN** a registrar_clerk without `program.quota.manage` posts a quota mutation
- **THEN** the command is denied
