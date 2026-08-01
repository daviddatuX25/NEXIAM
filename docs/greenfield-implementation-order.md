# NEXIAM Greenfield Implementation Order

**Parent plan:** `docs/greenfield-architecture-plan.md`
**Domain reference:** `docs/master-architecture-picture.md`
**Date:** 2026-08-01

This document is the concrete build sequence. Each step names what to build, which audit findings it addresses, what it depends on, and what it unblocks. Steps within a phase can be parallelized; phases are sequential.

---

## Phase 0 — Project scaffold and tooling

**Depends on:** nothing
**Unblocks:** everything

| Step | What to build | Audit finding addressed |
|---|---|---|
| 0.1 | Initialize clean Laravel project (latest stable) | — |
| 0.2 | Add Inertia.js + Svelte 5 with TypeScript (strict mode) | P1.6: legacy JS bridge and untyped props |
| 0.3 | Configure PHPStan at max level, zero errors, no baseline | §4.1: 988 suppressed occurrences |
| 0.4 | Configure ESLint + Prettier on source only (exclude build output) | §4.1: 309 source errors, `public/build` inflation |
| 0.5 | Configure `svelte-check` strict mode, scoped to source | §4.1: 1,073 errors, vendor `vite.config.js` pollution |
| 0.6 | Set up Pest with architecture tests from day one | §4.1: zero domain tests |
| 0.7 | Set up CI pipeline — all green, no red-by-default | §4.2: no-growth contract |
| 0.8 | Install and configure shadcn-svelte with typed component props | P1.6: untyped UI primitives |
| 0.9 | Define shared `PageProps` TypeScript type and Inertia middleware | P0.8: `usePage()` without typed `$page` |
| 0.10 | Configure queue connections with `after_commit: true` | P0.2: notifications dispatched inside transactions |
| 0.11 | Set up pre-commit hooks (Pint + Prettier + type checks) | §4.2: formatting discipline |

**Deliverable:** empty application that boots, passes all quality tools at zero, and has CI green.

---

## Phase 1 — Domain model foundation

**Depends on:** Phase 0
**Unblocks:** Phase 2 (identity), Phase 3 (admissions)

These are the core tables and Eloquent models that every capability touches. Build them with the schema problems from §P0.4 solved from the start.

| Step | What to build | Audit finding addressed |
|---|---|---|
| 1.1 | `academic_years` table and model; locked singleton activation command | P0.4: "one active academic year" enforced by racy app checks |
| 1.2 | `programs` and `courses` tables and models | — (foundational) |
| 1.3 | `rooms` table and model; transactional slot/booking strategy | P0.4: non-atomic booking, counter drift |
| 1.4 | `aptitude_areas` table and model with proper FK constraints | P0.4: missing FK/index on `aptitude_area_id` |
| 1.5 | `settings` / institutional config table and model | — (foundational) |
| 1.6 | Audit event table with nullable subject columns, canonical JSON, sequence column | P0.4: sentinel `auditable_id = 0`; P0.6: insufficient audit truth |
| 1.7 | Architecture tests: models don't use HTTP, actions don't render responses | §4.2: dependency direction |

**Decision gate required before starting:**
- **Gate 5 (applicant lifetime):** Is `Applicant` a durable person across cycles, or per-application? This decides the `applicants` schema (Phase 3).

**Deliverable:** clean schema with database-enforced constraints, factories for every model, architecture tests passing.

---

## Phase 2 — Identity and access

**Depends on:** Phase 1 (audit table for auth events)
**Unblocks:** Phase 3 (admissions needs auth), Phase 4 (exam needs proctor policy)

| Step | What to build | Audit finding addressed |
|---|---|---|
| 2.1 | Staff `users` table, model, and Laravel auth (email + password) | — (foundational) |
| 2.2 | Role and permission system with authoritative catalog | P0.3: three divergent catalogs |
| 2.3 | Permanent organizational role/permission grants (Spatie or custom) | P0.3: hard-coded role lists repeated |
| 2.4 | Role middleware that checks permissions, not role name strings | P0.3: route middleware rejects before policy |
| 2.5 | Applicant `applicants` table with separate guard/provider | P0.3: applicant as separate actor, not staff role |
| 2.6 | Applicant identifier + password authentication | P0.3: identifier-key is undefined |
| 2.7 | Hashed setup/reset tokens with atomic one-time invalidation | P0.3: plaintext tokens |
| 2.8 | Rate limiting on all auth endpoints | P0.3: rate limiting coverage |
| 2.9 | Google OAuth with explicit trust domain, verified-email check, account-linking consent, audit event, route throttle | P0.3: auto-attach without policy |
| 2.10 | Policy base classes for each capability boundary | P0.3: authorization model |
| 2.11 | Proctor capability granted by session assignment, evaluated in policy (not role middleware) | P0.3: proctor duty overlay |
| 2.12 | Auth tests: login, setup, reset, OAuth link, rate limit, policy matrix | — |

