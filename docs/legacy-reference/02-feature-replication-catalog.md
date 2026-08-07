# 02 — Feature Replication Catalog

**Purpose:** consolidate the old feature surface into target behavior decisions before greenfield recreation

| Consolidated target capability | Legacy evidence | Disposition | Greenfield rule |
|---|---|---|---|
| Public admission intake | `ApplicationController`, application requests/models, applicant pages | `REWRITE` | Implement `SubmitAdmissionAttempt`; durable applicant identity, cycle-scoped attempt, collision-safe reference, challenge after commit, acceptance remains separate |
| Registrar intake and decision | `ApplicationController`, admin application pages, pipeline service, application policy | `REWRITE` | Split page adapters from `Correct`, `Accept`, `Dismiss`, bounded `Reopen/Archive` commands; retain history and scope |
| Applicant access and recovery | portal auth/setup/reset controllers, applicant guard, portal pages | `REWRITE` | Hash-only one-time challenge lifecycle, non-enumerating errors, purpose/subject binding, own-data scope |
| Reference data and academic setup | academic-year/course/room/aptitude/template admin areas | `REWRITE` | New reference aggregates, effective dates, deactivation not destructive delete, explicit office/capability ownership |
| Exam session planning | `Admin/ExamSessionController`, `ExamSchedulingService`, assistant controller/service, scheduling pages | `REWRITE` | Separate slot planning, reservation, assignment, publication, and session lifecycle; enforce capacity and one effective deadline |
| Proctor/session operations | `app/Http/Controllers/Proctor/`, roster pages, attendance/submission flows | `REWRITE` | Proctor is an assignment-scoped duty overlay; only assigned session operations; online/manual fallback before offline capability |
| Manual assessment/grading | grading controllers, `GradingSessionService`, grading pages/models | `REWRITE` | One active grading version, explicit `open → review → finalized`, transactional scores, correction versions, human authority |
| Score import | import controllers/services/pages and legacy OMR artifacts | `REWRITE` + `SPIKE` | Stage batch/rows, row-atomic validation, idempotent authoritative command; OMR remains proposal/review only |
| OMR observation | OMR services/research plans | `SPIKE` | Output confidence/provenance/proposals; never writes authoritative score directly; manual scoring is the fallback |
| Consultation decision | legacy result/consultation pages and summary models | `REWRITE` | Human consultation decision with explicit online/F2F branch; keep decision separate from model suggestions |
| Result preparation/release | `ReleaseController`, release pages, release notifications | `REWRITE` | Prepare document, release, notify, reverse/supersede as distinct commands with prerequisites and audit evidence |
| Document generation/printing | `ReleasePrintController`, print services, `result_sheet_templates/`, PDF/DOCX assets | `PRESERVE` behavior + `REWRITE` trust boundary | Version templates, private artifacts, typed placeholders, checksum, operation state, print/distribution evidence |
| Applicant notifications | notification classes, notification pages, mail paths | `PRESERVE` intent + `REWRITE` delivery | After-commit projection, recipient/channel deduplication, preferences, retry/terminal failure, truthful pending/sent status |
| Audit and protected views | audit service/controller/pages and audit artifacts | `REWRITE` | Canonical event payload, serialized chain/checkpoints/verification, reasoned protected reads, break-glass markers |
| Roles and route protection | route middleware strings, policies, access inventory | `REJECT` as authority + `REWRITE` | Capabilities/scopes/contextual duties from GF-04/GF-21; UI hiding never replaces server enforcement |
| Staff/applicant shells | Svelte layouts, nav components, breadcrumb/topbar patterns | `PRESERVE` affordances + `REWRITE` contract | Use GF-22 shells/page props/state contract; retain useful wayfinding only after accessibility/scope review |
| Reports and dashboards | admin reports, Chart.js pages, filters/exports | `REWRITE` | Scoped read models with denominator/freshness, table alternatives, safe exports; charts never decide |
| AI companion/RAG | portal AI controller, knowledge pages, provider packages | `SPIKE` | Separate policy, own-data, and typed task contexts; citations/refusal/confirmation/audit; no provider authority |
| Decision-support models | provider services, legacy model plans, result pages | `SPIKE` | Versioned proposal artifacts with input/model/provenance; counselor/Test Administrator remains authoritative |
| Legacy migrations | `database/migrations/` (86 files) | `REJECT` as target history | Design one fresh PostgreSQL migration history from GF-19; map legacy data separately only after target schema stabilizes |
| Legacy tests | current test files and historical verification artifacts | `PRESERVE` as case evidence | Re-express scenarios as PHPUnit/Vitest/Playwright target evidence; never claim target proof from legacy tests |
| Legacy planning/manuscript | `legacy_codebase/`, `manuscript/`, root audits | `PRESERVE` as evidence | Cite, reconcile, and promote only through GF-13/GF-14/GF-24 and OpenSpec |

## Consolidated feature rule

A target feature is considered clarified only when the catalog row identifies:

1. its authoritative actor/capability;
2. its owned state and transitions;
3. its data scope;
4. its happy path and recovery path;
5. its visible UI states;
6. its audit/notification obligations;
7. its target module/command/query/page;
8. its acceptance evidence.

If any of these are unknown, the feature remains a clarification, spike, or bounded fallback rather than an implementation task.
