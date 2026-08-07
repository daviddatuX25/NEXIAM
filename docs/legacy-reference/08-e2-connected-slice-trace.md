# E2 Connected Slice — Legacy → Target Trace Ledger

**Status:** ACTIVE — slice-critical parity for GF-11 `REQ-VS-001`–`REQ-VS-032`
**Date:** 2026-08-05
**Authority:** [`../prd.md`](../prd.md) §4; [`../greenfield-foundation/11-connected-vertical-slice-and-acceptance.md`](../greenfield-foundation/11-connected-vertical-slice-and-acceptance.md)
**Parent index:** [README.md](README.md)

This ledger completes Legacy → Target Trace rows for the **connected admission-to-release slice only**. It does not claim exhaustive legacy coverage outside GF-11.

Row shape matches [`../project-context.md`](../project-context.md) §3.1.

## Summary

| Disposition | Count | Notes |
|---|---:|---|
| `DEFER (+ presentation PRESERVE via Nexi chrome)` | 1 | |
| `PRESERVE + prove` | 1 | |
| `PRESERVE affordances + REWRITE contract` | 1 | |
| `PRESERVE behavior + REWRITE ownership` | 1 | |
| `PRESERVE intent + REWRITE` | 1 | |
| `PRESERVE intent + REWRITE delivery` | 1 | |
| `REWRITE` | 20 | |
| `REWRITE (+ SPIKE for OMR import)` | 1 | |
| `REWRITE + CONDITIONAL in E2` | 1 | |
| `REWRITE + EVIDENCE HOLD` | 1 | |
| `REWRITE evidence targets` | 1 | |
| `REWRITE; OMR gated` | 1 | |
| `SPIKE + DEFER functional to E3` | 1 | |

**Total REQ-VS rows:** 32 (must remain 32).

## Trace rows

### REQ-VS-001 — Foundation reset/seed

| Field | Value |
|---|---|
| Legacy source path(s) | `system/` Compose + `foundation:reset`; legacy seeders as evidence only |
| Observed actor / scope | ops/demo operator |
| Visible states | ready / failed migration |
| Disposition | **REWRITE** |
| FR/BR / GF links | FR-065; NFR-035; BR-001; GF-11 REQ-VS-001 |
| Target command / page | `foundation:reset` / migrate / seed |
| Capability / scope / duty | ops |
| Transaction / idempotency / audit | deterministic recreate; no production data wipe ambiguity |
| Notes / holds | E0/E1 already prove empty-DB seed path; E2 extends fixture to full journey. |
| Evidence targets | Pest feature + Playwright SCN-VS mapped in GF-11 §6.1 |

### REQ-VS-002 — Authentication

| Field | Value |
|---|---|
| Legacy source path(s) | legacy staff/applicant auth controllers; `AuthenticatedSessionController` |
| Observed actor / scope | all actors |
| Visible states | login loading/invalid/denied/success |
| Disposition | **REWRITE** |
| FR/BR / GF links | FR-005–FR-007; FR-033; NFR-038; GF-11 REQ-VS-002 |
| Target command / page | session login + applicant challenge setup |
| Capability / scope / duty | auth session; not capability alone |
| Transaction / idempotency / audit | hashed credentials; no identifier-as-secret |
| Notes / holds | Applicant No alone insufficient — post-submission credential required. |
| Evidence targets | Pest feature + Playwright SCN-VS mapped in GF-11 §6.1 |

### REQ-VS-003 — Own-record / cross-scope isolation

| Field | Value |
|---|---|
| Legacy source path(s) | legacy policies + broad application access (REJECT as authority) |
| Observed actor / scope | Applicant own; staff office/program scopes |
| Visible states | denied / empty-not-leak |
| Disposition | **REWRITE** |
| FR/BR / GF links | FR-033; FR-037; NFR-*; BR authz; GF-11 REQ-VS-003 |
| Target command / page | CapabilityAuthorizer + resource scope |
| Capability / scope / duty | office/program/own/assigned/duty |
| Transaction / idempotency / audit | deny without state change; audit deny |
| Notes / holds | Never reintroduce role-string middleware as authz. |
| Evidence targets | Pest feature + Playwright SCN-VS mapped in GF-11 §6.1 |

