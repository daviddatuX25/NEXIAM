# E2 Epics and Stories — Connected Admission-to-Release Slice

**Status:** ACTIVE — story package for OpenSpec handoff  
**Date:** 2026-08-05  
**PRD:** [`prd.md`](prd.md)  
**UX catalog:** [`e2-page-state-catalog.md`](e2-page-state-catalog.md)  
**Legacy traces:** [`legacy-reference/08-e2-connected-slice-trace.md`](legacy-reference/08-e2-connected-slice-trace.md)  
**Slice acceptance:** [GF-11](greenfield-foundation/11-connected-vertical-slice-and-acceptance.md)

Story readiness follows master §11. Active OpenSpec: `e2-connected-admission-release-slice`. **Parallel rule:** when a feature lands in `system/`, update the matching story here in the same change — field contracts, dispositions, and GWT — so later review has decisions recorded beside the code.

## Coverage map

| Epic | Stories | Primary REQ-VS | Primary FR |
|---|---|---|---|
| E2-E1 Foundation continuity | E2-S01 | 001, 016 | FR-065 |
| E2-E2 Admission intake & identity | E2-S02–S05 | 002–004, 019 | FR-002–007 |
| E2-E3 Exam session & proctor | E2-S06–S09 | 005–007, 020 | FR-008–016 |
| E2-E4 Grading (manual) | E2-S10–S12 | 008–009, 017, 021 | FR-017, FR-041 |
| E2-E5 Consultation & release | E2-S13–S16 | 011–015, 022–023 | FR-018–032 |
| E2-E6 UX / a11y / audit evidence | E2-S17–S20 | 015, 018, 026–032 | FR-033, FR-062 |
| E2-E7 Gated holds (non-coding) | E2-S21 | 010, 024–025, 029 | Tier B/C |

**FR→epic coverage (slice):** all COMMITTED Tier A journey FRs mapped; gated Tier B/C explicitly held in E2-S21.

---

## Epic E2-E1 — Foundation continuity

### E2-S01 — Deterministic seed supports the connected journey
- **Value:** Demo/QA can recreate the same actors and starting state.
- **FR/BR:** FR-065; REQ-VS-001, 016; BR-001
- **Disposition:** REWRITE (ops path from E0/E1)
- **Authz:** ops only; no applicant data leak in seed logs
- **Pages:** P-OPS-01
- **States:** ready / failed migration
- **GWT:**
  - Given empty approved DB, When `foundation:reset` runs, Then seeded actors/refs match fixture version (SCN-VS-016).
  - Given reset, When staff logs in, Then `/staff` ready without prior journey residue.
- **Tests:** Pest feature reset/seed; existing E1 auth smoke extended.

---

## Epic E2-E2 — Admission intake and identity

### E2-S02 — Submit cycle-scoped admission attempt
- **Status:** IMPLEMENTED (intake field expansion 2026-08-05)
- **FR/BR:** FR-001, FR-002, FR-004; REQ-VS-004, 019; SCN-VS-001/002/003
- **Disposition:** REWRITE — `SubmitAdmissionAttempt` + `AdmissionIntakeData`
- **Authz:** public submit within published window
- **Pages:** `/` Home (product landing), P-PUB-01 `/apply`, confirmation
- **Capability:** public route (no staff capability); server validates window/programs
- **Tx/audit:** atomic ApplicantIdentity + AdmissionAttempt + audit; hashed access challenge; after-commit notify outbox
- **Intake field contract (legacy → greenfield):**
  - PRESERVE: first/middle/last/suffix, email, birthdate, sex, applicant_type (`new`|`transferee`), last_school_enrolled, strand/previous course, gwa, phone, address_line/city/province/zip_code, terms_accepted with **Privacy Policy hyperlink+dialog** (legacy), three ordered program preferences (distinct)
  - ADD (D-086): catalog-driven digital grades — `intake_grade_fields` + `admission_attempt_grade_values` (seed GWA/ENG/FIL/MATH/SCI; no hard-coded subject columns; hard-copy Form 138/TOR still required)
  - REWRITE: preferences store `programs.code` (not legacy `courses.id`); no fabricated `@nexiam.local` email; access key hashed only (D-085)
  - REJECT on public path: `accept_immediately`; plaintext access keys in DB; planning/meta copy on product pages
