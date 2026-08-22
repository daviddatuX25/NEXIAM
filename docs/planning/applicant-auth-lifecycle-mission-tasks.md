# Applicant Auth + Lifecycle Mission — Detailed Task Breakdown (Planning Input)

**Status:** PLANNING INPUT ONLY — authorizes nothing. Feeds future OpenSpec changes.
**Scope owner:** applicant-identifier + stateless-auth + cross-cycle-lifecycle workstream (founder split from OMR work).
**Repo root:** `/home/user/Projects/NEXIAM`. Greenfield code lives in `system/`; `legacy_codebase/application/` + `docs/legacy-reference/` are EVIDENCE ONLY.
**Research basis:** read-only verification of every file/symbol named below (2026-08-18).

---

## 0. Mission & authority (locked founder decisions)

These are the binding decisions this breakdown must satisfy. Where a task conflicts with them, the task is wrong, not the decision.

| # | Locked decision |
|---|---|
| D-1 | **Canonical public term: "Application No."** Stop introducing "reference code" / "reference number" in new UI, events, docs, and tests. **Application No. and Application Key are treated as the same public identifier for now** (they are the same string on the paper trail; the Application Key additionally unlocks the portal). Legacy-only template placeholder `applicant_reference` is deprecated and must never be emitted by greenfield code. |
| D-2 | **Applicant auth = stateless Application No. + Private Key** (the key is the access key minted at submission, D-085). **Birthdate is NEVER a credential** — it is a knowledge factor *in addition to* the key, never sufficient alone, never used to reset anything. |
| D-3 | **Remember-Me is explicit opt-in; session persistence is the default.** No silent persistent cookies. Default session behavior stays as today (database session, 120-min lifetime, `expire_on_close=false`). |
| D-4 | **Required auth defenses:** per-Application-No failed-attempt lockout (**3 strikes → 30 min**), **IP rate limiting (10/min)**, **bot protection (e.g. Turnstile)**, **audit events** for every auth outcome. |
| D-5 | **Recovery = resend/reissue.** Registrar-assisted reissue exists today (`ReissueApplicantAccessKey`); "resend" must be closed (see research gap G-AUTH-2 — initial key email is promised by D-085 but never dispatched). **Staff reading plaintext keys requires a separate security review** — never a staff-facing "view key" surface. |
| D-6 | **Key format mismatch to reconcile:** `ApplicantAccessKey` mints **8-char** (`XXXX-XXXX`) hashed keys; the proposed spec says **6-char**. Decide/recommend final format; **keep one-way hashing**. (Recommendation in T2.1: keep 8-char — D-085 wording, 40-bit entropy, existing hashes/keys unaffected.) |
| D-7 | **Cross-cycle:** one admission attempt per applicant per cycle (DB unique `(applicant_identity_id, academic_cycle_id)` — exists). **NO same-cycle retakes for now** (`SubmitAdmissionAttempt` throws `DuplicateAdmissionAttemptException` — keep). **Later-cycle application creates a NEW attempt**; prior attempt + result are preserved as history. **If a prior result may satisfy a later cycle, derive via policy/query against prior attempts — NEVER copy/move/overwrite** (Phase 4). **No campus dimension** until the institution defines one. |

Authority order for implementation (from `docs/project-context.md`): founder decisions → master roadmap → GF contracts → active OpenSpec → tests → legacy evidence.

---

## 1. Out of scope / blocked

- **OMR capture / grading-capture OMR screens / `omr-profile-test-readiness` change** — owned by another agent session; the ONLY active OpenSpec change. Do not create OpenSpec change directories, do not touch `openspec/changes/`. (OMR-domain identifier fields `detected_applicant_number` / `applicant_number_proposal` are a *different* domain term — the OMR bubble field name. They are listed in research only to prevent accidental renaming.)
- **Result-sheet printing / admission-slip printing / print jobs** — being built elsewhere. Reference only as **future dependencies** (Phase 1 terminology affects the templates they render: legacy placeholders `applicant_no` / `applicant_number` / `applicant_reference`). No printing tasks are planned here.
- **Same-cycle retakes / retry policy** — explicitly deferred by D-7. The `SubmitAdmissionAttempt` duplicate exception is the enforcement; do not soften it.
- **Missed-exam / reassignment policy** (master-reference §6.6) — separate parity item, not this mission.
- **Nexi "forgot key"** — DEFER past E2 per D-085.
- **Free-form applicant password / self-service password** — REJECTED (D-085); `CompleteApplicantCredentialSetup` is a deprecated throw-only stub and stays that way.
- **Campus dimension** — none until the institution defines one (D-7).
- **Program Head / guidance bulk tooling beyond attempt-scoped queries** — only touched where a named query carries `reference_code` and needs the rename or cycle filter.

---

## 2. Research evidence (exact files/symbols found, current gaps)

### 2.1 Terminology surface (`reference_code` / `reference_number` / `applicant_no`)

**DB / model / migration:**
- `system/database/migrations/2026_08_05_160000_create_e2_connected_slice_tables.php` — `admission_attempts.reference_code` (line ~43, `string()->unique()`); `admission_reference_counters` table (`next_value`, unique per `academic_cycle_id`); unique `(applicant_identity_id, academic_cycle_id)` on `admission_attempts` (D-7 enforcement); `applicant_access_challenges` (token_hash, request_key unique, expires_at, consumed_at).
- `system/app/Models/Admission/AdmissionAttempt.php` — `$fillable` includes `'reference_code'` (line 31); no other column name touches.
- `system/app/Admission/Support/ReferenceCodeGenerator.php` — `next(AcademicCycle $cycle, int $maxAttempts = 5): string` returns `APP-<year>-%05d` (e.g. `APP-2026-00001`); row-locked counter under `DB::transaction` + `lockForUpdate`; `AdmissionReferenceCounter` model (INV-003 collision safety).
- `system/app/Models/Admission/AdmissionReferenceCounter.php` — counter model (table `admission_reference_counters`).

**Audit payload keys (event types are append-only hash-chain — new events only, never rewrite history):**
- `system/app/Admission/Actions/SubmitAdmissionAttempt.php` — `admission.attempt_submitted` payload `reference_code` (line 128).
- `system/app/Admission/Actions/AcceptAdmissionAttempt.php` — `admission.accepted` payload + notification payload `reference_code` (lines 45, 56, 60).
- `system/app/Admission/Actions/AmendPendingAdmissionAttempt.php` (line 96), `ArchiveAdmissionAttempt.php` (37), `AuthorizeAcceptedAdmissionAmendment.php` (92), `CorrectAdmissionAttempt.php` (111), `ReconsiderAdmissionAttempt.php` (56), `ReissueApplicantAccessKey.php` (65, `admission.access_key_reissued`), `ReopenAdmissionAttempt.php` (45), `RequestApplicationCorrection.php` (45), `ResubmitApplicationCorrection.php` (108).
- `system/app/Admission/Actions/IssueApplicantAccessChallenge.php` — `admission.access_challenge_issued` (no reference_code; challenge_id/attempt_id only — fine).
- `system/app/Admission/Actions/AuthenticateApplicantWithAccessKey.php` — `admission.applicant_login` (account_id/identity_id/user_id; **no application_no — add it**).

**Queries / presenters / projections (payload key + search predicates):**
- `system/app/Consultation/Queries/EnrollmentEligibilityQuery.php` — `search()` filters `reference_code like %q%` (line 39), row key `reference_number` (50, 113); `cardFor()` audit payload `reference_code` (69); **no cycle filter, no cycle in payload** (gap G-CYC-2).
- `system/app/Consultation/Queries/ConsultationReadyPoolQuery.php` — row key `reference_number` (38), search predicate (43).
- `system/app/Consultation/Queries/ConsultationSessionDetailQuery.php` — `reference_number` (37).
- `system/app/Consultation/Queries/ConsultationDashboardQuery.php` — `row()` key `reference_code` (143); schedule-applicant rows `reference_code` (100); **cycle gaps: `sessionsToday`, `scheduledAttemptIds`, `anyScheduledIds`, `advisedIds`, `pendingAdvise` are NOT cycle-scoped** (gap G-CYC-3); `for(?string $cycleCode = null)` scopes only accepted-attempts + open/closed session counts.
- `system/app/Examination/Queries/ExamSessionDetailQuery.php` (51, 58), `MonitoringRosterQuery.php` (79), `ExamSessionPresenter.php` (158, 166).
- `system/app/Consultation/Support/ConsultationSessionPresenter.php` (162, 170).
- `system/app/Admission/Queries/ListAdmissionAttemptsQuery.php` — search predicate `reference_code` (63), row key `reference_code` (126), **HAS cycle filter** (`cycle` query param + `cycle_code`/`cycle_name` in rows — already correct).
- `system/app/Admission/Queries/ShowAdmissionAttemptQuery.php` (34).
- `system/app/ProgramHead/Queries/ProgramHeadApplicantQueries.php` (35, 69), `ProgramHeadCandidateListsQuery.php` (90, 149, 176).
- `system/app/Ai/Applicant/ApplicantOwnDataProjector.php` — docblock type `reference_code` (19) + payload (53); `system/app/Ai/Adapters/Fake/FakeAiGateway.php` — copy `" (reference {$ref})"` (99-101).
- `system/app/Application/Capabilities/Handlers/Exams/ExamSessionProjection.php` (82), `ExamSessionAssignHandler.php` (40), `ExamSlotReserveHandler.php` (51).
- `system/app/Http/Controllers/...` — `Admission/AdmissionSupportController.php` (78, 81), `Admission/ApplicantApplyController.php` (87-98 flash keys `reference_code`), `Applicant/ApplicantPortalController.php` (114, 181 — `applicationShow`/`correctionForm`), `Applicant/ApplicantConsultationController.php` (56), `Applicant/ApplicantScheduleController.php` (51, 262 copy `Ref …`), `Staff/ApplicantQrController.php` (66), `Assessment/GradingController.php` (159, 261), `Assessment/GradingScoreController.php` (53), `Release/ReleaseController.php` (41 predicate, 56-57 rows; **no cycle predicate** — gap G-CYC-4).
- `system/app/Release/Actions/PrepareResultDocument.php` — placeholder HTML `Ref: …` (line 22; temporary body pending printing workstream), `ReleaseResult.php` — notification body + payload `reference_code` (115-120).

