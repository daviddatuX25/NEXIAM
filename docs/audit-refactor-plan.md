# NEXIAM Audit-Refactor Mission Plan
## Multi-Angle System Audit → Specs → Refactor → Build

**Created:** 2026-07-29
**Phase:** Audit (in progress)
**Completed:** 3 of 8 audit angles

---

## COMPLETED AUDITS

| # | Audit | Deliverable | Status |
|---|---|---|---|
| 1 | Codebase Architecture | `docs/architecture-audit.md` | ✅ 20 findings, 7 criticals |
| 2 | Access Control & RBAC | `docs/access-control-inventory.md` + `.html` | ✅ 41 use cases, 11 actors, Spatie target |
| 3 | Controller Architecture | `docs/audit-controllers.md` | ✅ 41 controllers, 538 lines, 5 criticals, 2 god-class splits |

---

## QUEUED AUDITS (5 remaining)

### 4. Service Layer Quality Audit
**Deliverable:** `docs/audit-services.md`
**Scope:**
- All 34 services deep-dived
- Classification: deep module vs shallow pass-through vs dependency-creating
- Testability assessment (accepts deps vs creates internally)
- Missing abstractions (what should be a service but isn't)
- Proposed refactors (DashboardService, AiCompanionService, etc.)

### 5. Database Schema Audit
**Deliverable:** `docs/audit-database.md`
**Scope:**
- Every migration inspected for: foreign keys, indexes, nullable bandaids, soft deletes
- Missing columns: `audit_logs.chain_hash`, `applicants.identifier_key`
- Normalization issues, redundant columns, orphan risk
- Migration hygiene: too many renames, bandaids, cleanup migrations

### 6. Frontend Architecture Audit
**Deliverable:** `docs/audit-frontend.md`
**Scope:**
- All 81 Svelte pages sized and assessed
- Monster components (>400 lines)
- Create/Edit duplication pairs
- Shared component reuse opportunities
- Inertia pattern consistency (props shape, deferred loading, form handling)
- Missing screens for new roles (Program Head dashboard, etc.)

### 7. Feature Completeness Audit
**Deliverable:** `docs/audit-features.md`
**Scope:**
- Cross-reference `manuscript/Phase(RAD-based) Work/features.md` against actual code
- Every manuscript-scoped feature: Built, Partial, or Missing
- Specific gap identified for each missing feature
- Build effort estimate per feature

### 8. Security & API Audit
**Deliverable:** `docs/audit-security.md`
**Scope:**
- Auth guard fragmentation (web vs applicant)
- CSRF coverage
- Input validation patterns (FormRequest usage)
- Rate limiting coverage
- API response consistency
- Error handling conventions
- Inertia data shape conventions

---

## POST-AUDIT PHASES

### Phase B — Specs
- Using audit deliverables as input
- Formal ADRs for key decisions
- Spatie permission catalog
- Refactor scope document

### Phase C — P0 Refactors
- God controller decomposition
- N+1 query fixes
- Role rename + Spatie install
- Proctor middleware fix

### Phase D — P1 Features
- HMAC audit chain
- Email notifications
- In-app messaging
- Identifier key auth

### Phase E — P2 New Builds
- OMR engine
- RAG chatbot
- Admission support engines
- Triage Dashboard

---

## KEY ARCHITECTURAL DECISIONS (already settled)

1. **Roles:** system_it_admin, registrar_admin, registrar_clerk, registrar_support_staff, guidance_counselor, guidance_clerk, guidance_support_staff, program_head, proctor, applicant
2. **RBAC:** Spatie permissions (not raw role checks). Test_admin = permission set, not role.
3. **Proctor:** Role assigned by guidance_counselor, scoped via exam_session_user pivot
4. **Account creation:** system_it_admin creates permanent roles. Office heads create temp support staff.
5. **Applicant auth:** Identifier key (not email+password). Current code uses email+password — needs refactor.

---

## NOTES
- BMAD: too greenfield-oriented for this stage
- OpenSpec: no guide available currently
- Proceeding with manual audit documents until spec tools are ready
- All audit deliverables live in `docs/` with prefix `audit-*`