### REQ-VS-004 — Admission attempt lifecycle

| Field | Value |
|---|---|
| Legacy source path(s) | `ApplicationController`, admin application pages, pipeline service |
| Observed actor / scope | Applicant submit; Registrar review |
| Visible states | draft/submitted/accepted/dismissed/archived |
| Disposition | **REWRITE** |
| FR/BR / GF links | FR-002–FR-003; FR-004; BR reopen; GF-11 REQ-VS-004 |
| Target command / page | `SubmitAdmissionAttempt` / `Correct*` / `Accept*` / `Dismiss*` / reopen-archive |
| Capability / scope / duty | admission.submit; admission.review |
| Transaction / idempotency / audit | atomic attempt+audit; non-destructive reopen |
| Notes / holds | Split page adapters; explicit commands replace pipeline side effects. |
| Evidence targets | Pest feature + Playwright SCN-VS mapped in GF-11 §6.1 |

### REQ-VS-005 — Exam session create/assign/publish

| Field | Value |
|---|---|
| Legacy source path(s) | `Admin/ExamSessionController`, `ExamSchedulingService`, scheduling pages |
| Observed actor / scope | Test Administrator capability holder |
| Visible states | draft/conflict/published/capacity-full |
| Disposition | **REWRITE** |
| FR/BR / GF links | FR-008–FR-012; GF-11 REQ-VS-005 |
| Target command / page | `CreateExamSession` / `AssignAttemptToExamSession` / `PublishExamSession` |
| Capability / scope / duty | exam.schedule |
| Transaction / idempotency / audit | transactional capacity/time conflict; after-commit notify |
| Notes / holds | Manual assignment + late/direct-assessment paths remain. |
| Evidence targets | Pest feature + Playwright SCN-VS mapped in GF-11 §6.1 |

### REQ-VS-006 — Contextual proctor duty

| Field | Value |
|---|---|
| Legacy source path(s) | `Proctor/` controllers; roster pages; permanent Proctor role (REJECT) |
| Observed actor / scope | eligible staff via assignment |
| Visible states | assigned / revoked / closed → denied |
| Disposition | **REWRITE** |
| FR/BR / GF links | FR-013; FR-033; GF-11 REQ-VS-006 |
| Target command / page | `AssignProctorDuty` / `RevokeProctorDuty` |
| Capability / scope / duty | exam.proctor.duty contextual |
| Transaction / idempotency / audit | duty grant/revoke audited; disappears after close |
| Notes / holds | Proctor is assignment-scoped, not permanent role. |
| Evidence targets | Pest feature + Playwright SCN-VS mapped in GF-11 §6.1 |

### REQ-VS-007 — Attendance / submission

| Field | Value |
|---|---|
| Legacy source path(s) | proctor roster attendance/submission flows |
| Observed actor / scope | assigned proctor / session staff |
| Visible states | window-closed / idempotent-replay / success |
| Disposition | **REWRITE** |
| FR/BR / GF links | FR-015; NFR-010; GF-11 REQ-VS-007 |
| Target command / page | `RecordAttendance` / `RecordSubmission` (GF-11 spine) |
| Capability / scope / duty | exam.proctor.operate |
| Transaction / idempotency / audit | idempotency key; legal-time window; actor attribution |
| Notes / holds | Repeat with same key must not duplicate side effects. |
| Evidence targets | Pest feature + Playwright SCN-VS mapped in GF-11 §6.1 |

### REQ-VS-008 — Manual scoring fallback

