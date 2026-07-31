# NEXIAM Architecture Audit and Refactor Blueprint

**Purpose:** the authoritative map for deciding what to preserve, what to refactor, what to replace, and how to add the capstone features without compounding inherited SecureCAT debt.

**Method:** source inspection, call-path tracing, live quality-tool execution, dependency audit, migration/model review, requirement-to-code comparison, and Laravel 12 documentation. Findings are tagged as **Observed**, **Measured**, **Requirement**, or **Decision needed** so that opinions do not become facts by repetition.

**Operating order:** **specifications → P0 safety/refactors → P1 features → P2 builds**. Do not scaffold a replacement application until the audit and architecture decisions are accepted.

---

## 1. Executive verdict

NEXIAM should **not** be treated as either a clean greenfield application or a hopeless legacy rewrite.

It is a working Laravel monolith with substantial reusable admissions, scheduling, proctoring, grading, release, document-generation, notification, and RAG infrastructure. Its main risk is that important workflows are distributed across large controllers, large services, model hooks, direct queries, notifications, and role checks without characterization tests. Adding OMR, admission-support engines, a triage dashboard, Program Head access, messaging, identifier-key authentication, and chained auditing directly onto those seams would make the capstone harder to verify and harder to defend.

The recommended strategy is a **strangler-style modernization inside the existing Laravel application**:

1. preserve the database and working user journeys;
2. characterize stable behavior with tests;
3. establish explicit use-case and query boundaries one workflow at a time;
4. replace only the subsystems whose requirements genuinely changed;
5. add one small Python inference sidecar/worker for CV and embedding workloads instead of fragmenting the whole product into microservices;
6. require every touched or new module to be green even while inherited debt remains baselined.

A full rewrite is justified only if a later proof shows that preserving a subsystem costs more than replacing it. The present evidence does **not** justify rewriting the whole product.

---

## 2. Corrections to earlier audit claims

The first three audits were useful reconnaissance, but several conclusions were too absolute or factually stale. The refactor plan must use these corrected statements.

| Earlier claim | Corrected finding |
|---|---|
| `app(Service::class)` is an anti-pattern and equivalent to constructor injection | Laravel supports manual container resolution, but its documentation says most objects should receive dependencies through automatic injection. `app()` is valid for dynamic/framework-edge resolution; repeatedly using it for mandatory controller dependencies hides the dependency graph and complicates isolated tests. It is a secondary smell, not the main disease. |
| Every controller should have a service and all business logic belongs in services | Simple resource reads may use Eloquent directly. Extract a use-case/action when there is a workflow, invariant, transaction, side effect, reusable rule, authorization-sensitive operation, or external integration. Do not create ceremonial one-line wrappers. |
| DashboardController is the gold standard | Its HTTP method is thin and uses constructor injection, which is good. However, it calls thirteen query methods across `DashboardService` (265 lines) and `DashboardAnalyticsService` (645 lines). Those services repeat role checks, issue many independent queries, contain DB-driver/schema fallbacks, and even reference Spatie's `model_has_roles` although the application uses `role_user`. It is a useful thin-adapter example, not yet a good whole-module example. |
| Dual staff/applicant guards are a critical duplication bug | Separate guards/providers are a legitimate boundary between staff and applicant identities. The real problems are the 501-line mixed-concern `PortalAuthController`, custom reset/setup implementation, and the unresolved identifier-key design. Share primitives where safe; do not collapse distinct identities merely to remove duplication. |
| No rate limiting | Staff login, portal login/forgot-password, and AI companion routes are rate-limited. Coverage and production tuning still need tests. |
| Applicant setup-token expiry column is missing | `applicants.setup_token_expires_at` exists, but setup and applicant-reset tokens are stored and queried in plaintext. Token hashing and atomic one-time use are named P0 security work, not merely characterization. |
| `exam_session_applicant` lacks foreign keys | Its session/applicant foreign keys are constrained with cascade deletion; actor fields added later are constrained and null on deletion. A more serious question is the global unique constraint on `applicant_id`, which permits only one exam-session assignment for an applicant across all time. |
| Email notifications are not built | Eleven notification classes exist; schedule, status, staff-assignment, and result notifications already use mail and/or database channels and are invoked by current workflows. Preference management, delivery guarantees, after-commit behavior, and end-to-end tests are incomplete. |
| Zero tests because no `tests/` directory exists | Pest is now scaffolded with two example tests, but there are still **zero meaningful domain/feature tests**. The risk conclusion remains; the file-system claim was stale. |
| 3,200 ESLint errors describe source debt | The repository-wide command incorrectly lints compiled `public/build` assets. Source-only lint reports **309 errors in 94 files**. The generated bundle accounts for most of the inflated count and must be ignored in ESLint config. |
| Database “bandaid” migrations are current schema defects | Historical corrective migrations are not automatically defects. Do not rewrite applied migration history. Verify the resulting schema; optionally squash only when creating a fresh, controlled baseline. |
| No API versioning is an architecture defect | NEXIAM is primarily a server-rendered Inertia application. API versioning is needed only for a supported external/mobile API contract, not as a generic quality badge. |

