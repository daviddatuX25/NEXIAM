# E1 Identity & Reference Foundation Tasks

## Spike

- [x] S1 Install and verify `spatie/laravel-permission` on Laravel 13 / PostgreSQL 16
- [x] S2 Record GO WITH CONDITION decision; disable Spatie Gate auto-registration
- [x] S3 Add `CapabilityAuthorizer` as GF-21 decision seam over Spatie grants
- [x] S4 Denial/allow Pest coverage for authorizer + capability middleware

## Identity / reference

- [x] I1 Staff authentication surfaces (login/logout session)
- [x] I2 Seed minimal capability catalog from GF-04 vocabulary (not legacy role strings)
- [x] I3 Office/program/cycle scope context on the actor session
- [x] I4 Expand reference catalogs required by E2 beyond E0 institution/cycle
- [x] I5 Denial/audit evidence pack + live Compose reset/seed + `/login`→`/staff` spot-check (`docs/greenfield-foundation/evidence/e1-identity-reference-evidence.md`)

## Guardrails

- Do not recreate legacy role middleware.
- Do not start E2 journey commands/pages until E1 exit evidence exists.