**Frontend (Svelte):** `Admission/Confirmation.svelte` (prop `reference_code`, label "Applicant reference"), `Applicant/Portal.svelte` (already `identity.applicant_no` — good), `Applicant/MyApplication.svelte` (66 "Ref …"), `Applicant/Consultation.svelte` (115), `Applicant/Schedule.svelte` (121), `Applicant/Correction.svelte` (51, 69), `Admission/ApplicantAdmissionSupport.svelte` (155, 162, 164), `Admission/Index.svelte` (406, 465), `Admission/Show.svelte` (270, 274, 280 "Reference …"), `Assessment/GradingScoreInput.svelte` (51, 90, 117, 198), `Assessment/GradingSession.svelte` (160), `Consultation/ConsultationSessionShow.svelte` (`reference_number` 64-77, 561, 691), `Examination/Monitoring.svelte` (161), `Examination/Show.svelte` (`reference_number` 43-54, 353, 443), `ProgramHead/ApplicantShow.svelte` (31, 226), `ProgramHead/ProgramShow.svelte` (269, 308, 349, 398, 423, 445), `Registrar/EnrollmentEligibility.svelte` (`reference_number` 83, 105), `Release/Consultation.svelte` (173, 207, 235), `Components/kit/ApplicantScanModal.svelte` (140 — staff scan roster; `detected_applicant_number` stays OMR-domain), `Assessment/Omr/Capture.svelte` (150, 946, 973 — roster `reference_code` renames; `detected_applicant_number` stays).

**Tests:** `tests/Feature/Admission/AdmissionDetailTest.php` (29, 213), `AdmissionListTest.php` (42, 63, 67), `ApplicantAccessTest.php` (95, 98 session keys), `CorrectionAndKeyLifecycleTest.php` (90), `Applicant/ApplicantPortalInterfaceTest.php` (68, 190), `Registrar/EnrollmentEligibilityTest.php` (164), `Release/ReleaseFlowTest.php` (133). OMR tests use `applicant_number` domain terms — untouched.

**Seeds:** `database/seeders/ApplicantPortalDemoSeeder.php` (pinned keys `PORT-ONLN`/`PORT-F2F2`/`PORT-DEFL`, `ApplicantAccessKey::hash` at 279, challenge upsert 281-304), `JourneyFixtureSeeder.php` (`DEMO-ACPT`/`DEMO-PEND`), `config/seed.php` (applicant keys). `ReferenceCatalogSeeder.php` = academic reference *catalogs* (programs/aptitude areas) — unrelated to reference codes; do not rename.

**Legacy evidence:**
- `legacy_codebase/application/app/Services/ResultSheetTemplateService.php` — `PLACEHOLDERS` (37-44): `applicant_reference` (labelled "Reference number (Deprecated: use applicant_number or applicant_no)", line 432), `applicant_number` ("Applicant number"), `applicant_no` (alias); replacements map all three to `$data['reference']` from `$app?->reference_number` (1016, 661-663).
- `legacy_codebase/application/app/Services/AdmissionSlipTemplateService.php` — `PLACEHOLDERS` (18-27): `reference_number`, `applicant_number`, `applicant_no` (+ `_2` variants), all mapped to `$data['reference_number']`; sample data `'reference_number' => 'APP-2026-00001'` (209) — **legacy sample format matches the greenfield generator output exactly**.

### 2.2 Auth surface

- `system/app/Admission/Support/ApplicantAccessKey.php` — `DISPLAY_LENGTH = 9` (incl. hyphen), `RAW_LENGTH = 8`, alphabet `ABCDEFGHJKLMNPQRSTUVWXYZ23456789` (Crockford-like, no 0/O/1/I/L); `mint()`, `normalize()` (strips non-alphanumerics, uppercases), `format()` (`XXXX-XXXX`), `hash()` = `sha256(normalized)` **one-way**, `isValidShape()` uses `/^[A-Z2-9]+$/` — **weaker than the mint alphabet (accepts I/L/O/0/1) — gap G-AUTH-1**.
- `system/app/Admission/Actions/AuthenticateApplicantWithAccessKey.php` — `handle(accessKey, birthdate)`; looks up challenge by `token_hash` (unconsumed, unexpired) → identity → compares birthdate → upserts `ApplicantAccount`/`User` (random 40-char password for applicant users) → `ApplicantCapabilityGrants::sync` → audit `admission.applicant_login`. **No lockout, no failed-attempt ledger, no rate-limit inside the action** (gap G-AUTH-3). Generic `RuntimeException('Invalid access key or birthdate.')` everywhere — good, keep.
- `system/app/Admission/Actions/IssueApplicantAccessChallenge.php` — creates challenge with `expires_at = now()->addYear()` (durable until reissue).
- `system/app/Admission/Actions/ReissueApplicantAccessKey.php` — version guard (`expectedVersion`), blocks archived attempts, revokes all unconsumed challenges for the identity (`consumed_at = now()`), mints + issues new key, bumps `version`, audits `admission.access_key_reissued` (payload has `reference_code` → rename).
- `system/app/Models/Admission/ApplicantAccessChallenge.php` — fillable: `applicant_identity_id, admission_attempt_id, purpose, token_hash, request_key, expires_at, consumed_at`. **Challenge is tied to an attempt** (cross-cycle relevance: each cycle's attempt gets its own key).
- `system/app/Http/Requests/Applicant/ApplicantLoginRequest.php` — shape validation + `prepareForValidation` normalizes via `ApplicantAccessKey::format`.
- `system/app/Http/Controllers/Applicant/ApplicantPortalController.php` — `login()` calls the action, `Auth::guard('web')->login($user)` **without remember flag**, session regenerate, `loginForm()` ships demo key prefill (local/testing only).
- `system/routes/web.php` (68-75) — `/applicant/login` POST with **`throttle:5,1`** (IP-based, 5/min — below D-4's 10/min, no per-Application-No lockout); `applicant.setup` alias redirect.
- **No `users.remember_token` column** in any migration (grep: no matches) — remember-me requires a migration (gap G-AUTH-4).
- `system/app/Http/Requests/Auth/LoginRequest.php` (staff) — `RateLimiter::hit`/`tooManyAttempts` pattern to mirror; `system/resources/js/Pages/Auth/Login.svelte` has the "Remember me" checkbox pattern to mirror.
- **No bot-protection integration anywhere** (grep: no turnstile/recaptcha/hCaptcha) — gap G-AUTH-5.
- **No email dispatch of the access key at submission** — `SubmitAdmissionAttempt` mints + issues challenge, `ApplicantApplyController::submit` flashes plaintext to the confirmation screen; no `NotificationIntentDispatcher` call exists (gap G-AUTH-2 — D-085 promised "email backup delivery of the same access key"). `NotificationDelivery`/`NotificationIntentDispatcher`/`NotificationIntent`/`NotificationRecipients` exist and are the delivery mechanism to use.
- `system/app/Admission/Support/ApplicantScope.php` — `attemptBelongsToUser` + `identityIdForUser` (identity-scoped account → one portal login sees the identity's attempts).
- `system/config/session.php` — `driver=database`, `lifetime=120`, `expire_on_close=false` (session persistence default, D-3). `system/config/cache.php` — `CACHE_STORE=database` default (RateLimiter can use DB cache; Redis available for prod).
- Staff auth separation: same `web` guard + `users` table; applicant users get random passwords; separation today is capability-based (`CapabilityAuthorizer`), not a distinct guard (gap G-AUTH-6 — needs an audit + tests, not necessarily a new guard).

### 2.3 Cross-cycle surface

- `system/app/Applicant/Support/ApplicantPortalProjection.php` — `for(User $user)` picks **latest attempt only** (`orderByDesc('id')->first()`, lines 39-44); identity block emits `applicant_no` = that attempt's `reference_code`; **no cycle list, no "My applications" switcher** (gap G-CYC-1).
- `system/app/Http/Controllers/Applicant/ApplicantPortalController.php` — `dashboard()` renders the projection; `applicationShow()`/`correctionForm()`/`resubmitCorrection()` are already **per-attempt with `ApplicantScope::attemptBelongsToUser`** (correct shape for multi-cycle).
- `system/app/Consultation/Queries/EnrollmentEligibilityQuery.php` — gaps G-CYC-2 (no cycle filter / payload).
- `system/app/Consultation/Queries/ConsultationDashboardQuery.php` — gap G-CYC-3 (today-schedule + pending sets unscoped).
- `system/app/Http/Controllers/Release/ReleaseController.php::consultation` — search over accepted attempts **without cycle predicate** (gap G-CYC-4); single-match redirects to `admission-support.show`.
- Attempt-scoped child records (already FK'd to `admission_attempt_id`, i.e. never person-merged): `exam_assignments`, `exam_attendance_records`, `exam_submission_records`, `score_records`, `grading_sessions` (via exam session), `consultation_decisions`, `consultation_assignments`, `result_documents`, `result_releases`, `program_selections`, `program_pools`, `program_follow_ups`, `enrollment_stamps`, `admission_attempt_grade_values` (all in `system/database/migrations/2026_08_05_160000_create_e2_connected_slice_tables.php` and related). `ResultRelease` has `is_current` per attempt (already attempt-scoped, not person-scoped).
- `system/app/Admission/Support/NormalizedIdentityKey.php` — identity dedupe key (INV-002), unrelated to application no.

### 2.4 Result-reuse

- **No prior-attempt policy or query exists** (grep: no `prior_attempt`/`previous_attempt`/`prior cycle` matches). All lifecycle derivation is in `system/app/Admission/Support/AttemptProgramFacts.php` (read-only derivation; never persisted) and `system/app/Consultation/Support/AttemptResultReadiness.php` — the natural shape to mirror for reuse derivation.
- `system/app/Consultation/Queries/EnrollmentEligibilityQuery.php::cardFor` — status-fact card (accepted/grading_finalized/result_released/advised) is the integration point for a "prior result may satisfy this cycle" fact.
- `system/app/Models/Foundation/AcademicCycle.php` — `code`, `name`, `school_year`, `semester`, `opens_on`, `closes_on`, `is_active`, singleton `activate()`/`deactivate()`; `staffLabel()` (staff vocabulary: "Academic year + Semester").
- Config seam precedent: `config/seed.php`, `config/omr.php` (feature-flag style config files exist).

### 2.5 Audit infrastructure

- `system/app/Foundation/Actions/RecordAuditEvent.php` — hash-chained append-only audit (`prev_hash` → `event_hash`); **payload keys may change for new events, historical rows are immutable and must never be rewritten**.
- `system/database/migrations/2026_08_04_045703_create_audit_events_table.php` — `audit_events` (`uuid id`, `event_type`, `actor`, `payload json`, `prev_hash`, `event_hash` unique).
- `system/app/Notifications/NotificationDelivery.php` + `NotificationIntentDispatcher.php` — authoritative delivery rows with `template_key`, `payload` (carries `subject`/`body`/`action_label`/`action_url`/`attempt_id`/`reference_code`), status machine `pending → processing → delivered/retrying → failed_terminal`. Notification payloads are new rows per event — rename the key for new events only.

---

## 3. Phase 1 — Terminology migration: reference_code → application_no (Application No.)

**Phase goal:** every greenfield surface — column, generator, model, audit payload keys, notification payloads, queries/presenters/projections (incl. AI projections), Svelte props/copy, seeds, tests — uses `application_no` (public label "Application No."). No new "reference code"/"reference number" strings anywhere in `system/`. Legacy template placeholders keep their legacy keys as read-only compatibility (never emitted by greenfield code).

**Decision task up front:** DB column rename vs code-level rename. **Recommendation: DB column rename** (`admission_attempts.reference_code` → `admission_attempts.application_no`). Rationale: PostgreSQL `RENAME COLUMN` is cheap and non-rewriting; the column is young (2026-08-05 migration, dev environments only); code-level aliasing violates the repo rule "no shims, aliases, deprecated paths"; a renamed column makes accidental re-introduction fail loudly at the DB layer. Keep the value format `APP-YYYY-00001` unchanged (matches legacy slip sample `APP-2026-00001` exactly — no value backfill required; backfill would be required only if the format changed, which it does not). `admission_reference_counters` → `admission_application_no_counters` for consistency (internal, same migration).

### T1.1 — DB migration: rename column + counter table (MIGRATION)
- **Goal:** rename `admission_attempts.reference_code` → `application_no`; `admission_reference_counters` → `admission_application_no_counters`; keep unique constraints, format, and counter semantics intact.
- **Scope:** new migration `system/database/migrations/2026_08_XX_XXXXXX_rename_reference_code_to_application_no.php`; tables `admission_attempts`, `admission_reference_counters`.
- **Steps:** 1) `Schema::rename` on `admission_attempts.reference_code` → `application_no` (unique index follows the column). 2) Rename counter table; re-point model `$table`. 3) `down()` reverses both. 4) Verify no other table references the old column name (grep `reference_code` in `system/database/migrations` — only this migration). 5) Update `AdmissionReferenceCounter` model → rename to `AdmissionApplicationNoCounter` (new file; delete old — clean cutover, no alias class) and `ReferenceCodeGenerator` → `ApplicationNoGenerator` (T1.2).
- **Acceptance:** fresh `foundation:reset --force` DB has `admission_attempts.application_no` unique, counter table named `admission_application_no_counters`, generator emits `APP-YYYY-00001` sequences collision-safe; `down()` restores original names.
- **Test mapping:** Pest Feature — extend existing admission-submit suite asserting the column name in `assertDatabaseHas('admission_attempts', ['application_no' => …])`; no existing test may reference the old column after the sweep.
- **Dependencies & order:** FIRST in Phase 1 (everything else depends on the new name). Requires T1.2 in the same change so the codebase never runs with a mismatch mid-migration.
- **Risks & open questions:** risk — a straggler `reference_code` reference breaks at runtime; mitigate with the grep sweep (T1.3-T1.9) landing in the same change. Idempotency: migrations run once; guard `Schema::hasColumn`/`hasTable` defensively if this project's convention requires repeatable ops. Open question — rename counter table at all vs. leave internal table name (recommend rename for consistency; cheap now, noisy later).

