# UX Flows + End-to-End Testing — Detailed Task Breakdown (Planning Input)

**Status:** PLANNING INPUT ONLY — authorizes nothing. Feeds future OpenSpec changes.
**Date:** 2026-08-18
**Scope:** ALL BUILT greenfield surfaces under `system/` EXCEPT OMR and printing (see §1). Coverage ledger = [`docs/e2-page-state-catalog.md`](../e2-page-state-catalog.md) (P-* rows), QA cadence = [`docs/qa-test-strategy-research.md`](../qa-test-strategy-research.md) (§6, R1–R10).
**Evidence rule:** `legacy_codebase/` + `docs/legacy-reference/` are evidence only. Every surface below is Svelte 5 (Inertia page) unless stated; state arrives as Inertia page props destructured in the page's `<script>` block (verified per page), with `usePage()` for `auth.user`, `errors.*`, `flash.*`, `page.url`.

---

## 0. Mission & authority (locked founder decisions, listed)

These are fixed and are NOT re-opened by this plan:

1. **Coverage ledger = `docs/e2-page-state-catalog.md`.** Each P-* row's states must map to a test owner (Pest Feature / Vitest / Playwright journey / exploratory-manual). (QA research R4; catalog header "responsibility and page states are fixed"; URI strings are implementation decisions.)
2. **Test pyramid posture (R1, R4, R8).** Playwright suite stays deliberately small — one spec per high-value journey, never per button; per-button behavior lives in Pest Feature + Vitest; any E2E CI failure is diagnosed via trace viewer first and pushed down into a lower-level test when no lower-level test fails (Fowler rule 1 / Google E2E evidence).
3. **Role-first locators (R3).** New Playwright specs use `getByRole`/`getByLabel` first; `getByTestId` only as deliberate exception; convention recorded in the future change's design.
4. **Automated a11y scans are findings, not conformance (R5, W3C).** An axe-class pass on journey specs is required but never called "WCAG AA conformant"; conformance requires knowledgeable human evaluation (W3C WAI position).
5. **Quarterly WCAG-EM-style full-product audit (R7).** Product is small enough to evaluate entirely (WCAG-EM 2: skip sampling for few views); each audit = automated scan → keyboard-only walk of every complete process → screen-reader spot check → report per WCAG-EM structure.
6. **GF-11 SCN-VS journeys are the journey inventory** (SCN-VS-001..022, [`greenfield-foundation/11-connected-vertical-slice-and-acceptance.md`](../greenfield-foundation/11-connected-vertical-slice-and-acceptance.md) §7): submit/isolate, duplicate race, access establishment, unauthorized-accept denial, conflicting scheduling, contextual proctor duty, attendance/submission idempotency, manual-scoring-without-inference, OMR abstention (out of scope here), incomplete-finalization block, human-owned decision, release-and-delivery, non-destructive unrelease/reopen, program-scope denial, audit tamper evidence, deterministic reset, extension/close one-command, resume-partial-import (unbuilt surface — gap), preview-vs-publication, notification projection, RAG-refusal fallback (Nexi), PH scoped metrics.
7. **GF-22 interface contract** ([`greenfield-foundation/22-user-experience-and-interface-contract.md`](../greenfield-foundation/22-user-experience-and-interface-contract.md)): canonical page props §3.5; route responsibility §4.1; breadcrumb rules §4.2; dialog/drawer/confirmation contract §5.4 (focus trap, Escape only when not mid-commit, return focus to trigger, never color alone); toast/feedback contract §6 (toast never claims mail/release/import/provider success without authoritative evidence — REQ-VS-028/030); 24-state page-state vocabulary §7 (`initial_loading`, `refreshing`, `ready`, `empty_scope`, `empty_filter`, `not_yet_available`, `denied`, `duty_expired`, `feature_gated`, `purpose_required`, `break_glass_required`, `validation_error`, `server_error`, `conflict`, `accepted_for_processing`, `degraded_provider`, `unknown_outcome`, `audit_failed`, `offline_ready`, `offline_pending`, `offline_conflict`, `manual_reconciliation_required`, `success_pending_effect`, `not_found`).
8. **Catalog page_state vocabulary is the shared slice vocabulary** (9 states): `initial-loading`, `ready`, `empty/not-yet-available`, `denied`, `validation`, `server-error`, `stale/conflict`, `degraded/retry`, `success/pending`. Breadcrumbs server-supplied; toasts never the sole error/permission signal; Nexi is locked chrome only (no provider mutations in E2).
9. **Authz authority:** GF-21 `CapabilityAuthorizer` decides; Spatie is persistence/helper only; UI hiding never replaces server enforcement; every surface's capability gate is a Pest-asserted contract (deny must not leak).
10. **Version/idempotency guards are UI contracts.** State-transition POSTs carry `expected_version`; reservation/decision/OMR flows carry `idempotency_key` (`newIdempotencyKey()`); tests must assert stale-version rejection and key replay/conflict (no silent overwrite).
11. **Existing-feature layout rule (repo rule):** mimic legacy Index → Create/Edit → Show shape (list page links to separate create/edit pages; actions on Show/list). Do not invent layouts needing founder correction.
12. **Only one active OpenSpec change** (`openspec/changes/omr-profile-test-readiness/`, owned by another agent session). This document creates no OpenSpec directories and touches nothing under `openspec/changes`.

## 1. Out of scope / blocked

| Item | Why | Handling |
|---|---|---|
| **OMR** — P-OMR-01/P-OMR-02 rows, `Assessment/Omr/*.svelte`, `/grading/sessions/{gs}/omr*` routes, `Setup/OmrConfiguration/*`, `omr-profile-test-readiness` change | Explicit mission exclusion; another agent session owns the active change | Never tasks here. P-OMR rows are marked `OWNER: n/a (out of scope)` in the Phase 1 matrix. Grading tasks reference OMR only as a future dependency (manual path must keep working). |
| **Printing** — `PrintingController` routes (`/printing/jobs/*`), `PrintPipelineTest.php`, print jobs UI inside `Setup/OmrConfiguration/Index.svelte`, result-sheet print flow | Being built elsewhere; mission says future-dependency-only | Reference as future dependency for P-REL-01 (prepare document → print evidence) and applicant results retrieval; never plan tasks. |
| **Nexi provider/RAG internals** — `app/Nexi/*` engine behavior | Catalog §5: chrome only via `NexiAssistPanel` in E2 | Only user-facing chrome (dock/panel mount states, absence on public shells) is in scope (T2.16, T5.9). |
| **Offline proctoring sync UI** | Catalog §5 excludes it; not built | Not a task; noted as not-existing surface (gap). |
| **Expanded analytics inventory beyond conditional P-PH-01/02** | Catalog §5 | P-PH-01/02 tasks cover only built dashboards. |
| **Legacy codebase** | Evidence only | Cited for journey shape; no tasks touch `legacy_codebase/`. |
| **P-AUD-01 audit-verification UI** | No route/page exists (see §2 gap G-02) | Discovery gap, not silent invention; listed in Phase 2 as a product-gap dependency. |
| **P-REL-01/02 release prepare/release/unrelease UI** | Routes + actions + Pest tests exist; **no page surface** (see gap G-01) | Discovery gap; T2.9 plans the missing surface + its tests. |
| **P-EXM-03 slot-plans** | Superseded by Option A `booking_mode` schema (see gap G-03) | Catalog drift; T1.2 disposes the row. |
| **`/applicant/results` dedicated page** | No route (see gap G-04) | Results surface lives in the status-spine hub; catalog drift noted. |
| **`Applicant/Setup.svelte` + `/applicant/setup` POST** | Orphaned after D-085 (see gap G-05) | Gap; T3.10 flags removal/redirect decision. |
| **`Staff/ApplicantActionHub.svelte`** | Orphaned page file, no route, nothing imports it (see gap G-06) | Gap; QR destinations render inline in the scan modal. |
| **SCN-VS-018 (resume partial score import)** | Staged import UI not built | Discovery gap; not invented here. |
| **SCN-VS-021 (RAG refusal → confirmed inquiry fallback)** | Inquiry creation UI not built (D-079 retained?) | Discovery gap; Nexi chrome tasks cover only refusal/fallback display. |

## 2. Research evidence (verified 2026-08-18)

### 2.1 Ledger + strategy + contracts
- `docs/e2-page-state-catalog.md` — 32 P-* rows (P-AUTH-01, P-PUB-01, P-APP-01..07, P-REG-01..03, P-EXM-01/01b/01c/02/03, P-PRC-01/02, P-GRD-01/02, P-OMR-01/02, P-CON-01, P-REL-01/02, P-NTF-01, P-PH-01..03, P-QR-01, P-AUD-01, P-OPS-01, P-UI-01); 9-state vocabulary; shell list (Staff/Applicant/Proctor/Guest/Break-glass); dialog/toast/banner rules §4.
- `docs/qa-test-strategy-research.md` — §6 cadence (per-cut coverage, quarterly WCAG-EM, scheduled exploratory, trace-first CI failures); §7 R1–R10; §0 active-change conflict note.
- `docs/greenfield-foundation/22-user-experience-and-interface-contract.md` — §3.5 canonical props, §4.1 route responsibility, §4.2 breadcrumbs, §5.4 dialog contract, §6 toast contract, §7 24-state table, §8 chart contract.
- `docs/greenfield-foundation/11-connected-vertical-slice-and-acceptance.md` — SCN-VS-001..022 (§7), evidence package (§8), stop gates (§9), exit condition (§10: one repeatable browser journey + denied/recovery variants + traceability package).
- `docs/legacy-reference/04-ui-ux-observed-baseline.md` (presentation baseline, §4 evidence-to-capture checklist), `08-e2-connected-slice-trace.md` (REQ-VS-001..032 dispositions), `10-admission-attempt-detail-trace.md` (P-REG-02), `11-exam-scheduling-trace.md` (SCH-01..17, legacy defects list), `02-feature-replication-catalog.md`.

### 2.2 Harness (verified)
- `system/playwright.config.ts` — `testDir: ./tests/browser`, chromium-only project, `trace: 'on-first-retry'`, CI `retries: 2`, `fullyParallel`, `baseURL` `http://127.0.0.1:18000` (Compose), opt-in local `artisan serve` via `PLAYWRIGHT_START_SERVER`, reporter `line`.
- `system/vitest.config.ts` — `include: tests/frontend/**/*.test.ts`, `environment: 'node'` (**no jsdom** — component-mount tests need an environment change; `@testing-library/svelte` is installed but unused).
- `system/package.json` — scripts: `test` = `vitest run`, `test:browser` = `playwright test`, `quality` = check+lint+format+test; `@playwright/test ^1.62.1`, `vitest ^4.1.10`, `@testing-library/svelte ^5.4.2`, `jsdom ^30.0.1`.
- `system/tests/Pest.php` — `uses(TestCase::class)->in('Feature','Unit')`, `uses(RefreshDatabase::class)->in('Feature')`, global `require_once` of 3 Support helper files.
- `system/tests/TestCase.php` — sqlite `:memory:` pinning, CSRF disabled, Inertia pages path pinned to `resources/js/Pages`.
- `system/tests/Support/E2Helpers.php` — `seedE2Foundation()` (idempotent `DatabaseSeeder` sentinel), `seedE2JourneyFoundation()` (`JourneyFixtureSeeder`), `seedE2OmrFoundation()` (`OmrDemoSeeder`), `seedE2AdmissionSupportFoundation()` (`AdmissionSupportDemoSeeder`), `submitSyntheticApplicant()` (real `SubmitAdmissionAttempt::handle`, returns `{attempt, access_token}`).
- `system/tests/Support/AdmissionSupportHelpers.php` — `buildReleasedAdvisedAttempt()` (full submit→accept→exam→grade→finalize→decision→prepare→release journey, ~6–8s), constants `DEFAULT_PROGRAM=BSN`, `OTHER_PROGRAM=BSPSY`.
- `system/tests/Support/GradingHelpers.php` — `buildGradingFixture()`, `buildOmrGradingFixture()`, `activeAreas()`.
- `pestphp/pest-plugin-browser` v5.0.1 installed but unused (viable alternative/addition to raw Playwright — decision point for the future change).

### 2.3 Existing test inventory (verified)
- **Pest Feature (62 files, ~387 tests)** — full per-file coverage map in scout artifact `agent://UxE2eMissionBreakdown.PestCoverageInventory`. Key suites per surface: `Admission/*` (Detail, Flow, List, RouteBinding, SupportWorkflow, Access, CorrectionAndKeyLifecycle, ProgramSelection), `Applicant/ApplicantPortalInterfaceTest`, `Assessment/GradingWorkflowTest` (20), `Consultation/ConsultationSessionFlowTest` (17), `Examination/*` (Monitoring 6, Pages 3, SlotReservationFlow 10, Schema, Access, RouteBinding, ProctorDutyAssign 3), `Notifications/NotificationDeliveryTest` (11), `Registrar/EnrollmentEligibilityTest` (6), `Release/*` (Flow 3, WithoutDecision 2), `Setup/*` (AcademicYear 4, AptitudeScoring 9, Hub 6, ProgramQuota 4, OmrConfiguration/Usability/ProfileReadiness), `Staffing/*` (PersonnelLifecycle 26, StaffNavigation 4, TaCapabilityDelegation 4), `Auth/StaffAuthenticationTest`, `Authorization/*`, `Capabilities/*`, `Ai/*` (Nexi: FoundationSession, RunGovernance, KnowledgeGovernance, PolicyInquiryRag, ApplicantInquiry, ExamSessionAssistant, ChooseSlotSkill), `UiKit/GalleryTest`, `Printing/PrintPipelineTest` (future-dep only).
- **Pest Unit (5 files)** — `Assessment/NativeBundleTest`, `NativeManifestDeriverTest`, `OmrTemplateLayoutSolverTest`, `Authorization/ExamCapabilitySyncTest`, `ExampleTest`.
- **Playwright (2 specs / 4 tests)** — `tests/browser/foundation.spec.ts` (E0 runtime + `/health`), `tests/browser/nexi-foundation.spec.ts` (no Nexi dock on `/login` and `/applicant/login`). **No SCN-VS journey specs exist** — GF-11's "Playwright SCN-VS" evidence targets are unfulfilled in `tests/browser` (browser evidence so far is manual checkpoint verification).
- **Vitest (6 files)** — `applicant-portal.test.ts` (spine contract, reservation contract, nav contract — pure shape tests, no component mount), `foundation.test.ts` (home copy), `idempotency-key.test.ts`, `nexi-session.test.ts` (NexiSessionView mount matrix on `resources/js/types/nexi.ts`), `nexi-transport.test.ts`, `omr-preview-geometry.test.ts` (OMR — future-dep).