---

## 3. Measured system map

### 3.1 Code size

| Area | Measured state |
|---|---:|
| Controllers | 41 files, 9,123 lines |
| Services | 35 PHP files (34 top-level + 1 legacy Concern), 9,456 lines |
| Models | 25 files, 1,720 lines |
| Policies | 10 files, 494 lines |
| Form Requests | 46 files, 1,464 lines |
| Migrations | 86 files, 2,817 lines |
| Svelte source | 199 components/pages, 26,203 lines |
| Inertia pages | 85 page files |
| Meaningful automated tests | 0 |

Line count is a navigation signal, not a design rule. Cohesion, dependency direction, transaction ownership, and change coupling determine whether a module should split.

### 3.2 Runtime shape today

```text
Public / Staff / Applicant browsers
                │
        Laravel web routes (328 lines)
                │
     auth guard + role middleware
                │
      Controllers + Form Requests
       │          │            │
       │          │            └── direct Eloquent / DB queries
       │          └── policies and inline role checks
       └── services resolved by DI or app()
                     │
        ┌────────────┼─────────────────────────┐
        │            │                         │
   MariaDB/SQLite  queued notifications   external/process adapters
                                     OpenRouter, Mixedbread,
                                     DOCX/ODT/PDF, spreadsheet I/O
```

### 3.3 Business capabilities already present

- staff and applicant authentication, plus a new Google OAuth staff-login/account-linking path that requires explicit security characterization;
- public/staff application intake and bulk imports;
- applicant portal, application editing, status tracking, admission slips;
- exam scheduling, staff/proctor assignment, attendance, submissions, monitoring;
- grading sessions, manual scores, spreadsheet score import;
- consultation summaries, release/unrelease, result-sheet generation;
- queued database/mail notifications;
- audit event recording and redaction;
- knowledge-document ingestion and a Mixedbread-backed AI companion;
- reports, settings, institutional setup, rooms, courses, academic years.

These are assets to characterize and selectively retain—not evidence that every implementation is correct.

---

## 4. Quality and security ledgers

### 4.1 Current reproducible baselines

| Ledger | Measured result | Correct interpretation |
|---|---:|---|
| PHPStan level 6 baseline | 743 ignore rules suppressing **988 occurrences**; current baseline-backed run reports 0 unsuppressed errors | 450 in `app/Http`, 325 in `app/Services`, 95 in `app/Models`; this is a module-risk ranking, not permission to add errors. |
| ESLint, repository-wide | 3,200 errors | Inflated by compiled `public/build` assets. |
| ESLint, source-only | **309 errors / 94 files** | 122 `no-unused-vars`, 112 missing Svelte each keys, then smaller correctness/reactivity groups. This is the actionable frontend ledger. |
| Svelte check | **1,073 errors + 206 warnings / 123 files** | 961 contain the dominant `$$ComponentProps`/prop-inference class; 113 error lines fall outside that class and require individual review. Do not label all 1,073 equally. |
| Prettier, source-only | **232 files** | Formatting debt; format in an isolated commit before semantic frontend refactors. |
| Pest | **2 example tests pass / 2 assertions**; no domain tests | Tooling and boot path work; business behavior is not protected. |
| npm production audit | 0 known vulnerabilities | Green at audit time. |
| Composer audit | **9 advisories in PhpSpreadsheet 4.5.0** | 1 critical, 6 high, 2 medium. Current advisories affect releases through 5.8.0; target 5.9+ and test imports. |

### 4.2 No-growth quality contract

Until inherited debt is retired:

1. no pull request may increase any scoped baseline;
2. source and generated assets must be measured separately;
3. touched PHP files must pass PHPStan without adding baseline entries;
4. new or substantially rebuilt Svelte modules must be TypeScript; small retained-JS edits may use explicit JSDoc contracts, but every touched module must pass scoped ESLint + `svelte-check` without new suppressions;
5. new use cases require tests before merge;
6. architecture violations use an explicit allowlist/baseline—never a permanently red CI job;
7. each refactor must shrink at least one ledger or remove an allowlisted architecture violation;
8. the eventual clean baseline is zero, and the old baseline is deleted only after verified reduction.

Architecture tests should encode dependency direction, not superficial size limits. Start with audit-mode/allowlisted checks such as:

```php
arch('models do not depend on HTTP delivery')
    ->expect('App\\Models')
    ->not->toUse('App\\Http');

arch('application modules do not render responses')
    ->expect(['App\\Actions', 'App\\Queries', 'App\\Services'])
    ->not->toUse(['Inertia\\Inertia', 'Illuminate\\Http\\RedirectResponse']);

```

Pest's class-dependency assertions should not be assumed to detect global `app()` / `resolve()` function calls. Track those call sites with a verified static search or a custom architecture expectation, baseline the legacy list, and fail CI only when it grows. Controller `DB` use and other legacy seams may follow the same explicit violation-list pattern. Do not write a permanently failing architecture suite or force harmless direct Eloquent reads into shallow wrappers.

### 4.3 Immediate security stop-ship