### T1.2 — Code: generator + counter model rename (CODE)
- **Goal:** `ReferenceCodeGenerator` → `ApplicationNoGenerator`; `AdmissionReferenceCounter` → `AdmissionApplicationNoCounter`; update the one call site.
- **Scope:** `system/app/Admission/Support/ReferenceCodeGenerator.php` (class + `next()` internals — `AdmissionReferenceCounter::query()`, counter create), `system/app/Models/Admission/AdmissionReferenceCounter.php` (class + `$table`), `system/app/Admission/Actions/SubmitAdmissionAttempt.php` (constructor type + property `$referenceCodeGenerator` → `$applicationNoGenerator`, call `->next($cycle)`).
- **Steps:** rename files/classes; update table name string; update docblock (INV-003 comment stays); update the single injection site; delete old files (no shims).
- **Acceptance:** `grep -r "ReferenceCodeGenerator\|AdmissionReferenceCounter" system/` → zero hits; submit flow still allocates `APP-YYYY-00001` under concurrency (INV-003 retry loop intact).
- **Test mapping:** existing Pest Feature admission-submit suite (already covers generation) re-run green.
- **Dependencies & order:** after T1.1; before T1.3.
- **Risks & open questions:** none material.

### T1.3 — Code: AdmissionAttempt model rename (CODE)
- **Goal:** `$fillable['reference_code']` → `'application_no'`; no accessor aliases.
- **Scope:** `system/app/Models/Admission/AdmissionAttempt.php` (fillable line 31).
- **Steps:** rename fillable entry; sweep the model file for stray docblock strings.
- **Acceptance:** model persists/reads `application_no`; no `reference_code` reference remains in the file.
- **Test mapping:** none beyond existing suite (covered by T1.1 acceptance tests).
- **Dependencies & order:** after T1.1.
- **Risks & open questions:** none.

### T1.4 — Code: audit payload keys in Actions (CODE — audit-sensitive)
- **Goal:** new `admission.*` events carry `application_no` instead of `reference_code`; historical hash-chained rows are NEVER rewritten.
- **Scope:** `system/app/Admission/Actions/` — `SubmitAdmissionAttempt` (`admission.attempt_submitted`), `AcceptAdmissionAttempt`, `AmendPendingAdmissionAttempt`, `ArchiveAdmissionAttempt`, `AuthorizeAcceptedAdmissionAmendment`, `CorrectAdmissionAttempt`, `ReconsiderAdmissionAttempt`, `ReissueApplicantAccessKey` (`admission.access_key_reissued`), `ReopenAdmissionAttempt`, `RequestApplicationCorrection`, `ResubmitApplicationCorrection`; also `AuthenticateApplicantWithAccessKey` (`admission.applicant_login` — **add** `application_no` for the authenticated attempt; the challenge row carries `admission_attempt_id`).
- **Steps:** for each action, rename payload key `reference_code` → `application_no` (value stays `$attempt->application_no`); event type strings unchanged; add `application_no` to the login event payload (resolve via challenge→attempt; the action currently resolves challenge→identity only — include the attempt lookup); document in the change that audit history is append-only and payload keys apply to new rows only.
- **Acceptance:** all new audit rows use `application_no`; `grep -r "reference_code" system/app/Admission/Actions/` → zero hits (except historical docs/comments); hash chain verification still passes (no rewrite).
- **Test mapping:** Pest Feature — extend `AdmissionDetailTest`-style assertions (`assertDatabaseHas('audit_events', ['event_type' => 'admission.attempt_submitted', 'payload' => …])` with `application_no` key) and a login test asserting `admission.applicant_login` payload contains `application_no`.
- **Dependencies & order:** after T1.1; parallelizable with T1.5-T1.9.
- **Risks & open questions:** audit-query consumers that read old rows (pre-change `reference_code`) — decide: readers must accept both keys or only new keys (recommend: only new keys; old dev data is disposable via reset; document in change). No production data exists yet (dev-only), so no dual-read shim.

### T1.5 — Code: notification payload keys + copy (CODE)
- **Goal:** notification payloads and body copy use Application No.; template keys unchanged.
- **Scope:** `system/app/Admission/Actions/AcceptAdmissionAttempt.php` (notification payload `reference_code` → `application_no`, body `"Your application {$attempt->application_no} has been accepted…"`), `system/app/Release/Actions/ReleaseResult.php` (body `"…for {$attempt->application_no}…"`, payload key), `system/app/Release/Actions/PrepareResultDocument.php` (`Ref:` → `Application No.:` in the placeholder body — coordinated with the printing workstream).
- **Steps:** rename keys/copy; keep `attempt_id`, `action_url`, template keys.
- **Acceptance:** `grep -r "reference_code" system/app/Admission/Actions system/app/Release/Actions` → zero hits; notification center renders Application No. copy.
- **Test mapping:** Pest Feature — `ReleaseFlowTest` (notification assertions) + `AdmissionDetailTest` updated; Vitest if notification fixtures carry the key.
- **Dependencies & order:** after T1.1; coordinate `PrepareResultDocument` copy change with the printing workstream (they own the eventual template).
- **Risks & open questions:** none.