| Field | Value |
|---|---|
| Legacy source path(s) | grading controllers, `GradingSessionService`, grading pages |
| Observed actor / scope | Guidance scoring capability |
| Visible states | provider-down / ready / invalid scores |
| Disposition | **REWRITE** |
| FR/BR / GF links | FR-017; FR-041; REQ-VS-017; GF-11 REQ-VS-008 |
| Target command / page | manual score write commands |
| Capability / scope / duty | grading.score.manual |
| Transaction / idempotency / audit | transactional multi-area writes |
| Notes / holds | Must complete journey with OMR/worker unavailable. |
| Evidence targets | Pest feature + Playwright SCN-VS mapped in GF-11 §6.1 |

### REQ-VS-009 — Grading finalization completeness

| Field | Value |
|---|---|
| Legacy source path(s) | legacy finalize without completeness guard (evidence of gap) |
| Observed actor / scope | Guidance / Test Admin grading |
| Visible states | incomplete-block / finalized |
| Disposition | **REWRITE** |
| FR/BR / GF links | FR-017; FR-041; GF-11 REQ-VS-009 |
| Target command / page | `FinalizeGrading` |
| Capability / scope / duty | grading.finalize |
| Transaction / idempotency / audit | atomic fail if required area missing |
| Notes / holds | No released projection advance on incomplete scores. |
| Evidence targets | Pest feature + Playwright SCN-VS mapped in GF-11 §6.1 |

### REQ-VS-010 — OMR proposals (gated)

| Field | Value |
|---|---|
| Legacy source path(s) | import/OMR artifacts in legacy |
| Observed actor / scope | Guidance review |
| Visible states | uncertain / proposal / human-review |
| Disposition | **SPIKE + DEFER functional to E3** |
| FR/BR / GF links | FR-041; NFR-041; Tier B; GF-11 REQ-VS-010 |
| Target command / page | OMR proposal → human review commands (E3 OpenSpec) |
| Capability / scope / duty | grading.omr.review |
| Transaction / idempotency / audit | proposals never authoritative alone |
| Notes / holds | E2: UNVERIFIED functional path; manual fallback required. Hold DECISION/SPIKE. |
| Evidence targets | Pest feature + Playwright SCN-VS mapped in GF-11 §6.1 |

### REQ-VS-011 — Consultation decision

| Field | Value |
|---|---|
| Legacy source path(s) | legacy result/consultation pages and summary models |
| Observed actor / scope | Guidance Counselor |
| Visible states | draft / confirm / defer |
| Disposition | **REWRITE** |
| FR/BR / GF links | FR-023; FR-048; GF-11 REQ-VS-011 |
| Target command / page | `RecordConsultationDraft` / `ConfirmConsultationDecision` / `Defer*` |
| Capability / scope / duty | consultation.decide |
| Transaction / idempotency / audit | human action + rationale; signals provenance only |
| Notes / holds | Engines optional; counselor owns decision. |
| Evidence targets | Pest feature + Playwright SCN-VS mapped in GF-11 §6.1 |

### REQ-VS-012 — Release / unrelease

| Field | Value |
|---|---|
| Legacy source path(s) | `ReleaseController`, release pages, release notifications |
| Observed actor / scope | authorized Guidance release capability |
| Visible states | prereq-fail / released / reversed |
| Disposition | **REWRITE** |
| FR/BR / GF links | FR-018–FR-022; FR-032; GF-11 REQ-VS-012 |
| Target command / page | `ReleaseResult` / reverse-unrelease commands |
| Capability / scope / duty | result.release |
| Transaction / idempotency / audit | prerequisites gate; history-preserving reversal |
| Notes / holds | Preserve release history; visibility via supersession event. |
| Evidence targets | Pest feature + Playwright SCN-VS mapped in GF-11 §6.1 |

### REQ-VS-013 — After-commit notifications