### 2.4 Built page inventory (verified; 79 `.svelte` under `system/resources/js/Pages`)
Full per-page prop inventory in scout artifact `agent://UxE2eMissionBreakdown.SveltePageInventory`. Page families and their Inertia props (state source = controller-rendered props):
- **Admission/** — `Admission/Apply.svelte` (institution, cycle, window, programs, grade_fields, privacy_policy), `Admission/Confirmation.svelte` (reference_code, access_key), `Index.svelte` (attempts, filters, statuses, cycles, status_counts, capabilities), `Show.svelte` (attempt, programs, grade_fields, reissued_access_key, capabilities), `Admission/IntakeCorrectionFields.svelte` (shared form fields component), `Admission/ApplicantAdmissionSupport.svelte` (page_state, role, attempt, support, ph_record, ph_program, threads; decision dialog + pool + follow-up forms), `Admission/E2PageState.svelte` (page_state renderer, `data-testid=page-state`), `Admission/ToastBridge.svelte`.
- **Applicant/** — `Applicant/Portal.svelte` (identity, stages, notices, quick_links), `Applicant/Schedule.svelte` (attempt, assignment, application, calendar_days, message, pipeline; headline map: denied/pending_acceptance/waiting_for_reservation_open/calendar_open/assigned_published), `Applicant/SchedulePipeline.svelte` (stages), `Applicant/Consultation.svelte` (attempt, assignment, sessions, message; states: denied/not_result_ready/no_open_sessions/booking_open/assigned_or_reserved), `Applicant/ProgramChoice.svelte` (support; readiness/pool/analysis derived), `Applicant/MyApplication.svelte` (application), `Applicant/Correction.svelte` (mode, attempt, programs, grade_fields), `Applicant/MyQr.svelte` (attempt_id, qr), `Applicant/AdmissionSupport.svelte` (threads, access_history), `Login.svelte` (demo), `Setup.svelte` (page_state — orphaned, see G-05), `Applicant/ApplicantLayout.svelte` (shell; NexiDock + NotificationBell + logout).
- **Assessment/** — `Assessment/Grading.svelte` (sessions, candidate_exam_sessions, aptitude_areas_count, degraded), `Assessment/GradingSession.svelte` (session, applicants, roster_stats, progress_stats, can_finalize), `Assessment/GradingScoreInput.svelte` (session_id, session_status, finalized, attempt, exam, domains), `Omr/Capture.svelte` + `OmrRegionCrop.svelte` (out of scope).
- **Examination/** — `Index.svelte` (sessions, filters, statuses, cycles), `Examination/Create.svelte` (cycles, rooms, default_cycle_id), `Examination/Edit.svelte` (session, cycles, rooms), `Show.svelte` (session, assigned_applicants, available_applicants, reassign_targets, publish_block_reason, is_publishable), `Examination/Monitoring.svelte` (session, roster, can_manage, can_record), `Examination/MonitoringIndex.svelte` (sessions, can_manage).
- **Consultation/** — `Consultation/ConsultationSessions.svelte`, `Consultation/ConsultationSessionCreate.svelte` (cycles, rooms, default_cycle_id, booking_modes), `Consultation/ConsultationSessionEdit.svelte` (session, cycles, rooms, booking_modes), `Consultation/ConsultationSessionShow.svelte` (session, assigned_applicants, ready_pool, available_applicants, reassign_targets, publish_block_reason, is_publishable, is_completable, can_decide).
- **Release/** — `Applicant/Consultation.svelte` (dashboard, can_plan, search_query, search_results; scan modal), `Index.svelte` (**placeholder** — only E2PageState; gap G-01).
- **Registrar/** — `Registrar/EnrollmentEligibility.svelte` (query, results, card; page_state search/ready; facts: accepted/grading_finalized/result_released/advised).
- **ProgramHead/** — `Index.svelte` (programs, cycle), `ProgramHead/ProgramShow.svelte` (dashboard, lists), `ProgramHead/ApplicantShow.svelte` (record; decline + follow-up resolve forms).
- **Staff/** — `Home.svelte` (staff; scope + capability debug), `ApplicantScan.svelte` (scan_result, scanned_attempt, destinations), `ApplicantActionHub.svelte` (**orphaned**, gap G-06), `Personnel/{Index,Create,Edit}.svelte`, `Staff/Profile/Show.svelte`, `Staff/StaffLayout.svelte` (staff_navigation; NexiDock, NotificationBell, ApplicantScanModal, ToastManager).
- **Setup/** — hub + reference data (`Index`, `Programs`, `Rooms`, `Cycles`(+Create/Edit), `Grades`, `Aptitude`, `AptitudeScoring`, `Quotas`, `Privacy`) + OMR configuration family (future-dep).
- **Other** — `Auth/Login.svelte`, `Notifications/Index.svelte` (staff+applicant, `is_applicant` from usePage), `Knowledge/{Index,Create,Show}.svelte`, `Foundation/Status.svelte`, `Home.svelte`, `UiKit/Gallery.svelte`.
- **Shared kit** — `Components/kit/*` (PageHeaderBar, FilterToolbar, ActiveFilterChips, BulkActionBar, SwitchableListView, ViewModeToggle, SimplePagination, StatCard, KpiCard, ModeBanner, ToastManager, NotificationBell, ActionDropdown, InfoPopover, PasswordStrengthInput, ApplicantScanModal, Nexi/{NexiDock,NexiAssistPanel}, Guide/*); `Components/applicant/ReservationSlotList.svelte` + `Components/applicant/ReservationConfirmSheet.svelte`; `lib/auto-filter.js` (URL filter state), `lib/scan-modal.js` (QR modal), `lib/toast.js`, `lib/utils.js` (`newIdempotencyKey`), `types/nexi.ts` (NexiSessionView contract).

### 2.5 Routes (verified, `system/routes/web.php`, 523 lines)
All families: `/` home, `/health`, `/apply` (form/submit/confirmation), `/applicant/login` (+ GET `/applicant/setup` alias — no POST), staff `/login`, `/staff` home, `/nexi/*`, `/knowledge/*`, `/setup/*` (+ `/setup/omr/*` future-dep), `/staff/profile`, `/staff/personnel*` (+ grant/revoke-ta), `/admissions*` (index/show/accept/dismiss/correct/request-correction/authorize-amendment/reconsider/reopen/archive/reissue-access-key), `/applicant/portal`, `/applicant/schedule` (+reserve), `/applicant/consultation` (+reserve), `/applicant/applications/{attempt}/correct` (+store), `/applicant/applications/{attempt}`, `/exams/monitoring`, `/exams/sessions/{session}/monitoring`, `/exams/sessions*` (index/create/store/show/edit/update/assign/remove/reassign/publish/unpublish/cancel/close/extend/open-reservation/close-reservation), `/consultation/sessions*` (full lifecycle incl. open/close-reservation), `/registrar/enrollment-eligibility*`, `/exams/sessions/{session}/proctor-duty` + `/proctor-duty/{duty}/revoke`, monitoring attendance/submission, `/grading*` (index/store/show/omr*/scores/finalize), `/consultation` (dashboard) + `/consultation/{attempt}/decision` + `/consultation/applicants/{attempt}` (+support JSON), `/release/{attempt}/prepare`, `/release`, `/release/{attempt}`, `/release/records/{release}/unrelease`, `/printing/jobs/*` (future-dep), `/setup/omr/*` (future-dep), `/consultation/{attempt}/program-pool`, `/consultation/{attempt}/follow-up`, `/applicant/program-choice*` (acknowledge/select/transfer/cancel), `/staff/programs*` (+decline/follow-ups/threads), `/registrar/enrollment-eligibility/{attempt}/enrolling|enrolled|reverse`, `/staff/scan` (+resolve), `/applicant/qr`, `/applicant/admission-support`, `/ui-kit`, `/notifications*` (index/read/read-all/unread-count), `/foundation/capability-probe`.

### 2.6 Discovery gaps found (verified, not invented)
- **G-01 Release UI absent.** `release.prepare`, `release.store`, `release.unrelease` routes + `PrepareResultDocument`/`ReleaseResult`/`UnreleaseResult` actions + Pest `ReleaseFlowTest`/`ReleaseWithoutDecisionTest` exist; **no Svelte page posts them** (`Release/Index.svelte` renders only `E2PageState`). Staff release outbox (P-NTF-01) also has no UI. `ReleaseController::index` returns `Release/Index` with only `page_state`.
- **G-02 Audit UI absent.** `RecordAuditEvent` + audit tables exist; **no `/staff/audit*` route/page** for P-AUD-01 verification evidence.
- **G-03 P-EXM-03 slot-plans superseded.** `ExamSlotSchedulingSchemaTest` asserts Option A schema: no `slot_plans`/`reservations` tables; `booking_mode` on sessions + `open-reservation`/`close-reservation` replace the row's Open/Close/Finalize/Bypass commands. Catalog row is stale.
- **G-04 No `/applicant/results` route.** Result retrieval is mode-aware inside the status spine (`ApplicantPortalProjection::stages`, MODE_ONLINE artifact vs MODE_F2F zero scores); catalog row P-APP-03 names a results page that does not exist as a separate route.
- **G-05 `Applicant/Setup.svelte` orphaned.** D-085 removed password setup; route kept as GET alias only; the page's `form.post('/applicant/setup')` would 405. Dead UI + dead test target.
- **G-06 `Staff/ApplicantActionHub.svelte` orphaned.** No route renders it; nothing imports it; QR destinations render inline in `Components/kit/ApplicantScanModal.svelte`.
- **G-07 No Playwright journey specs.** `tests/browser` holds only foundation + nexi-foundation; every SCN-VS journey needs a spec (Phase 5).
- **G-08 No Vitest component mounts.** `vitest.config.ts` environment `node`; `@testing-library/svelte` unused; only pure-contract tests exist.
- **G-09 `SCN-VS-018`/`SCN-VS-021` surfaces not built** (staged import UI; confirmed-inquiry fallback UI) — recorded, not planned.

---

## 3. Phase 1 — Page-state catalog gap audit (P-* → test owner)

**Phase goal:** Make `docs/e2-page-state-catalog.md` a complete coverage ledger: every P-* row (except OMR/printing) maps every required page state to a concrete owning test (Pest Feature / Vitest / Playwright journey / exploratory-manual / none-gap), and every drift row is disposed (catalog correction or recorded gap).

### T1.1 Build the P-* → test-owner matrix

- **Goal:** Produce and land (inside the future change) the authoritative ownership matrix for all 32 catalog rows, replacing ad-hoc coverage checks.
- **Scope (files):** `docs/e2-page-state-catalog.md` (matrix section); existing suites: `system/tests/Feature/**`, `system/tests/frontend/**`, `system/tests/browser/**`; pages under `system/resources/js/Pages/**`; routes `system/routes/web.php`.
- **Steps:**
  1. For each row, list every state named in the catalog row's "Required page states" and the corresponding built page + props (inventory in §2.4).
  2. For each state, find an existing asserting test (Pest test name / Vitest describe / Playwright test) or mark `GAP`.
  3. Produce the matrix (planned artifact, see acceptance) with columns: P-* row / built page file(s) / controller+action / required states / owner test per state / gap disposition.
  4. Where the row's URI differs from the built route, record the built route (URIs are implementation decisions per catalog header).
- **Acceptance:** Matrix covers all 32 rows; every non-OMR, non-printing row has a named owner per state; every row with no owner carries a disposition (G-01..G-09 or "deferred to surface task T2.x/T3.x"). Matrix checked into the future change's design doc.
- **Test mapping:** none (documentation task) — its output gates T1.3.
- **Dependencies & order:** after this document is accepted; before any Phase 2/3 surface task (they consume the matrix).
- **Risks & open questions:** catalog rows reference surfaces that moved (P-EXM-03, P-PRC-01 URI) or don't exist (P-AUD-01, P-NTF-01) — the matrix must not invent owners for absent surfaces; record as gaps.

### T1.2 Resolve catalog drift rows

- **Goal:** Dispose each stale/absent catalog row with a written decision, not a silent edit.
- **Scope (files):** `docs/e2-page-state-catalog.md` rows: P-EXM-03 (slot-plans), P-PRC-01 (URI `/staff/proctor/sessions/{id}` vs built `/exams/sessions/{session}/monitoring` + `/exams/monitoring`), P-REL-01/P-REL-02 (no UI), P-NTF-01 (no UI), P-AUD-01 (no UI), P-APP-03 (`/applicant/results`), P-QR-01 (`/staff/applicants/{attempt}/actions` vs inline modal destinations), P-APP-01 (`/applicant/login` OK), P-OPS-01 (`/staff` + artisan). Evidence: `system/routes/web.php`, `system/tests/Feature/Examination/ExamSlotSchedulingSchemaTest.php`, `system/app/Http/Controllers/Release/ReleaseController.php`, `system/app/Http/Controllers/Staff/ApplicantQrController.php`.
- **Steps:**
  1. For each row: state built reality (route/page/action) with file evidence.
  2. Decide: (a) catalog correction (e.g. P-EXM-03 → booking-mode vocabulary; P-PRC-01 URI note; P-APP-03 results note), (b) product gap needing a future surface change (P-REL-01/02, P-NTF-01, P-AUD-01 → T2.9/T2.15 dependency), or (c) keep row as target contract with explicit "not yet built" marker.
  3. Apply the catalog edit inside the future change (this plan authorizes nothing).
- **Acceptance:** Every drift row has a one-line disposition recorded in the catalog; no row silently disappears; gaps G-01..G-06 are linked from the catalog.
- **Test mapping:** none (docs task); disposal decisions are re-verified by existing Pest tests (e.g. `ExamSlotSchedulingSchemaTest` proves the booking-mode schema).
- **Dependencies & order:** T1.1; before Phase 2 surface tasks that touch drifted rows (T2.4, T2.9, T2.13, T3.4).
- **Risks & open questions:** founder approval needed for removing/changing catalog contract rows (catalog header: "responsibility and page states are fixed") — propose, don't decide alone.

### T1.3 Verify every shared state is exercised per row