**Upgrade `phpoffice/phpspreadsheet` from 4.5.0 to 5.9+ before treating spreadsheet uploads as safe.** Applicant and score-import requests accept CSV/XLS/XLSX files up to 10 MB, and `SpreadsheetParser` passes uploaded Excel paths to `IOFactory::load()`. The current advisory set includes an `IOFactory::load` SSRF/RCE issue and several resource-exhaustion/SSRF/XSS issues. This is reachable application exposure, not an abstract dependency warning.

Upgrade acceptance must include malicious/oversized fixture tests, row/cell/workbook/decompression limits, formula/external-link disabling where supported, time/memory limits, abandoned-preview cleanup, and confirmation that all import/preview paths use the hardened shared parser. Exports also require spreadsheet/CSV formula neutralization: imported and public-form strings are stored raw, while `ReportExportService` writes them through `setCellValue`, so values beginning with formula-control characters can become active formulas in a staff-opened workbook.

**Rendered admission-slip HTML is a second reachable stop-ship.** Public application names and other applicant-controlled text are inserted by raw `str_replace` into an HTML template, then rendered with Svelte `{@html}` in the staff-facing single and bulk admission-slip pages. Escape text placeholders by context, keep only explicitly typed/trusted structural placeholders such as generated QR SVG raw, add abuse fixtures, and use CSP as defense in depth. The result-sheet `srcdoc` iframe currently omits `allow-scripts`, which reduces that specific sink's script risk, but its replacement contract still needs explicit trust typing.

### 4.4 Immediate functional stop-ship

The primary “Start session” buttons on both `Admin/TestAdmin/Index.svelte` and `Proctor/MySessions.svelte` call undefined `startSession(...)` at four call sites. Both pages actually define `initiateStart(...)`; clicking the shipped button raises a `ReferenceError` before the confirmation/post flow. Repair and characterize this core proctor path before adding new proctoring or offline features.

---

## 5. Architectural findings

### 5.1 P0 — fix or specify before major feature work

#### P0.1 No behavioral oracle

The application has mature workflows but no meaningful tests. Large-scale splitting without characterization tests risks preserving the wrong behavior or breaking hidden invariants.

Pin stable flows first:

1. public application submission and unique reference generation;
2. application acceptance, applicant account setup, and status notification;
3. exam publish/assignment, assigned-proctor authorization, attendance, and submission;
4. score entry/import, grading finalization, and pipeline progression;
5. consultation/result release, notification, and document generation.

Do not characterize requirements that are intentionally being replaced, such as course-recommendation AI behavior or CSV-as-OMR.

#### P0.2 Workflow ownership is fragmented

`ApplicationController` (1,245 lines), `Admin/ExamSessionController` (1,012), `SessionRosterController` (488), grading controllers, and `ReleaseController` jointly mutate application/exam/grading/release state, write audits, and send notifications. `ApplicationPipelineService` is called from 12 files, including controllers, services, a model, and a command. The pipeline is useful, but ownership is diffuse.

Target one command/use-case owner per transition:

- `AcceptApplication`
- `PublishExamSession`
- `AssignApplicantsToSession`
- `RecordAttendance`
- `RecordSubmission`
- `FinalizeGrading`
- `ReleaseResult`

Each command owns validation beyond FormRequest shape, transaction boundaries, audit write, state projection update, and after-commit event dispatch. This is not hypothetical: current exam publish and result release update base state, then notify, then advance pipeline, then audit without one transaction; a mid-flow failure can leave contradictory state. Application acceptance does use a transaction, but it queues notification work from inside that transaction while every configured queue connection currently has `after_commit => false`.

#### P0.3 Authorization model contradicts the capstone model

Current code requires a user to hold a permanent `proctor` role **and** be assigned through `exam_session_user`. The manuscript describes proctoring as a session-scoped duty overlay. Route middleware can reject an assigned staff member before `ExamSessionPolicy` gets the chance to verify the assignment.

There are three divergent catalogs: live-code roles such as `super_admin`, `registrar_administrator`, `test_administrator`, and `proctor`; the manuscript's System IT/Registrar/Guidance terminology; and the expanded access-control inventory with clerk/support/Program Head variants. Do not migrate roles until one authoritative catalog, permission matrix, and legacy mapping are approved.

Target:

- permanent organizational role/permission grants;
- applicant as a separate authenticated actor, not a staff role;
- proctor capability granted by session assignment and evaluated in policy;
- permissions for coarse capabilities;
- policies for resource scope and state;
- identifier-key authentication is required by the capstone but undefined. An identifier is a username, not a secure bearer credential. Specify identifier + password/passkey/OTP, recovery, rotation, rate limiting, and audit behavior;
- applicant setup and password-reset tokens are currently stored and queried in plaintext. Keep only a one-way hash server-side, compare the hash of the presented token, invalidate atomically on use, and test expiry/reuse/race behavior;
- the new Google OAuth callback automatically attaches a Google identity to an existing staff account by normalized email, without an explicit verified-email/domain policy, audit event, or route throttle; the callback also sits outside the `guest` group. Before merging/enabling it, specify trusted domains/account-linking consent, verify provider claims, constrain guest/link flows, handle credential races, rate-limit failures, and test account-switch/takeover scenarios;
- no hard-coded role lists repeated across routes, services, pages, and policies.