- **GWT:** happy submit with full fields; duplicate identity → conflict; missing preference_1 / terms → validation; confirmation shows reference only (not raw setup token)
- **Tests:** Pest AdmissionFlowTest + FoundationHealthTest apply page; Playwright pending G4

### E2-S03 — Registrar correct / accept / dismiss / reopen-archive
- **Status:** PARTIAL (2026-08-05) — queue P-REG-01 + detail P-REG-02 scaffolded under `admission-attempt-detail`
- **FR/BR:** FR-003; REQ-VS-004; SCN-VS-004
- **Disposition:** REWRITE — Correct/Accept/Dismiss/reopen/archive; split in-place Correct vs Request correction
- **Authz:** Registrar capabilities; deny Guidance/Program Head/Applicant (SCN-VS-004)
- **Pages:** P-REG-01 `/admissions`, P-REG-02 `/admissions/{id}`
- **States:** conflict (stale version), validation, denied(403), success; Dialog confirm for dismiss/request/archive/reopen
- **Contract:**
  - PRESERVE: list Accept/Dismiss only on pending; detail action bar; reopen non-destructive
  - REWRITE: CapabilityAuthorizer; optimistic `version`; `CorrectAdmissionAttempt` stays in status; `RequestApplicationCorrection` pending→needs_correction with reason (D-074 scaffold); Archive detail-only not list peer of Accept
  - LANDED: applicant resubmit portal (`/applicant/applications/{id}/correct`); registrar reissue access key on detail
  - DEFER: bulk; slip print; full pipeline milestone chrome; Nexi forgot-key
- **GWT:** show payload; unauthorized deny; request correction → needs_correction + audit; in-place correct stays pending; stale version errors
- **Tests:** Pest AdmissionListTest + AdmissionDetailTest; Playwright pending G4

### E2-S04 — Applicant access key and portal login (D-085)
- **Status:** IMPLEMENTED (2026-08-05) — access key + birthdate (D-085)
- **FR/BR:** FR-005–007; REQ-VS-002, 019; **D-085 / D-AUTH-01**
- **Disposition:** REWRITE — hashed durable access key + birthdate login
- **Pages:** confirmation (key claim), P-APP-01 `/applicant/login`, portal
- **Authz:** public login; portal own-record only after session
- **Contract:**
  - PRESERVE: post-submit access before acceptance; email backup of key
  - REWRITE: confirmation shows key once + Save on this device + continue with birthdate; login = key + birthdate
  - REJECT: free-form password setup; birthday-only auth; key-only auth; forcing email before first portal entry
  - LANDED: staff reissue on P-REG-02 (revoke prior hashes + mint once)
  - DEFER: Nexi forgot-key request
- **GWT:** submit → confirmation key (hidden by default, ABCD-EFGH); save-device stores key only; login with key+birthdate enters portal; **Sign out** returns to applicant login; wrong birthdate fails; key alone fails; hash-only storage; acceptance independence
- **Tests:** Pest auth lifecycle; portal own-record; no plaintext key in DB

### E2-S05 — Own-record isolation on applicant portal
- **FR/BR:** FR-029, FR-033; REQ-VS-003
- **Disposition:** REWRITE — CapabilityAuthorizer scopes
- **Pages:** P-APP-02
- **GWT:** Applicant A cannot read Applicant B; cross-scope staff denied
- **Tests:** Pest scope denial suite

---

## Epic E2-E3 — Exam session and proctor duty

### E2-S06 — Create / assign / publish exam session
- **FR/BR:** FR-008–012; REQ-VS-005; SCN-VS-005
- **Disposition:** REWRITE
- **Pages:** P-EXM-01, P-EXM-02
- **Capability:** `exam.schedule` (Test Administrator)
- **GWT:** capacity/time conflict transactional; one wins / one typed conflict; publish after-commit schedule notify
- **Tests:** Pest conflict; Playwright schedule

### E2-S07 — Assign and revoke contextual proctor duty
- **FR/BR:** FR-013; REQ-VS-006; SCN-VS-006
- **Disposition:** REWRITE — not permanent Proctor role
- **Pages:** P-EXM-02, P-PRC-01
- **GWT:** assigned staff can operate; revoke immediately denies; close removes duty
- **Tests:** Pest duty lifecycle