### T1.6 — Code: queries/presenters/projections rename + `reference_number` consolidation (CODE)
- **Goal:** every payload key becomes `application_no`; the mixed `reference_number`/`reference_code` keys unify to `application_no`; search predicates updated.
- **Scope (exact):** `system/app/Consultation/Queries/EnrollmentEligibilityQuery.php` (predicate 39, keys 50/113, audit 69), `ConsultationReadyPoolQuery.php` (38/43), `ConsultationSessionDetailQuery.php` (37), `ConsultationDashboardQuery.php` (100/143), `Examination/Queries/ExamSessionDetailQuery.php` (51/58), `Examination/Queries/MonitoringRosterQuery.php` (79), `Examination/Support/ExamSessionPresenter.php` (158/166), `Consultation/Support/ConsultationSessionPresenter.php` (162/170), `Admission/Queries/ListAdmissionAttemptsQuery.php` (63/126), `Admission/Queries/ShowAdmissionAttemptQuery.php` (34), `ProgramHead/Queries/ProgramHeadApplicantQueries.php` (35/69), `ProgramHead/Queries/ProgramHeadCandidateListsQuery.php` (90/149/176), `Application/Capabilities/Handlers/Exams/ExamSessionProjection.php` (82), `ExamSessionAssignHandler.php` (40), `ExamSlotReserveHandler.php` (51), `Http/Controllers/Admission/AdmissionSupportController.php` (78/81), `Http/Controllers/Applicant/ApplicantPortalController.php` (114/181), `ApplicantConsultationController.php` (56), `ApplicantScheduleController.php` (51/262), `Http/Controllers/Staff/ApplicantQrController.php` (66), `Http/Controllers/Assessment/GradingController.php` (159/261), `GradingScoreController.php` (53), `Http/Controllers/Release/ReleaseController.php` (41/56-57), `Ai/Applicant/ApplicantOwnDataProjector.php` (19/53), `Ai/Adapters/Fake/FakeAiGateway.php` (99-101).
- **Steps:** mechanical key rename per file (values unchanged); update `@return` docblock array shapes; update any `where('reference_code', …)` predicates to `where('application_no', …)`; grep-verify zero `reference_code|reference_number` in `system/app`.
- **Acceptance:** `grep -ri "reference_code\|reference_number" system/app` → zero hits; portal/staff/registrar/consultation/exam pages serialize `application_no`.
- **Test mapping:** Pest Feature sweep — update assertions in `AdmissionListTest`, `AdmissionDetailTest`, `ApplicantPortalInterfaceTest`, `EnrollmentEligibilityTest`, `ReleaseFlowTest`, `CorrectionAndKeyLifecycleTest`, `ApplicantAccessTest` (session flash keys `reference_code` → `application_no`); Vitest where props renamed.
- **Dependencies & order:** after T1.1; parallel with T1.4/T1.5 (same change, no cross-file conflicts beyond shared grep).
- **Risks & open questions:** `ApplicantApplyController` flash keys are session-backed — rename both write (submit) and read (confirmation) in the same commit; a stale key silently empties the confirmation box.

### T1.7 — Code: frontend props/copy (CODE)
- **Goal:** Svelte props renamed to `application_no`; visible copy uses "Application No." (never "Reference"/"Ref"/"reference").
- **Scope (exact):** `Admission/Confirmation.svelte` (prop 10, label "Applicant reference" → "Application No." at 62, copy 55-58), `Applicant/Portal.svelte` (keeps `identity.applicant_no` — verify), `Applicant/MyApplication.svelte` (66 "Ref" → "Application No."), `Applicant/Consultation.svelte` (115), `Applicant/Schedule.svelte` (121, 262 copy in controller payload), `Applicant/Correction.svelte` (51/69), `Admission/ApplicantAdmissionSupport.svelte` (155/162/164), `Admission/Index.svelte` (406/465), `Admission/Show.svelte` (270/274/280 "Reference …"), `Assessment/GradingScoreInput.svelte` (51/90/117/198), `Assessment/GradingSession.svelte` (160), `Consultation/ConsultationSessionShow.svelte` (64-77/561/691 — `reference_number` prop), `Examination/Monitoring.svelte` (161), `Examination/Show.svelte` (43-54/353/443), `ProgramHead/ApplicantShow.svelte` (31/226), `ProgramHead/ProgramShow.svelte` (269/308/349/398/423/445), `Registrar/EnrollmentEligibility.svelte` (83/105), `Release/Consultation.svelte` (173/207/235), `Components/kit/ApplicantScanModal.svelte` (140 — roster `reference_code` → `application_no`; `detected_applicant_number` untouched), `Assessment/Omr/Capture.svelte` (150/946/973 — roster prop only; OMR domain fields untouched).
- **Steps:** rename props; replace copy; keep OMR-domain `applicant_number`/`detected_applicant_number` fields exactly as-is (different domain).
- **Acceptance:** `grep -ri "reference_code\|reference_number" system/resources/js` → zero hits (except OMR domain terms and legacy-comment strings if any); browser shows "Application No." on confirmation, portal, My Application, staff show pages.
- **Test mapping:** Vitest component updates (applicant-portal suite, grading session suite); Playwright journey check on confirmation → portal; exploratory-manual on staff pages.
- **Dependencies & order:** after T1.6 (prop source); same change.
- **Risks & open questions:** OMR Capture.svelte mixes roster (`reference_code`) and detection (`detected_applicant_number`) terms in one component — rename only the roster binding; do not touch `applicant_number` keys in `proposal_json`/`identity_regions` (OMR contract, out of scope).

### T1.8 — Code: AI projections (CODE)
- **Goal:** AI own-data payload + fake-gateway copy use Application No.
- **Scope:** `system/app/Ai/Applicant/ApplicantOwnDataProjector.php` (docblock `reference_code` → `application_no`, payload key), `system/app/Ai/Adapters/Fake/FakeAiGateway.php` (line 99-101: `$ref` from `application_no`, copy `" (Application No. {$ref})"`).
- **Steps:** rename; keep the projection shape otherwise stable (Nexi own-data contract).
- **Acceptance:** Inquiry own-data payload carries `application_no`; fake adapter copy reads it.
- **Test mapping:** Pest Feature — existing Nexi/own-data tests updated (grep the suite for `reference_code`).
- **Dependencies & order:** after T1.1; parallel with T1.6.
- **Risks & open questions:** any persisted Nexi proposal payloads referencing `reference_code` are dev-only; no backfill.

### T1.9 — Code: seeds + tests sweep (CODE)
- **Goal:** no seed/test asserts the old term.
- **Scope:** `system/database/seeders/` (no direct `reference_code` today — verify; seeders call actions), `system/tests/Feature/` files listed in §2.1, `system/tests/frontend/` fixtures if any.
- **Steps:** rename assertions; re-run suite.
- **Acceptance:** `grep -ri "reference_code\|reference_number" system/tests system/database/seeders` → zero hits (OMR `applicant_number` terms excluded).
- **Test mapping:** full Pest + Vitest suite.
- **Dependencies & order:** last in Phase 1 (needs T1.1-T1.8 names in place).
- **Risks & open questions:** none.

### T1.10 — Decision/record: legacy-facing template placeholder contract (DOCUMENTATION-ONLY)
- **Goal:** record that greenfield printing emits `applicant_no`/`applicant_number` only; `applicant_reference` remains a legacy-only deprecated alias.
- **Scope:** evidence: `legacy_codebase/application/app/Services/ResultSheetTemplateService.php` (placeholders 37-44, deprecated label 432), `AdmissionSlipTemplateService.php` (18-27); the printing workstream's future template pipeline.
- **Steps:** write the decision into the future OpenSpec proposal (§ mapping): legacy template files may keep `applicant_reference`/`reference_number` placeholders for old .docx/.odt templates, but greenfield rendering code (when built) maps the canonical `application_no` value into `applicant_number` + `applicant_no` keys only; `applicant_reference` never receives a value from greenfield.
- **Acceptance:** decision recorded; no code change.
- **Test mapping:** none.
- **Dependencies & order:** independent; must land in the change before printing workstream consumes Application No.
- **Risks & open questions:** printing templates authored with `applicant_reference` will render empty unless the printing workstream updates template files — flag in cross-workstream dependencies.

---

## 4. Phase 2 — Applicant auth rework (stateless Application No. + key, defenses, recovery)

**Phase goal:** close every auth gap in §2.2: key-format decision, per-Application-No lockout (3× → 30 min), IP rate limiting (10/min), bot protection (Turnstile), explicit remember-me, resend/reissue recovery, full audit, staff-auth separation proof. Birthdate stays a knowledge factor, never a credential.

### T2.1 — Decision: final access-key format (DECISION + CODE — [SECURITY])
- **Goal:** reconcile D-085's 8-char key with the 6-char proposal; lock the format; tighten shape validation.
- **Scope:** `system/app/Admission/Support/ApplicantAccessKey.php` (constants, alphabet, `isValidShape` regex), `system/app/Http/Requests/Applicant/ApplicantLoginRequest.php` (validation copy), `docs/greenfield-foundation/decisions/D-085-applicant-access-credential.md` (update) + new ADR entry.
- **Steps:** 1) **Recommendation: keep 8-char** `XXXX-XXXX` — D-085 wording ("8 unambiguous chars"), 40 bits entropy from a 32-symbol alphabet (vs 30 bits at 6 chars) for a durable (1-year, reissue-only rotation) credential; hashing is `sha256(normalized)` and unchanged; existing minted keys/hashes remain valid — no migration. A later switch to 6 would force mass reissue (hashes are keyed to length via shape check) — document that cost. 2) Fix `isValidShape` regex `/^[A-Z2-9]+$/` → `/^[A-HJ-KM-NP-Z2-9]+$/` (exclude I/L/O/0/1 to match the mint alphabet — current regex accepts un-mintable characters) [SECURITY]. 3) Update `ApplicantLoginRequest` error copy to keep `ABCD-EFGH` format text. 4) Record the decision in the change + D-085 update.
- **Acceptance:** decision recorded with rationale; `isValidShape('ABCD-EFGH')` true, `isValidShape('ABCD-EFOI')` false, `isValidShape('ABCD-EF01')` false; hash function untouched; all pinned demo keys (`PORT-ONLN` etc.) still validate.
- **Test mapping:** Pest Unit — `ApplicantAccessKeyTest`-style: alphabet coverage, shape rejections, normalize/format/hash round-trip, determinism.
- **Dependencies & order:** FIRST in Phase 2 (auth flows depend on the locked shape).
- **Risks & open questions:** open question — is 6-char driven by a paper-space constraint (slips/QR)? If yes, record why 8 was retained (entropy + D-085) and note OMR bubble-field length is a separate concern (out of scope).

