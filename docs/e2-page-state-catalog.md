# E2 Page / Route / State Catalog

**Status:** ACTIVE — Connected Admission-to-Release Slice  
**Date:** 2026-08-05  
**Authority:** [`prd.md`](prd.md) §4; [GF-11](greenfield-foundation/11-connected-vertical-slice-and-acceptance.md); [GF-22](greenfield-foundation/22-user-experience-and-interface-contract.md); [ui-kit-locked-start-set.md](greenfield-foundation/evidence/ui-kit-locked-start-set.md)  
**UI kit reuse:** `PageHeaderBar`, `FilterToolbar`, `BulkActionBar`, `ModeBanner`, `ActiveFilterChips`, `ToastManager`, `AuthenticatedLayout` / guest shells, `NexiAssistPanel` (presentation only)

Exact URI strings are implementation decisions; **responsibility and page states are fixed**.

## 1. Shared page_state vocabulary (every connected page)

| State | Meaning | UI contract |
|---|---|---|
| `initial-loading` | First authorized fetch in progress | skeleton / progress; not empty-success |
| `ready` | Authorized data loaded | primary content |
| `empty` / `not-yet-available` | Authorized, zero rows or prereq not met | explicit empty copy + next action |
| `denied` | Capability/scope failed | denial surface; no leaked fields |
| `validation` | Field/server validation failed | inline errors; preserve input |
| `server-error` | Unexpected failure | retry + correlation id |
| `stale` / `conflict` | Version/idempotency conflict | refresh / reselection |
| `degraded` / `retry` | Provider/side-effect degraded | banner + manual path |
| `success` / `pending` | Authoritative commit or accepted-for-processing | truthful wording only |

Breadcrumbs: server-supplied trail with authorized ancestors, current page, scope context, mobile collapse (REQ-VS-027).  
Toasts: never sole error/permission signal (REQ-VS-028/030).  
Nexi: locked chrome only; no provider mutations in E2.

## 2. Shells

| Shell | Actors | Kit / notes |
|---|---|---|
| Staff | Registrar, Guidance, Proctor-on-duty, Program Head | `AuthenticatedLayout` + server nav; management-list chrome for queues |
| Applicant | Applicant | simpler portal shell; own-data only |
| Proctor/session | Assigned proctor | session identity + effective deadline sticky; no unrelated nav |
| Guest / public | Unauthenticated submit / login / challenge | `GuestLayout` |
| Break-glass | Super Admin | persistent banner; not dismissible as toast |

## 3. Route responsibility catalog