### E2-S08 — Attendance and submission idempotency
- **FR/BR:** FR-015; REQ-VS-007; SCN-VS-007
- **Pages:** P-PRC-01
- **GWT:** repeat same idempotency key → same outcome, no duplicate; outside window denied
- **Tests:** Pest idempotency

### E2-S09 — Extend deadline and close opens grading once
- **FR/BR:** FR-014, FR-016; REQ-VS-020; SCN-VS-017
- **Pages:** P-PRC-01, P-PRC-02
- **GWT:** effective deadline applies; manual/scheduler close race reconciles; grading opens exactly once
- **Tests:** Pest race; scheduler feature

---

## Epic E2-E4 — Manual grading

### E2-S10 — Manual multi-area score entry without inference
- **FR/BR:** FR-017, FR-041; REQ-VS-008, 017, 021; SCN-VS-008
- **Pages:** P-GRD-01
- **GWT:** provider down → ModeBanner degraded; manual scores commit transactionally
- **Tests:** Pest with inference disabled

### E2-S11 — Block incomplete finalization
- **FR/BR:** FR-017; REQ-VS-009; SCN-VS-010
- **Pages:** P-GRD-02
- **GWT:** missing aptitude area fails atomically; no scored projection advance
- **Tests:** Pest finalize guard

### E2-S12 — Staged import seam (minimal) with manual fallback
- **FR/BR:** NFR-023; REQ-VS-021; SCN-VS-018
- **Disposition:** REWRITE minimal seam; OMR SPIKE held
- **Pages:** P-GRD-01 (optional import panel) or DEFER UI if seam-only
- **GWT:** batch identity + row states OR explicit DEFER UI with documented hold pointing to manual path
- **Tests:** Pest batch states if implemented; else story documents DEFER with REQ-VS-008 proof

---

## Epic E2-E5 — Consultation, documents, release, notify

### E2-S13 — Counselor consultation decision
- **FR/BR:** FR-023, FR-048; REQ-VS-011; SCN-VS-011
- **Pages:** P-CON-01
- **GWT:** approve/modify/override/defer recorded; signals provenance only; no autonomous state change
- **Tests:** Pest consultation commands

### E2-S14 — Prepare result document + internal watermarked preview
- **FR/BR:** FR-030–031; REQ-VS-014, 022, 032; SCN-VS-019
- **Pages:** P-REL-01
- **GWT:** untrusted values encoded; checksum/template version; external denied pre-release; async → AcceptedForProcessing
- **Tests:** Pest document safety; Playwright preview

### E2-S15 — Release, notify after commit, applicant retrieve
- **FR/BR:** FR-032; REQ-VS-012, 013, 023, 030; SCN-VS-012/020
- **Pages:** P-REL-02, P-APP-03, P-APP-04, P-NTF-01
- **GWT:** prereq gate; after-commit notify once; failure retryable; applicant sees released only; empty UI cannot mask unread
- **Tests:** Pest outbox; Playwright release→portal

### E2-S16 — Reverse / unrelease preserves history
- **FR/BR:** REQ-VS-012; SCN-VS-013
- **Pages:** P-REL-02
- **GWT:** unrelease with reason; history preserved; visibility via supersession
- **Tests:** Pest reversal

---

## Epic E2-E6 — Cross-cutting UX, a11y, audit

### E2-S17 — Connected pages expose explicit page_state model
- **FR/BR:** FR-062; REQ-VS-026, 028
- **Pages:** all P-* journey pages
- **GWT:** each page demonstrates loading/empty/denied/validation/error/conflict/degraded as applicable; failure ≠ empty success
- **Tests:** Pest Inertia props; Playwright state fixtures

### E2-S18 — Breadcrumbs, responsive, keyboard
- **FR/BR:** REQ-VS-027, 031
- **Pages:** staff + applicant multi-step
- **GWT:** server breadcrumbs; narrow drawer; keyboard dialogs/filters/tables
- **Tests:** Playwright a11y/responsive

### E2-S19 — Notification center and toast truthfulness
- **FR/BR:** REQ-VS-030; NFR-055
- **Kit:** ToastManager
- **GWT:** dedupe; never claim Sent/Released without evidence; SR live regions
- **Tests:** Vitest toast helpers; Playwright notify