**Decision gates required before starting:**
- **Gate 1 (role catalog):** approve one authoritative catalog with explicit legacy mapping
- **Gate 2 (proctor model):** session-assignment overlay, who assigns/revokes
- **Gate 3 (identifier auth):** identifier + password/passkey/OTP, recovery, rotation
- **Gate 4 (Google OAuth):** trusted domains, linking consent, credential races

**Deliverable:** staff and applicant can authenticate; role/permission system works; policies enforce capability boundaries; proctor is assignment-based; tokens are hashed; OAuth is safe.

---

## Phase 3 — Admissions core

**Depends on:** Phase 2 (applicant auth, roles)
**Unblocks:** Phase 4 (exam needs applications), Phase 7 (documents need application data)

| Step | What to build | Audit finding addressed |
|---|---|---|
| 3.1 | `applications` table with DB-enforced uniqueness on reference number | P0.4: `COUNT + 1` race condition |
| 3.2 | Reference number generator: DB sequence or locked counter, one contract for web + bulk import | P0.4: two formats, two sequences |
| 3.3 | `SubmitApplication` command/action with transaction, duplicate check via DB unique constraint | P0.4: concurrent duplicates both pass |
| 3.4 | Application pipeline state machine with single source of truth | P0.4: state spread across base/pipeline/pivot/grading status |
| 3.5 | `AcceptApplication` command: transaction + audit + applicant activation + after-commit notification | P0.2: fragmented ownership |
| 3.6 | `RejectApplication`, `ReopenApplication` commands with archive semantics (no hard-delete of applicant) | P0.4: reopen erases admission history |
| 3.7 | Appointment booking with atomic slot check (`booked_count < max_slots` in transaction or derived count) | P0.4: concurrent overbooking |
| 3.8 | Bulk applicant import with shared reference generator and hardened spreadsheet parser | P0.5: PhpSpreadsheet exposure |
| 3.9 | Applicant portal pages: status tracking, profile editing | — |
| 3.10 | Staff application management pages: list, view, accept, reject, reopen | — |
| 3.11 | Pipeline transition tests; concurrent-submission tests; import abuse tests | — |

**Decision gate required before starting:**
- **Gate 6 (pipeline source of truth):** stored projection vs. derived; who may force/backtrack
- **Gate 11 (retention/reversal):** what reopen, delete, and reassignment mean after history exists

**Deliverable:** applications flow from submission to acceptance with database-safe concurrency, atomic pipeline transitions, and no data loss on reopen.

---

## Phase 4 — Examination and proctoring

**Depends on:** Phase 3 (needs accepted applicants to assign)
**Unblocks:** Phase 5 (grading needs exam submissions)

| Step | What to build | Audit finding addressed |
|---|---|---|
| 4.1 | `exam_sessions` table; `room_id` FK with explicit delete policy (not CASCADE on nullable) | P0.4: CASCADE despite nullable |
| 4.2 | `exam_session_applicant` pivot with uniqueness scoped to cycle (not global) | P0.4: global unique on `applicant_id` forbids retakes |
| 4.3 | `PublishExamSession` command with transaction + audit | P0.2: publish updates state then notifies then audits without transaction |
| 4.4 | `AssignApplicantsToSession` command with transactional slot check | P0.2: fragmented ownership |
| 4.5 | Proctor assignment via `exam_session_user` with policy-based access (no role requirement) | P0.3: proctor duty overlay |
| 4.6 | `RecordAttendance` command | P0.2 |
| 4.7 | `RecordSubmission` command | P0.2 |
| 4.8 | Room booking with non-overlapping constraint (transactional or DB constraint) | P0.4: racy overlap checks |
| 4.9 | Exam management pages: create session, assign applicants, assign proctors | — |
| 4.10 | Proctor pages: my sessions, attendance, submission recording | P0.8: broken Start-session handler |
| 4.11 | Session roster pages | — |
| 4.12 | Tests: assignment, attendance, submission, proctor access, concurrent booking | — |