Spatie Permission is a reasonable implementation, but adopting it does not solve an unsettled role model. Specify permissions and scopes first.

#### P0.4 Data integrity/concurrency risks

- `Application::nextReferenceNumber()` uses `COUNT + 1` inside a transaction without a locked counter; concurrent submissions can generate the same unique reference and one request will fail. The same application flow performs an identity duplicate read and later inserts without a database uniqueness rule, so concurrent duplicate submissions can both pass.
- Appointment booking validates only that the row exists, then increments/decrements `booked_count` separately from application writes. It does not atomically require `is_active` or `booked_count < max_slots`, so concurrent submissions can overbook and failed/missed updates can drift the counter. Treat booking as a locked transaction or derive occupancy from applications.
- `exam_session_applicant` has a global unique index on `applicant_id`. That forbids retakes or another session in a later cycle unless the same applicant record is never reused. Confirm the domain invariant; otherwise scope uniqueness to the admission attempt/cycle.
- The model declares `Application::hasOne(Applicant::class)`, but `applicants.application_id` is nullable and not unique, so the database does not enforce that one-to-one relationship. More fundamentally, decide whether `Applicant` is a durable person/login and `Application` is a repeatable admission attempt; if so, the current ownership direction must evolve before multi-cycle scaling.
- Reopen and bulk-reopen hard-delete the accepted application's applicant record without a transaction or lifecycle-stage guard. Applicant deletion cascades through exam assignments, scores, grading pivots, consultation summaries, and AI messages; a late reopen can therefore erase the admission history it is supposed to revise. Conversely, hard-deleting an application sets `applicants.application_id` to null, leaving an orphan identity. Specify archive/reopen/delete semantics and preserve immutable assessment history.
- A fresh migration succeeds on disposable SQLite, but the resulting `applicant_scores` schema has no FK/index from nullable `aptitude_area_id` to `aptitude_areas`: the edited create migration defines only an integer and the later rename migration early-returns. Fresh and upgraded installations can diverge. The nullable three-column unique also allows duplicate “no area” score rows.
- Fresh schema inspection also confirms `exam_sessions.room_id` remains `CASCADE` despite later becoming nullable; `applications.course_preference_1` cascades while preferences 2/3 null; and `grading_sessions.exam_session_id` is indexed but not unique. Align destructive actions with retention policy and enforce one grading session per exam session if that is the domain invariant.
- Web and bulk-import application references use different formats and independent read-next sequences. Define one generator and one uniqueness/retry contract for every intake path.
- Direct-assessment reconciliation marks an applicant `scored` when the direct session has **zero** scores. Pipeline transitions also merge milestones with an unlocked read-modify-write, so concurrent transitions can regress state or lose milestone metadata. Fix the inverted rule and serialize authoritative transitions.
- “One active academic year,” non-overlapping room booking, and one grading session per exam session are enforced only by racy application checks/updates. Use a locked singleton/activation command, transactional room-slot strategy, and unique grading-session constraint where the agreed invariants require them.
- audits without a subject use `auditable_type = ''` and `auditable_id = 0`; use nullable subject columns or an explicit event subject model instead of sentinel polymorphic IDs.
- state is represented across base status, pipeline status, pivot flags, grading status, and summaries. Transition commands must define the source of truth and update projections atomically.

#### P0.5 PhpSpreadsheet exposure

See §4.3. Upgrade and harden before additional import work or OMR-adjacent uploads. Centralize reader selection and limits, parse once per workflow, neutralize formula-capable cells at every spreadsheet/CSV export boundary, and expire abandoned preview files.

#### P0.6 Audit truth is insufficient for the stated threat model

Current `AuditService` is a useful event vocabulary/redaction layer, but it is not tamper-evident. A simple `hash_hmac(event + previous_hash)` is also insufficient by itself:

- concurrent inserts need serialization/sequence handling;
- canonical payload encoding must be specified;
- deletion of the tail may not be detectable;
- anyone with both DB and HMAC key access can rewrite the chain;
- key rotation and historical verification must be defined.

The OpenSpec must define sequence, previous hash, canonical JSON, dedicated audit key/version, transaction/locking, verification, periodic external checkpoints, append-only DB privileges, and failure behavior. This is a security design, not merely a new column.

#### P0.7 Rendered-document trust boundary is missing

Admission-slip and result-sheet modules currently mix trusted template markup, applicant-controlled text, generated SVG/row fragments, and office-authored comments in untyped replacement arrays. Introduce placeholder metadata (`escaped_text`, `trusted_html`, `document_text`, `table_rows`), context-specific encoders, template validation, CSP, and malicious-value characterization. Never solve this by escaping the entire finished document or by trusting every placeholder because the template itself is administrative.

#### P0.8 Shipped core frontend actions are not behaviorally protected