### T2.2 — DB migration: lockout + attempt ledger (MIGRATION — [SECURITY])
- **Goal:** durable, DB-backed lockout state per Application No. + an auth-attempt ledger for audit/forensics.
- **Scope:** new migration creating `applicant_login_lockouts` (`application_no` string unique PK, `failed_attempts` unsigned int, `locked_until` timestamp nullable, `last_failed_at` timestamp, timestamps) and `applicant_login_attempts` (id, `application_no` nullable, `applicant_identity_id` nullable FK, `outcome` string success|failure|locked, `ip_address` string nullable, `attempted_at` timestamp, timestamps; index `(application_no, attempted_at)`). Alternative considered: columns on `applicant_access_challenges` — rejected (challenges are per-attempt/per-key; lockout is per-Application-No across attempts and must survive reissue).
- **Steps:** write migration + `down()`; register models `ApplicantLoginLockout` / `ApplicantLoginAttempt` under `system/app/Models/Admission/` (or `Auth/` — recommend `Admission/` next to the challenge model); set `$table`, fillable, casts.
- **Acceptance:** `foundation:reset --force` creates both tables; unique on `application_no`; indexes present; down() drops cleanly.
- **Test mapping:** Pest Feature migration smoke (schema assertions) — or covered by T2.3 behavioral tests.
- **Dependencies & order:** before T2.3/T2.4; parallel with T2.5/T2.6 migrations.
- **Risks & open questions:** idempotency — standard migrations run once; keep `Schema::hasTable` guard if repo convention demands. Open question — should the lockout key be `application_no` (D-4 wording: per-Application-No) or identity? Locked: per-Application-No (D-4). Note: same identity re-applying next cycle gets a new Application No. → fresh lockout state (acceptable; IP limiter still covers abuse).

### T2.3 — Code: lockout enforcement in auth action (CODE — [SECURITY])
- **Goal:** 3 failed attempts → 30-min lockout per Application No.; generic failure messages; success resets; full audit.
- **Scope:** `system/app/Admission/Actions/AuthenticateApplicantWithAccessKey.php` (extend `handle`), `system/app/Admission/Support/ApplicantLoginGuard.php` (new support class — lockout read/record/check), `RecordAuditEvent` call sites.
- **Steps:** 1) resolve candidate `application_no` by hashing? **No** — the key is hashed; the lockout must key on the *public* Application No. which the applicant types on the login form. Decide the lockout identifier: the challenge lookup happens by `token_hash` — before that, the form only has the key. Resolve: normalize the input key, look up the challenge by hash first (cheap, one query), then key the lockout on that challenge's attempt's `application_no`. Failure paths that never resolve a challenge (bad shape/no match) key on a sentinel/`unknown` bucket + IP limiter (T2.4). 2) On failure: increment `failed_attempts`, set `locked_until = now()+30min` when count reaches 3, write `applicant_login_attempts` row (outcome=failure), audit `admission.login_failed` (application_no when known, else `identity:unknown`). 3) On lockout hit: reject before credential check with generic message + audit `admission.login_locked`. 4) On success: delete/clear lockout row, write ledger row (outcome=success), audit `admission.applicant_login` (add `application_no`). 5) All user-facing messages stay the single generic string — no enumeration.
- **Acceptance:** 3 wrong keys → 4th attempt rejected even with the correct key until 30 min pass; correct key + birthdate succeeds after window; ledger rows written per attempt; audit events present; messages identical across all failure modes.
- **Test mapping:** Pest Feature — new `ApplicantLoginLockoutTest`: strike counting, lockout trigger, expiry, success reset, generic-message non-enumeration, ledger rows, audit events.
- **Dependencies & order:** after T2.2; before T2.4 (limiter composes with the same action).
- **Risks & open questions:** [SECURITY] timing side-channels (hash lookup vs shape check order) — keep the existing constant-ish flow; do not short-circuit early with distinguishable errors. Concurrency: `lockForUpdate` on the lockout row inside the action's transaction.

### T2.4 — Code: IP rate limiting 10/min (CODE — [SECURITY])
- **Goal:** replace `throttle:5,1` with a named 10/min-per-IP limiter; keep generic responses.
- **Scope:** `system/routes/web.php` (line 70-72), new `App\Http\Middleware\ApplicantLoginThrottle` or `RateLimiter::for('applicant-login')` in `system/app/Providers/AppServiceProvider.php` (or `RouteServiceProvider` — verify which provider exists), `AuthenticateApplicantWithAccessKey` unaffected.
- **Steps:** define named limiter `applicant-login` = `RateLimiter::perMinute(10)` keyed by IP (`$request->ip()`); apply to POST `/applicant/login`; return the standard `Too Many Attempts` 429 with `Retry-After`; audit a `admission.login_rate_limited` event (or rely on 429 middleware — decide; recommend explicit event for forensics).
- **Acceptance:** 11 rapid POSTs → 11th gets 429; limiter keyed per IP (two IPs independent); audit row present on throttle.
- **Test mapping:** Pest Feature — `ApplicantLoginThrottleTest` (11 attempts, 429, Retry-After, distinct-IP independence).
- **Dependencies & order:** after T2.3 (same action surface); can land in parallel.
- **Risks & open questions:** cache driver is `database` by default — DB-backed RateLimiter works but adds a query per login; note Redis option for prod. `throttle:5,1` removal must be atomic with the new limiter (no double-limiting).

### T2.5 — Code: bot protection (Turnstile) (CODE — [SECURITY])
- **Goal:** Cloudflare Turnstile (or equivalent) on the applicant login (and apply) surfaces; explicit local/testing bypass.
- **Scope:** `system/config/services.php` (`turnstile.site_key`, `turnstile.secret_key`, `turnstile.enabled`), new validation rule `system/app/Rules/TurnstileToken.php` (server-side verify against `https://challenges.cloudflare.com/turnstile/v0/siteverify`), `ApplicantLoginRequest` (add `cf-turnstile-response` rule when enabled), `resources/js/Pages/Applicant/Login.svelte` + `Admission/Confirmation.svelte` (widget + token submit), possibly `Admission/Apply.svelte` (same bot surface — decide scope: login mandatory, apply recommended).
- **Steps:** 1) config keys + `.env.example` entries (no secrets in repo). 2) rule: when `enabled` false (local/testing), always pass; else verify token, fail with generic message. 3) inject site key prop into login/confirmation pages; widget renders only when enabled. 4) tests with disabled config + mocked verify.
- **Acceptance:** with `turnstile.enabled=true`, login POST without a valid token fails generically; widget renders; local/testing default (enabled=false) unchanged; no console errors.
- **Test mapping:** Pest Feature — `ApplicantLoginBotProtectionTest` (disabled bypass, enabled-with-mock verify, missing token rejection); Vitest — widget render/no-render by prop.
- **Dependencies & order:** independent of T2.2-T2.4; order after T2.1 (shape) and before T2.7 (recovery flows should not bypass protection).
- **Risks & open questions:** founder said "e.g. Turnstile" — provider is a decision; this task assumes Turnstile with a config seam that allows another provider later. Third-party call in the request path — timeout/retry policy + degrade behavior when Turnstile API is down (recommend fail-open in dev, fail-closed in prod behind a config flag; decide explicitly).

### T2.6 — DB + Code: remember-me explicit opt-in (MIGRATION + CODE — [SECURITY])
- **Goal:** opt-in persistent session via Laravel remember-token; session persistence remains default (D-3).
- **Scope:** migration adding `remember_token` (string 100, nullable) to `users`; `system/app/Http/Controllers/Applicant/ApplicantPortalController.php::login` (`Auth::guard('web')->login($user, $request->boolean('remember'))`); `ApplicantLoginRequest` (add `remember` boolean, nullable); `resources/js/Pages/Applicant/Login.svelte` (checkbox mirroring `Auth/Login.svelte` line 67-70 pattern); `Admission/Confirmation.svelte` continue-form (same opt-in checkbox; default unchecked).
- **Steps:** 1) migration + `down()`. 2) `User` model: no fillable change needed (token managed by auth). 3) controller honors the flag; session regeneration stays. 4) checkbox copy: explicit ("Keep me signed in on this device" with shared-device caveat); **no checkbox = no token** (default). 5) tests: default-off asserts no `remember_token` write; opt-in writes + restores session across browser close (via cookie simulation).
- **Acceptance:** un-checked → no `users.remember_token` change; checked → token set and `remember_web_*` cookie issued; logout clears token; copy explicit.
- **Test mapping:** Pest Feature — `ApplicantRememberMeTest` (opt-in/opt-out, cookie presence, logout revocation); Vitest — checkbox default unchecked.
- **Dependencies & order:** independent; after T2.1.
- **Risks & open questions:** [SECURITY] remember token = bearer credential; ensure `Auth::logout` revokes it (Laravel default when using `logoutOtherDevices` semantics — verify `logoutCurrentDevice`/`logout` clears token); shared-device copy must warn. Open question — should applicant remember-me be disabled entirely until a device-trust review? Recommendation: ship opt-in per D-3 with copy + revocation tests.