**Deliverable:** exam sessions with correct slot management, assignment-based proctor access, and transactional state changes.

---

## Phase 5 — Assessment and grading

**Depends on:** Phase 4 (needs exam submissions to grade)
**Unblocks:** Phase 6 (release needs finalized grades)

| Step | What to build | Audit finding addressed |
|---|---|---|
| 5.1 | `applicant_scores` table with proper FK to `aptitude_areas`, correct unique constraint (no duplicate "no area" rows) | P0.4: missing FK, nullable unique allows duplicates |
| 5.2 | `grading_sessions` table with unique constraint per exam session | P0.4: `grading_sessions.exam_session_id` not unique |
| 5.3 | Manual score entry action | — |
| 5.4 | Spreadsheet score import with hardened parser (latest PhpSpreadsheet, formula neutralization, size limits, reader selection, decompression limits) | P0.5: SSRF/RCE in PhpSpreadsheet 4.5.0 |
| 5.5 | Analyze → preview → confirm import UX (preserve legacy pattern) | §6 preserve: import UX |
| 5.6 | `FinalizeGrading` command: transaction + audit + pipeline progression | P0.2: fragmented ownership |
| 5.7 | Direct-assessment reconciliation: correct the inverted zero-score rule | P0.4: marks `scored` when zero scores |
| 5.8 | Pipeline transitions with serialized read-modify-write (no concurrent regression) | P0.4: unlocked milestone merge |
| 5.9 | Grading management pages | — |
| 5.10 | Tests: score entry, import with malicious fixtures, finalization, concurrent transitions | — |

**Deliverable:** scoring works with database-enforced constraints, hardened imports, and atomic pipeline transitions.

---

## Phase 6 — Consultation, release, and documents

**Depends on:** Phase 5 (needs finalized grades to release)
**Unblocks:** Phase 8 (dashboards need released data)

| Step | What to build | Audit finding addressed |
|---|---|---|
| 6.1 | `consultation_summaries` table and model | — |
| 6.2 | Consultation summary creation with counselor decisions | — |
| 6.3 | `ReleaseResult` command: transaction + audit + after-commit notification | P0.2: release without transaction |
| 6.4 | Unrelease action with audit trail | — |
| 6.5 | Typed placeholder system for document templates (`escaped_text`, `trusted_html`, `document_text`, `table_rows`) | P0.7: untyped replacement arrays, XSS |
| 6.6 | Context-specific encoders per placeholder type | P0.7: raw `str_replace` |
| 6.7 | Admission slip generator with CSP and escaped applicant text | P0.7: `{@html}` with applicant-controlled text |
| 6.8 | Result sheet generator with trust-typed `srcdoc` | P0.7: rendered-document trust boundary |
| 6.9 | DOCX/PDF rendering service (decomposed: template validation, data projection, format rendering, conversion) | P1.1: `ResultSheetTemplateService` 1,254 lines |
| 6.10 | Report exports with formula neutralization at every CSV/spreadsheet boundary | P0.5: `setCellValue` with formula-control characters |
| 6.11 | Release/result pages | — |
| 6.12 | Tests: release workflow, document rendering with malicious values, export formula neutralization | — |

**Deliverable:** consultation and release with safe document generation, typed placeholders, and formula-safe exports.

---

## Phase 7 — Audit and compliance

**Depends on:** Phase 1 (audit table exists); can run parallel to Phases 3-6 as an infrastructure module
**Unblocks:** nothing directly (all commands already write audit events; this adds chain integrity)

| Step | What to build | Audit finding addressed |
|---|---|---|
| 7.1 | Audit service with event vocabulary and secret redaction (preserve legacy catalog) | §6 preserve: `AuditService` event catalog |
| 7.2 | Append-only chain: sequence, previous hash, canonical JSON encoding | P0.6: insufficient tamper evidence |
| 7.3 | Serialized insert handling for concurrent writes | P0.6: concurrent inserts |
| 7.4 | Dedicated audit HMAC key with version column | P0.6: key rotation |
| 7.5 | External checkpoint support (periodic hash export) | P0.6: tail deletion undetectable |
| 7.6 | Verification command/health check | P0.6 |
| 7.7 | Append-only DB privilege configuration for production | P0.6: DB + key access = full rewrite |
| 7.8 | Tests: chain verification, concurrent writes, key rotation, tampering detection | — |