| Field | Value |
|---|---|
| Legacy source path(s) | notification classes, mail paths, notification pages |
| Observed actor / scope | system after commit; applicant recipient |
| Visible states | pending / sent / failed / retry |
| Disposition | **PRESERVE intent + REWRITE delivery** |
| FR/BR / GF links | FR-032; NFR-055; REQ-VS-023; GF-11 REQ-VS-013 |
| Target command / page | notification outbox after commit |
| Capability / scope / duty | notify.deliver |
| Transaction / idempotency / audit | exactly-once emit; observable failure; retryable |
| Notes / holds | Toast must not claim delivery without evidence. |
| Evidence targets | Pest feature + Playwright SCN-VS mapped in GF-11 §6.1 |

### REQ-VS-014 — Document generation safety

| Field | Value |
|---|---|
| Legacy source path(s) | `ReleasePrintController`, templates, PDF/DOCX assets |
| Observed actor / scope | staff prepare/print; applicant retrieve released |
| Visible states | pending-gen / ready / denied-external |
| Disposition | **PRESERVE behavior + REWRITE ownership** |
| FR/BR / GF links | FR-030; FR-031; SCN-VS-019; GF-11 REQ-VS-014 |
| Target command / page | `PrepareResultDocument` + private storage |
| Capability / scope / duty | result.document |
| Transaction / idempotency / audit | checksum/template version; encode untrusted values |
| Notes / holds | Async gen returns AcceptedForProcessing + operation ID. |
| Evidence targets | Pest feature + Playwright SCN-VS mapped in GF-11 §6.1 |

### REQ-VS-015 — Audit verifiability

| Field | Value |
|---|---|
| Legacy source path(s) | audit service/controller/pages |
| Observed actor / scope | authorized audit viewers; break-glass |
| Visible states | verify-pass / tamper-fail |
| Disposition | **REWRITE** |
| FR/BR / GF links | FR-034; NFR-045; GF-11 REQ-VS-015 |
| Target command / page | `RecordAuditEvent` + chain/checkpoint |
| Capability / scope / duty | audit.read / write |
| Transaction / idempotency / audit | atomic with mutation; redacted; attributable |
| Notes / holds | Tamper fixture must fail verification. |
| Evidence targets | Pest feature + Playwright SCN-VS mapped in GF-11 §6.1 |

### REQ-VS-016 — Session continuity + demo reset

| Field | Value |
|---|---|
| Legacy source path(s) | legacy session; greenfield `foundation:reset` |
| Observed actor / scope | all users; ops |
| Visible states | re-login restores; reset recreates |
| Disposition | **REWRITE** |
| FR/BR / GF links | FR-065; REQ-VS-001; GF-11 REQ-VS-016 |
| Target command / page | session + deterministic seed |
| Capability / scope / duty | ops |
| Transaction / idempotency / audit | authoritative server state on refresh |
| Notes / holds | Demo fixtures versioned. |
| Evidence targets | Pest feature + Playwright SCN-VS mapped in GF-11 §6.1 |

### REQ-VS-017 — Provider outage resilience

| Field | Value |
|---|---|
| Legacy source path(s) | legacy partial coupling to helpers |
| Observed actor / scope | Guidance manual path |
| Visible states | degraded banner / manual continue |
| Disposition | **REWRITE** |
| FR/BR / GF links | FR-041; NFR-052; REQ-VS-008; GF-11 REQ-VS-017 |
| Target command / page | feature flags + degraded UI |
| Capability / scope / duty | grading.score.manual |
| Transaction / idempotency / audit | no hard dependency on inference for core path |
| Notes / holds | E2 proves manual core without Python worker. |
| Evidence targets | Pest feature + Playwright SCN-VS mapped in GF-11 §6.1 |

### REQ-VS-018 — Browser quality evidence

| Field | Value |
|---|---|
| Legacy source path(s) | Playwright/Pest browser; legacy sparse |
| Observed actor / scope | QA |
| Visible states | console/network clean |
| Disposition | **REWRITE evidence targets** |
| FR/BR / GF links | NFR-039; FR-062; GF-11 REQ-VS-018 |
| Target command / page | Playwright journeys SCN-VS-* |
| Capability / scope / duty | n/a |
| Transaction / idempotency / audit | no silent failures |
| Notes / holds | Connected journeys must leave no unhandled console errors. |
| Evidence targets | Pest feature + Playwright SCN-VS mapped in GF-11 §6.1 |