| ID | Responsibility | Primary actors | Proposed route family | Primary commands | Kit composites | Required page states | REQ-VS |
|---|---|---|---|---|---|---|---|
| P-AUTH-01 | Staff/applicant login | all | `/login` | session create | GuestLayout, PasswordStrengthInput (setup) | loading, validation, denied, ready | 002, 016 |
| P-PUB-01 | Public admission submit | Applicant (public) | `/apply` | `SubmitAdmissionAttempt` | Guest form | loading, validation, conflict-duplicate, success+challenge pending | 004, 019, 026–028 |
| P-APP-01 | Access key + birthdate login (D-085) | Applicant | `/applicant/login` | `AuthenticateApplicantWithAccessKey` | Confirmation key claim + Login |
| P-APP-02 | Applicant status portal | Applicant | `/applicant` | reads | portal cards | empty-not-yet, ready, denied | 003, 016, 026 |
| P-APP-03 | Schedule / result retrieve | Applicant | `/applicant/schedule`, `/applicant/results` | reads; `ReserveExamSlot` / cancel when plan open | calendar (primary) + confirm Dialog; document preview for results | pending_acceptance, waiting_for_reservation_open, calendar_open, reserved, assigned_published, not-released, pending-gen, ready | 005, 012, 014, 022, 023 |
| P-APP-04 | Notification list/read | Applicant | `/applicant/notifications` | `MarkNotificationRead` | list + ToastManager | empty-vs-unread, ready | 023, 030 |
| P-REG-01 | Registrar intake queue | Registrar | `/staff/admissions` | list/filter | PageHeaderBar, FilterToolbar, BulkActionBar?, SwitchableListView | empty, ready, denied | 004, 026–028, 031 |
| P-REG-02 | Attempt detail + correct/accept/dismiss | Registrar | `/staff/admissions/{id}` | Correct/Accept/Dismiss/reopen/archive | PageHeaderBar, Dialog confirm | conflict, validation, success | 004, 015, 028 |
| P-EXM-01 | Exam session planning queue | Test Admin | `/exams/sessions` | list/filter; link Create | management-list chrome (legacy TestScheduling Index) | empty, ready | 005 |
| P-EXM-01b | Create exam session | Test Admin | `/exams/sessions/create` | `CreateExamSession` | form page | validation, conflict | 005 |
| P-EXM-01c | Edit draft exam session | Test Admin | `/exams/sessions/{id}/edit` | update draft | form page | draft-only, denied | 005 |
| P-EXM-02 | Session detail assign/publish | Test Admin | `/exams/sessions/{id}` | Assign*/Remove*/Publish*/Cancel* | filters, Dialog | capacity conflict, publish-blocked, published | 005, 006 |
| P-EXM-03 | Slot plan open/close/finalize | Test Admin | `/exams/slot-plans` (+ create/edit) | Open/Close/Finalize/Bypass ExamSlotPlan | list + form pages | open, closed, bypassed | 005 |
| P-PRC-01 | Proctor roster / attendance / submission | Assigned Proctor | `/staff/proctor/sessions/{id}` | attendance/submission/start/extend | session shell | window-closed, idempotent replay, degraded | 006, 007, 020 |
| P-PRC-02 | Close / reconcile | Test Admin / Proctor | same or modal | `CloseExamSession` | confirm Dialog | race, grading-opened-once | 020, 016 |
| P-GRD-01 | Grading workspace | Guidance | `/staff/grading/{session}` | manual score writes | forms, ModeBanner | provider-degraded, validation | 008, 017, 021 |
| P-GRD-02 | Finalize grading | Guidance | action on P-GRD-01 | `FinalizeGrading` | confirm Dialog | incomplete-block, success | 009 |
| P-CON-01 | Consultation decision | Counselor | `/staff/consultation/{attempt}` | Record/Confirm/Defer | form + provenance panel | draft, confirm | 011 |
| P-REL-01 | Result prepare / internal preview | Guidance | `/staff/results/{attempt}` | PrepareResultDocument | watermarked preview | pending-gen, watermark | 014, 022, 032 |
| P-REL-02 | Release / reverse | Guidance | actions on P-REL-01 | Release / Unrelease | high-risk Dialog | prereq-fail, released, reversed | 012, 013, 015 |
| P-NTF-01 | Staff notification status (delivery) | Guidance/ops | panel on release | read outbox | status chips | pending/failed/retry | 013, 030 |
| P-PH-01 | Program Head projection (conditional) | Program Head | `/staff/programs/{id}` | projection query | StatCard/KpiCard if approved | suppressed, denied individual | 025, 029 |
| P-AUD-01 | Audit verification evidence | authorized / break-glass | `/staff/audit/...` | verify | table | tamper-fail | 015 |
| P-OPS-01 | Demo reset / foundation status | ops | existing `/staff` + artisan | `foundation:reset` | Foundation Status | ready/fail | 001, 016 |
| P-UI-01 | UI kit gallery (non-journey) | staff auth | `/ui-kit` | none | full kit | ready | n/a (E1b) |

## 4. Dialog / toast / banner rules (slice)

| Pattern | When | Truthful wording |
|---|---|---|
| Risk confirmation Dialog | Accept/Dismiss/Finalize/Release/Unrelease/Close | Names irreversible/publish effects; keyboard accessible |
| Pending toast | After-commit side effect accepted | “Queued” / “Accepted for processing” + operation id — never “Sent”/“Released” without evidence |
| ModeBanner / degraded | Worker/provider down | Manual path remains available |
| Break-glass banner | Super Admin session | Persistent; not a toast |

## 5. Out of catalog for E2

- Nexi provider/RAG pages (chrome only via `NexiAssistPanel`)
- OMR capture/review UI (gated REQ-VS-010)
- Offline proctoring sync UI
- Expanded analytics inventory beyond conditional P-PH-01