### T2.7 — Code: recovery = resend/reissue (CODE — [SECURITY])
- **Goal:** close the D-085 email-backup gap (key emailed at submission) and provide "resend" that mints a new key (never resends plaintext of an old key — hash-only storage makes that impossible); keep registrar reissue.
- **Scope:** `system/app/Admission/Actions/SubmitAdmissionAttempt.php` (dispatch a `NotificationIntent` carrying the plaintext key **only to the applicant's email**, in-app notification carries NO key), `system/app/Admission/Actions/ReissueApplicantAccessKey.php` (unchanged behavior; rename payload key per T1.4), new `system/app/Admission/Actions/RequestApplicantAccessKeyResend.php` (applicant-facing: verifies identity via existing valid key+birthdate OR registrar-assisted flow; mints new key, revokes prior challenges — reuse the reissue inner logic, dedupe via an extracted `RotateApplicantAccessKey` support service), `NotificationIntent`/`NotificationRecipients` usage, `system/app/Http/Controllers/Applicant/` (new resend request route or fold into login "forgot key" — decide), `system/routes/web.php`.
- **Steps:** 1) extract key-rotation core (revoke-all + mint + issue + version bump) shared by registrar reissue and applicant resend. 2) submission-time mail intent with `redaction_class` marking the key payload sensitive; confirm `MailDeliveryChannel`/`NotificationMailable` renders it. 3) applicant resend endpoint with bot protection (T2.5) + rate limit + lockout semantics; response always generic ("If the key exists for this account, a new one was sent") to avoid enumeration. 4) audit `admission.access_key_resend_requested` + existing `admission.access_key_reissued`; add `application_no` payload (T1.4). 5) **Staff plaintext-key viewing: DO NOT build** (D-5; separate security review required) — note the boundary in the change.
- **Acceptance:** submission emails the key once (when mail channel enabled); resend mints + revokes + emails; old key stops working immediately after resend; generic responses; audit rows present; registrar reissue path unchanged except payload key.
- **Test mapping:** Pest Feature — `ApplicantKeyRecoveryTest`: submit-email intent payload (key present for mail channel, absent for in-app), resend rotates + revokes (old key rejected, new key accepted), enumeration-safe responses, audit events.
- **Dependencies & order:** after T2.5 (bot protection on the new endpoint); uses T1.4 payload rename.
- **Risks & open questions:** [SECURITY] plaintext key in notification payload + mail transport — the mail channel is the only place plaintext may travel post-submit; ensure in-app payload strips the key (`redaction_class` + `NotificationProjection` never serializes it); log redaction. Open question — applicant-initiated resend without a valid session (forgot everything): registrar-assisted only (D-085 E2 scope), so the endpoint requires an authenticated applicant OR the registrar flow; do not build self-service email-based reset without a security review.

### T2.8 — Code: staff-auth separation audit (CODE — [SECURITY])
- **Goal:** prove applicant sessions cannot reach staff surfaces and staff sessions cannot act as applicants; fix any hole.
- **Scope:** `system/app/Authorization/CapabilityAuthorizer.php`, `system/app/Authorization/ActorScope.php`, `ApplicantCapabilityGrants.php` (grant surface), route middleware in `system/routes/web.php`, `ApplicantPortalController::dashboard` (any-user gate), staff controllers.
- **Steps:** 1) audit: applicant user (random password, applicant capabilities only) hitting staff routes → every staff page/action gate on `CapabilityAuthorizer::allows` (verify no capability-less staff route exists; grep controllers for missing authorizer usage). 2) staff user hitting `/applicant/portal` → projection returns `empty` (identity-less) or a truthful "no application" state — verify no data leak. 3) mixed-role edge: a user who is BOTH staff and applicant (same email collision blocked in `AuthenticateApplicantWithAccessKey` — line 61-64 throws when staffProfile exists; keep + test). 4) document the boundary decision (single `web` guard is accepted because capability matrix is the wall; a separate applicant guard is a rejected alternative — record ADR-style note).
- **Acceptance:** new Pest tests assert 403/empty for both directions; no applicant capability grants staff pages; collision path tested.
- **Test mapping:** Pest Feature — `ApplicantStaffSeparationTest` (applicant→staff 403/redirect, staff→portal empty, email-collision rejection).
- **Dependencies & order:** independent; can run parallel with T2.3-T2.6.
- **Risks & open questions:** if the audit finds a capability-less staff route, that route must be gated in this task — scope may grow; escalate rather than silently shipping.

### T2.9 — Code: auth test suite consolidation (CODE)
- **Goal:** one coherent suite covering the D-4 defense stack end-to-end.
- **Scope:** `system/tests/Feature/Applicant/` (new files from T2.3-T2.7) + `ApplicantAccessTest.php` updates.
- **Steps:** after T2.3-T2.7 land, run the full applicant suite; fix interactions (e.g., lockout test must not trip IP limiter and vice versa — use distinct IPs/keys).
- **Acceptance:** full suite green; each defense has at least one failing-path test.
- **Test mapping:** Pest Feature suite.
- **Dependencies & order:** LAST in Phase 2.
- **Risks & open questions:** test isolation — ledger/lockout rows must be cleaned per test (RefreshDatabase).

---

## 5. Phase 3 — Cross-cycle visibility (one attempt per cycle; later cycle = new attempt, prior preserved)

**Phase goal:** applicant portal shows all attempts with a cycle switcher ("My applications"); registrar eligibility + consultation dashboard + release search are cycle-scoped and carry cycle in rows; every record stays attempt-specific (never person-merged).

### T3.1 — Code: multi-attempt portal projection (CODE)
- **Goal:** `ApplicantPortalProjection::for` projects the identity's attempts per cycle (latest first), defaulting to the latest; each attempt gets its own spine/stages.
- **Scope:** `system/app/Applicant/Support/ApplicantPortalProjection.php` (replace `orderByDesc('id')->first()` with a per-identity attempts query: `where('applicant_identity_id', $identityId)->with('academicCycle:id,code,name,school_year,semester')->orderByDesc('academic_cycle_id')->orderByDesc('id')->get()`); payload gains `applications` (list of `{attempt_id, application_no, cycle: {code,name,staffLabel}, status, submitted_at}`) + `active_attempt_id`; existing `stages`/`identity`/`notices` remain for the active attempt.
- **Steps:** 1) rewrite `for()` to load all attempts (small N; no pagination needed now — note scale guard later). 2) keep release/result resolution per attempt. 3) add cycle metadata per attempt. 4) `empty` state unchanged (no attempts at all). 5) audit: none (read-only projection).
- **Acceptance:** portal payload lists every attempt with cycle + application_no; stages reflect the selected attempt; latest attempt is default.
- **Test mapping:** Pest Feature — extend `ApplicantPortalInterfaceTest` with a two-cycle fixture (later attempt + prior released attempt): both listed, stages per attempt, prior result preserved.
- **Dependencies & order:** before T3.2/T3.3; requires Phase 1 names.
- **Risks & open questions:** performance — all attempts materialized per hub load; fine at dev scale, note pagination seam for later. `orderByDesc('academic_cycle_id')` assumes id order ≈ cycle chronology (verify seed ordering; use `opens_on` if needed).

### T3.2 — Code: portal "My applications" cycle switcher (CODE)
- **Goal:** `Applicant/Portal.svelte` renders the application list + switcher; switching changes the visible spine via server-side selection (query param or Inertia visit), never client-side stage reordering.
- **Scope:** `system/resources/js/Pages/Applicant/Portal.svelte`, `ApplicantPortalController::dashboard` (accept `?application=`/`?attempt=` selection, re-project), possibly `Applicant/MyApplication.svelte` (cycle label on the read-only page).
- **Steps:** 1) server-authored selector: hub payload includes `applications`; controller validates the selected attempt belongs to the user (`ApplicantScope::attemptBelongsToUser`) and re-projects. 2) UI: "My applications" section listing Application No. + cycle (staff vocabulary: "2025-2026 · Semester 1" via `staffLabel`) + status; active row highlighted; switching is a link (no client reorder). 3) deep-link to `MyApplication` per attempt unchanged.
- **Acceptance:** founder/browser can switch cycles and see that attempt's truthful stages; prior-cycle dismissed/archived attempts show terminal cards (not empty spine — existing behavior preserved per attempt); own-scope enforced.
- **Test mapping:** Pest Feature — selection param ownership (403 for another user's attempt, 404/redirect for bogus id); Vitest — switcher renders list, active state; Playwright journey — switch between two seeded cycles.
- **Dependencies & order:** after T3.1.
- **Risks & open questions:** UI lock — follow applicant-shell design (`2026-08-15-applicant-portal-interface/design.md` §2 nav + breadcrumbs); no new chrome.

### T3.3 — Code: portal controller multi-attempt wiring (CODE)
- **Goal:** `dashboard`/`applicationShow`/`correctionForm` fully cycle-aware.
- **Scope:** `system/app/Http/Controllers/Applicant/ApplicantPortalController.php` (dashboard selection param; applicationShow already per-attempt — add cycle breadcrumb), `system/resources/js/Pages/Applicant/MyApplication.svelte` (breadcrumb + "Application No." + cycle label).
- **Steps:** thread the selected attempt through; keep `ApplicantScope::attemptBelongsToUser` on every attempt-bound route (already present — verify + test).
- **Acceptance:** every applicant route bound to an attempt id enforces own-scope regardless of cycle.
- **Test mapping:** Pest Feature — cross-cycle own-scope tests (attempt from another cycle/user rejected).
- **Dependencies & order:** after T3.1; parallel with T3.2.
- **Risks & open questions:** none.

### T3.4 — Code: registrar eligibility cycle filter (CODE)
- **Goal:** `EnrollmentEligibilityQuery` becomes cycle-scoped; rows carry cycle; UI filter.
- **Scope:** `system/app/Consultation/Queries/EnrollmentEligibilityQuery.php` (`search(string $query, ?string $cycleCode = null)` — when null, scope to active cycle via `AcademicCycle::active()`; add `academic_cycle_id`/`cycle_code` to row payload; `cardFor` unchanged except payload key), `system/app/Http/Controllers/Registrar/EnrollmentEligibilityController.php` (pass query param), `resources/js/Pages/Registrar/EnrollmentEligibility.svelte` (cycle selector + cycle column), `system/routes/web.php` (query param already passes through).
- **Steps:** 1) default scope = active cycle (staff vocabulary label). 2) optional explicit cycle override. 3) row payload `application_no`, `name`, `status`, `cycle_code`, `cycle_name`. 4) UI selector mirrors `ListAdmissionAttemptsQuery` cycle filter pattern.
- **Acceptance:** search within active cycle returns only that cycle's attempts; explicit cycle filter works; rows show cycle; a same-name applicant across two cycles appears as two distinct rows (attempt-specific, not person-merged).
- **Test mapping:** Pest Feature — extend `EnrollmentEligibilityTest` (cycle default, explicit filter, cross-cycle duplicates distinct); Vitest — selector render.
- **Dependencies & order:** after Phase 1 (rename) + T1.6; before T4.2 (reuse fact integrates here).
- **Risks & open questions:** active-cycle default could hide history — acceptable (Registrar counter is current-cycle work surface; history via explicit filter).