### REQ-VS-019 — Public submit + access challenge

| Field | Value |
|---|---|
| Legacy source path(s) | ApplicationController public; portal auth/setup/reset |
| Observed actor / scope | public applicant; staff assist |
| Visible states | challenge issued / used / expired / delivery-fail |
| Disposition | **REWRITE** |
| FR/BR / GF links | FR-002; FR-005–FR-007; FR-004; GF-11 REQ-VS-019 |
| Target command / page | `SubmitAdmissionAttempt` + `IssueApplicantAccessChallenge` / `CompleteApplicantAccessSetup` |
| Capability / scope / duty | admission.submit; applicant.access.setup |
| Transaction / idempotency / audit | after_commit delivery; acceptance independent; no fabricated email |
| Notes / holds | Hashed one-time challenge; durable ApplicantIdentity. |
| Evidence targets | Pest feature + Playwright SCN-VS mapped in GF-11 §6.1 |

### REQ-VS-020 — Effective deadline + single grading open

| Field | Value |
|---|---|
| Legacy source path(s) | exam close paths; scheduler |
| Observed actor / scope | proctor/admin/scheduler |
| Visible states | extended / closed-once / race |
| Disposition | **REWRITE** |
| FR/BR / GF links | FR-014; FR-016; GF-11 REQ-VS-020 |
| Target command / page | `ExtendExamSession` / `CloseExamSession` (+ scheduler) |
| Capability / scope / duty | exam.operate / exam.close |
| Transaction / idempotency / audit | one effective deadline; grading opens exactly once |
| Notes / holds | Reconcile unresolved submissions or reason-override. |
| Evidence targets | Pest feature + Playwright SCN-VS mapped in GF-11 §6.1 |

### REQ-VS-021 — Transactional scores / staged import

| Field | Value |
|---|---|
| Legacy source path(s) | grading + import controllers/services |
| Observed actor / scope | Guidance |
| Visible states | row-invalid / batch-resume / manual fallback |
| Disposition | **REWRITE (+ SPIKE for OMR import)** |
| FR/BR / GF links | FR-017; FR-041; NFR-023; GF-11 REQ-VS-021 |
| Target command / page | manual multi-area write; staged import batch (minimal E2) |
| Capability / scope / duty | grading.score.manual; grading.import |
| Transaction / idempotency / audit | row-atomic validation; resumable idempotency |
| Notes / holds | E2 prioritizes manual writes; staged import may be thin seam. |
| Evidence targets | Pest feature + Playwright SCN-VS mapped in GF-11 §6.1 |

### REQ-VS-022 — Internal vs external result preview

| Field | Value |
|---|---|
| Legacy source path(s) | release print/preview pages |
| Observed actor / scope | staff internal; applicant external after release |
| Visible states | watermarked / denied |
| Disposition | **REWRITE** |
| FR/BR / GF links | FR-030; FR-031; GF-11 REQ-VS-022 |
| Target command / page | internal preview query + release gate |
| Capability / scope / duty | result.preview.internal; result.release |
| Transaction / idempotency / audit | audited preview; external denied until prereqs |
| Notes / holds | Watermark + privacy status required. |
| Evidence targets | Pest feature + Playwright SCN-VS mapped in GF-11 §6.1 |

### REQ-VS-023 — Applicant notification projection

| Field | Value |
|---|---|
| Legacy source path(s) | notification pages/list |
| Observed actor / scope | Applicant own |
| Visible states | empty-with-unread-bug (REJECT) / list / read |
| Disposition | **REWRITE** |
| FR/BR / GF links | FR-029; FR-032; REQ-VS-030; GF-11 REQ-VS-023 |
| Target command / page | notification list/read projection |
| Capability / scope / duty | notify.read.own |
| Transaction / idempotency / audit | scoped; empty UI cannot mask unread |
| Notes / holds | MarkNotificationRead idempotent. |
| Evidence targets | Pest feature + Playwright SCN-VS mapped in GF-11 §6.1 |

