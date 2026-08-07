> **DOCUMENT INTEGRITY:** This file previously contained literal tool-omission markers and is HISTORICAL only. A pre-repair copy is in `docs/greenfield-foundation/quarantine/`. Do not use as sequence or implementation authority. Use `docs/GREENFIELD-MASTER-REFERENCE.md` and `docs/MASTER-DELIVERY-ROADMAP.md`.

greenfield-implementation-order.md 386L
# NEXIAM Greenfield Implementation Order
**Status:** HISTORICAL INPUT — superseded as sequence authority by the master delivery roadmap `docs/greenfield-foundation/14-readiness-gates-and-reconciled-roadmap.md` (GF-14) and the implementation handoff `docs/greenfield-foundation/24-bmad-openspec-implementation-package.md` plus active OpenSpec changes
**Use:** Preserve phase/detail as evidence; follow GF-14 for whole-project sequencing and GF-24/OpenSpec for executable work.
... [lean-ctx: omitted 3 lines]
This document is the concrete build sequence. Each step names what to build, which audit findings it addresses, what it depends on, and what it unblocks. Steps within a phase can be parallelized; phases are sequential.
... [lean-ctx: omitted 1 lines]
## Phase 0 — Project scaffold and tooling
**Depends on:** nothing
... [lean-ctx: omitted 6 lines]
| 0.4 | Configure ESLint + Prettier on source only (exclude build output) | §4.1: 309 source errors, `public/build` inflation |
| 0.5 | Configure `svelte-check` strict mode, scoped to source | §4.1: 1,073 errors, vendor `vite.config.js` pollution |
... [lean-ctx: omitted 2 lines]
| 0.8 | Install and configure shadcn-svelte with typed component props | P1.6: untyped UI primitives |
| 0.9 | Define shared `PageProps` TypeScript type and Inertia middleware | P0.8: `usePage()` without typed `$page` |
| 0.10 | Configure queue connections with `after_commit: true` | P0.2: notifications dispatched inside transactions |
| 0.11 | Set up pre-commit hooks (Pint + Prettier + type checks) | §4.2: formatting discipline |
... [lean-ctx: omitted 2 lines]
## Phase 1 — Domain model foundation
**Depends on:** Phase 0
... [lean-ctx: omitted 4 lines]
| 1.1 | `academic_years` table and model; locked singleton activation command | P0.4: "one active academic year" enforced by racy app checks |
... [lean-ctx: omitted 1 lines]
| 1.3 | `rooms` table and model; transactional slot/booking strategy | P0.4: non-atomic booking, counter drift |
| 1.4 | `aptitude_areas` table and model with proper FK constraints | P0.4: missing FK/index on `aptitude_area_id` |
| 1.5 | `settings` / institutional config table and model | — (foundational) |
| 1.6 | Audit event table with nullable subject columns, canonical JSON, sequence column | P0.4: sentinel `auditable_id = 0`; P0.6: insufficient audit truth |
| 1.7 | Architecture tests: models don't use HTTP, actions don't render responses | §4.2: dependency direction |
... [lean-ctx: omitted 4 lines]
## Phase 2 — Identity and access
**Depends on:** Phase 1 (audit table for auth events)
... [lean-ctx: omitted 3 lines]
| 2.1 | Staff `users` table, model, and Laravel auth (email + password) | — (foundational) |
| 2.2 | Role and permission system with authoritative catalog | P0.3: three divergent catalogs |
| 2.3 | Permanent organizational role/permission grants (Spatie or custom) | P0.3: hard-coded role lists repeated |
... [lean-ctx: omitted 1 lines]
| 2.5 | Applicant `applicants` table with separate guard/provider | P0.3: applicant as separate actor, not staff role |
| 2.6 | Applicant identifier + password authentication | P0.3: identifier-key is undefined |
| 2.7 | Hashed setup/reset tokens with atomic one-time invalidation | P0.3: plaintext tokens |
... [lean-ctx: omitted 1 lines]
| 2.9 | Google OAuth with explicit trust domain, verified-email check, account-linking consent, audit event, route throttle | P0.3: auto-attach without policy |
... [lean-ctx: omitted 1 lines]
| 2.11 | Proctor capability granted by session assignment, evaluated in policy (not role middleware) | P0.3: proctor duty overlay |
... [lean-ctx: omitted 6 lines]
**Deliverable:** staff and applicant can authenticate; role/permission system works; policies enforce capability boundaries; proctor is assignment-based; tokens are hashed; OAuth is safe.
... [lean-ctx: omitted 1 lines]
## Phase 3 — Admissions core
**Depends on:** Phase 2 (applicant auth, roles)
... [lean-ctx: omitted 3 lines]
| 3.1 | `applications` table with DB-enforced uniqueness on reference number | P0.4: `COUNT + 1` race condition |
... [lean-ctx: omitted 1 lines]
| 3.3 | `SubmitApplication` command/action with transaction, duplicate check via DB unique constraint | P0.4: concurrent duplicates both pass |
... [lean-ctx: omitted 1 lines]
| 3.5 | `AcceptApplication` command: transaction + audit + applicant activation + after-commit notification | P0.2: fragmented ownership |
| 3.6 | `RejectApplication`, `ReopenApplication` commands with archive semantics (no hard-delete of applicant) | P0.4: reopen erases admission history |
| 3.7 | Appointment booking with atomic slot check (`booked_count < max_slots` in transaction or derived count) | P0.4: concurrent overbooking |
| 3.8 | Bulk applicant import with shared reference generator and hardened spreadsheet parser | P0.5: PhpSpreadsheet exposure |
... [lean-ctx: omitted 6 lines]
**Deliverable:** applications flow from submission to acceptance with database-safe concurrency, atomic pipeline transitions, and no data loss on reopen.
... [lean-ctx: omitted 1 lines]
## Phase 4 — Examination and proctoring
**Depends on:** Phase 3 (needs accepted applicants to assign)
... [lean-ctx: omitted 3 lines]
| 4.1 | `exam_sessions` table; `room_id` FK with explicit delete policy (not CASCADE on nullable) | P0.4: CASCADE despite nullable |
| 4.2 | `exam_session_applicant` pivot with uniqueness scoped to cycle (not global) | P0.4: global unique on `applicant_id` forbids retakes |
| 4.3 | `PublishExamSession` command with transaction + audit | P0.2: publish updates state then notifies then audits without transaction |
| 4.4 | `AssignApplicantsToSession` command with transactional slot check | P0.2: fragmented ownership |
| 4.5 | Proctor assignment via `exam_session_user` with policy-based access (no role requirement) | P0.3: proctor duty overlay |
| 4.6 | `RecordAttendance` command | P0.2 |
... [lean-ctx: omitted 3 lines]
| 4.10 | Proctor pages: my sessions, attendance, submission recording | P0.8: broken Start-session handler |
... [lean-ctx: omitted 4 lines]
## Phase 5 — Assessment and grading
**Depends on:** Phase 4 (needs exam submissions to grade)
... [lean-ctx: omitted 3 lines]
| 5.1 | `applicant_scores` table with proper FK to `aptitude_areas`, correct unique constraint (no duplicate "no area" rows) | P0.4: missing FK, nullable unique allows duplicates |
| 5.2 | `grading_sessions` table with unique constraint per exam session | P0.4: `grading_sessions.exam_session_id` not unique |
... [lean-ctx: omitted 1 lines]
| 5.4 | Spreadsheet score import with hardened parser (latest PhpSpreadsheet, formula neutralization, size limits, reader selection, decompression limits) | P0.5: SSRF/RCE in PhpSpreadsheet 4.5.0 |
... [lean-ctx: omitted 1 lines]
| 5.6 | `FinalizeGrading` command: transaction + audit + pipeline progression | P0.2: fragmented ownership |
| 5.7 | Direct-assessment reconciliation: correct the inverted zero-score rule | P0.4: marks `scored` when zero scores |
| 5.8 | Pipeline transitions with serialized read-modify-write (no concurrent regression) | P0.4: unlocked milestone merge |
... [lean-ctx: omitted 4 lines]
## Phase 6 — Consultation, release, and documents
**Depends on:** Phase 5 (needs finalized grades to release)
... [lean-ctx: omitted 5 lines]
| 6.3 | `ReleaseResult` command: transaction + audit + after-commit notification | P0.2: release without transaction |
... [lean-ctx: omitted 1 lines]
| 6.5 | Typed placeholder system for document templates (`escaped_text`, `trusted_html`, `document_text`, `table_rows`) | P0.7: untyped replacement arrays, XSS |
| 6.6 | Context-specific encoders per placeholder type | P0.7: raw `str_replace` |
| 6.7 | Admission slip generator with CSP and escaped applicant text | P0.7: `{@html}` with applicant-controlled text |
| 6.8 | Result sheet generator with trust-typed `srcdoc` | P0.7: rendered-document trust boundary |
| 6.9 | DOCX/PDF rendering service (decomposed: template validation, data projection, format rendering, conversion) | P1.1: `ResultSheetTemplateService` 1,254 lines |
| 6.10 | Report exports with formula neutralization at every CSV/spreadsheet boundary | P0.5: `setCellValue` with formula-control characters |
... [lean-ctx: omitted 4 lines]
## Phase 7 — Audit and compliance
**Depends on:** Phase 1 (audit table exists); can run parallel to Phases 3-6 as an infrastructure module
... [lean-ctx: omitted 3 lines]
| 7.1 | Audit service with event vocabulary and secret redaction (preserve legacy catalog) | §6 preserve: `AuditService` event catalog |
| 7.2 | Append-only chain: sequence, previous hash, canonical JSON encoding | P0.6: insufficient tamper evidence |
... [lean-ctx: omitted 2 lines]
| 7.5 | External checkpoint support (periodic hash export) | P0.6: tail deletion undetectable |
... [lean-ctx: omitted 1 lines]
| 7.7 | Append-only DB privilege configuration for production | P0.6: DB + key access = full rewrite |
| 7.8 | Tests: chain verification, concurrent writes, key rotation, tampering detection | — |
... [lean-ctx: omitted 2 lines]
**Deliverable:** tamper-evident audit chain with verification, key rotation, and external checkpoints.
... [lean-ctx: omitted 1 lines]
## Phase 8 — Communications and notifications
**Depends on:** Phase 2 (needs users/applicants to notify)
... [lean-ctx: omitted 4 lines]
| 8.2 | Mail + database notification channels with after-commit dispatch | P0.2: notifications inside transactions |
... [lean-ctx: omitted 2 lines]
| 8.5 | Failed-job visibility and retry for notification queue | P1.4: swallowed notification failures |
| 8.6 | Notification pages and mark-as-read actions | P0.8: `usePage()` without `$page` |
| 8.7 | Tests: delivery, preference filtering, idempotency, failure/retry | — |
**Deliverable:** notification system with preferences, idempotency, after-commit delivery, and operational visibility.
... [lean-ctx: omitted 1 lines]
## Phase 9 — Knowledge, RAG, and AI companion
**Depends on:** Phase 2 (applicant auth for scoped retrieval), Phase 6 (released results for own-data access)
... [lean-ctx: omitted 3 lines]
| 9.1 | Knowledge document ingestion with Mixedbread adapter | §6 preserve: Mixedbread ingestion/retrieval |
| 9.2 | Scoped retrieval: institutional corpus with citations and confidence threshold | P1.5: contradictory RAG scope |
| 9.3 | Authorized applicant own-data retrieval (results, schedule, approved assignments) | P1.5: manuscript requires applicant-specific retrieval |
| 9.4 | Confidence refusal behavior (below threshold → decline with explanation) | P1.5: no confidence contract |
... [lean-ctx: omitted 2 lines]
| 9.7 | Tests: retrieval accuracy, confidence threshold, refusal, scope boundary, prompt injection defense | — |
... [lean-ctx: omitted 1 lines]
- **Gate 8 (RAG scope):** authorized data, recommendation boundary, threshold, citation, escalation
... [lean-ctx: omitted 2 lines]
## Phase 10 — Capstone research features
**Depends on:** Phase 5 (scores), Phase 6 (consultation), Phase 9 (RAG)
... [lean-ctx: omitted 7 lines]
| 10.5 | Engine registry: versioned manifest, adapter contract, health/status, lifecycle | §8: engine registry absent |
| 10.6 | Immutable inference provenance (engine version + input hash + output + timestamp) | §8: immutable provenance |
| 10.7 | Yukee aptitude engine adapter with validated mapping data | §8: deterministic/versioned |
| 10.8 | BGE interest engine adapter with precomputed program embeddings | §8: precompute and version |
| 10.9 | Counselor triage dashboard: read model combining aptitude + interest signals | §8: triage dashboard |
| 10.10 | Counselor approve/modify/override/defer with rationale and engine versions | §8: no autonomous decision |
... [lean-ctx: omitted 1 lines]
| 10.12 | Tests: OMR processing, engine registry, provenance, triage decisions | — |
... [lean-ctx: omitted 4 lines]
**Deliverable:** full capstone research stack — OMR, engine registry, Yukee/BGE adapters, triage dashboard, Program Head dashboard.
... [lean-ctx: omitted 1 lines]
## Phase 11 — Dashboards and reporting
**Depends on:** Phases 3-6 (data to display), Phase 2 (role-scoped access)
... [lean-ctx: omitted 3 lines]
| 11.1 | Role-scoped dashboard query facade (single entry point, typed view model) | P1.2: thirteen query methods, role checks in every chart |
| 11.2 | Staff dashboard with focused read queries per widget | P1.1: `DashboardAnalyticsService` 645 lines |
| 11.3 | Report export service (decomposed by format, not monolithic) | P1.1: `ReportExportService` 837 lines |
| 11.4 | Settings and institutional admin pages | — |
... [lean-ctx: omitted 2 lines]
| 11.7 | Tests: dashboard queries by role, export correctness | — |
... [lean-ctx: omitted 2 lines]
## Phase 12 — Messaging and offline (deferred capstone)
**Depends on:** Phases 2, 8, 9
... [lean-ctx: omitted 3 lines]
| 12.1 | In-app messaging: bounded inquiry/thread model, office assignment | §8: messaging absent |
... [lean-ctx: omitted 2 lines]
| 12.4 | Offline proctor sync: outbox/sync protocol (not cache-only PWA) | §8: offline proctoring absent |
| 12.5 | Conflict detection and resolution rules | §8: defer until conflict rules exist |
| 12.6 | Tests: messaging, offline queue, sync, conflict resolution | — |
**Deliverable:** in-app messaging with RAG handoff; offline proctor capability with proper sync protocol.
... [lean-ctx: omitted 1 lines]
## Phase 13 — Hardening and delivery
**Depends on:** all prior phases
... [lean-ctx: omitted 3 lines]
| 13.1 | E2E defense journeys (full admission lifecycle) | §12: behavior verification |
| 13.2 | Performance tests (dashboard queries, import throughput, OMR batch) | §12: operations |
... [lean-ctx: omitted 1 lines]
| 13.4 | Backup/restore tests | §12: operations |
... [lean-ctx: omitted 2 lines]
| 13.7 | Fresh migration + seed verification on both SQLite and MariaDB | P0.4: fresh-vs-upgraded drift (solved — only one history) |
... [lean-ctx: omitted 1 lines]
**Deliverable:** production-ready application with verified security, performance, and operational resilience.
... [lean-ctx: omitted 1 lines]
## Dependency graph (phases)
```
Phase 0 (scaffold)
    |
Phase 1 (domain model)
    |
Phase 2 (identity)─────────────────────┐
    |                                    |
Phase 3 (admissions)    Phase 7 (audit) | Phase 8 (notifications)
    |                        |           |       |
Phase 4 (exams)             |           |       |
    |                        |           |       |
Phase 5 (grading)           |           |       |
    |                        |           |       |
Phase 6 (release/docs)─────┘           |       |
    |                                    |       |
Phase 9 (RAG)───────────────────────────┘       |
    |                                            |
Phase 10 (capstone research)                     |
    |                                            |
Phase 11 (dashboards) ──── runs incrementally ───┘
    |
Phase 12 (messaging/offline)
    |
Phase 13 (hardening)
```
... [lean-ctx: omitted 2 lines]
## Decision gate resolution order
Gates must be resolved **before** the phase that needs them, not all at once:
... [lean-ctx: omitted 4 lines]
| 3 | Identifier + password auth design | Phase 2 |
| 4 | Google OAuth trust/linking policy | Phase 2 |
| 5 | Applicant lifetime across cycles | Phase 1 (schema), Phase 3 |
... [lean-ctx: omitted 2 lines]
| 8 | RAG scope and confidence contract | Phase 9 |
... [lean-ctx: omitted 1 lines]
| 10 | Program Head scope/quota | Phase 10 |
| 11 | Retention/reversal semantics | Phase 3 |
... [lean-ctx: omitted 1 lines]


[… truncated at ~4084 of 4142 tokens — use ctx_read with lines= parameter to see specific sections]