- **Goal:** No catalog page state is claimed without an asserting test (R4 "complete coverage checked against a repo contract, not ad hoc").
- **Scope (files):** 9-state vocabulary (`initial-loading`, `ready`, `empty/not-yet-available`, `denied`, `validation`, `server-error`, `stale/conflict`, `degraded/retry`, `success/pending`) × every active row; `system/tests/Feature/**`, `system/tests/frontend/**`, `system/tests/browser/**`.
- **Steps:**
  1. From the T1.1 matrix, compute state × row cells with no owner.
  2. Classify each uncovered cell: (a) hard to reach (e.g. `server-error`, `degraded`) → Pest Feature with a forced provider failure / route error simulation, or exploratory-manual marker; (b) pure presentation (`initial-loading` skeleton, `empty`) → Pest Inertia assert or Vitest; (c) only meaningful in a real browser (loading flicker, degraded banner coexistence with manual path) → Playwright journey or exploratory.
  3. Record the classification in the matrix; Phase 2/3 tasks implement the missing tests.
- **Acceptance:** Every row state cell has an owner or an explicit "exploratory-manual / not-reproducible-in-automation" rationale; zero silent cells.
- **Test mapping:** none itself; generates concrete test tasks consumed by Phases 2/3.
- **Dependencies & order:** T1.1 → T1.2 → this.
- **Risks & open questions:** some states are currently unrendered by any page (e.g. `server-error` surfaces exist only in `usePage().errors` banners) — the audit may reveal contract gaps (page never renders the state) that are product gaps, not test gaps.

### T1.4 Record the coverage-ledger convention

- **Goal:** Make "catalog = coverage ledger" a durable repo rule so future cuts maintain it (R4 + QA research §6 cadence item 1).
- **Scope (files):** the future change's design doc; `docs/project-context.md`-style rule or `.ai/rules/` note (decide in change).
- **Steps:** 1. Write the convention: every feature cut updates the P-* row(s) it touches with owner tests; a cut ships only when each touched row state has an owner. 2. Wire the convention into the OpenSpec tasks template used by the future change.
- **Acceptance:** Convention text exists; T7.4 (per-cut gate) references it.
- **Test mapping:** none.
- **Dependencies & order:** T1.1..T1.3; feeds T7.4.
- **Risks & open questions:** enforce by review, not tooling (no new CI gate proposed here).

---

## 4. Phase 2 — Per-surface state coverage: staff surfaces

**Phase goal:** Every built staff surface (except OMR/printing) has per-state Pest/Vitest coverage matching the T1.1 matrix; uncovered states get their missing tests or an explicit exploratory owner. Each task names the Svelte page and its Inertia props (state source).

### T2.1 Registrar intake queue — P-REG-01

- **Goal:** `empty`, `ready`, `denied` + filter/search/pagination + capability-gated actions fully covered.
- **Scope:** `system/resources/js/Pages/Admission/Index.svelte` (props: page_state, attempts, filters, statuses, cycles, status_counts, capabilities; `router.get` filter + debounced search; `router.post` accept/dismiss/reopen with `expected_version` + reason); `system/app/Http/Controllers/Admission/AdmissionReviewController.php`; existing `system/tests/Feature/Admission/AdmissionListTest.php` (index denied, filter/paginate/search, status_counts, capabilities.accept).
- **Steps:** 1. Audit `AdmissionListTest` against the row states; 2. add missing Pest cases: empty-scope copy + next action, empty-filter + clear-filter, denied payload shape (no leaked fields), page_state prop serialization for ready/denied; 3. add Vitest for view-mode toggle + status badge mapping if not covered.
- **Acceptance:** Each state has a named test; denied asserts no attempt rows leak.
- **Test mapping:** Pest Feature (primary), Vitest (badge/toggle logic), exploratory-manual for skeleton `initial-loading` (SPA fetch flash).
- **Dependencies & order:** T1.1 matrix; independent of other Phase 2 tasks.
- **Risks & open questions:** none identified beyond fixture cost (reuse `submitSyntheticApplicant`).

### T2.2 Registrar attempt detail + correct / accept / dismiss / reconsider — P-REG-02

- **Goal:** `conflict`, `validation`, `success` states + the full dialog matrix (risk confirmations per catalog §4) covered.
- **Scope:** `system/resources/js/Pages/Admission/Show.svelte` (props: attempt, programs, grade_fields, reissued_access_key, capabilities; POSTs: accept/dismiss/archive/reopen/request-correction/reconsider/reissue-access-key, editForm.post(correct), amendForm.post(authorize-amendment); `Admission/IntakeCorrectionFields.svelte` shared fields); `AdmissionActionController`, `AdmissionReviewController`; existing `system/tests/Feature/Admission/AdmissionDetailTest.php` (show payload, corrections, amendments, reconsider, stale-version conflict, quiet-correct-after-accept), `CorrectionAndKeyLifecycleTest.php`.
- **Steps:** 1. Map each Show.svelte POST to its Pest coverage; 2. add missing: dialog-triggered commands each assert audit + capability; stale `expected_version` → `validation` with preserved input; `reissued_access_key` one-time display (mirror `ApplicantAccessTest` flash-once pattern); 3. add Vitest for the capability-derived `can*` flag matrix and key-visibility toggle.
- **Acceptance:** Every command on the page has an asserting test incl. denial-without-leak; conflict path returns typed version error.
- **Test mapping:** Pest Feature (primary), Vitest (can* matrix, toggle), exploratory-manual for double-submit feel.
- **Dependencies & order:** T1.1; T2.1 (shared fixtures).
- **Risks & open questions:** `window.prompt` for reasons on some POSTs (seen in `Index.svelte`) — a11y risk surfaced in Phase 4 (T4.3) and a possible product change (prompt → dialog).

### T2.3 Exam session index / create / edit — P-EXM-01, P-EXM-01b, P-EXM-01c

- **Goal:** list states + form `validation`/`conflict` + `draft-only`/`denied` covered.
- **Scope:** `system/resources/js/Pages/Examination/Index.svelte` (sessions, filters, statuses, cycles), `Examination/Create.svelte` (cycles, rooms, default_cycle_id), `Examination/Edit.svelte` (session, cycles, rooms; form.put with expected_version); `ExamSessionController`; existing `ExamSessionPagesTest.php` (3: list shape, create→show→edit→update journey, publish-without-applicants blocked), `ExamSessionAccessTest.php` (403), `ExamSlotReservationFlowTest.php` (edit locked while reservation open).
- **Steps:** 1. Add Pest: empty index + empty-filter; denied page_state; create validation errors; edit stale-version conflict; draft-only edit gate (cannot edit published); 2. Vitest: form field reset/processing states if logic is extracted.
- **Acceptance:** Every index state and both form states have named tests.
- **Test mapping:** Pest Feature (primary), Vitest (optional), Playwright journey T5.4 covers the real create→assign→publish flow.
- **Dependencies & order:** T1.1; before T2.4.
- **Risks & open questions:** legacy SCH-02/SCH-03 shape preserved (list → separate create/edit) — locked by repo rule.

### T2.4 Exam session detail: assign / publish / reservation / reassign — P-EXM-02

- **Goal:** `capacity conflict`, `publish-blocked`, `published` states + reassign/remove paths covered at the page-contract level.
- **Scope:** `system/resources/js/Pages/Examination/Show.svelte` (props: session, assigned_applicants, available_applicants, reassign_targets, publish_block_reason, is_publishable; POSTs: assign (sequential multi-post), remove, reassign, publish, unpublish, cancel, open-reservation, close-reservation, extend — all with expected_version); `ExamSessionController`; existing `ExamSessionPagesTest` (publish-without-applicants blocked), `ExamSlotReservationFlowTest` (capacity deny, staff overwrite guard, reassign single+bulk, reservation lock on edits, capability deny).
- **Steps:** 1. Audit existing tests vs the row states; 2. add missing Pest: unpublish→draft with reason; cancel terminal + reason; extend effective-deadline; publish_block_reason serialization for each gate (room/time/future/≥1 applicant); capacity-conflict payload typed for reselection; 3. Vitest: publish-gate reason dialog rendering, reassign dialog state.
- **Acceptance:** Each gate reason is asserted in the payload; every Show.svelte POST has a test incl. version conflict.
- **Test mapping:** Pest Feature (primary), Vitest (dialog logic), Playwright T5.4 (journey).
- **Dependencies & order:** T2.3; row P-EXM-03 disposition from T1.2.
- **Risks & open questions:** proctor time-conflict check is a declared SPIKE (SCH-16) — do not assert conflict-safe proctors.

### T2.5 Exam monitoring: attendance / submission — P-PRC-01, P-PRC-02

- **Goal:** `window-closed`, `idempotent replay`, `degraded` + attendance/submission recorded states covered; proctor duty is contextual (SCN-VS-006).
- **Scope:** `system/resources/js/Pages/Examination/MonitoringIndex.svelte` (sessions, can_manage), `Examination/Monitoring.svelte` (session, roster, can_manage, can_record; router.post attendance + submission with idempotency_key); `ExamMonitoringController`, `ProctorDutyController`; existing `ExamMonitoringTest` (6: manage/duty scoping, index denied, roster actions, duty-record), `ExamProctorDutyAssignTest` (3), `AdmissionFlowTest` (attendance idempotency), `GradingWorkflowTest` (close guarded on attendance completeness).
- **Steps:** 1. Add Pest: window-closed (attendance after effective deadline rejected); idempotent replay (same key, one record, no duplicate); duty-revoked mid-session denies further actions; proctor sees only duty sessions; 2. Vitest: per-row busy flags, attendance badge mapping; 3. exploratory: degraded/offline banner coexistence.
- **Acceptance:** SCN-VS-006/007/017 states each have a named test.
- **Test mapping:** Pest Feature (primary), Vitest (roster row logic), exploratory-manual (degraded network simulation).
- **Dependencies & order:** T1.1; T2.4 fixtures (assigned+publish+open sessions).
- **Risks & open questions:** close/reconcile (P-PRC-02) currently lives in `ExamSessionController::close` (staff scheduling route) — no separate close UI page; page-level task keeps close as an action on the monitoring/session surfaces, matching built routes.

### T2.6 Grading workspace: manual score input — P-GRD-01

- **Goal:** `provider-degraded`, `validation`, roster/progress states covered; manual path must complete without inference (SCN-VS-008).
- **Scope:** `system/resources/js/Pages/Assessment/Grading.svelte` (sessions, candidate_exam_sessions, aptitude_areas_count, degraded; open dialog), `Assessment/GradingSession.svelte` (session, applicants, roster_stats, progress_stats, can_finalize), `Assessment/GradingScoreInput.svelte` (session_id, session_status, finalized, attempt, exam, domains; router.put scores with idempotency_key, router.delete clear); `GradingController`, `GradingScoreController`; existing `GradingWorkflowTest` (20: open idempotent, roster, transactional multi-domain save, non-roster deny, clear, finalize, degraded mode, candidates gating), `GradingAccessTest` (403), `ScoringConversionTest` (6).
- **Steps:** 1. Map page states to tests; 2. add missing Pest: degraded prop serialization (provider flag) + manual save works when degraded; validation errors per domain; score input page carries conversion rows + frozen output (partially covered by `ScoringConversionTest`); 3. Vitest: conversion-table row computation + frozen output rendering, saving/clearing flags, finalized-locks-editing.
- **Acceptance:** Every P-GRD-01 state has a named owner; manual completion journey asserted end-to-end (SCN-VS-008 evidence).
- **Test mapping:** Pest Feature (primary), Vitest (GradingScoreInput computation), Playwright T5.5 (journey).
- **Dependencies & order:** T1.1; T2.5 (closed sessions produce grading); OMR is future-dep only.
- **Risks & open questions:** none beyond fixture runtime (use `buildGradingFixture`).

### T2.7 Grading finalize — P-GRD-02

- **Goal:** `incomplete-block` and `success` states covered incl. version lock.
- **Scope:** `Assessment/GradingSession.svelte` finalize action (`router.post` with expected_version); `GradingController::finalize`; existing `GradingWorkflowTest` (finalize rejects incomplete/no-scores/empty-roster; success bumps version + locks; stale version rejected).
- **Steps:** 1. Confirm each incomplete-block variant has a test (missing required area named); 2. add Pest: finalize from the page contract (Inertia assert of `can_finalize` + errors.finalize payload); 3. Vitest: finalizing flag + incomplete-block banner rendering.
- **Acceptance:** SCN-VS-010 asserted (atomic failure, no released projection advances).
- **Test mapping:** Pest Feature (primary), Vitest (banner), Playwright T5.5 (journey).
- **Dependencies & order:** T2.6.
- **Risks & open questions:** none.

### T2.8 Release consultation decision surface — P-CON-01 (staff side)

- **Goal:** decision record (`approve`/`modify`/`override`/`defer`) with rationale + dialog, `decision-draft`/`advised`/`pending-ack` states, online-vs-f2f mode handling covered.
- **Scope:** `system/resources/js/Pages/Release/Consultation.svelte` (dashboard, can_plan, search; scan modal), `system/resources/js/Pages/Admission/ApplicantAdmissionSupport.svelte` (decision dialog: `decisionForm.post(consultation.decision)` with idempotency_key; pool form `program-pool.set`; follow-up form `program-follow-up.open`); `ReleaseController::consultation/recordDecision`; `ProgramPoolController`; existing `ReleaseFlowTest` (decision idempotency), `AdmissionSupportWorkflowTest` (pool/advised/pending-ack, consultation support query), `ConsultationSessionFlowTest` (portal not_result_ready gating).
- **Steps:** 1. Audit states vs tests; 2. add missing Pest: decision required rationale validation; decision on unreleased result blocked; defer does not mark advised (partially in `EnrollmentEligibilityTest`); idempotency replay no-duplicate (exists); 3. Vitest: role-aware tab rendering (guidance vs staff), readiness label map; 4. exploratory: search → single-match auto-redirect to workspace.
- **Acceptance:** SCN-VS-011 asserted (human owns decision; signals remain provenance only).
- **Test mapping:** Pest Feature (primary), Vitest (role tabs), Playwright T5.6 (journey).
- **Dependencies & order:** T1.1; T2.7 (finalized scores prerequisite).
- **Risks & open questions:** catalog P-CON-01 points at `/consultation` page; decision UI actually lives in the shared applicant workspace — note in T1.2 matrix.

### T2.9 Release prepare / release / unrelease UI + staff outbox — P-REL-01, P-REL-02, P-NTF-01 (product gap + coverage)