### REQ-VS-024 — RAG gates (gated E3)

| Field | Value |
|---|---|
| Legacy source path(s) | legacy AiCompanion |
| Observed actor / scope | Applicant / staff assist modes |
| Visible states | refuse / confirm-fallback |
| Disposition | **DEFER (+ presentation PRESERVE via Nexi chrome)** |
| FR/BR / GF links | FR-056–FR-058; FR-064; Tier B; GF-11 REQ-VS-024 |
| Target command / page | E3 RAG OpenSpec; E2 NexiAssistPanel presentation only |
| Capability / scope / duty | ai.rag.policy; ai.rag.own-data (separate) |
| Transaction / idempotency / audit | no inquiry until confirm fallback |
| Notes / holds | E2: locked Nexi chrome only — DEFER provider. |
| Evidence targets | Pest feature + Playwright SCN-VS mapped in GF-11 §6.1 |

### REQ-VS-025 — Program Head projection

| Field | Value |
|---|---|
| Legacy source path(s) | admin reports / program views |
| Observed actor / scope | Program Head program scope |
| Visible states | aggregate / denied individual / suppressed |
| Disposition | **REWRITE + EVIDENCE HOLD** |
| FR/BR / GF links | FR-037; FR-055; D-ROLE-05; GF-11 REQ-VS-025 |
| Target command / page | `ProgramHeadProjectionQuery` |
| Capability / scope / duty | program.head.read |
| Transaction / idempotency / audit | metric dictionary/freshness/privacy threshold |
| Notes / holds | EV-004/EV-017 holds; minimal E2 only if decision allows. |
| Evidence targets | Pest feature + Playwright SCN-VS mapped in GF-11 §6.1 |

### REQ-VS-026 — Explicit page states

| Field | Value |
|---|---|
| Legacy source path(s) | legacy inconsistent empty success |
| Observed actor / scope | all connected pages |
| Visible states | loading/ready/empty/denied/validation/error/conflict/degraded |
| Disposition | **REWRITE** |
| FR/BR / GF links | FR-062; GF-22; GF-11 REQ-VS-026 |
| Target command / page | Inertia page props `page_state` |
| Capability / scope / duty | n/a presentation |
| Transaction / idempotency / audit | never render failure as empty success |
| Notes / holds | Applies to every E2 connected page. |
| Evidence targets | Pest feature + Playwright SCN-VS mapped in GF-11 §6.1 |

### REQ-VS-027 — Server-authorized nav + breadcrumbs

| Field | Value |
|---|---|
| Legacy source path(s) | Svelte layouts, breadcrumb/topbar patterns |
| Observed actor / scope | staff/applicant |
| Visible states | mobile collapse / dirty-back |
| Disposition | **PRESERVE affordances + REWRITE contract** |
| FR/BR / GF links | FR-062; GF-22; GF-11 REQ-VS-027 |
| Target command / page | AuthenticatedLayout + breadcrumb component |
| Capability / scope / duty | server nav grants |
| Transaction / idempotency / audit | safe authorized ancestors only |
| Notes / holds | Use locked UI kit shells. |
| Evidence targets | Pest feature + Playwright SCN-VS mapped in GF-11 §6.1 |

### REQ-VS-028 — Mutation UX contract

| Field | Value |
|---|---|
| Legacy source path(s) | legacy confirm()/toast-only errors |
| Observed actor / scope | all mutating actors |
| Visible states | invalid/conflict/pending/success |
| Disposition | **REWRITE** |
| FR/BR / GF links | FR-062; GF-22 §§5–7; GF-11 REQ-VS-028 |
| Target command / page | Form Request + command + confirmation dialogs |
| Capability / scope / duty | per-command capability |
| Transaction / idempotency / audit | idempotent submit; toast never sole error/permission signal |
| Notes / holds | Risk-appropriate confirmation required. |
| Evidence targets | Pest feature + Playwright SCN-VS mapped in GF-11 §6.1 |