The undefined Start-session handler is the most severe example, but direct inspection also found six calls to nonexistent Inertia Svelte `form.setData`, 24 assignments to dead `$form.onFinish` properties, notification read actions dereferencing `usePage()` without `$page`, and non-reactive public-nav URL checks. These are live runtime/API-contract defects hidden inside the larger lint ledger. Gate 1 needs targeted browser/feature characterization, not only bulk diagnostic reduction.

### 5.2 P1 — architecture needed for safe scaling

#### P1.1 God controllers and god services

Moving code from controllers into generic services is not enough. The service layer already contains large mixed modules:

- `ResultSheetTemplateService` — 1,254 lines / 17 public operations;
- `ReportExportService` — 837 lines;
- `DashboardAnalyticsService` — 645 lines / 11 query operations;
- `ApplicantImportService` — 515 lines;
- `SpreadsheetParser` — 457 lines;
- document renderers/converters across overlapping services.

Split by cohesive capability and stable interface, not arbitrary line limits. Good boundaries include template validation, placeholder compilation, applicant data projection, format-specific rendering, conversion, and delivery.

#### P1.2 Dashboard is query orchestration, not domain behavior

The controller is thin, but it invokes roughly a dozen separate query methods and each service embeds role checks. Introduce a single role/scope-aware dashboard query facade that returns a typed view model. Internally compose focused read queries and cache only where measurement justifies it. Authorization determines the allowed projection; role names should not be repeated in every chart method.

#### P1.3 Hidden dependencies and inconsistent resolution

Use constructor/method injection for required application dependencies. Keep Laravel facades where they improve conventional framework code. Reserve `app()` for genuinely dynamic/optional resolution or framework bootstrapping. The rule is dependency visibility and testability—not ideological prohibition.

#### P1.4 Side effects occur inline and inconsistently

Notifications, audit writes, pipeline updates, and document work are triggered from multiple controllers; some notification failures are swallowed manually. Command handlers should commit core state and mandatory audit data synchronously, then dispatch idempotent queued listeners **after commit** for email, expensive document work, and integrations. Retry behavior and dead-letter/failed-job visibility become explicit.

#### P1.5 RAG scope is internally contradictory and must be specified

The current AI companion already retrieves institutional documents through Mixedbread and has guardrails, but its prompt includes applicant details and all available courses and tells the model to give advice. Meanwhile, the manuscript explicitly requires applicant-specific retrieval of the user's own results, schedule, and AI-optimized assignments in addition to institutional documents. That is broader than “policy-only RAG” and recommendation-adjacent, so the audit must not silently delete it.

Reconcile the boundary in OpenSpec: authenticated read-only retrieval may expose the applicant's own results/schedule/approved assignments; institutional answers require citations and confidence refusal; placement/course recommendations remain a separate versioned decision-support capability with counselor oversight. Preserve suitable ingestion/retrieval adapters, but replace the use-case contract, prompt, evidence, citations, refusal path, and applicant-to-staff fallback. “Prompt-injection keyword detection” is defense in depth, not a security boundary.

#### P1.6 Frontend debt is concentrated and measurable

Largest pages include Release/Index (806 lines), Applications/Index (708), and near-identical AptitudeAreas Create/Edit pages (660/659, 97.8% line similarity). Several form pairs exceed 80–94% similarity. The copied forms have also copied obsolete Inertia API assumptions (`setData`, assigned `onFinish`) into many pages. Extract shared form modules only where fields, lifecycle, and adapter contract are genuinely common.

The 961 `$$ComponentProps` diagnostics concentrate at page call sites but originate largely in roughly twenty untyped UI primitives; type those leverage points before editing hundreds of callers. The current bridge already has `checkJs: true`, only a small TypeScript beachhead, and no shared `PageProps` declaration. Require TypeScript for new/substantially rebuilt modules, permit explicit JSDoc for small retained-JS changes, and migrate feature-by-feature. The target is typed page props, form data, events, component props, and external payloads at boundaries. Track the remaining approximate warning groups as separate ledgers: about 91 local-state/reactivity warnings and about 57 accessibility warnings.

### 5.3 P2 — cleanup/operability

- Make ESLint ignore `public/build` and other generated output.
- Narrow `jsconfig.json` / `svelte-check` scope to maintained frontend source; a broad run currently discovers Laravel's vendor exception-renderer `vite.config.js` and fails before checking NEXIAM. Exclude `vendor`, `public/build`, `storage`, caches, and archived legacy code.
- Commit Pint/Prettier formatting separately from semantic changes.
- Remove or justify unused adapters such as `OpenRouterChatService` after container-binding verification.
- Replace schema/table-existence fallbacks in normal application queries with deterministic migrations and setup health checks.
- Add structured error monitoring, queue/failed-job observability, import metrics, audit verification health, and integration health checks.
- Consider migration squashing only for a controlled fresh baseline; preserve production migration history otherwise.

---

## 6. What to preserve