### T3.5 — Code: consultation dashboard cycle scoping (CODE)
- **Goal:** close G-CYC-3 — every dashboard set is cycle-scoped when a cycle is selected.
- **Scope:** `system/app/Consultation/Queries/ConsultationDashboardQuery.php` (`for(?string $cycleCode = null)`): scope `sessionsToday` (whereHas cycle or join `consultation_sessions.academic_cycle_id`), `scheduledAttemptIds`, `anyScheduledIds`, `advisedIds`, `pendingAdvise` to the selected cycle (assignment/decision rows join through `consultation_sessions.academic_cycle_id`); `notYetScheduled` already derives from cycle-scoped `$attempts` but its readiness filter must not pull other cycles (verify).
- **Steps:** 1) when `$cycle !== null`, add `where('academic_cycle_id', $cycle->id)` to session queries and derive assignment/decision id sets from cycle-scoped sessions. 2) when `$cycle === null` (no active cycle configured), keep current unfiltered behavior but document it as a degraded mode; prefer defaulting to active cycle like `ConsultationSessionListQuery` (lines 52-60). 3) rows already carry `reference_code` → `application_no` (Phase 1).
- **Acceptance:** with two cycles seeded, dashboard stats/counts only include the selected cycle's sessions/assignments/decisions; no cross-cycle bleed in `today_scheduled`/`pending_advise`/`not_yet_scheduled`.
- **Test mapping:** Pest Feature — `ConsultationDashboardCycleTest` (two cycles, verify each stat excludes the other cycle).
- **Dependencies & order:** after Phase 1; parallel with T3.4.
- **Risks & open questions:** `advisedIds`/`anyScheduledIds` are currently global `ConsultationDecision`/`ConsultationAssignment` scans — cycle-scoping them requires the join through `consultation_sessions` (assignments) and decisions' attempts' `academic_cycle_id` (decisions table has no cycle column — join via `admission_attempts`). Note the query-shape change.

### T3.6 — Code: release/consultation search cycle predicate (CODE)
- **Goal:** `ReleaseController::consultation` search scoped to the actor's active cycle (G-CYC-4).
- **Scope:** `system/app/Http/Controllers/Release/ReleaseController.php` (`consultation`, lines 36-58): add `->where('academic_cycle_id', $activeCycleId)` (resolve from `$request->user()->actorScope()->activeCycleCode` → `AcademicCycle` id; if no active cycle, return empty results + truthful state, not unfiltered), include `cycle_code` in search-result rows.
- **Steps:** 1) resolve active cycle id once. 2) scope the accepted-attempt search. 3) single-match redirect unchanged. 4) `ConsultationDashboardQuery::for($cycle)` already receives the same cycle — consistent.
- **Acceptance:** searching a name with attempts in two cycles returns only the active cycle's rows; zero active cycle → empty results with clear page state.
- **Test mapping:** Pest Feature — `ReleaseConsultationCycleTest` (cross-cycle search isolation, no-active-cycle state).
- **Dependencies & order:** after Phase 1; parallel with T3.5.
- **Risks & open questions:** actorScope `activeCycleCode` may be null for some roles — verify guidance users always have one; if not, gate explicitly.

### T3.7 — Code: attempt-specific record integrity audit (CODE/TESTS)
- **Goal:** prove no query person-merges across attempts; fix any found.
- **Scope:** audit all queries in §2.3 that filter by identity: `ApplicantPortalProjection::for` (fixed in T3.1), `ApplicantOwnDataProjector::forUser` (check — should mirror portal selection), `ApplicantScope` helpers; verify child-record FKs remain attempt-scoped (`exam_assignments`, `score_records`, `result_releases`, `consultation_decisions`, `program_selections`, `enrollment_stamps` all keyed by `admission_attempt_id`).
- **Steps:** 1) grep for `where('applicant_identity_id'` and `applicantAccount` joins outside the portal/projection layer. 2) add cross-cycle isolation tests: same identity, two attempts, one released — portal, eligibility, dashboard, release search, grading, program-head lists each expose the correct attempt's data.
- **Acceptance:** no identity-level data merges; test matrix green.
- **Test mapping:** Pest Feature — cross-cycle isolation suite.
- **Dependencies & order:** after T3.1-T3.6 (surfaces exist to test).
- **Risks & open questions:** `ApplicantOwnDataProjector` (AI) latest-attempt selection mirrors the portal — must be updated alongside T3.1 or the AI assistant answers from a stale attempt; coordinate.

### T3.8 — Code: cross-cycle key/login semantics (CODE/DECISION)
- **Goal:** locked decision: an applicant's old-cycle key remains valid (challenges are not consumed on success) and signs them into their identity → portal shows all cycles; new-cycle submit mints a NEW key bound to the new attempt.
- **Scope:** `system/app/Admission/Actions/SubmitAdmissionAttempt.php` (already mints per attempt — verify + document), `AuthenticateApplicantWithAccessKey` (unchanged), `ApplicantPortalProjection` (T3.1), tests.
- **Steps:** 1) document the semantics in the change: challenge is per-attempt; auth is per-identity; success does NOT consume; reissue revokes all unconsumed for the identity. 2) test: login with cycle-1 key → see cycle-1 + cycle-2 attempts; reissue revokes both (already the behavior — verify the revoke-all scope in `ReissueApplicantAccessKey`).
- **Acceptance:** described semantics proven by tests.
- **Test mapping:** Pest Feature — cross-cycle login + reissue revocation test.
- **Dependencies & order:** after T3.1; can parallel T3.4.
- **Risks & open questions:** none beyond recording.

---

## 6. Phase 4 — Result-reuse policy seam (derive, never copy)

**Phase goal:** a policy/query seam that derives "prior attempt may satisfy this cycle" facts from preserved prior attempts; configurable; integrated into eligibility + portal; audited on application; no data movement.

### T4.1 — Design + Code: prior-attempt derivation query + config seam (CODE)
- **Goal:** `PriorAttemptResultQuery` derives reuse facts; `config/admission.php` exposes policy knobs.
- **Scope:** new `system/app/Admission/Queries/PriorAttemptResultQuery.php` — `factsFor(ApplicantIdentity $identity, AcademicCycle $cycle, ?string $programCode = null): PriorAttemptResultFacts` (data object: `has_eligible_prior`, `prior_attempt_id`, `prior_cycle_code`, `prior_status`, `prior_result_released`, `prior_program_code`, `applicable`); new `system/app/Admission/Data/PriorAttemptResultFacts.php`; new `system/config/admission.php` — `result_reuse.enabled` (bool, default false), `lookback_cycles` (int, default null = all), `require_result_released` (bool default true), `require_same_program` (bool default true), `exclude_statuses` (default `[dismissed, archived]`); mirror `AttemptProgramFacts` read-only style (never persists).
- **Steps:** 1) query prior attempts of the identity for `academic_cycle_id < current` (or any prior cycle), apply policy knobs, return facts. 2) **Never copy/move/overwrite** — the prior attempt rows, releases, and documents are untouched; the new attempt's own pipeline remains authoritative. 3) `enabled=false` → facts return `applicable=false` (no behavior change until founder flips).
- **Acceptance:** with config default (disabled), zero behavior change; enabling returns derived facts only; prior rows byte-identical before/after (assert in test).
- **Test mapping:** Pest Feature/Unit — `PriorAttemptResultQueryTest`: released-prior eligible, unreleased-prior not, dismissed/archived excluded, lookback window, same-program rule, enabled=false no-op.
- **Dependencies & order:** independent of Phase 3 (but integrates in T4.2); after Phase 1 naming.
- **Risks & open questions:** open question — definition of "may satisfy": released result in prior cycle + same program + applicant not enrolled elsewhere? Leave the *business rule* as config knobs with conservative defaults; founder decides exact semantics at enablement. No campus dimension (D-7).

### T4.2 — Code: eligibility + portal integration (CODE)
- **Goal:** reuse facts surface on the registrar eligibility card and the applicant portal notice.
- **Scope:** `system/app/Consultation/Queries/EnrollmentEligibilityQuery.php::cardFor` (add `prior_result` fact block: `{eligible: bool, prior_cycle: ?string, prior_application_no: ?string, prior_program: ?string}` when `config('admission.result_reuse.enabled')`), `resources/js/Pages/Registrar/EnrollmentEligibility.svelte` (fact chip "Prior result may satisfy this cycle"), `ApplicantPortalProjection` (notice when facts applicable: "Your previous result may satisfy this application — the Registrar will verify."), `Applicant/Portal.svelte` notices block.
- **Steps:** 1) wire the query into cardFor + projection with config guard. 2) copy per GF-22 truthful-state rules (never claims acceptance). 3) no write path.
- **Acceptance:** card + portal show the derived notice only when facts applicable and config enabled; nothing otherwise.
- **Test mapping:** Pest Feature — eligibility card fact, portal notice; Vitest — chip/notice render states.
- **Dependencies & order:** after T4.1 + T3.4 (card cycle-scoped).
- **Risks & open questions:** copy review — the notice must not promise admission; phrased as verification step.