### REQ-VS-029 — Dashboard chart contracts

| Field | Value |
|---|---|
| Legacy source path(s) | Chart.js admin reports |
| Observed actor / scope | Program Head / admin |
| Visible states | zero/no-data/suppressed/stale/error |
| Disposition | **REWRITE + CONDITIONAL in E2** |
| FR/BR / GF links | FR-055; FR-037; GF-11 REQ-VS-029 |
| Target command / page | scoped metric read models |
| Capability / scope / duty | report.read.scoped |
| Transaction / idempotency / audit | accessible table alternative; no domain mutation |
| Notes / holds | Only if dashboard surfaces ship; else DEFER with hold. |
| Evidence targets | Pest feature + Playwright SCN-VS mapped in GF-11 §6.1 |

### REQ-VS-030 — Notification center + toasts

| Field | Value |
|---|---|
| Legacy source path(s) | toast patterns; notification UI |
| Observed actor / scope | all |
| Visible states | dedupe / severity / dismiss / redaction |
| Disposition | **PRESERVE intent + REWRITE** |
| FR/BR / GF links | FR-032; FR-062; GF-22 §6; GF-11 REQ-VS-030 |
| Target command / page | `ToastManager` + notification center |
| Capability / scope / duty | notify.* |
| Transaction / idempotency / audit | never claim delivery/release beyond evidence |
| Notes / holds | Reuse locked kit ToastManager. |
| Evidence targets | Pest feature + Playwright SCN-VS mapped in GF-11 §6.1 |

### REQ-VS-031 — Responsive + keyboard a11y

| Field | Value |
|---|---|
| Legacy source path(s) | legacy responsive shells |
| Observed actor / scope | all critical journeys |
| Visible states | narrow / keyboard / drawer |
| Disposition | **PRESERVE + prove** |
| FR/BR / GF links | FR-062; NFR-039; GF-11 REQ-VS-031 |
| Target command / page | Playwright a11y journeys |
| Capability / scope / duty | n/a |
| Transaction / idempotency / audit | filters/dialogs/tables/previews usable |
| Notes / holds | Include degraded states. |
| Evidence targets | Pest feature + Playwright SCN-VS mapped in GF-11 §6.1 |

### REQ-VS-032 — Document/OMR graphic provenance

| Field | Value |
|---|---|
| Legacy source path(s) | OMR/doc previews |
| Observed actor / scope | Guidance / release viewers |
| Visible states | provenance / watermark / uncertainty |
| Disposition | **REWRITE; OMR gated** |
| FR/BR / GF links | FR-030; REQ-VS-010/014/022; GF-11 REQ-VS-032 |
| Target command / page | document preview components; OMR E3 |
| Capability / scope / duty | result.document; grading.omr.review |
| Transaction / idempotency / audit | never imply autonomous authority |
| Notes / holds | E2 commits result-document provenance; OMR graphics DEFER. |
| Evidence targets | Pest feature + Playwright SCN-VS mapped in GF-11 §6.1 |

## Explicit holds

| ID | Hold | Unblocks |
|---|---|---|
| REQ-VS-010 | SPIKE/DEFER — OMR functional path | E3 OMR OpenSpec + RES-G2 |
| REQ-VS-024 | DEFER — RAG provider | E3 RAG OpenSpec; E2 keeps Nexi chrome only |
| REQ-VS-025 | EVIDENCE HOLD — Program Head metrics | EV-004/EV-017 + D-ROLE-05 |
| REQ-VS-029 | CONDITIONAL — charts only if surface ships | metric dictionary |
| REQ-VS-032 (OMR portion) | DEFER graphics | same as REQ-VS-010 |

No COMMITTED core journey step is left `UNVERIFIED` without a disposition. Gated rows are explicit holds, not silent omissions.