1. Laravel 12 + Inertia 2 + Svelte 5 modular monolith.
2. Separate staff and applicant auth providers, subject to identifier-key redesign.
3. Eloquent models, current database, and working admission/exam/grading/release data.
4. `ApplicationPipelineService` transition vocabulary and reconciliation concept, while centralizing who invokes transitions.
5. `AuditService` event catalog and secret redaction, while replacing storage integrity mechanics.
6. FormRequest adoption and existing policy foundation.
7. Analyze → preview → confirm import UX.
8. Existing notification classes and queue infrastructure.
9. Mixedbread knowledge-document ingestion/retrieval adapter where it satisfies the new RAG contract.
10. Existing DOCX/ODT/PDF capabilities, after splitting and characterization.
11. shadcn-svelte component base, after fixing prop contracts/types.

Preserve behavior because it is verified and required—not merely because code already exists.

---

## 7. Target architecture

### 7.1 Pragmatic Laravel shape

```text
┌─────────────────────────────────────────────────────────────────────┐
│ Delivery: routes, middleware, FormRequests, controllers, Inertia    │
│   - authenticate, authorize, validate transport, invoke one use case│
├─────────────────────────────────────────────────────────────────────┤
│ Application commands/actions             Read queries/view models   │
│ AcceptApplication                        BuildStaffDashboard         │
│ PublishExamSession                       ListApplications            │
│ RecordAttendance                         BuildSessionRoster          │
│ FinalizeGrading                          BuildTriageDashboard        │
│ ReleaseResult                            BuildProgramHeadDashboard   │
│   - transaction + invariants + mandatory audit                     │
├─────────────────────────────────────────────────────────────────────┤
│ Domain model: Eloquent entities, value objects, transition rules    │
│ Admissions | Examinations | Assessment | Release | Identity         │
├─────────────────────────────────────────────────────────────────────┤
│ Async/application events: mail, messaging, integrations, documents │
├─────────────────────────────────────────────────────────────────────┤
│ Infrastructure adapters: MariaDB, queue, mail, object/file storage, │
│ Mixedbread/LLM, Office conversion, Python inference worker          │
└─────────────────────────────────────────────────────────────────────┘
```

### 7.2 Capability map

| Capability | Owns | Must not own |
|---|---|---|
| Identity & Access | staff/applicant auth, permissions, office scope, proctor assignment policy | admissions workflow |
| Admissions | application/attempt, acceptance, portal status, admission slip | exam attendance, scoring |
| Examination Operations | sessions, rooms, assignments, proctor duty, attendance/submission | score interpretation |
| Assessment & Grading | answer ingestion, OMR review, scores, grading lifecycle | result communication |
| Consultation & Release | summary, counselor decision, release/unrelease, result documents | authentication |
| Communications | notification preferences, mail/database notifications, inquiry threads | core state transitions |
| Audit & Compliance | canonical append-only event, chain/checkpoints, verification | business decision-making |
| Knowledge & RAG | institutional corpus plus authorized retrieval of the applicant's own results, schedule, and approved assignments; citations and confidence/refusal | generating placement recommendations or autonomous decisions |
| Admission Decision Support | engine registry, inference results, triage decision, provenance | autonomous admission decisions |
| Reporting & Documents | read models, exports, template rendering, conversion | mutation of domain state |

### 7.3 Rules with judgment, not dogma

- Controllers are HTTP adapters; no fixed 15-line or 150-line law.
- Direct Eloquent is acceptable for a simple, local read. Complex/reused/scoped reads become query objects.
- Use-case commands own workflows and transactions.
- Eloquent models own relationships, casts, local invariants, and useful scopes—not multi-system orchestration.
- Repositories are not mandatory around Eloquent. Introduce interfaces for external providers, multiple implementations, or a seam that tests genuinely need.
- Events/listeners are for decoupled side effects; do not hide essential synchronous invariants in asynchronous listeners.
- Audit writes that prove a state change belong in the same transaction; email and integrations run after commit.
- New modules are typed and green from birth.

---

## 8. Capstone feature architecture

| Feature | Actual starting point | Architecture direction |
|---|---|---|
| Identifier-key login | email/password + expiring setup/reset tokens | First specify whether the key is a public username or secret credential. Recommended: opaque applicant identifier as login name plus password/recovery; never rely on a predictable identifier alone. |
| Email notifications | substantially present | add preferences, event/after-commit dispatch, idempotency, templates, delivery tests, and operational visibility. |
| Applicant-context RAG | partial AI companion with unresolved scope | keep safe ingestion/retrieval pieces; permit authorized own-result/schedule/approved-assignment retrieval; require cited institutional answers, confidence refusal, and messaging fallback; keep placement recommendation in the separate decision-support module. |
| OMR CV | absent; CSV score import exists | Python/OpenCV queued worker, object storage, template/version record, confidence per bubble, human-review queue, idempotent import into grading. Never process large scans synchronously in the HTTP request. |
| Engine registry | absent | versioned manifest/schema, adapter contract, health/status, immutable inference provenance, sandbox/active lifecycle. Do not make arbitrary manifests executable. |
| Yukee aptitude engine | absent | deterministic/versioned engine behind the same contract, with validated mapping data and reproducible inputs. |
| BGE interest engine | absent | deploy in the same Python inference sidecar initially; precompute program embeddings and version model/corpus. Split service only when operational evidence demands it. |
| Triage dashboard | absent | read model combining independent aptitude and interest signals; counselor records approve/modify/override/defer with rationale and engine versions. No autonomous final decision. |
| Program Head dashboard | absent | read-only, program-scoped query model and policy; quota and pipeline definitions must be specified. |
| In-app messaging | absent | bounded inquiry/thread model, office assignment, unread state, retention/access policy, RAG refusal handoff. |
| HMAC audit chain | absent storage integrity; event catalog exists | dedicated cryptographic design described in §5.1; include external checkpoints and verification operations. |
| Offline proctoring | absent | defer until workflow tests and conflict rules exist; use an outbox/sync protocol, not cache-only PWA claims. |