**Decision gate required before starting:**
- **Gate 5 (audit threat model):** which attacker, where checkpoints, who holds keys

**Deliverable:** tamper-evident audit chain with verification, key rotation, and external checkpoints.

---

## Phase 8 — Communications and notifications

**Depends on:** Phase 2 (needs users/applicants to notify)
**Can run parallel to Phases 3-6** — notification dispatch is an after-commit listener, not a blocker

| Step | What to build | Audit finding addressed |
|---|---|---|
| 8.1 | Notification preference system per user/applicant | §8: add preferences |
| 8.2 | Mail + database notification channels with after-commit dispatch | P0.2: notifications inside transactions |
| 8.3 | Idempotent notification delivery (dedup key per event + recipient) | §8: add idempotency |
| 8.4 | Notification templates for: schedule, status, staff-assignment, result | §6 preserve: eleven notification classes |
| 8.5 | Failed-job visibility and retry for notification queue | P1.4: swallowed notification failures |
| 8.6 | Notification pages and mark-as-read actions | P0.8: `usePage()` without `$page` |
| 8.7 | Tests: delivery, preference filtering, idempotency, failure/retry | — |

**Deliverable:** notification system with preferences, idempotency, after-commit delivery, and operational visibility.

---

## Phase 9 — Knowledge, RAG, and AI companion

**Depends on:** Phase 2 (applicant auth for scoped retrieval), Phase 6 (released results for own-data access)
**Unblocks:** Phase 10 (decision support uses retrieval)

| Step | What to build | Audit finding addressed |
|---|---|---|
| 9.1 | Knowledge document ingestion with Mixedbread adapter | §6 preserve: Mixedbread ingestion/retrieval |
| 9.2 | Scoped retrieval: institutional corpus with citations and confidence threshold | P1.5: contradictory RAG scope |
| 9.3 | Authorized applicant own-data retrieval (results, schedule, approved assignments) | P1.5: manuscript requires applicant-specific retrieval |
| 9.4 | Confidence refusal behavior (below threshold → decline with explanation) | P1.5: no confidence contract |
| 9.5 | Messaging fallback to office staff when RAG refuses | P1.5: no fallback |
| 9.6 | AI companion pages with guardrails | — |
| 9.7 | Tests: retrieval accuracy, confidence threshold, refusal, scope boundary, prompt injection defense | — |

**Decision gate required before starting:**
- **Gate 8 (RAG scope):** authorized data, recommendation boundary, threshold, citation, escalation

**Deliverable:** scoped RAG with institutional answers, applicant own-data, confidence/citation, and staff fallback.

---

## Phase 10 — Capstone research features

**Depends on:** Phase 5 (scores), Phase 6 (consultation), Phase 9 (RAG)
**This is the capstone contribution layer**

| Step | What to build | Audit finding addressed |
|---|---|---|
| 10.1 | Python inference sidecar setup (shared worker for OMR, BGE, Yukee) | §8: Python/OpenCV worker |
| 10.2 | OMR ingestion: object storage, template/version record, confidence per bubble | §8: OMR CV absent |
| 10.3 | OMR human-review queue with confidence threshold | §8: human-review queue |
| 10.4 | Idempotent OMR-to-grading import | §8: idempotent import into grading |
| 10.5 | Engine registry: versioned manifest, adapter contract, health/status, lifecycle | §8: engine registry absent |
| 10.6 | Immutable inference provenance (engine version + input hash + output + timestamp) | §8: immutable provenance |
| 10.7 | Yukee aptitude engine adapter with validated mapping data | §8: deterministic/versioned |
| 10.8 | BGE interest engine adapter with precomputed program embeddings | §8: precompute and version |
| 10.9 | Counselor triage dashboard: read model combining aptitude + interest signals | §8: triage dashboard |
| 10.10 | Counselor approve/modify/override/defer with rationale and engine versions | §8: no autonomous decision |
| 10.11 | Program Head scoped dashboard (read-only, program-scoped query + policy) | §8: PH dashboard absent |
| 10.12 | Tests: OMR processing, engine registry, provenance, triage decisions | — |