### T4.3 — Code: audit + idempotency for reuse application (CODE)
- **Goal:** if/when the registrar (or policy) marks a new attempt as covered by a prior result, that decision is a one-time idempotent, audited action — never silent.
- **Scope:** new `system/app/Admission/Actions/ApplyPriorResultCoverage.php` (idempotency-keyed: `prior_coverage:<identity>:<target_cycle>`; validates facts still true; writes a `prior_result_coverage` marker row — new table via migration; audits `admission.prior_result_covered` with `prior_attempt_id`, `target_attempt_id`, `application_no`s, `policy_snapshot`), new migration `prior_result_coverages` (unique `(target_attempt_id)`, FK both attempts, `applied_by_user_id` nullable, `policy_snapshot json`).
- **Steps:** 1) migration (idempotent, unique guard). 2) action: re-derive facts at apply time (never trust a stale read), write marker in the same transaction as audit, reject on conflict. 3) expose via a registrar action (button on the eligibility card) gated by existing registrar capability. 4) **Reads of coverage are projections; coverage never mutates the prior attempt or copies its result.**
- **Acceptance:** apply once succeeds; re-apply with same key is a no-op; facts changed between read and apply → typed conflict; audit row present with policy snapshot.
- **Test mapping:** Pest Feature — coverage action: happy path, idempotent replay, stale-facts conflict, audit assertions.
- **Dependencies & order:** after T4.1 (facts are the apply-time re-derivation source) + T4.2.
- **Risks & open questions:** whether coverage should exist at all vs. pure read-only flagging is a product decision — this task builds the *seam* (action + marker) so the founder can flip the workflow later; keep it minimal and flag "workflow UI deferred until founder defines coverage semantics".

### T4.4 — Code: cross-cycle policy tests (CODE)
- **Goal:** whole-seam behavior tests across cycles.
- **Scope:** `system/tests/Feature/Admission/PriorResultReuseTest.php`.
- **Steps:** seed identity with cycle-1 released result; submit cycle-2 attempt; assert facts, card, notice, coverage action, prior-row immutability (checksum before/after), reissue/archive interactions.
- **Acceptance:** suite green; immutability assertion passes.
- **Test mapping:** Pest Feature.
- **Dependencies & order:** last in Phase 4.
- **Risks & open questions:** none.

---

## 7. Phase 5 — Documentation, seeds, HANDOFF

**Phase goal:** founder-reviewable records, developer-ready demo data, and a HANDOFF that satisfies project-context §3.7.

### T5.1 — Docs: decisions + master reference (DOCUMENTATION)
- **Scope:** `docs/GREENFIELD-MASTER-REFERENCE.md` (checkpoint entry), `docs/project-context.md` (work-rule updates if the auth model changed), `docs/greenfield-foundation/decisions/D-085-applicant-access-credential.md` (update: 8-char locked, defenses, resend/reissue, remember-me opt-in), new decision or ADR for Application No. terminology + key format (T2.1) + cross-cycle attempt model (D-7).
- **Steps:** record every locked decision in §0 with file links; update master-reference checkpoint after the change ships.
- **Acceptance:** docs cite the exact decision text; no "reference code" terminology introduced in new doc copy.
- **Test mapping:** none (documents).
- **Dependencies & order:** parallel throughout; final polish at the end.
- **Risks & open questions:** none.

### T5.2 — Seeds: cross-cycle + reuse demo data (CODE)
- **Scope:** `system/database/seeders/ApplicantPortalDemoSeeder.php` (add a second-cycle attempt for one existing identity, preserving the prior cycle's released result; add a reuse-eligible identity), `config/seed.php` (new pinned keys if needed), `DatabaseSeeder` wiring.
- **Steps:** extend seeders idempotently (distinct identities/attempts; pinned keys; real actions for submit/release so state is authoritative); print keys + birthdates like `JourneyFixtureSeeder`.
- **Acceptance:** `foundation:reset --force` yields: an identity with two cycle attempts (prior released), portal switcher demo, eligibility + dashboard cycle demo data, and (when enabled) a reuse-eligible identity.
- **Test mapping:** Pest Feature smoke (seeder idempotency) + exploratory-manual demo walk.
- **Dependencies & order:** after Phase 3 (switcher) + Phase 4 (reuse) surfaces exist.
- **Risks & open questions:** keep the existing `PORT-*` keys stable (tests + founder muscle memory depend on them); add, don't rekey.

### T5.3 — HANDOFF: credentials + recreate + URLs (DOCUMENTATION)
- **Scope:** future active OpenSpec change's `HANDOFF.md` (modeled on `openspec/changes/2026-08-15-applicant-portal-interface/HANDOFF.md`).
- **Steps:** staff/applicant logins (keys + birthdates + `DEMO-*`/`PORT-*` + new cycle keys), seeder/reset commands, expected starting states per scenario, URLs (`/applicant/portal`, `/applicant/applications/{attempt}`, `/registrar/enrollment-eligibility`, `/consultation`, `/release/consultation`), and the auth-defense demo path (lockout: 3 wrong keys on one Application No.).
- **Acceptance:** a fresh developer can demo every new surface from the HANDOFF alone.
- **Test mapping:** none (documentation; verified by exploratory-manual).
- **Dependencies & order:** final.
- **Risks & open questions:** none.

### T5.4 — Tests: full-suite parity + Playwright journeys (CODE)
- **Scope:** run full Pest + Vitest; add Playwright journeys for: confirmation → portal (key shown once, save-on-device), login with wrong key ×3 → lockout message, remember-me opt-in restore, portal cycle switcher, eligibility cycle filter, consultation dashboard cycle isolation.
- **Steps:** port the new browser journeys into the existing Playwright setup (see `2026-08-15-applicant-portal-interface` evidence contract); no console errors.
- **Acceptance:** suite green; journeys pass at 390px + desktop.
- **Test mapping:** Playwright journeys + full Pest/Vitest.
- **Dependencies & order:** final validation.
- **Risks & open questions:** lockout journey must reset state (fresh DB or cleanup) to avoid flakiness.

---

## Cross-workstream dependencies

| Workstream | Dependency direction | What |
|---|---|---|
| **OMR / `omr-profile-test-readiness`** (other session, active) | OUT (this mission does not touch it) | OMR-domain `detected_applicant_number` / `applicant_number_proposal` fields are untouched by the rename; `OmrCaptureSheet`/`RunOmrSheetAnalysis`/`OmrCheckerDetection` stay as-is. `Capture.svelte` roster prop (`reference_code` → `application_no`) must land AFTER the OMR change's file ownership is clear (shared file) — coordinate via the other session before editing that file. |
| **Printing / result-sheet + admission-slip** (being built elsewhere) | IN: this mission's terminology feeds it; OUT: no printing tasks here | `PrepareResultDocument` placeholder copy change (`Ref:` → `Application No.:`) is cosmetic until printing owns real templates; the real contract is T1.10 — greenfield renders `applicant_number`/`applicant_no` values only; legacy `applicant_reference` stays deprecated-alias. Printing must not introduce `reference_code`/`reference_number` names. |
| **Nexi / Inquiry** | IN | `ApplicantOwnDataProjector` + `FakeAiGateway` rename (T1.8) and the AI latest-attempt selection must mirror the portal's multi-attempt behavior (T3.7); Nexi kernel rows use the same application capability keys — no new capabilities introduced by this mission. |
| **`2026-08-15-applicant-portal-interface`** (implemented; archive pending founder review) | IN | Its HANDOFF seeded keys (`PORT-DEFL`/`PORT-ONLN`/`PORT-F2F2`, birthdate `2003-09-25`) and login prefill must remain working through Phase 2; its login/confirmation pages are the edit surfaces for remember-me + Turnstile. |
| **`institutional-public-experience`** (implemented; archive pending) | IN | Home page CTAs and copy reference "Applicant sign in" — verify no "reference number" wording introduced there (grep during T1.9). |
| **Capability/authorization** | IN | `ApplicantCapabilityGrants` unchanged; T2.8 audit may surface a gating gap that needs a `CapabilityCatalog` addition — coordinate if found. |

## Proposed OpenSpec change mapping

**Recommendation: two future OpenSpec changes** (not one) — they differ in domain, risk profile, and review surface:

1. **`applicant-application-no-and-auth-defenses`** (terminology + auth rework = Phases 1 + 2 + T5.1/T5.3/T5.4 doc/test portions)
   - **Why separate:** terminology touches ~70 files mechanically but is *visible everywhere* (founder-facing copy + audit payloads + AI projections) and must be reviewable as one atomic rename; auth defenses are security-sensitive with their own review gate (D-5: staff plaintext-key reading requires separate security review) and their own acceptance tests. Bundling keeps the rename's blast radius reviewable without burying security work; splitting from lifecycle keeps the security review from blocking the lifecycle feature and vice versa.
   - **Delta specs (draft):** `terminology/application-no` (column + payload keys + copy; legacy placeholder contract), `auth/lockout-rate-limit-bot-protection`, `auth/remember-me-opt-in`, `auth/recovery-resend-reissue`, `auth/staff-separation-audit`, `auth/key-format-decision`.

2. **`applicant-cross-cycle-lifecycle-and-result-reuse`** (Phases 3 + 4 + remaining T5.2/T5.4)
   - **Why separate:** cross-cycle visibility changes user journeys and query semantics (portal switcher, eligibility/dashboard/release scoping) and the result-reuse seam introduces a configurable policy with an idempotent audited action — a product decision surface (what "may satisfy" means) that deserves its own review; the auth change must ship first so terminology is stable under it.
   - **Delta specs (draft):** `applicant/my-applications-cycle-switcher`, `registrar/eligibility-cycle-filter`, `consultation/dashboard-cycle-scoping`, `release/consultation-cycle-scoping`, `admission/prior-result-reuse-policy`.

**Rationale for two:** (a) founder review ergonomics — terminology/auth is a single "what the applicant sees and how they sign in" review; lifecycle/reuse is a "how cycles behave" review; (b) dependency — Phase 3/4 tasks assume Phase 1 names, so change 2 explicitly depends on change 1; (c) risk isolation — a security flag on change 1 (lockout/recovery) must not hold up the neutral cycle-scoping work, and change 2's policy knobs (`result_reuse.enabled=false` default) must not dilute the auth review.

**Change 1 tasks:** T1.1–T1.10, T2.1–T2.9, T5.1 (terminology+auth portions), T5.3 (auth portion), T5.4 (auth journeys).
**Change 2 tasks:** T3.1–T3.8, T4.1–T4.4, T5.1 (lifecycle portion), T5.2, T5.3 (lifecycle portion), T5.4 (lifecycle journeys).

Both changes: one active at a time (repo rule); `HANDOFF.md` per §3.7; master-reference checkpoint update on ship.