---

## 9. Refactor-versus-rewrite decision

| Subsystem | Recommendation | Reason |
|---|---|---|
| Whole Laravel application | Refactor in place | working breadth, database, integrations, and screens make a greenfield rewrite high-risk. |
| Application/exam controllers | Replace incrementally behind characterization tests | responsibilities and workflows changed enough to justify new use-case boundaries. |
| Role/authorization layer | Planned migration | terminology and duty-overlay requirements conflict with current role checks. |
| Applicant auth UI/use case | Replace flow, keep separate guard/provider | new identifier semantics; identity boundary remains valid. |
| AI companion | Replace application contract, reuse vetted adapters | current prompt, manuscript applicant-context requirement, and separate recommendation engine need one explicit boundary. |
| Imports | Keep UX; harden/upgrade parser | valuable workflow with immediate dependency risk. |
| Document pipeline | Refactor/decompose, do not rewrite blindly | complex and working; high regression risk. |
| Svelte frontend | Incremental TypeScript modernization | wholesale translation delays capstone features and yields weak value. |
| OMR/embedding inference | New Python sidecar/worker | libraries and resource profile differ from Laravel; asynchronous boundary is natural. |

---

## 10. OpenSpec program

Do not create one unreviewable “rewrite everything” change. Use an umbrella program with dependency-ordered changes:

### Foundation changes

1. `quality-ledgers-and-generated-asset-scope`
2. `characterize-critical-admission-lifecycle`
3. `characterize-exam-grading-release-lifecycle`
4. `upgrade-and-harden-spreadsheet-imports`
5. `harden-rendered-templates-and-tabular-exports`
6. `harden-applicant-setup-and-reset-token-lifecycle`
7. `secure-google-oauth-account-linking`
8. `settle-role-permission-and-proctor-duty-model`
9. `define-applicant-identifier-auth-semantics`
10. `define-audit-chain-threat-model`

### Refactor changes

11. `centralize-application-command-workflows`
12. `centralize-exam-session-command-workflows`
13. `centralize-grading-and-release-workflows`
14. `introduce-scoped-read-models-and-dashboard-query`
15. `decompose-document-and-report-rendering`
16. `typed-svelte-boundaries-for-touched-features`

### Feature changes

17. `applicant-context-rag-with-confidence-and-fallback`
18. `omr-ingestion-review-and-score-import`
19. `admission-engine-registry-and-provenance`
20. `yukee-and-bge-engine-adapters`
21. `counselor-triage-dashboard`
22. `program-head-scoped-dashboard`
23. `applicant-and-interoffice-messaging`
24. `offline-proctor-sync` (only after conflict semantics are approved)

Each OpenSpec change must include current behavior, desired behavior, invariants, authorization matrix, data migration, failure/retry behavior, observability, test scenarios, rollout/rollback, and ledger impact.

---

## 11. Execution roadmap

### Gate 0 — clean audit inputs

- preserve the current uncommitted tooling work;
- fix measurement scope (`public/build` ignored by ESLint);
- run Pint + Prettier and commit formatting alone;
- keep the current untracked `quality.yml` draft disabled/unmerged until formatting is landed and each red checker has a deliberate no-growth baseline or touched-code gate; then capture reproducible reports in CI artifacts;
- decide and document the authoritative role catalog and identifier semantics.

This intentionally overrides `features.md`'s compressed P0 schedule, which places OMR, RBAC, identifier auth, and notifications together in Weeks 1–3. Safety, characterization, and role/auth specifications must precede OMR and broad feature construction; the reason for the reorder is dependency risk, not reduced capstone priority.

### Gate 1 — stop-ship safety

- upgrade PhpSpreadsheet to 5.9+ and harden imports;
- escape applicant-controlled document placeholders and neutralize formula-capable spreadsheet/CSV cells;
- repair and characterize the broken Start-session buttons and verified Inertia runtime API misuse;
- preserve applicant/exam/grading history across reopen/delete operations;
- create factories/fixtures for critical flows;
- add characterization and policy tests;
- add architecture tests in audit/baselined mode;
- verify fresh migration and seed from zero on both SQLite/test and the production database engine; reconcile fresh-versus-upgraded FK/index/delete-action drift.

### Gate 2 — P0 architecture seams