- **Goal:** Close gap G-01: build the missing release surfaces (prepare document, watermarked internal preview, release with online/f2f mode choice, unrelease with reason, delivery-status outbox chips) and cover them. Until built, mark `OWNER: product gap`.
- **Scope (build):** `system/resources/js/Pages/Release/Index.svelte` (replace placeholder: prepared-document list, release/unrelease actions with high-risk Dialog per catalog §4, mode selection online/f2f, P-NTF-01 outbox status chips pending/failed/retry); existing backend: routes `release.prepare`/`release.store`/`release.unrelease`, `ReleaseController` (prepareDocument/release/unrelease/index), actions `PrepareResultDocument`/`ReleaseResult`/`UnreleaseResult`, models `ResultDocument`/`ResultRelease`; existing Pest `ReleaseFlowTest` (notify-once, decision idempotency, own-scope), `ReleaseWithoutDecisionTest` (release w/o decision, mode/template defaults).
- **Scope (tests):** after build — Pest: prepare → `pending-gen` → `ready` state serialization; release blocked when no READY document (prereq-fail); unrelease history-preserving (SCN-VS-013); mode defaults f2f; outbox status truthfulness (fresh rows pending, never delivered); Playwright T5.5 extends to preview → release → applicant results.
- **Steps:** 1. Design the page against `ReleaseController` payloads + GF-22 §5.4 dialog contract; 2. build page + forms (idempotency_key on release); 3. add Pest + Vitest; 4. mark P-REL-01/02/P-NTF-01 owned in catalog.
- **Acceptance:** Prepare→release→unrelease user journey exists in UI; every catalog state has an owner; SCN-VS-012/013/019/020 evidence complete.
- **Test mapping:** Pest Feature + Vitest + Playwright (after build); exploratory-manual for watermark visibility.
- **Dependencies & order:** product decision (this is a gap close, needs a future change); depends on T1.2 disposal; printing stays a future dependency (document generation is async — toast must say "Accepted for processing", never "released").
- **Risks & open questions:** async generation semantics (operation ID + accepted_for_processing) must be respected in UI wording; online vs f2f mode must drive score serialization (mode-aware portal) — assert mode recorded (existing `ReleaseWithoutDecisionTest`).

### T2.10 Consultation planning: sessions / assign / reserve — P-CON-01 (staff side)

- **Goal:** list + create/edit + detail states (publish gates, capacity, reservation lifecycle) covered.
- **Scope:** `system/resources/js/Pages/Consultation/ConsultationSessions.svelte` (filters, publish/open-reservation/close-reservation/complete/cancel with expected_version+reason), `Consultation/ConsultationSessionCreate.svelte` (booking_modes radio — no default), `Consultation/ConsultationSessionEdit.svelte` (expected_version), `Consultation/ConsultationSessionShow.svelte` (assigned_applicants, ready_pool, available_applicants, reassign_targets, publish_block_reason, is_publishable, is_completable, can_decide); `ConsultationSessionController`; existing `ConsultationSessionFlowTest` (17: booking mode required, assign blocked until result-ready, publish ≥1 assignment, lifecycle, reserve open/close, capacity deny, overwrite guard, reservation locks edits, capability deny, ready pool, reassign, cancel terminal, idempotent reserve, portal states, own-scope).
- **Steps:** 1. Map page states to the 17 tests; 2. add missing Pest: empty list + empty-filter; denied page_state; publish_block_reason payload per gate; close-reservation payload; 3. Vitest: booking-mode radio required-state, actionError aggregation from errors.*; 4. exploratory: capacity-percent UI on Show.
- **Acceptance:** Every catalog consultation state has a named owner; row states incl. `pool-conflict`, `follow-up-open`, `stale/conflict` linked to `AdmissionSupportWorkflowTest` coverage.
- **Test mapping:** Pest Feature (primary), Vitest (dialogs/aggregation), exploratory (capacity UI).
- **Dependencies & order:** T1.1; T2.8 (result-ready gating).
- **Risks & open questions:** legacy parity — consultation sessions are a greenfield ADD (no legacy consultation booking); mimic exam-session management-list chrome.

### T2.11 Registrar enrollment eligibility: search + card — P-REG-03

- **Goal:** `not-found`, `missing-core`, `may-start`, `enrolling-follow-up`, `final-ready`, `paper-program-change`, `quota-variance`, `enrolled`, `stale/conflict` states covered; every lookup audited.
- **Scope:** `system/resources/js/Pages/Registrar/EnrollmentEligibility.svelte` (query, results, card; router.get search; facts accepted/grading_finalized/result_released/advised); `EnrollmentEligibilityController`, `EnrollmentStampController` (stamp-enrolling/stamp-enrolled/reverse); existing `EnrollmentEligibilityTest` (6: facts+eligible, missing-requirement naming, deferred≠advised, audit, 403, search by reference+name), `AdmissionSupportWorkflowTest` (stamp lifecycle, reverse, paper-finalized).
- **Steps:** 1. Audit card states vs tests; 2. add missing Pest: not-found copy (no leak), quota-variance fact row, paper-program-change fact row, enrolling-follow-up interim card, reverse restores pre-final selection (exists), stale/conflict on stamp with expected_version if present; 3. Vitest: card fact-row rendering + variance badge; 4. Playwright T5.6 covers search→card journey.
- **Acceptance:** Each row state has a named owner; audit assertion per lookup (exists).
- **Test mapping:** Pest Feature (primary), Vitest (card), Playwright T5.6.
- **Dependencies & order:** T1.1; T2.8 (advised prerequisite).
- **Risks & open questions:** none.

### T2.12 Program Head: index / dashboard / candidate record — P-PH-01, P-PH-02, P-PH-03

- **Goal:** `zero/no-data/stale/error`, `quota-conflict`, `denied`, cross-scope denial, `protected-ready`, `enrolling-follow-up`, `final-blocked` states covered.
- **Scope:** `system/resources/js/Pages/ProgramHead/Index.svelte` (programs, cycle), `ProgramHead/ProgramShow.svelte` (dashboard, lists; metric cards, stage tabs, resolveForm.post follow-ups.resolve), `ProgramHead/ApplicantShow.svelte` (record; tier A/B gating; declineForm.post program-head.applicants.decline; SVG score bar chart); `ProgramHeadController`, `ProgramHeadActionController`; existing `AdmissionSupportWorkflowTest` (PH candidate lists, decline blocks, Tier B ack + audit), `ProgramQuotaTest` (4), `StaffNavigationTest`.
- **Steps:** 1. Map states to tests; 2. add missing Pest: denied page_state for unassigned program + cross-program 403 (SCN-VS-014); no-data dashboard variant; quota-conflict payload on quota update; decline gate (canDecline only tier B, not enrolling/enrolled) — partially covered; follow-up resolve; 3. Vitest: stage label/variant map, metric card rendering with zero denominators; 4. exploratory: chart table-alternative presence (GF-22 §8).
- **Acceptance:** SCN-VS-014/022 asserted; every row state has an owner.
- **Test mapping:** Pest Feature (primary), Vitest (cards/tabs), exploratory (chart alternatives).
- **Dependencies & order:** T1.1; T2.8/T2.11 (advised + selection state).
- **Risks & open questions:** chart data/no-data/suppressed variants per GF-22 §8 need explicit tests (tables/alternatives) — a11y task T4.x cross-link.

### T2.13 Staff QR scan + action hub — P-QR-01

- **Goal:** `camera-denied`, `invalid`, `expired/window-closed`, `no-destination/denied`, `ready` states; same QR → role/duty/scope-specific destinations (SCN-VS-003/014/022 cross-checks).
- **Scope:** `system/resources/js/Pages/Staff/ApplicantScan.svelte` (scan_result, scanned_attempt, destinations; form.post resolve), `system/resources/js/Components/kit/ApplicantScanModal.svelte` (destinations state, router.visit), `system/resources/js/lib/scan-modal.js`, orphaned `Staff/ApplicantActionHub.svelte` (G-06 — dispose: delete or wire); `Staff\ApplicantQrController`; existing `AdmissionSupportWorkflowTest` (QR permanent/deterministic/tamper-rejecting; same QR → actor menus registrar/guidance/outsider-empty).
- **Steps:** 1. Dispose G-06 (catalog note + delete-or-wire decision in future change); 2. add Pest: resolve endpoint states for each destination class + deny-no-destination; camera-denied is client-side → exploratory/Playwright with camera permissions stubbed; 3. Vitest: scan-modal destination list + preselect flow; 4. Playwright T5.7 journey (manual code entry — camera not required).
- **Acceptance:** Every P-QR-01 state has an owner; outsider QR yields empty no-leak destinations.
- **Test mapping:** Pest Feature (resolve endpoints), Vitest (modal), Playwright T5.7, exploratory (camera-denied preflight).
- **Dependencies & order:** T1.1; T1.2 (row URI drift).
- **Risks & open questions:** browser camera APIs are flaky in CI — keep camera out of automated journeys (manual code entry path).

### T2.14 Staff auth + personnel + staff home — P-AUTH-01, P-OPS-01

- **Goal:** login states (`loading`, `validation`, `denied`, `ready`), personnel lifecycle states, staff home scope/capability debug covered; session continuity (SCN-VS-016 re-login).
- **Scope:** `system/resources/js/Pages/Auth/Login.svelte` (usePage errors; form.post /login), `system/resources/js/Pages/Staff/Home.svelte` (staff; scope JSON + capability badges), `Personnel/{Index,Create,Edit}.svelte` (roles, can_full_hr, default_office, support_role, can_reset_password; grant-ta/revoke-ta), `Staff/Profile/Show.svelte`, `Staff/StaffLayout.svelte` (staff_navigation); `AuthenticatedSessionController`, `PersonnelController`, `TestAdministratorController`; existing `StaffAuthenticationTest` (login/logout/invalid + audit), `PersonnelLifecycleTest` (26), `TaCapabilityDelegationTest` (4), `StaffNavigationTest` (4), `CapabilitySyncCommandTest`.
- **Steps:** 1. Map states; 2. add missing Pest: login page_state serialization, rate-limit/denied path, re-login restores authorized nav (session continuity), personnel empty directory, deactivated-user login blocked (exists); 3. Vitest: PasswordStrengthInput (T6.7), role chips, nav active-state (T6.10); 4. Playwright T5.3 covers login→staff home journey.
- **Acceptance:** SCN-VS-002/016 states asserted; personnel surface fully covered.
- **Test mapping:** Pest Feature (primary), Vitest (components), Playwright T5.3.
- **Dependencies & order:** T1.1; auth overlap with peer doc `AuthLifecycleMissionBreakdown` — coordinate ownership (see Cross-workstream dependencies).
- **Risks & open questions:** `Auth/Login.svelte` pre-fills demo credentials (`registrar.clerk@nexiam.test`) — decide whether automated journeys rely on it or on the seeded fixture credentials from HANDOFF.

### T2.15 Setup hub + reference data — (setup surfaces; P-OPS-01 support)

- **Goal:** setup readiness hub states + every reference-data list/form state covered (empty, ready, denied, validation); OMR configuration pages listed as future-dep only.
- **Scope:** `system/resources/js/Pages/Setup/Index.svelte` (readiness, capabilities; first-fail auto-expand), `Setup/Programs.svelte`, `Setup/Rooms.svelte`, `Setup/Cycles.svelte`(+Create/Edit), `Setup/Grades.svelte`, `Setup/Aptitude.svelte`, `Setup/AptitudeScoring.svelte`, `Setup/Quotas.svelte`, `Setup/Privacy.svelte`; `SetupDashboardController` + 9 setup controllers; existing `SetupHubTest` (6), `AcademicYearSetupTest` (4), `AptitudeScoringTest` (9), `ProgramQuotaTest` (4), `CapabilityKernelTest` (8: setup readiness via kernel), `SetupRouteBindingTest` (27 routes).
- **Steps:** 1. Map hub/readiness states to tests (kernel parity exists); 2. add missing Pest: empty lists per reference surface (some surfaces always seeded — use clean-DB variant), validation on duplicate (partially exists), denied page_state per capability; 3. Vitest: readiness percent + category status mapping, first-fail expand; 4. exploratory: hub guidance copy accuracy.
- **Acceptance:** Every setup surface state has an owner; readiness hub coverage complete.
- **Test mapping:** Pest Feature (primary), Vitest (hub logic), exploratory (copy).
- **Dependencies & order:** T1.1; independent.
- **Risks & open questions:** none; OMR setup pages (`Setup/OmrConfiguration/**`) are future-dep — do not plan coverage now.

### T2.16 Nexi user-facing chrome — (assistant surfaces)

- **Goal:** dock/panel mount states per surface profile (`staff`, `applicant`, `setup`, `applicant.schedule`…), `degraded`/`inquiry_only`/`quota_exhausted`/`suspended`/`hidden`, absence on public shells (SCN-VS-024-ish / NexiFoundationSession surface catalog).
- **Scope:** `system/resources/js/Components/kit/Nexi/NexiDock.svelte`, `Components/kit/Nexi/NexiAssistPanel.svelte`, `system/resources/js/types/nexi.ts` (NexiSessionView), mounts in `Staff/StaffLayout.svelte` + `Applicant/ApplicantLayout.svelte` + `Layouts/AuthenticatedLayout.svelte`; `system/app/Nexi/Support/NexiSurfaceRegistry.php`-family (surface profiles); existing `NexiFoundationSessionTest` (6 profiles, page packs, availability resolver fail-closed, degraded dock), `nexi-session.test.ts` + `nexi-transport.test.ts` (view contract), `nexi-foundation.spec.ts` (absence on public/login shells).
- **Steps:** 1. Map each surface profile to a mount test; 2. add missing Pest: each built page pack key resolves (staff home, setup hub, applicant schedule); unregistered route → degraded dock; 3. Vitest: dock/panel render variants (hidden/quota/degraded) — extend mount matrix to the Svelte components (jsdom environment per T6.0); 4. Playwright T5.9: authenticated staff/applicant see dock, public/login do not (extends existing spec).
- **Acceptance:** Every user-facing Nexi state has an owner; provider mutations remain out of scope.
- **Test mapping:** Pest Feature (pack resolution), Vitest (component variants), Playwright T5.9 (shell parity).
- **Dependencies & order:** T1.1; T6.0 (jsdom for component mounts).
- **Risks & open questions:** catalog §5 says chrome only in E2 — keep provider/RAG behavior out; do not plan Nexi inquiry UI tasks.

---

## 5. Phase 3 — Per-surface state coverage: applicant + public surfaces

**Phase goal:** Every built applicant/public surface has per-state coverage; portal spine, reservation, and conflict states get particular depth (catalog P-APP-01..07, P-PUB-01).

### T2.17 Knowledge source lifecycle pages (staff; Nexi evidence surfaces)