### E2-S20 — Audit attribution and tamper evidence
- **FR/BR:** FR-034; REQ-VS-015; SCN-VS-015
- **Pages:** P-AUD-01
- **GWT:** critical events attributable/redacted; tamper fixture fails verify
- **Tests:** Pest audit chain

### E2-S20b — Browser journey quality gate
- **FR/BR:** REQ-VS-018
- **GWT:** connected Playwright journeys leave no unhandled console errors or failed required requests
- **Tests:** Playwright report artifact

---

## Epic E2-E7 — Gated capability promotion

### E2-S21 — Development-qualified OMRChecker proposals with production hold
- **Status:** ACTIVE PHASE B PLAN — Phase A fake-provider capture shipped 2026-08-12
- **FR/BR:** FR-039–041; NFR-041; BR-031, BR-035, BR-054, BR-060; REQ-VS-010, 032
- **Value:** Test Administrators can develop and evaluate real marked-sheet proposals without making the detector a grading authority or falsely granting production clearance.
- **Disposition:** REWRITE legacy automated-OMR goal; PRESERVE shipped capture/gallery/review shape; SPIKE pinned native-bundle activation, headless isolated detection, and aligned evidence; DEFER calibrated confidence, automatic acceptance, Batch/offline/multi-page/free-form OCR.
- **Authz:** existing dedicated OMR capability plus session/sensitivity/lifecycle scope; worker repeats authority before invocation and cannot bind or score.
- **Pages:** P-OMR-01 capture/gallery and P-OMR-02 sheet review; ordinary P-GRD-01 manual scoring remains the complete fallback.
- **State/transaction:** upload creates one durable per-sheet run after commit; provider call occurs outside locks; persistence requires current run/claim/version; human review remains mandatory.
- **GWT:** Given the generated pack and matching development qualification, When Manual/Auto capture uses OMRChecker, Then observed marks reach review through the existing UI and NEXIAM alone derives correctness/scores.
- **GWT:** Given ambiguous marks, identity conflict, worker outage, timeout, malformed output, stale result, or revoked authority, When analysis executes, Then no score is written and a truthful retry/manual path remains.
- **GWT:** Given only synthetic benchmark evidence, When production selects OMRChecker, Then readiness fails closed with no fake fallback.
- **Tests:** Pest activation/envelope/run/adapter/state tests; Python native-bundle activation/worker/fixture tests; reproducible benchmark; Chromium Manual/Auto/outage proof.

### E2-S22 — Keep remaining gated capabilities explicit
- **Covers:** REQ-VS-024 (RAG), real provider/SDK activation (BR-022), and expanded analytics/exports beyond D-ROLE-05/D-080/D-090.
- **Disposition:** SPIKE/DEFER/EVIDENCE HOLD per [`08-e2-connected-slice-trace.md`](legacy-reference/08-e2-connected-slice-trace.md) and `2026-08-15-admission-model-program-head`.
- **Acceptance:** engine/provider holds remain documented; current consultation visualization uses only real released/application/consultation evidence; Nexi chrome remains presentation-only.

## Epic E2-E8 — Admission support, program choice, and Program Head

**Status:** APPROVED PLAN 2026-08-15 — implementation queued behind the sole active OMR cut.

### E2-S23 — Promote capabilities and canonical admission-support states
- **Covers:** FR-077–085; REQ-REP-003–010; D-ROLE-05/D-080/D-089–D-093.
- **Acceptance:** capability rows, grants, state tables, constraints, projections, audit names, and denied-path tests exist before UI.

### E2-S24 — Guidance pool and applicant program selection
- **Covers:** FR-078–080; REQ-REP-004/005.
- **Acceptance:** initial Guidance finalization atomically appends immutable pool/options + attributable pending advised default + FCFS reservation; applicant may acknowledge or transfer; commitment/idempotency guards prevent duplicate/overbook; later pool versions retain standing choice; paper finalization owns out-of-pool/quota variance.

### E2-S25 — Program Head dashboard and canonical lists
- **Covers:** FR-036/038/053; REQ-REP-001/005.
- **Acceptance:** own-program metrics + Potential/Upcoming/Follow-up/Enrolling/Enrolled lists show definition/source/freshness/basis tags; cross-program access denied.