- introduce command handlers for admissions, exam, grading, and release transitions;
- move transactions/audit/state progression into those handlers;
- refactor proctor access to assignment-based policy;
- migrate required controller dependencies to visible injection;
- keep routes and UI stable while internals move.

### Gate 3 — P1 capstone foundations

- implement approved role/permission migration;
- implement identifier-key login semantics;
- implement cryptographically specified audit chain;
- finish notification preferences/delivery guarantees;
- replace the AI companion contract with the approved applicant-context RAG boundary, confidence refusal, and messaging fallback.

### Gate 4 — P2 research contribution

- build OMR worker and human-review flow;
- build engine registry/provenance;
- add Yukee and BGE adapters;
- add triage and Program Head dashboards.

### Gate 5 — polish and delivery

- messaging and offline sync;
- reduce legacy baselines in retained code;
- E2E defense journeys;
- performance/security/backup-restore tests;
- fresh repository/history only after specs and migration path are locked.

---

## 12. Verification gates

A phase is not complete because code was moved.

- **Behavior:** characterization and feature tests pass.
- **Authorization:** policy matrix tests prove allowed and denied paths, including cross-program/session access.
- **Data:** fresh migration/seed passes; upgrade migration is rehearsed against a sanitized copy; constraints and rollback are checked.
- **Quality:** no ledger grows; touched/new modules are green.
- **Security:** dependency audits green; upload abuse tests pass; secrets are not logged; audit verification is tested.
- **Operations:** queued work is idempotent; failed jobs are visible/retryable; health checks cover integrations.
- **Traceability:** every architecture decision maps to OpenSpec scenarios and downstream implementation/tests.

---

## 13. Decisions that must not be hidden as implementation details

1. **Authoritative permanent role catalog:** live code, manuscript, and access-control inventory currently expose three different catalogs; approve the target and an explicit legacy mapping.
2. **Identifier key:** username-like identifier, one-time setup token, or bearer secret? Recommendation: identifier + password, with recovery and rate limiting.
3. **Applicant lifetime:** can one person apply or test across multiple academic years? This decides whether applicant/session uniqueness is valid.
4. **Pipeline source of truth:** stored projection versus purely derived state, and who may force/backtrack it.
5. **Audit threat model:** which attacker is the HMAC chain intended to detect, where are checkpoints stored, and who holds the key?
6. **OMR operational input:** scanner/camera formats, sheet template, batch size, confidence threshold, and human-review SLA.
7. **Program scope/quota meaning:** required for Program Head policy and dashboard.
8. **RAG scope and confidence contract:** authorized applicant-specific data, recommendation boundary, retrieval metric, threshold, citation behavior, corpus ownership, and escalation destination.
9. **Deployment shape:** recommended Laravel Compose stack plus one Python inference worker—not independent microservices per engine at capstone scale.
10. **Rewrite trigger:** define measurable criteria; do not choose rewrite because current files are large.
11. **Retention and reversal:** decide what reopen, application deletion, applicant deletion, and reassignment mean after exam history exists; destructive cascade behavior must not be an accidental implementation detail.

---

## 14. Bottom line

> NEXIAM's best path is not “put every query in a service” and not “start over.” It is to preserve verified behavior, make critical workflows explicit and transactional, settle identity and authorization semantics, turn quality debt into no-growth ledgers, and add the research features through small typed modules with reproducible provenance and human control.

This document supersedes the earlier “thin controller → service behind everything” picture. It remains a living audit: future evidence should amend measured findings, while architecture decisions change only through reviewed ADR/OpenSpec updates.

---

## 15. Evidence provenance and reproduction

Primary project evidence:

- `docs/architecture-audit.md`
- `docs/access-control-inventory.md`
- `docs/audit-controllers.md`
- `docs/audit-refactor-plan.md`
- `manuscript/NEXIAM_Manuscript_Working_Sections.md`
- `manuscript/Phase(RAD-based) Work/features.md`
- current `app/`, `routes/`, `database/migrations/`, `resources/js/`, tests, dependency locks, and quality configs.

Key reproducible commands (run from the project root):

```bash
npm run check -- --output human
npx eslint resources/js vite.config.js scripts --format json
npx prettier --check resources/js vite.config.js scripts
npm audit --omit=dev --json
composer audit --locked --no-dev --format=json
```

PHPStan debt is calculated by summing each `count:` in `phpstan-baseline.neon`, not by counting only baseline blocks. Source metrics are recomputed from the working tree, so they may change as the current uncommitted quality-tooling work is completed.

Laravel-practice correction was checked against the Laravel 12 service-container and authorization documentation:

- <https://laravel.com/docs/12.x/container>
- <https://laravel.com/docs/12.x/authorization>

An independent source-backed adversarial review plus focused database and frontend reviews were completed on 2026-07-31. The focused findings were rechecked against live source before inclusion. A read-only-mounted `composer:2` container also completed all 86 migrations against disposable SQLite with exit 0; post-migration `PRAGMA` inspection confirmed the documented missing aptitude-area FK/index and the current room/course/grading constraint behavior. Production-engine migration rehearsal remains a Gate 1 requirement.

No credential values are part of this audit.