- **Goal:** Knowledge index/create/show states (`ready`/`denied`, lifecycle badges active/review_required/approved/revoked/rejected/index_failed, extract/approve/publish/revoke with reason) covered as user-facing staff surfaces feeding Nexi governance.
- **Scope:** `system/resources/js/Pages/Knowledge/Index.svelte` (page_state, sources, filters, statuses, capabilities; router.get filter), `Knowledge/Create.svelte` (quarantine draft form), `Knowledge/Show.svelte` (source, version, capabilities; router.post extract/approve/publish/revoke with confirm + reason; flash.status banner); `KnowledgeSourceController`; existing `NexiKnowledgeGovernanceTest` (one-step publish for registrar admin + guidance counselor, clerk draft-only, revoke denies retrieve, fixture gates, capability-gated index page_state), `NexiPolicyInquiryRagTest` (citations/refusal). Public/other foundation surfaces: `Home.svelte` + `Foundation/Status.svelte` are already owned by `FoundationHealthTest` + `tests/browser/foundation.spec.ts` — record as owned in the T1.1 matrix, no new task.
- **Steps:** 1. Map page states to tests; 2. add missing Pest: empty sources list; index denied page_state (exists); create validation; revoke reason required; extract failure → index_failed badge; 3. Vitest: lifecycle badge variant map, revoke-reason dialog state; 4. exploratory: quarantine draft flow.
- **Acceptance:** Every Knowledge page state has an owner; lifecycle vocabulary asserted.
- **Test mapping:** Pest Feature (primary), Vitest (badges/dialog), exploratory.
- **Dependencies & order:** T1.1; independent of Phase 2 staff tasks (Nexi-adjacent).
- **Risks & open questions:** Nexi engine behavior stays out of scope; only page states are owned here.

### T3.1 Public admission apply + confirmation — P-PUB-01

- **Goal:** `loading`, `validation`, `conflict-duplicate`, `success + challenge-pending` states covered; privacy-policy dialog; duplicate-program auto-clear.
- **Scope:** `system/resources/js/Pages/Admission/Apply.svelte` (props: institution, cycle, window, programs, grade_fields, privacy_policy; form.post /apply with grades map + 3 preferences), `Admission/Confirmation.svelte` (reference_code, access_key; continue-to-portal form.post /applicant/login; localStorage save notice; keyVisible toggle); `ApplicantApplyController`; existing `FoundationHealthTest` (apply page exposes programs + open window), `AdmissionFlowTest` (submit creates attempt + hashed challenge, duplicate identity conflict, privacy policy id).
- **Steps:** 1. Map states; 2. add missing Pest: window-closed submission blocked; duplicate identity typed conflict surfaced to form; validation error payload + preserved input; confirmation flash one-time (mirror `ApplicantAccessTest`); 3. Vitest: duplicate-preference auto-clear effect, key-visible toggle, localStorage-blocked notice; 4. Playwright T5.1 journey (submit → confirmation → applicant login).
- **Acceptance:** SCN-VS-001/002/003 asserted at page contract; every P-PUB-01 state owned.
- **Test mapping:** Pest Feature (primary), Vitest (form logic), Playwright T5.1, exploratory (skeleton loading).
- **Dependencies & order:** T1.1; before T3.2 (login flows from confirmation).
- **Risks & open questions:** access key is shown once — Playwright must capture it in-test (fixture pattern) rather than rely on localStorage.

### T3.2 Applicant login — P-APP-01

- **Goal:** access-key + birthdate login states (`ready`, `validation`, `denied`) + challenge non-enumeration covered.
- **Scope:** `system/resources/js/Pages/Applicant/Login.svelte` (demo hint, key visibility toggle, form.post /applicant/login with throttle:5,1); `ApplicantPortalController::login`; existing `ApplicantAccessTest` (key+birthdate auth, key reusable, birthdate-alone fails, wrong birthdate fails, redirect, flash-once, logout, demo login), `CorrectionAndKeyLifecycleTest` (reissued key revokes old).
- **Steps:** 1. Audit vs states; 2. add missing Pest: throttled attempts (5/min) deny path; generic non-enumerating error wording; denied page_state; 3. Vitest: key-visibility toggle, demo-hint fill; 4. Playwright T5.1/T5.2 use seeded demo credentials.
- **Acceptance:** Every P-APP-01 state has an owner; no identifier-as-secret behavior asserted.
- **Test mapping:** Pest Feature (primary), Vitest (toggle), Playwright T5.1.
- **Dependencies & order:** T3.1.
- **Risks & open questions:** auth overlap with `AuthLifecycleMissionBreakdown` peer doc — coordinate test ownership (see Cross-workstream dependencies).

### T3.3 Applicant status-spine hub — P-APP-02

- **Goal:** `empty-not-yet`, `ready`, `denied` + stage status vocabulary (done/current/upcoming/action/auto) + mode-aware results (online artifact vs f2f zero scores) covered.
- **Scope:** `system/resources/js/Pages/Applicant/Portal.svelte` (identity, stages, notices, quick_links; badgeFor mapping), `Applicant/ApplicantLayout.svelte` (applicant_navigation, breadcrumbs, NexiDock, NotificationBell); `system/app/Applicant/Support/ApplicantPortalProjection.php` (stages(), notices, quick_links, canonical order); existing `ApplicantPortalInterfaceTest` (hub empty state, needs_correction action stage, MODE_ONLINE vs MODE_F2F, dismissed terminal card, guidance default auto stage, 8-stage order, nav keys, own-scope 403), `applicant-portal.test.ts` (Vitest contract: stage order, spine statuses, release-mode serialization, guidance default, nav set).
- **Steps:** 1. Map states; 2. add missing Pest: notices variants (correction_notes, results_blocked, dismissal_reason, archived) each render; quick_links authorized set; denied own-scope; 3. Vitest: badgeFor mapping incl. unknown-status fallback; 4. Playwright T5.2 journey (spine → schedule).
- **Acceptance:** Every P-APP-02 state has an owner; mode-aware results asserted both directions.
- **Test mapping:** Pest Feature (primary), Vitest (contract + component), Playwright T5.2, exploratory (skeleton).
- **Dependencies & order:** T1.1; T3.2 (login fixture).
- **Risks & open questions:** none.

### T3.4 Exam schedule + reservation states — P-APP-03 (schedule half)

- **Goal:** `pending_acceptance`, `waiting_for_reservation_open`, `calendar_open`, `reserved`, `assigned_published` headline states + full-slot disable + reserve conflict covered.
- **Scope:** `system/resources/js/Pages/Applicant/Schedule.svelte` (attempt, assignment, application, calendar_days, message, pipeline; headline map; router.post /applicant/schedule/reserve with idempotency_key), `Applicant/SchedulePipeline.svelte` (stages), `Components/applicant/ReservationSlotList.svelte` + `Components/applicant/ReservationConfirmSheet.svelte`; `ApplicantScheduleController`; existing `ExamSlotReservationFlowTest` (open reserve/close block, capacity deny, waiting/calendar/assigned states, idempotent reserve), `ApplicantAccessTest` (demo login → waiting state), `applicant-portal.test.ts` (reservation contract: slot state derivation, full-slot disable).
- **Steps:** 1. Map headline states to tests; 2. add missing Pest: assigned_published headline when assignment exists + publish; reserve on full session typed conflict; reserve replay with same key reuses assignment (exists); staff-only booking mode hides calendar for applicant; 3. Vitest: headline map, full-slot disable, confirm-sheet submit state; 4. Playwright T5.2.
- **Acceptance:** Every P-APP-03 schedule state has an owner; SCN-VS-005/007 asserted from the applicant side.
- **Test mapping:** Pest Feature (primary), Vitest (ReservationSlotList/ConfirmSheet), Playwright T5.2.
- **Dependencies & order:** T1.1; T2.4 (staff open-reservation prerequisite fixture).
- **Risks & open questions:** `booking_mode` staff_only default (asserted in `ExamSlotReservationFlowTest`) means journeys need a published + reservation-open session fixture (use `ExamSlotReservationFlowTest` helpers).

### T3.5 Applicant consultation booking — (P-CON-01 applicant side)

- **Goal:** `denied`, `not_result_ready`, `no_open_sessions`, `booking_open`, `assigned_or_reserved` headline states + FCFS reserve + idempotency covered.
- **Scope:** `system/resources/js/Pages/Applicant/Consultation.svelte` (attempt, assignment, sessions, message; router.post /applicant/consultation/reserve with idempotency_key); `ApplicantConsultationController`; existing `ConsultationSessionFlowTest` (portal not_result_ready, no_open_sessions, reserve published-only, capacity deny, idempotent reserve, own-scope).
- **Steps:** 1. Map headline states; 2. add missing Pest: assigned_or_reserved headline after reserve; booking_open with full slots disables selection; message prop variants; 3. Vitest: headline map + slot disable (share pattern with T3.4).
- **Acceptance:** Every applicant consultation state has an owner.
- **Test mapping:** Pest Feature (primary), Vitest (headline/slots), exploratory.
- **Dependencies & order:** T1.1; T2.10 (staff publish fixture); T2.8 (result-ready gate).
- **Risks & open questions:** none.

### T3.6 Applicant program choice — P-APP-05

- **Goal:** `result-not-released`, `not-advised`, `default-pending`, `selected`, `full/not-configured`, `declined`, `reconsideration`, `enrolling-locked`, `paper-finalized`, `stale/conflict` states covered.
- **Scope:** `system/resources/js/Pages/Applicant/ProgramChoice.svelte` (support prop; derived readiness/pool/analysis; acknowledgeForm/selectForm/cancelForm with idempotency_key; confirm dialogs); `ApplicantProgramChoiceController`; existing `ProgramSelectionTest` (pool advised default reserves quota, ack→transfer atomic swap, full-quota fail-closed, enrolling-block, replay idempotent, cross-payload conflict).
- **Steps:** 1. Map states to tests; 2. add missing Pest: result-not-released page_state; not-advised copy (no default); declined terminal selection card; reconsideration (after PH decline) card; paper-finalized banner; stale/conflict via expected_version if present; 3. Vitest: readiness gates (selectionExists, canStillChoose, options filter excluding selected), dialog confirm flow; 4. Playwright T5.6 (ack → see selected state).
- **Acceptance:** Every P-APP-05 state has an owner; quota conflict asserted without mutation.
- **Test mapping:** Pest Feature (primary), Vitest (derived gates), Playwright T5.6.
- **Dependencies & order:** T1.1; T2.8/T2.12 (advised default + PH decline fixtures).
- **Risks & open questions:** selection states are many — keep Pest payload assertions lean (facts + state labels) and push rendering to Vitest.

### T3.7 Applicant notifications — P-APP-04

- **Goal:** `empty-vs-unread`, `ready` + read/mark-all + own-scope + failed-item handling covered.
- **Scope:** `system/resources/js/Pages/Notifications/Index.svelte` (notifications prop; usePage auth.user.is_applicant; router.post read then router.visit action_url; read-all; unread_count badge); `NotificationController`; existing `NotificationDeliveryTest` (11: dispatch dedupe, worker channels, retry/failed_terminal, truthfulness, own-data gate, mark-read idempotent, mark-all, unread-count JSON, capability gate, acceptance emits).
- **Steps:** 1. Map states; 2. add missing Pest: empty list copy; unread badge count vs list; action_url navigation contract; 3. Vitest: NotificationBell unread polling (T6.8), item read-state rendering; 4. Playwright T5.8.
- **Acceptance:** SCN-VS-020 asserted (unread count reflects authoritative delivery; cross-applicant mutation denied).
- **Test mapping:** Pest Feature (primary), Vitest (bell), Playwright T5.8.
- **Dependencies & order:** T1.1; T2.9 (release emits notifications) for journey depth.
- **Risks & open questions:** notification list is shared staff/applicant — keep `is_applicant` branch tested both ways.

### T3.8 Applicant QR — P-APP-07

- **Goal:** `ready`, `expired-refresh`, `window-closed`, `denied` states + print affordance covered.
- **Scope:** `system/resources/js/Pages/Applicant/MyQr.svelte` (attempt_id, qr; canvas render; print button); `ApplicantQrController`; existing `AdmissionSupportWorkflowTest` (QR permanent/deterministic/tamper-rejecting, same-QR actor menus — staff side).
- **Steps:** 1. Map states; 2. add missing Pest: denied without capability; qr payload shape (deterministic per attempt); 3. Vitest: canvas render failure fallback logging; 4. exploratory: print dialog behavior (printing itself future-dep).
- **Acceptance:** Every P-APP-07 state has an owner.
- **Test mapping:** Pest Feature, Vitest, exploratory (print).
- **Dependencies & order:** T1.1.
- **Risks & open questions:** QR rendering depends on `qrcode` lib canvas — keep automated assertions on payload, not pixel output.

### T3.9 Applicant admission support + access history — P-APP-06