**Decision gates required before starting:**
- **Gate 6 (OMR):** scanner formats, sheet template, batch size, confidence threshold, review SLA
- **Gate 7 (Program scope):** quota definitions, pipeline integration
- **Gate 8 (RAG scope):** recommendation boundary vs. decision support

**Deliverable:** full capstone research stack — OMR, engine registry, Yukee/BGE adapters, triage dashboard, Program Head dashboard.

---

## Phase 11 — Dashboards and reporting

**Depends on:** Phases 3-6 (data to display), Phase 2 (role-scoped access)
**Can start incrementally** as earlier phases complete

| Step | What to build | Audit finding addressed |
|---|---|---|
| 11.1 | Role-scoped dashboard query facade (single entry point, typed view model) | P1.2: thirteen query methods, role checks in every chart |
| 11.2 | Staff dashboard with focused read queries per widget | P1.1: `DashboardAnalyticsService` 645 lines |
| 11.3 | Report export service (decomposed by format, not monolithic) | P1.1: `ReportExportService` 837 lines |
| 11.4 | Settings and institutional admin pages | — |
| 11.5 | Academic year, course, room, program admin pages | — |
| 11.6 | User management pages | — |
| 11.7 | Tests: dashboard queries by role, export correctness | — |

**Deliverable:** dashboards and admin pages with clean query architecture.

---

## Phase 12 — Messaging and offline (deferred capstone)

**Depends on:** Phases 2, 8, 9
**Build only after conflict semantics are approved**

| Step | What to build | Audit finding addressed |
|---|---|---|
| 12.1 | In-app messaging: bounded inquiry/thread model, office assignment | §8: messaging absent |
| 12.2 | Unread state, retention/access policy | §8 |
| 12.3 | RAG refusal → messaging handoff | P1.5: messaging fallback |
| 12.4 | Offline proctor sync: outbox/sync protocol (not cache-only PWA) | §8: offline proctoring absent |
| 12.5 | Conflict detection and resolution rules | §8: defer until conflict rules exist |
| 12.6 | Tests: messaging, offline queue, sync, conflict resolution | — |

**Deliverable:** in-app messaging with RAG handoff; offline proctor capability with proper sync protocol.

---

## Phase 13 — Hardening and delivery

**Depends on:** all prior phases
**This is the final gate before submission/deployment**

| Step | What to build | Audit finding addressed |
|---|---|---|
| 13.1 | E2E defense journeys (full admission lifecycle) | §12: behavior verification |
| 13.2 | Performance tests (dashboard queries, import throughput, OMR batch) | §12: operations |
| 13.3 | Security audit: dependency scan, upload abuse, auth edge cases | §12: security |
| 13.4 | Backup/restore tests | §12: operations |
| 13.5 | Queue failure and retry verification | §12: operations |
| 13.6 | Deployment configuration (Laravel Compose + Python sidecar) | §13 gate 9: deployment shape |
| 13.7 | Fresh migration + seed verification on both SQLite and MariaDB | P0.4: fresh-vs-upgraded drift (solved — only one history) |
| 13.8 | Final quality report: all tools at zero | — |

**Deliverable:** production-ready application with verified security, performance, and operational resilience.

---

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

Phases 7 (audit) and 8 (notifications) are infrastructure that can run **parallel** to the main 3→4→5→6 pipeline. Phase 11 (dashboards) grows incrementally as data becomes available.

---

## Decision gate resolution order

Gates must be resolved **before** the phase that needs them, not all at once:

| Gate | Decision | Blocks phase |
|---|---|---|
| 1 | Authoritative role catalog | Phase 2 |
| 2 | Proctor duty overlay model | Phase 2 |
| 3 | Identifier + password auth design | Phase 2 |
| 4 | Google OAuth trust/linking policy | Phase 2 |
| 5 | Applicant lifetime across cycles | Phase 1 (schema), Phase 3 |
| 6 | Pipeline source of truth | Phase 3 |
| 7 | Audit chain threat model | Phase 7 |
| 8 | RAG scope and confidence contract | Phase 9 |
| 9 | OMR operational inputs | Phase 10 |
| 10 | Program Head scope/quota | Phase 10 |
| 11 | Retention/reversal semantics | Phase 3 |
| 12 | Deployment shape | Phase 13 |