### E2-S26 — Tiered candidate and protected record access
- **Covers:** FR-037; REQ-REP-006; D-080.
- **Acceptance:** Potential summary is minimal; protected view requires released result + strong assigned-program relationship; other program options/history and raw artifacts are absent; audit + applicant access history/notice emitted.

### E2-S27 — Follow-up, PH decline, and reconsideration
- **Covers:** FR-081; REQ-REP-007; D-089.
- **Acceptance:** normal processing needs no PH action; direct PH selection decline is pre-enrolling; Guidance opens an edge follow-up before enrolling, assigned PH resolves it during enrolling, and it blocks enrolled only; decline writes canonical outcome/releases quota; reasons/audit/notices and overlap/race guards required.

### E2-S28 — Bounded admission-support messaging
- **Covers:** FR-054/082; D-092.
- **Acceptance:** PH↔Guidance and current-selection/follow-up PH↔applicant threads enforce participants/scope; potential-only outreach denied; UI/server states are unread/ready/denied without a general inbox.

### E2-S29 — Consultation-embedded admission-support visualization
- **Covers:** FR-048–052/085; REQ-REP-010; D-093.
- **Acceptance:** existing consultation selected-attempt workspace shows real released-score charts + same-value table, preferences, structured consultation evidence, independent future-analysis regions, and human decision/pool/follow-up/history; no provider/fake engine ships; YuKee and deterministic/rule-based plugins remain separate deferred changes; PH Tier B reuse remains bounded.

### E2-S30 — Registrar admission-completion stamps
- **Covers:** FR-083; REQ-REP-008; D-091.
- **Acceptance:** Registrar starts enrolling on sheet submission after core milestones and may acknowledge the pending default; final enrolled requires terminal edge follow-up + signed-paper verification, records final program even outside pool/quota, releases reservation, reports variance, and enforces split freeze/reversal rules.

### E2-S31 — Universal applicant QR and actor-specific action hub
- **Covers:** FR-084; REQ-REP-009; D-092.
- **Acceptance:** one portal/printed opaque applicant locator contains no sensitive fields, action, destination, or authority; authenticated Registrar, Guidance/TA, and PH receive only ordinary capability/duty/scope-derived destinations through scanner/manual fallback; invalid/expired/no-destination states reveal no applicant data; scan never mutates.

### E2-S32 — Demonstration data and end-to-end proof
- **Covers:** all E2-E8 stories.
- **Acceptance:** deterministic founder/demo states—not a fake admission engine—staff/applicant credentials, URLs/reset steps, consultation chart/table and no-analysis evidence, same-QR role-menu isolation, targeted suites + full-suite blocker/status, and Chromium proof are recorded in HANDOFF.

---

## Story ID index

| ID | Title |
|---|---|
| E2-S01 | Deterministic seed |
| E2-S02 | Submit admission attempt |
| E2-S03 | Registrar review commands |
| E2-S04 | Access key + birthdate login (D-085) |
| E2-S05 | Own-record isolation |
| E2-S06 | Exam create/assign/publish |
| E2-S07 | Proctor duty |
| E2-S08 | Attendance/submission |
| E2-S09 | Extend/close/grading-open |
| E2-S10 | Manual scoring |
| E2-S11 | Finalize completeness |
| E2-S12 | Import seam / defer |
| E2-S13 | Consultation |
| E2-S14 | Result document/preview |
| E2-S15 | Release + notify + retrieve |
| E2-S16 | Unrelease |
| E2-S17 | page_state model |
| E2-S18 | Breadcrumb/a11y |
| E2-S19 | Notifications/toasts |
| E2-S20 | Audit evidence |
| E2-S20b | Browser quality |
| E2-S21 | Development-qualified OMRChecker |
| E2-S22 | Remaining gated holds |
| E2-S23 | Promote capabilities and canonical admission-support states |
| E2-S24 | Guidance pool and applicant program selection |
| E2-S25 | Program Head dashboard and canonical lists |
| E2-S26 | Tiered candidate and protected record access |
| E2-S27 | Follow-up, PH decline, and reconsideration |
| E2-S28 | Bounded admission-support messaging |
| E2-S29 | Consultation-embedded admission-support visualization |
| E2-S30 | Registrar admission-completion stamps |
| E2-S31 | Universal applicant QR and actor-specific action hub |
| E2-S32 | Demonstration data and end-to-end proof |