- **Goal:** `empty`, `unread`, `ready`, `denied` states + own-scope threads + access-history audit list covered.
- **Scope:** `system/resources/js/Pages/Applicant/AdmissionSupport.svelte` (threads, access_history; read-only view), route `/applicant/admission-support` (`RECORD_THREAD_PARTICIPATE`); existing `AdmissionSupportWorkflowTest` (threads, QR menus), `NexiApplicantInquiryTest` (cross-applicant snapshot denied).
- **Steps:** 1. Map states; 2. add missing Pest: empty threads + empty access_history copy; denied for non-granted applicant; unread badge from thread states; own-scope isolation (another applicant's threads 403); 3. Vitest: event-label mapping, open/closed badges.
- **Acceptance:** Every P-APP-06 state has an owner.
- **Test mapping:** Pest Feature (primary), Vitest (labels).
- **Dependencies & order:** T1.1; T2.8 (thread creation fixture).
- **Risks & open questions:** none.

### T3.10 My Application + applicant correction — (P-APP-02 support, correction journey)

- **Goal:** read-only own application, correction resubmit/amend flows, `validation` + preserved input + freeze-after-accept covered.
- **Scope:** `system/resources/js/Pages/Applicant/MyApplication.svelte` (application; status labels), `Applicant/Correction.svelte` (mode amend/resubmit; form.post with expected_version; shared `Admission/IntakeCorrectionFields.svelte`), `ApplicantPortalController::correctionForm/resubmitCorrection`; existing `CorrectionAndKeyLifecycleTest` (request→resubmit→pending v3, quiet amend, freeze after accept, own-scope 403 on GET+POST), `ApplicantPortalInterfaceTest` (my application read-only own-scope).
- **Steps:** 1. Map states; 2. add missing Pest: validation errors preserved (identity fields), mode distinction amend vs resubmit, correction page redirect when frozen (exists); 3. Vitest: IntakeCorrectionFields shared-field rendering for both hosts, status label variants; 4. dispose G-05 (`Applicant/Setup.svelte`): decide remove/redirect in future change; add Pest asserting GET `/applicant/setup` behavior after disposal.
- **Acceptance:** Correction journey fully owned; G-05 disposed.
- **Test mapping:** Pest Feature (primary), Vitest (shared fields), exploratory.
- **Dependencies & order:** T1.1; T3.1 (applicant login fixture).
- **Risks & open questions:** G-05 — the orphaned Setup page must not silently become a test target; record decision (delete page + route alias, or repoint to portal login).

---

## 6. Phase 4 — Keyboard / focus / dialog + accessibility spot audits

**Phase goal:** GF-22 §5.4/§6/§7 accessibility obligations are checked on every built surface: automated axe-class scan (R5), keyboard-only walks of complete processes (WCAG 2.2 §5.2.3), dialog focus contract, validation focus management, and toast-not-sole-signal audit. Automated findings are findings only — never "WCAG AA conformant" claims (W3C WAI position, R5).

### T4.1 Wire an axe-class automated scan into the browser suite

- **Goal:** Every journey spec runs an automated a11y scan; results reported as findings, stored per spec, and surfaced in CI output.
- **Scope (files):** `system/tests/browser/**` + `system/playwright.config.ts`; dependency add (decide: `@axe-core/playwright` or `puppeteer`-equivalent — verify current `@playwright/test ^1.62.1` compatibility at change time); new shared fixture/helper `system/tests/browser/support/a11y.ts` (proposed).
- **Steps:** 1. Add axe dependency; 2. create a reusable scan helper (inject axe, run on `page`, filter to `wcag2a`/`wcag2aa` tags, attach violations as structured findings with target selectors + impact); 3. call it at the end of each journey spec; 4. keep a known-issues allowlist file (with rationale + owner) so regressions are visible; 5. never gate builds on zero violations without human review (R5).
- **Acceptance:** Each journey spec emits a scan finding set; allowlist exists; README-level note "automated findings only, not conformance" recorded in the future change design.
- **Test mapping:** Playwright journey integration (all T5.* specs).
- **Dependencies & order:** T5.0 harness first; runs alongside T5.1..T5.9; feeds T7.1 quarterly audits.
- **Risks & open questions:** axe on Svelte/Inertia needs `page` post-navigation stable state; Inertia page transitions (SPA) require re-scan per route visit, not just initial load — helper must scan after each navigation the spec performs.

### T4.2 Keyboard-only walk of every complete process

- **Goal:** Every complete process (WCAG 2.2 conformance §5.2.3: login → hub → each journey) is operable by keyboard alone; focus order sane; no keyboard traps.
- **Scope (files):** processes: staff login → staff home → admissions → detail → accept (T5.3); exam create → assign → publish → monitoring (T5.4); grading open → scores → finalize (T5.5); applicant login → spine → schedule → reserve (T5.2); consultation decision → program choice (T5.6); QR scan manual entry → destinations (T5.7); notifications read (T5.8). Surfaces: all Pages listed in §2.4.
- **Steps:** 1. Script each process as a keyboard-only Playwright journey (Tab/Shift+Tab/Enter/Escape/Space only — no mouse); 2. assert focus lands on the primary action after each navigation; 3. assert no focus loss to `body` on dialog open/close (see T4.3); 4. log every step for the WCAG-EM report; 5. record findings per process.
- **Acceptance:** One keyboard-only walk per process passes or yields filed findings; findings triaged into the allowlist or fixed before the quarterly report (T7.1).
- **Test mapping:** Playwright (keyboard-only variants of T5.* journeys) + exploratory-manual (screen-reader nuance).
- **Dependencies & order:** T4.1 infrastructure; T5.* journeys exist as mouse-driven baselines to mirror.
- **Risks & open questions:** bits-ui dialog primitives claim focus management — verify Escape-mid-commit and return-focus-to-trigger (GF-22 §5.4) empirically; Svelte 5 transition animations can break `expect(...).toBeFocused()` timing — use `toBeVisible` first.

### T4.3 Dialog focus-management audit (all confirm dialogs)

- **Goal:** Every risk-confirmation Dialog (catalog §4 list) traps focus, closes on Escape only when not mid-commit, returns focus to trigger, and never relies on color alone.
- **Scope (files):** dialogs on `Admission/Index.svelte` + `Show.svelte` (accept/dismiss/archive/reopen/reconsider/reissue/correct/amend), `Examination/Show.svelte` (publish/unpublish/cancel/reassign/remove, reason dialogs), `Consultation/*` (publish/complete/cancel/reassign, reason dialogs), `Assessment/GradingSession.svelte` (finalize), `Assessment/GradingScoreInput.svelte` (clear scores), `Applicant/ProgramChoice.svelte` (ack/select/transfer/cancel), `Admission/ApplicantAdmissionSupport.svelte` (decision dialog), `ProgramHead/ApplicantShow.svelte` (decline), `Registrar/EnrollmentEligibility.svelte` (stamp confirm), `Personnel/Index.svelte` (deactivate/activate/grant-ta/revoke-ta), `Setup/*` (activate/deactivate, retire confirms), `Components/ui/dialog/*` primitives, `Components/kit/ActionDropdown.svelte`.
- **Steps:** 1. Inventory every Dialog usage (file + trigger + action) into a checklist; 2. keyboard-only test each: open → focus trapped → Escape → focus returns to trigger; 3. assert mid-commit Escape is ignored (submit in flight); 4. assert confirm button is reachable and labeled with the business effect (truthful wording per catalog §4); 5. fix or file findings.
- **Acceptance:** Checklist covers every dialog; each passes or has a filed finding; `window.prompt` usages found in `Admission/Index.svelte` are replaced by Dialog or filed as product change (a11y: native prompt is not focus-controllable).
- **Test mapping:** Playwright (keyboard-focused) + exploratory-manual (screen reader).
- **Dependencies & order:** T4.2; before T7.1 first quarterly audit.
- **Risks & open questions:** `window.prompt` in `Admission/Index.svelte` (reasons) — native prompt violates the dialog contract; product change needed (replace with Dialog) — flag to founder.

### T4.4 Form validation focus management + preserved input

- **Goal:** `validation_error` state per GF-22 §7: field + summary errors, focus to first invalid field, preserved safe input after validation/network failure.
- **Scope (files):** forms: `Admission/Apply.svelte`, `Applicant/Correction.svelte`, `Examination/Create.svelte`/`Examination/Edit.svelte`, `Consultation/ConsultationSessionCreate.svelte`/`Consultation/ConsultationSessionEdit.svelte`, `Assessment/GradingScoreInput.svelte`, `Setup/*` forms, `Staff/Personnel/Create.svelte`/`Staff/Personnel/Edit.svelte`, `Auth/Login.svelte`, `Applicant/Login.svelte`.
- **Steps:** 1. For each form, provoke server validation (Pest can pre-seed invalid submit; Playwright fills invalid data); 2. assert error summary appears, input values preserved, focus moves to first invalid field; 3. assert no data loss on Inertia validation redirect; 4. file findings for any form that loses input (GF-22 §2 legacy-defect list: "forms that lose input after validation/network failure").
- **Acceptance:** Every listed form passes or has a filed finding; regression tests added for fixed forms.
- **Test mapping:** Pest Feature (validation payloads — mostly exist) + Playwright (focus/preservation) + exploratory.
- **Dependencies & order:** T4.2; T5.* journeys provide the navigation scaffolding.
- **Risks & open questions:** Inertia validation errors arrive as `errors.*` page props — component-level focus logic lives in `Components/ui/input` wrappers; audit the wrapper, not each page.

### T4.5 Toast / banner not-sole-signal audit

- **Goal:** Toasts never the sole error/permission signal (REQ-VS-028/030); no toast claims mail/release/import/provider success without authoritative evidence (GF-22 §6); pending toasts use "Queued/Accepted for processing" wording.
- **Scope (files):** `Components/kit/ToastManager.svelte`, `lib/toast.js`, flash usage in controllers (`->with('status', ...)` across `Admission*Controller`, `ReleaseController`, `ExamSessionController`, `ConsultationSessionController`, `GradingController`, `Setup*Controller`, `NotificationController`), `Admission/ToastBridge.svelte`, `Components/kit/ModeBanner.svelte` usage on `Assessment/Grading*.svelte`.
- **Steps:** 1. Inventory every flash/toast message string vs its command's real side effect (release vs queued, delivered vs pending); 2. assert each commit surface also renders an in-page authoritative state (not toast-only); 3. grep for banned claims ("Sent"/"Released" without evidence) — file findings; 4. assert `success/pending` wording on async ops (`ReleaseController` prepare/release).
- **Acceptance:** No banned-claim toast in the codebase; every error/permission event has an in-page signal; findings fixed or filed.
- **Test mapping:** Pest Feature (flash payload wording — add asserts on `->with('status')` strings) + Playwright (toast + in-page presence) + exploratory.
- **Dependencies & order:** T4.3; T2.9 (release surfaces land first so wording can be verified live).
- **Risks & open questions:** wording contracts live in ~10 controllers — a shared assertion helper (Pest) keeps the audit durable.

### T4.6 WCAG-EM-style manual walk (full product)

- **Goal:** Periodic human evaluation of the entire product (small product → skip sampling per WCAG-EM 2 §3.1) covering all SCs on representative views + complete processes; report per WCAG-EM structure (scope, sample, results, limits).
- **Scope (files):** all Pages in §2.4 except OMR/printing; report template (proposed) `docs/planning/wcag-em-report-template.md` inside the future change; evidence folder convention `docs/greenfield-foundation/evidence/a11y-*`.
- **Steps:** 1. Fix scope (A/AA target), actor set, and view list; 2. automated scan pass (T4.1) + keyboard pass (T4.2) + screen-reader spot pass (T4.7) + manual visual pass (contrast, target size, focus visibility — WCAG 2.2 new SCs 2.4.11/2.5.8); 3. evaluate every complete process; 4. produce WCAG-EM-structured report with per-SC results + evidence; 5. never claim conformance from tool output.
- **Acceptance:** First full report produced in the future change; findings triaged; follow-up tasks filed.
- **Test mapping:** exploratory-manual (primary) with automated support; findings become Pest/Vitest/Playwright regression tasks.
- **Dependencies & order:** T4.1–T4.5 outputs feed it; first run scheduled as the quarterly audit (T7.1).
- **Risks & open questions:** needs a human reviewer (or agent review with recorded limits); W3C: knowledgeable human evaluation required — document reviewer + limits honestly.

### T4.7 Screen-reader spot check on spine hub + score surfaces

- **Goal:** Status spine (Applicant/Portal.svelte), reservation sheets, grading score input, and released-result surfaces announce state changes (live regions), correct headings/landmarks, and non-color-dependent status.
- **Scope (files):** `Applicant/Portal.svelte`, `Applicant/Schedule.svelte` + `Components/applicant/ReservationConfirmSheet.svelte`, `Assessment/GradingScoreInput.svelte`, `Admission/ApplicantAdmissionSupport.svelte` (score profile region), `Admission/E2PageState.svelte` (debug — must not be announced), toasts (T4.5).
- **Steps:** 1. With a screen reader (or Playwright aria-snapshot), walk each surface; 2. assert headings hierarchy, status conveyed textually (not color alone), live-region announcements on reserve/finalize/toast; 3. file findings.
- **Acceptance:** Each listed surface passes or has filed findings.
- **Test mapping:** exploratory-manual (primary) + Playwright `ariaSnapshot` where available.
- **Dependencies & order:** T4.2/T4.3; feeds T7.1.
- **Risks & open questions:** screen-reader pairing availability in the harness; Playwright `ariaSnapshot` is a reasonable proxy for structure, not for actual SR announcements — record the limit.

### T4.8 Contrast / target-size / focus-visibility spot checks on kit primitives

- **Goal:** WCAG 2.2 SC 1.4.3 (contrast), 2.5.8 (target size ≥24×24 or spaced), 2.4.7/2.4.11 (focus visible, not obscured) spot-checked on shared kit components so every page inherits compliance.
- **Scope (files):** `Components/ui/button`, `input`, `select`, `checkbox`, `dialog`, `badge`, `table`, `tabs`, `toggle-group`, `switch`, `popover`, `command`, `card`, `textarea`, `label`, `separator`, `file-upload`; `Components/kit/*` (FilterToolbar, ActiveFilterChips, BulkActionBar, SwitchableListView, StatCard, KpiCard, NotificationBell, ActionDropdown, InfoPopover, PasswordStrengthInput, SimplePagination).
- **Steps:** 1. Sample each primitive at default + dark theme on the UiKit gallery (`UiKit/Gallery.svelte`, `/ui-kit`); 2. check contrast pairs against Tailwind 4 tokens; 3. check interactive target sizes; 4. check focus ring visibility (and not obscured by sticky headers — 2.4.11); 5. file findings per primitive.
- **Acceptance:** Every kit primitive reviewed; findings filed; theme-toggle (dark mode in StaffLayout) audited for contrast parity.
- **Test mapping:** exploratory-manual + axe pass on `/ui-kit` (T4.1) + optional Playwright pixel checks.
- **Dependencies & order:** T4.1; parallel to T4.6.
- **Risks & open questions:** dark-mode contrast is a common failure — make it explicit in the walk.

---

## 7. Phase 5 — Playwright journey specs

**Phase goal:** One spec per high-value journey (R1), role-first locators (R3), deterministic seeded fixtures, shared auth state; the suite stays deliberately small (9 journeys + harness). These specs are the GF-11 §10 exit-condition browser evidence (repeatable journeys + denied/recovery variants + traceability).

### T5.0 Shared journey harness + config hardening

- **Goal:** Journeys run against Compose (`:18000`) with deterministic seeds, isolated browser contexts, shared signed-in state (login once per role), role-first locator conventions, and CI-friendly reporting/traces.
- **Scope (files):** `system/playwright.config.ts` (add firefox project for journey specs per R2; `reporter: 'html'` on CI; keep `trace: 'on-first-retry'`, CI `retries: 2`); new `system/tests/browser/support/` (proposed): `seed.ts` (reset + seed via `artisan foundation:reset` + fixture seeders with recorded credentials), `auth.setup.ts` (storage-state per role: registrar.clerk/admin, guidance.counselor, test administrator, program.head.bsn, applicant via access key), `locators.ts` (role-first conventions + allowed `getByTestId` exceptions); consult `system/tests/Support/E2Helpers.php` + `AdmissionSupportHelpers.php` seeders for the exact fixture credentials and reset commands (also recorded in the active OpenSpec `HANDOFF.md`).
- **Steps:** 1. Extract seeded credential + reset contract into the support file; 2. add role login setup projects (storageState); 3. add firefox project; 4. switch CI reporter; 5. document locator conventions in the change design (R3); 6. keep `nexi-foundation.spec.ts`/`foundation.spec.ts` untouched (they're shell-parity + health checks).
- **Acceptance:** `npx playwright test` runs chromium + firefox journeys against a fresh seeded Compose app; no spec repeats login; traces available on CI retry.
- **Test mapping:** Playwright (harness itself).
- **Dependencies & order:** FIRST in Phase 5; requires seeded fixture contract (coordinate with seed workstream).
- **Risks & open questions:** Compose app must be reset per run (deterministic seed SCN-VS-016) — decide reset-in-webServer vs CI job step; `PLAYWRIGHT_START_SERVER` opt-in local mode must not break `:18000` default.

### T5.1 J-PUB-01 — Public submit → confirmation → applicant login (SCN-VS-001/003)

- **Goal:** The public intake journey end-to-end: fill Apply, submit, see confirmation + one-time access key, continue to applicant login, authenticate.
- **Steps:** 1. Guest context → `/apply` (window open, programs listed); 2. fill form incl. grades + 3 preferences; 3. submit → confirmation shows reference_code + access_key; 4. click continue → `/applicant/login` prefilled; 5. submit key + birthdate → portal hub; 6. assert audit/reference on `/staff` (registrar context) or via API.
- **Acceptance:** Attempt visible in registrar queue with correct identity; duplicate-identity resubmission shows typed conflict (SCN-VS-002); validation errors preserve input (T4.4 cross-check).
- **Test mapping:** Playwright journey (role-first locators; `getByLabel` on form fields).
- **Dependencies & order:** T5.0; T3.1 coverage underpins.
- **Risks & open questions:** access key one-time display — capture from DOM within the same test (no localStorage reliance).

### T5.2 J-APP-01 — Applicant status spine → schedule reservation (SCN-VS-005/020)

- **Goal:** Login as a seeded accepted applicant (reservation open): hub spine renders canonical stages; schedule shows `calendar_open`; reserve a slot; confirm sheet; hub stage flips to reserved/assigned; full-slot conflict path denied.
- **Steps:** 1. Applicant login (demo key fixture); 2. `/applicant/portal` — assert 8-stage spine + current stage; 3. `/applicant/schedule` — calendar open, slots; 4. reserve via confirm sheet (idempotency key); 5. return to hub — schedule stage updated; 6. negative variant: staff-only session hides calendar; 7. negative variant: full session disables slots.
- **Acceptance:** Reserve commits once (replay does not duplicate — SCN-VS-007); conflict states shown truthfully.
- **Test mapping:** Playwright journey.
- **Dependencies & order:** T5.0; T2.4/T3.4 fixtures.
- **Risks & open questions:** needs a published + reservation-open session in the seed — extend `JourneyFixtureSeeder` or reuse `ExamSlotReservationFlowTest` fixture logic in the seeding helper.

### T5.3 J-REG-01 — Staff login → intake queue → detail → correct / accept (SCN-VS-004/013)

- **Goal:** Registrar journey: login, filter queue, open attempt detail, request correction, applicant resubmits, accept with dialog + reason; denied variant for a non-registrar role.
- **Steps:** 1. Registrar login (storageState); 2. `/admissions` — filter by status, search reference; 3. open detail — payload + actions; 4. request-correction (dialog + reason) → status needs_correction; 5. switch applicant context → correction form → resubmit; 6. back to registrar → accept via risk dialog; 7. negative variant: guidance counselor without accept capability sees no accept action / denied (no leak).
- **Acceptance:** Status transitions visible in UI each step; audit trail exists (assert via registrar-visible evidence or API); denied path leaks nothing.
- **Test mapping:** Playwright journey.
- **Dependencies & order:** T5.0; T2.1/T2.2 underpinning; auth overlap with peer doc (coordinate).
- **Risks & open questions:** role-switching within one spec requires two browser contexts (registrar + applicant) — keep contexts separate, share state via API/DB assertions rather than cross-context cookies.

### T5.4 J-EXM-01 — Create → assign → publish → monitor attendance/submission (SCN-VS-005/006/007/017)

- **Goal:** Test Administrator journey: create session, assign accepted applicants, publish (gate checks), open reservation, applicant reserves, proctor (duty-assigned) records attendance + submission; close; grading auto-opens.
- **Steps:** 1. TA login → `/exams/sessions` → create (form validation path) → draft; 2. detail: assign applicants; publish blocked until gates → satisfy → publish; 3. open-reservation; 4. applicant reserves (second context); 5. assign proctor duty (or seed) → proctor context → monitoring page → attendance + submission for roster; 6. close → grading appears in `/grading` index.
- **Acceptance:** Publish gates enforce (publish_block_reason visible); attendance/submission idempotent replay (SCN-VS-007); close opens grading exactly once (SCN-VS-017); proctor sees only duty session (SCN-VS-006).
- **Test mapping:** Playwright journey.
- **Dependencies & order:** T5.0; T2.3–T2.5 underpinning.
- **Risks & open questions:** longest journey — split into two specs if flaky (planning/conduct), but keep the close→grading handoff asserted at least once per run.

### T5.5 J-GRD-01 — Grading open → manual scores → finalize → release → applicant results (SCN-VS-008/010/012/013/019)

- **Goal:** Guidance journey with provider degraded: open grading, enter manual scores per domain, finalize (incomplete-block path first), release with mode, applicant sees mode-aware results; unrelease path (SCN-VS-013).
- **Steps:** 1. Guidance login; 2. `/grading` — open session (degraded banner visible); 3. score input page — save scores (idempotency key), conversion rows; 4. finalize → incomplete-block error (missing domain) → complete → finalize; 5. prepare result document → release (f2f or online mode chosen); 6. applicant context: MODE_ONLINE → results stage with score artifact; MODE_F2F → zero scores, claim-at-consultation; 7. unrelease with reason → applicant results re-blocked (history preserved).
- **Acceptance:** SCN-VS-008 (manual completes w/o inference), SCN-VS-010 (incomplete block atomic), SCN-VS-012 (release+notify once), SCN-VS-013 (reversal preserves history), SCN-VS-019 (preview ≠ publication) — all exercised in UI.
- **Test mapping:** Playwright journey (extendable by T2.9 release UI once built).
- **Dependencies & order:** T5.0; T2.6–T2.9 underpinning; release UI build (G-01) is a dependency for the release half — until then, drive release via API and assert applicant-visible results.
- **Risks & open questions:** document generation async (accepted_for_processing) — spec must await actual READY artifact (poll API) before asserting applicant view; keep wording assertions truthful.

### T5.6 J-CON-01 — Consultation decision → program choice → enrollment eligibility (SCN-VS-011/022)

- **Goal:** Guidance records decision (approve) + advised default pool; applicant acknowledges/selects; Program Head sees candidate; Registrar verifies eligibility card and stamps.
- **Steps:** 1. Guidance: search applicant from `/consultation`, open workspace, record decision (dialog + rationale); set advised pool (default BSN); 2. applicant: program-choice page — advised default with ack; acknowledge → selected; 3. Program Head context: dashboard shows candidate; 4. Registrar: enrollment-eligibility search → card shows all facts + eligible; stamp enrolling → enrolled; 5. negative: deferred decision → not advised on card.
- **Acceptance:** SCN-VS-011 (human owns decision), SCN-VS-014/022 (PH scope: another program denied), card facts match released+advised state.
- **Test mapping:** Playwright journey.
- **Dependencies & order:** T5.0; T2.8/T2.11/T2.12/T3.6 underpinning.
- **Risks & open questions:** four roles in one journey — use separate storageStates + API/DB verification between context switches.

### T5.7 J-QR-01 — QR scan / manual code → role-specific destinations (SCN-VS-014/022)

- **Goal:** Staff scans (or enters) an applicant QR; destinations vary by actor; outsider gets empty no-leak.
- **Steps:** 1. Registrar: `/staff/scan`, manual code entry → destinations include enrollment eligibility; 2. navigate destination → eligible card; 3. Guidance: same code → admission-support/consultation destinations; 4. outsider (no capability): empty destinations, no leak.
- **Acceptance:** Same QR yields role/duty/scope-specific menus (asserted server-side in Pest; UI journey asserts the rendered destination set).
- **Test mapping:** Playwright journey (manual entry path; camera out of scope).
- **Dependencies & order:** T5.0; T2.13 underpinning.
- **Risks & open questions:** camera-denied preflight is exploratory-only; keep the spec to manual code entry for CI stability.

### T5.8 J-NTF-01 — Notification read / unread (SCN-VS-020)

- **Goal:** Applicant sees notification from release (or proctor duty), unread badge reflects authoritative count, mark read navigates to action URL; cross-applicant isolation.
- **Steps:** 1. Seed a delivered notification (release emits in-app); 2. applicant: bell badge count + list; 3. mark read → count decrements, action_url visit; 4. negative: second applicant's list empty/denied (no cross-read).
- **Acceptance:** SCN-VS-020 asserted in UI (counts truthful, own-data only).
- **Test mapping:** Playwright journey.
- **Dependencies & order:** T5.0; T2.9/T3.7 underpinning.
- **Risks & open questions:** none.

### T5.9 J-NEX-01 — Nexi dock shell parity

- **Goal:** Extend `nexi-foundation.spec.ts` pattern: authenticated staff/applicant shells mount the dock; public/login shells do not; dock opens the assist panel; degraded/hidden variants render per surface profile.
- **Steps:** 1. staff context → dock visible → open panel → disclosure text; 2. applicant context → dock visible; 3. public `/` + `/login` + `/applicant/login` → absent (already covered — keep); 4. hidden-profile surface (if any built) → absent.
- **Acceptance:** Shell parity across all four shell classes; no provider mutation exercised.
- **Test mapping:** Playwright journey.
- **Dependencies & order:** T5.0; T2.16 underpinning.
- **Risks & open questions:** panel content depends on `nexi.session` prop — assert chrome only (disclosure/availability), never RAG answers.

### T5.10 CI hardening + failure runbook

- **Goal:** CI failures are diagnosable (traces reachable) and drive push-down fixes (R8).
- **Scope (files):** `system/playwright.config.ts` (html reporter on CI, artifact retention), CI workflow (wherever browser tests run), `docs/planning/e2e-failure-runbook.md` (proposed, inside future change).
- **Steps:** 1. Reporter/artifact config; 2. write the runbook: open trace viewer (or `trace.playwright.dev` for private applicant-data traces) → identify failing action → reproduce locally → if no lower-level test fails, add the Pest/Vitest test (Fowler rule 1) → rerun; 3. record the runbook in the change.
- **Acceptance:** CI produces HTML report + traces on retry; runbook committed.
- **Test mapping:** none (process task) — outcome is faster diagnosis.
- **Dependencies & order:** after T5.1..T5.9 land.
- **Risks & open questions:** applicant-data traces are sensitive — default to in-browser processing (`trace.playwright.dev`) or private artifact storage.

---

## 8. Phase 6 — Vitest component gaps

**Phase goal:** Component-level interaction logic is covered at the Vitest layer (pyramid: behavior pushed down from browser specs); fills G-08 (no component mounts today — `vitest.config.ts` runs `environment: 'node'`, `@testing-library/svelte` installed but unused).

### T6.0 Enable jsdom component test environment

- **Goal:** Component-mount tests can run; existing pure-contract suites stay fast.
- **Scope (files):** `system/vitest.config.ts` (add `environmentMatchGlobs` or per-file `// @vitest-environment jsdom` for component suites; keep `node` default for contract suites), `system/tests/frontend/setup.ts` (proposed: testing-library svelte cleanup + matchers).
- **Steps:** 1. Add jsdom environment for `tests/frontend/components/**` (proposed dir); 2. add setup file; 3. smoke-mount one kit component; 4. keep `npm run test` green.
- **Acceptance:** A component suite renders Svelte 5 runes components under jsdom; contract suites unchanged.
- **Test mapping:** Vitest (harness).
- **Dependencies & order:** FIRST in Phase 6.
- **Risks & open questions:** Svelte 5 + jsdom needs `svelte.config` test plugin config (verify `@sveltejs/vite-plugin-svelte` test option in `vite.config.ts`/`vitest.config.ts` at change time).

### T6.1 Status-spine badge mapping (Applicant/Portal.svelte)

- **Goal:** `badgeFor(status)` mapping for done/current/action/auto/next + unknown fallback covered.
- **Scope:** `system/resources/js/Pages/Applicant/Portal.svelte` (badgeFor), `Components/ui/badge`.
- **Steps:** 1. Extract or mount; 2. assert label/variant per status incl. fallback; 3. assert unknown status does not crash.
- **Acceptance:** Mapping matrix tested.
- **Test mapping:** Vitest component.
- **Dependencies & order:** T6.0.
- **Risks & open questions:** none.

### T6.2 Reservation slot-state logic (ReservationSlotList / ReservationConfirmSheet)

- **Goal:** Slot state derivation (remaining/capacity), full-slot disable, confirm-sheet submit/reserving flags covered at component level.
- **Scope:** `system/resources/js/Components/applicant/ReservationSlotList.svelte`, `Components/applicant/ReservationConfirmSheet.svelte`; contract already shape-tested in `applicant-portal.test.ts` — move/augment to real component mounts.
- **Steps:** 1. Mount with slot fixtures; 2. assert full slots disabled + copy; 3. assert sheet confirm flow + busy state; 4. keep the contract suite in sync (no duplication drift).
- **Acceptance:** Component behavior covered; contract suite still passes.
- **Test mapping:** Vitest component.
- **Dependencies & order:** T6.0; T3.4.
- **Risks & open questions:** mount requires Inertia form context — stub `useForm`-adjacent props (sheets take plain props).

### T6.3 Schedule headline map + full-slot disable

- **Goal:** `page_state` → headline map and slot availability rendering covered.
- **Scope:** `system/resources/js/Pages/Applicant/Schedule.svelte` (headline map), shared with `Applicant/Consultation.svelte` (T3.5).
- **Steps:** 1. Extract headline mapping to a testable module or mount with prop variants; 2. assert each of the 5 schedule + 4 consultation headlines; 3. assert full slots disable.
- **Acceptance:** Every headline string tested; no typo regressions.
- **Test mapping:** Vitest component.
- **Dependencies & order:** T6.0.
- **Risks & open questions:** none.

### T6.4 ProgramChoice readiness gates

- **Goal:** Derived gates (`selectionExists`, `canStillChoose`, options filter excluding selected, analysis presence) covered.
- **Scope:** `system/resources/js/Pages/Applicant/ProgramChoice.svelte` (readiness/pool/analysis derivations).
- **Steps:** 1. Mount with support fixtures (pending_acknowledgement, active, enrolling, enrolled, declined); 2. assert button/form visibility per state; 3. assert options exclude selected code.
- **Acceptance:** Gate matrix tested for all P-APP-05 states.
- **Test mapping:** Vitest component.
- **Dependencies & order:** T6.0; T3.6.
- **Risks & open questions:** none.

### T6.5 GradingScoreInput conversion computation + frozen output

- **Goal:** Conversion-table row computation, frozen converted output on record, finalized lock, saving/clearing flags covered.
- **Scope:** `system/resources/js/Pages/Assessment/GradingScoreInput.svelte` (conversion_rows, frozen output, finalized gate).
- **Steps:** 1. Mount with conversion fixtures; 2. assert row lookup + out-of-table null; 3. assert frozen snapshot renders when recorded; 4. assert finalized disables edits; 5. assert save/clear busy flags.
- **Acceptance:** All P-GRD-01 input states covered at component level.
- **Test mapping:** Vitest component.
- **Dependencies & order:** T6.0; T2.6.
- **Risks & open questions:** computation logic may live inline — extract pure helper if needed for testability (small refactor, no behavior change).

### T6.6 E2PageState renders all 9 catalog states

- **Goal:** The shared debug/state component renders every catalog vocabulary value with correct copy (and stays out of a11y announcements per T4.7).
- **Scope:** `system/resources/js/Pages/Admission/E2PageState.svelte`.
- **Steps:** 1. Mount with each of the 9 states; 2. assert rendered text; 3. assert `data-testid=page-state`.
- **Acceptance:** 9/9 states render.
- **Test mapping:** Vitest component.
- **Dependencies & order:** T6.0.
- **Risks & open questions:** component is presentational — no logic risk.

### T6.7 PasswordStrengthInput behavior

- **Goal:** Strength meter states (weak/medium/strong), password confirmation mismatch hint covered.
- **Scope:** `system/resources/js/Components/kit/PasswordStrengthInput.svelte`, used by `Staff/Personnel/Create.svelte` + `Applicant/Setup.svelte`.
- **Steps:** 1. Mount with password fixtures; 2. assert strength classification; 3. assert a11y labels/aria on the meter.
- **Acceptance:** Meter + labels tested.
- **Test mapping:** Vitest component.
- **Dependencies & order:** T6.0; T2.14.
- **Risks & open questions:** none.

### T6.8 NotificationBell unread badge + polling

- **Goal:** Unread count fetch/render, poll interval, and click-to-list behavior covered.
- **Scope:** `system/resources/js/Components/kit/NotificationBell.svelte`, `NotificationController::unreadCount` (route `/notifications/unread-count`).
- **Steps:** 1. Mount with unread-count fixture; 2. assert badge; 3. assert poll triggers (fake timers); 4. assert zero-count hidden.
- **Acceptance:** Bell behavior covered for staff + applicant contexts.
- **Test mapping:** Vitest component.
- **Dependencies & order:** T6.0; T3.7.
- **Risks & open questions:** polling via router — mock Inertia router in the test.

### T6.9 Filter / URL-state logic (auto-filter, chips, view mode)

- **Goal:** Debounced URL-backed filters, active-filter chips, and view-mode toggle state covered.
- **Scope:** `system/resources/js/lib/auto-filter.js`, `Components/kit/FilterToolbar.svelte`, `Components/kit/ActiveFilterChips.svelte`, `Components/kit/ViewModeToggle.svelte`, `Components/kit/SwitchableListView.svelte`; used by `Admission/Index.svelte`, `Examination/Index.svelte`, `Consultation/ConsultationSessions.svelte`, `Knowledge/Index.svelte`, `Personnel/Index.svelte`.
- **Steps:** 1. Test auto-filter debounce + query-string sync; 2. mount chips + view toggle; 3. assert chip removal clears filter; 4. assert view mode persists via URL (if implemented).
- **Acceptance:** Filter state logic covered once for the shared kit (all pages inherit).
- **Test mapping:** Vitest component + unit (auto-filter).
- **Dependencies & order:** T6.0.
- **Risks & open questions:** auto-filter may couple to Inertia router — mock at boundary.

### T6.10 Shell navigation + breadcrumbs (StaffLayout / ApplicantLayout)

- **Goal:** Server-authorized nav rendering, active section highlighting (match modes from `StaffNavigationTest`), breadcrumb rendering, logout action covered.
- **Scope:** `system/resources/js/Pages/Staff/StaffLayout.svelte` (staff_navigation), `Applicant/ApplicantLayout.svelte` (applicant_navigation), `lib` nav helpers if any; contract shape already in `applicant-portal.test.ts` (nav keys).
- **Steps:** 1. Mount layouts with nav fixtures; 2. assert section links + active state; 3. assert breadcrumb trail renders from props; 4. assert logout posts; 5. keep contract suite in sync.
- **Acceptance:** Nav/breadcrumb rendering covered for both shells.
- **Test mapping:** Vitest component.
- **Dependencies & order:** T6.0; T2.14/T3.3.
- **Risks & open questions:** layouts mount NexiDock/NotificationBell — stub those children to keep tests hermetic.

### T6.11 Extend applicant-portal contract suite (conflict + release-mode edges)

- **Goal:** Contract suite covers remaining spine/reservation edges: stale/conflict notices, MODE_F2F `book_consultation` action, guidance-default ack action, quick_links authorization.
- **Scope:** `system/tests/frontend/applicant-portal.test.ts` (existing describes: stage order, spine statuses, release modes, guidance default, reservation contract, nav contract).
- **Steps:** 1. Add cases for notices variants (correction_notes, results_blocked, dismissed, archived); 2. add quick_links authorized set; 3. add conflict-state serialization if the projection exposes one.
- **Acceptance:** Suite covers all portal contract edges.
- **Test mapping:** Vitest (contract).
- **Dependencies & order:** T3.3 (map states first).
- **Risks & open questions:** none.

---

## 9. Phase 7 — Recurring audit cadence

**Phase goal:** Coverage and quality stay current after the one-time tasks land: per-cut coverage gate (R4), quarterly WCAG-EM audit (R7), scheduled exploratory sessions (Fowler), trace-first CI failure discipline (R8).

### T7.1 Quarterly WCAG-EM full-product audit

- **Goal:** Every quarter, run the T4.1–T4.7 passes over the entire product and publish a WCAG-EM-structured report (scope → sample → results → limits).
- **Scope (files):** all Pages §2.4 (minus OMR/printing), report template + evidence convention from T4.6.
- **Steps:** 1. Snapshot the page list (catalog); 2. run automated scan (T4.1) + keyboard walk (T4.2) + SR spot check (T4.7) + manual visual pass (T4.8); 3. evaluate complete processes (WCAG 2.2 §5.2.3); 4. publish report; 5. triage findings into regression tests.
- **Acceptance:** Report published each quarter; findings filed as tasks with owners.
- **Test mapping:** exploratory-manual + Playwright/Vitest regression follow-ups.
- **Dependencies & order:** T4.1–T4.8 land first; cadence starts the quarter after.
- **Risks & open questions:** reviewer availability; W3C conformance claims never made from automation alone.

### T7.2 Scheduled exploratory sessions (destructive mindset)

- **Goal:** Regular manual sessions over staff + applicant surfaces (Fowler: design/usability issues invisible to automation); findings become regression tests.
- **Scope (files):** staff: admissions, exams, monitoring, grading, consultation, release, registrar, PH, setup, personnel; applicant: spine, schedule, results, program choice, notifications, QR, support; guided by the T1.1 matrix to target uncovered states.
- **Steps:** 1. Session brief per surface (actors, scopes, target states); 2. destructive actions (double-submit, stale versions, partial networks, mid-commit Escape, rapid clicks); 3. record findings (severity, repro); 4. convert each accepted finding into a Pest/Vitest/Playwright regression; 5. log session + outcomes in the evidence folder.
- **Acceptance:** One session per quarter minimum; findings pipeline closed.
- **Test mapping:** exploratory-manual (primary) → regression tests (output).
- **Dependencies & order:** T1.1 matrix as the session map; after Phase 2/3 coverage lands.
- **Risks & open questions:** schedule commitment; findings may be product changes (needs founder) vs test gaps (fix in change).

### T7.3 E2E CI failure runbook enforcement

- **Goal:** Every CI E2E failure follows the trace-first + push-down discipline (R8).
- **Scope (files):** runbook from T5.10; `system/tests/browser/**`.
- **Steps:** 1. On failure: open trace → identify action → reproduce → if no lower-level test fails, add Pest/Vitest test (Fowler rule 1) → rerun; 2. log the push-down test alongside the fix.
- **Acceptance:** Runbook followed; E2E suite size stays bounded (no per-button bloat).
- **Test mapping:** none (process).
- **Dependencies & order:** T5.10.
- **Risks & open questions:** discipline enforcement is process-level — document in the change.

### T7.4 Per-feature-cut catalog coverage gate

- **Goal:** A feature cut ships only when every P-* row state it touches has an owner test (R4 cadence item 1).
- **Scope (files):** the T1.4 convention; OpenSpec task template of future changes.
- **Steps:** 1. On each cut, diff touched catalog rows; 2. verify owners exist; 3. block/flag cuts with silent cells; 4. update the matrix.
- **Acceptance:** No cut ships with uncovered states; matrix stays current.
- **Test mapping:** none (process gate) — enforced by review.
- **Dependencies & order:** T1.4.
- **Risks & open questions:** enforcement lives in review discipline; consider a CI lint of the matrix (future).

### T7.5 Coverage ledger freshness check

- **Goal:** Quarterly re-run of the T1.1 matrix against the live catalog + test tree to catch drift (new pages without owners, removed pages with orphan tests).
- **Scope (files):** `docs/e2-page-state-catalog.md`, `system/tests/**`, `system/resources/js/Pages/**`.
- **Steps:** 1. Re-derive page list + test list; 2. diff against matrix; 3. file corrections.
- **Acceptance:** Matrix fresh each quarter; orphaned pages/tests surfaced (e.g. G-05/G-06 patterns).
- **Test mapping:** none (audit) + cleanup tasks as output.
- **Dependencies & order:** T1.1; cadence with T7.1.
- **Risks & open questions:** none.

---

## Cross-workstream dependencies

| Workstream | Dependency direction | Coordination required |
|---|---|---|
| **`omr-profile-test-readiness` change (active, other agent)** | We depend on it staying untouched; it depends on manual grading/release paths staying green | Do NOT create OpenSpec dirs; grading/release tasks (T2.6–T2.9) must keep the manual path working (SCN-VS-008); R10 (resolve the dual-active-change conflict) must be settled before cadence work is scheduled under a change. |
| **Printing workstream (result-sheet printing / print jobs, built elsewhere)** | Future dependency only | T2.9 (release prepare) and T3.4/T5.5 (results retrieval) reference printing only as future evidence (print jobs/downloads); no tasks planned here. `PrintPipelineTest.php` remains future-dep. |
| **`AuthLifecycleMissionBreakdown` (peer doc, running)** | Overlapping surfaces: staff login (T2.14/T5.3), applicant login (T3.2), session continuity, personnel/profile (T2.14) | Coordinate test-ownership split for auth journeys (P-AUTH-01, P-APP-01) so Pest suites don't duplicate; cross-link both docs' task IDs. |
| **Seed/fixture workstream (E2Helpers, JourneyFixtureSeeder, AdmissionSupportDemoSeeder, GradingDemoSeeder)** | We depend on deterministic seeds + recorded credentials for T5.0 harness | Extend seeders with journey fixtures (published+reservation-open exam, released+advised attempt with online mode); record credentials + reset commands in active `HANDOFF.md` per repo rule. |
| **GF-11 exit evidence G2–G4/H4** | Phase 5 specs are the exit-condition browser evidence | Map each journey spec to SCN-VS IDs in the change design (traceability package §8); exit evidence can reuse T5.* runs. |
| **Catalog owner (`docs/e2-page-state-catalog.md`)** | Phase 1 edits the catalog | T1.2 disposals need founder approval (catalog states are fixed contract); apply edits inside the future change, not this plan. |
| **Release/audit product gaps (G-01/G-02)** | T2.9 + audit UI depend on a product change | New surfaces (release UI, audit UI) must go through a future OpenSpec change with their own page-state rows before coverage tasks can own them. |
| **QA research §0 conflict (two de-facto active changes)** | Blocks scheduling cadence work under any change (R10) | Surface in the future change proposal; do not schedule T7.* under a change until resolved. |

## Proposed OpenSpec change mapping

This document plans work; nothing here creates changes. Proposed future changes (each with `.openspec.yaml`, proposal, design, tasks, specs, HANDOFF per repo conventions):

| Proposed change | Phases/tasks folded in | Rationale |
|---|---|---|
| **`ui-ux-e2e-coverage-ledger`** | Phase 1 (T1.1–T1.4), Phase 6 (T6.0–T6.11), T7.4, T7.5 | Turns the catalog into a living coverage ledger (R4): matrix + drift disposal + Vitest component layer + per-cut gate. Backend state coverage (Phase 2/3 Pest gaps) rides along as the change's tasks (each surface task lists its missing Pest cases). |
| **`e2e-journey-specs`** | Phase 5 (T5.0–T5.10), T7.3 | Playwright journey suite + harness hardening + CI runbook; the GF-11 exit-condition browser evidence. Depends on the ledger change's fixtures + seed workstream. |
| **`accessibility-audit-cadence`** | Phase 4 (T4.1–T4.8), T7.1, T7.2 | axe wiring, keyboard/dialog/focus audits, WCAG-EM quarterly procedure + report template, exploratory-session pipeline. Runs quarterly after the first two changes land. |
| **`release-and-audit-ui` (product, precursor)** | Gap close for G-01/G-02 (T2.9, P-AUD-01, P-NTF-01) | Builds the missing release/prepare/unrelease/outbox + audit-verification surfaces; the coverage tasks for those rows depend on this change existing. |

Sequencing recommendation: `release-and-audit-ui` (unblocks release coverage) → `ui-ux-e2e-coverage-ledger` (ledger + backend coverage + Vitest) → `e2e-journey-specs` (browser evidence) → `accessibility-audit-cadence` (recurring audits). R10 (resolve the dual-active-change conflict) gates scheduling any of these.

---

*Planning input only. No OpenSpec directories created; no code, tests, seeds, migrations, `compose.yaml`, or `HANDOFF.md` modified. OMR and printing excluded per mission.*






