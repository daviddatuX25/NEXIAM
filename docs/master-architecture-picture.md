> **DOCUMENT INTEGRITY:** This file previously contained literal tool-omission markers and is HISTORICAL only. A pre-repair copy is in `docs/greenfield-foundation/quarantine/`. Do not use as sequence or implementation authority. Use `docs/GREENFIELD-MASTER-REFERENCE.md` and `docs/MASTER-DELIVERY-ROADMAP.md`.

master-architecture-picture.md 585L
# NEXIAM Architecture Audit and Refactor Blueprint
**Status:** HISTORICAL INPUT — source-backed audit evidence; superseded as target implementation and sequence authority by the master delivery roadmap `docs/greenfield-foundation/14-readiness-gates-and-reconciled-roadmap.md` (GF-14), the greenfield foundation suite, and approved OpenSpec changes. Legacy “Gate 0–5” numbering is replaced by GF-14 stable `G-*` / `R*` / `E*` IDs.
... [lean-ctx: omitted 1 lines]
**Method:** source inspection, call-path tracing, live quality-tool execution, dependency audit, migration/model review, requirement-to-code comparison, and Laravel 12 documentation. Findings are tagged as **Observed**, **Measured**, **Requirement**, or **Decision needed** so that opinions do not become facts by repetition.
**Strategic pivot (2026-08-01):** The original strangler-style modernization strategy has been superseded. NEXIAM will be rebuilt as a clean-slate project. The legacy codebase serves as a read-only reference for domain workflows, data models, access control intent, and integration contracts. See `docs/greenfield-architecture-plan.md` for the execution plan.
... [lean-ctx: omitted 1 lines]
## 1. Executive verdict
> **Superseded 2026-08-01.** The original verdict recommended strangler-style modernization inside the existing Laravel application. After completing all eight audit lenses, the project owner decided on a **clean-slate greenfield rebuild** instead. The audit findings below remain authoritative as a domain and requirements reference; the implementation strategy is now governed by `docs/greenfield-architecture-plan.md`.
The legacy codebase is a working Laravel monolith with substantial admissions, scheduling, proctoring, grading, release, document-generation, notification, and RAG infrastructure. The audit proved it also carries:
... [lean-ctx: omitted 4 lines]
- plaintext tokens, unescaped template injection, and vulnerable dependencies;
... [lean-ctx: omitted 1 lines]
Rather than characterize, baseline, and incrementally strangle each subsystem, the project will use this audit as a specification source and build clean modules from requirements. The domain model, workflow invariants, data relationships, and integration contracts documented below are the assets carried forward — not the code.
... [lean-ctx: omitted 1 lines]
## 2. Corrections to earlier audit claims
The first three audits were useful reconnaissance, but several conclusions were too absolute or factually stale. The refactor plan must use these corrected statements.
... [lean-ctx: omitted 2 lines]
| `app(Service::class)` is an anti-pattern and equivalent to constructor injection | Laravel supports manual container resolution, but its documentation says most objects should receive dependencies through automatic injection. `app()` is valid for dynamic/framework-edge resolution; repeatedly using it for mandatory controller dependencies hides the dependency graph and complicates isolated tests. It is a secondary smell, not the main disease. |
| Every controller should have a service and all business logic belongs in services | Simple resource reads may use Eloquent directly. Extract a use-case/action when there is a workflow, invariant, transaction, side effect, reusable rule, authorization-sensitive operation, or external integration. Do not create ceremonial one-line wrappers. |
| DashboardController is the gold standard | Its HTTP method is thin and uses constructor injection, which is good. However, it calls thirteen query methods across `DashboardService` (265 lines) and `DashboardAnalyticsService` (645 lines). Those services repeat role checks, issue many independent queries, contain DB-driver/schema fallbacks, and even reference Spatie's `model_has_roles` although the application uses `role_user`. It is a useful thin-adapter example, not yet a good whole-module example. |
| Dual staff/applicant guards are a critical duplication bug | Separate guards/providers are a legitimate boundary between staff and applicant identities. The real problems are the 501-line mixed-concern `PortalAuthController`, custom reset/setup implementation, and the unresolved identifier-key design. Share primitives where safe; do not collapse distinct identities merely to remove duplication. |
... [lean-ctx: omitted 2 lines]
| `exam_session_applicant` lacks foreign keys | Its session/applicant foreign keys are constrained with cascade deletion; actor fields added later are constrained and null on deletion. A more serious question is the global unique constraint on `applicant_id`, which permits only one exam-session assignment for an applicant across all time. |
| Email notifications are not built | Eleven notification classes exist; schedule, status, staff-assignment, and result notifications already use mail and/or database channels and are invoked by current workflows. Preference management, delivery guarantees, after-commit behavior, and end-to-end tests are incomplete. |
... [lean-ctx: omitted 5 lines]
## 3. Measured system map
### 3.1 Code size
| Area | Measured state |
... [lean-ctx: omitted 2 lines]
| Services | 35 PHP files (34 top-level + 1 legacy Concern), 9,456 lines |
... [lean-ctx: omitted 2 lines]
| Form Requests | 46 files, 1,464 lines |
... [lean-ctx: omitted 1 lines]
| Svelte source | 199 components/pages, 26,203 lines |
... [lean-ctx: omitted 1 lines]
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
... [lean-ctx: omitted 2 lines]
- exam scheduling, staff/proctor assignment, attendance, submissions, monitoring;
... [lean-ctx: omitted 1 lines]
- consultation summaries, release/unrelease, result-sheet generation;
... [lean-ctx: omitted 2 lines]
- knowledge-document ingestion and a Mixedbread-backed AI companion;
... [lean-ctx: omitted 1 lines]
These are assets to characterize and selectively retain—not evidence that every implementation is correct.
... [lean-ctx: omitted 1 lines]
## 4. Quality and security ledgers
### 4.1 Current reproducible baselines
| Ledger | Measured result | Correct interpretation |
... [lean-ctx: omitted 1 lines]
| PHPStan level 6 baseline | 743 ignore rules suppressing **988 occurrences**; current baseline-backed run reports 0 unsuppressed errors | 450 in `app/Http`, 325 in `app/Services`, 95 in `app/Models`; this is a module-risk ranking, not permission to add errors. |
... [lean-ctx: omitted 1 lines]
| ESLint, source-only | **309 errors / 94 files** | 122 `no-unused-vars`, 112 missing Svelte each keys, then smaller correctness/reactivity groups. This is the actionable frontend ledger. |
| Svelte check | **1,073 errors + 206 warnings / 123 files** | 961 contain the dominant `$$ComponentProps`/prop-inference class; 113 error lines fall outside that class and require individual review. Do not label all 1,073 equally. |
... [lean-ctx: omitted 3 lines]
| Composer audit | **9 advisories in PhpSpreadsheet 4.5.0** | 1 critical, 6 high, 2 medium. Current advisories affect releases through 5.8.0; target 5.9+ and test imports. |
### 4.2 No-growth quality contract
Until inherited debt is retired:
... [lean-ctx: omitted 3 lines]
4. new or substantially rebuilt Svelte modules must be TypeScript; small retained-JS edits may use explicit JSDoc contracts, but every touched module must pass scoped ESLint + `svelte-check` without new suppressions;
... [lean-ctx: omitted 5 lines]
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
... [lean-ctx: omitted 3 lines]
3. exam publish/assignment, assigned-proctor authorization, attendance, and submission;
... [lean-ctx: omitted 2 lines]
Do not characterize requirements that are intentionally being replaced, such as course-recommendation AI behavior or CSV-as-OMR.
#### P0.2 Workflow ownership is fragmented
`ApplicationController` (1,245 lines), `Admin/ExamSessionController` (1,012), `SessionRosterController` (488), grading controllers, and `ReleaseController` jointly mutate application/exam/grading/release state, write audits, and send notifications. `ApplicationPipelineService` is called from 12 files, including controllers, services, a model, and a command. The pipeline is useful, but ownership is diffuse.
... [lean-ctx: omitted 3 lines]
- `AssignApplicantsToSession`
... [lean-ctx: omitted 1 lines]
- `RecordSubmission`
... [lean-ctx: omitted 2 lines]
Each command owns validation beyond FormRequest shape, transaction boundaries, audit write, state projection update, and after-commit event dispatch. This is not hypothetical: current exam publish and result release update base state, then notify, then advance pipeline, then audit without one transaction; a mid-flow failure can leave contradictory state. Application acceptance does use a transaction, but it queues notification work from inside that transaction while every configured queue connection currently has `after_commit => false`.
#### P0.3 Authorization model contradicts the capstone model
Current code requires a user to hold a permanent `proctor` role **and** be assigned through `exam_session_user`. The manuscript describes proctoring as a session-scoped duty overlay. Route middleware can reject an assigned staff member before `ExamSessionPolicy` gets the chance to verify the assignment.
There are three divergent catalogs: live-code roles such as `super_admin`, `registrar_administrator`, `test_administrator`, and `proctor`; the manuscript's System IT/Registrar/Guidance terminology; and the expanded access-control inventory with clerk/support/Program Head variants. Do not migrate roles until one authoritative catalog, permission matrix, and legacy mapping are approved.
... [lean-ctx: omitted 6 lines]
- identifier-key authentication is required by the capstone but undefined. An identifier is a username, not a secure bearer [REDACTED:Bearer token] Specify identifier + password/passkey/OTP, recovery, rotation, rate limiting, and audit behavior;
- applicant setup and password-reset tokens are currently stored and queried in plaintext. Keep only a one-way hash server-side, compare the hash of the presented token, invalidate atomically on use, and test expiry/reuse/race behavior;
- the new Google OAuth callback automatically attaches a Google identity to an existing staff account by normalized email, without an explicit verified-email/domain policy, audit event, or route throttle; the callback also sits outside the `guest` group. Before merging/enabling it, specify trusted domains/account-linking consent, verify provider claims, constrain guest/link flows, handle credential races, rate-limit failures, and test account-switch/takeover scenarios;
... [lean-ctx: omitted 2 lines]
#### P0.4 Data integrity/concurrency risks
- `Application::nextReferenceNumber()` uses `COUNT + 1` inside a transaction without a locked counter; concurrent submissions can generate the same unique reference and one request will fail. The same application flow performs an identity duplicate read and later inserts without a database uniqueness rule, so concurrent duplicate submissions can both pass.
- Appointment booking validates only that the row exists, then increments/decrements `booked_count` separately from application writes. It does not atomically require `is_active` or `booked_count < max_slots`, so concurrent submissions can overbook and failed/missed updates can drift the counter. Treat booking as a locked transaction or derive occupancy from applications.
... [lean-ctx: omitted 1 lines]
- The model declares `Application::hasOne(Applicant::class)`, but `applicants.application_id` is nullable and not unique, so the database does not enforce that one-to-one relationship. More fundamentally, decide whether `Applicant` is a durable person/login and `Application` is a repeatable admission attempt; if so, the current ownership direction must evolve before multi-cycle scaling.
- Reopen and bulk-reopen hard-delete the accepted application's applicant record without a transaction or lifecycle-stage guard. Applicant deletion cascades through exam assignments, scores, grading pivots, consultation summaries, and AI messages; a late reopen can therefore erase the admission history it is supposed to revise. Conversely, hard-deleting an application sets `applicants.application_id` to null, leaving an orphan identity. Specify archive/reopen/delete semantics and preserve immutable assessment history.
- A fresh migration succeeds on disposable SQLite, but the resulting `applicant_scores` schema has no FK/index from nullable `aptitude_area_id` to `aptitude_areas`: the edited create migration defines only an integer and the later rename migration early-returns. Fresh and upgraded installations can diverge. The nullable three-column unique also allows duplicate “no area” score rows.
... [lean-ctx: omitted 2 lines]
- Direct-assessment reconciliation marks an applicant `scored` when the direct session has **zero** scores. Pipeline transitions also merge milestones with an unlocked read-modify-write, so concurrent transitions can regress state or lose milestone metadata. Fix the inverted rule and serialize authoritative transitions.
... [lean-ctx: omitted 3 lines]
#### P0.5 PhpSpreadsheet exposure
See §4.3. Upgrade and harden before additional import work or OMR-adjacent uploads. Centralize reader selection and limits, parse once per workflow, neutralize formula-capable cells at every spreadsheet/CSV export boundary, and expire abandoned preview files.
#### P0.6 Audit truth is insufficient for the stated threat model
Current `AuditService` is a useful event vocabulary/redaction layer, but it is not tamper-evident. A simple `hash_hmac(event + previous_hash)` is also insufficient by itself:
- concurrent inserts need serialization/sequence handling;
... [lean-ctx: omitted 4 lines]
The OpenSpec must define sequence, previous hash, canonical JSON, dedicated audit key/version, transaction/locking, verification, periodic external checkpoints, append-only DB privileges, and failure behavior. This is a security design, not merely a new column.
#### P0.7 Rendered-document trust boundary is missing
Admission-slip and result-sheet modules currently mix trusted template markup, applicant-controlled text, generated SVG/row fragments, and office-authored comments in untyped replacement arrays. Introduce placeholder metadata (`escaped_text`, `trusted_html`, `document_text`, `table_rows`), context-specific encoders, template validation, CSP, and malicious-value characterization. Never solve this by escaping the entire finished document or by trusting every placeholder because the template itself is administrative.
#### P0.8 Shipped core frontend actions are not behaviorally protected
The undefined Start-session handler is the most severe example, but direct inspection also found six calls to nonexistent Inertia Svelte `form.setData`, 24 assignments to dead `$form.onFinish` properties, notification read actions dereferencing `usePage()` without `$page`, and non-reactive public-nav URL checks. These are live runtime/API-contract defects hidden inside the larger lint ledger. Gate 1 needs targeted browser/feature characterization, not only bulk diagnostic reduction.
### 5.2 P1 — architecture needed for safe scaling
#### P1.1 God controllers and god services
Moving code from controllers into generic services is not enough. The service layer already contains large mixed modules:
- `ResultSheetTemplateService` — 1,254 lines / 17 public operations;
... [lean-ctx: omitted 1 lines]
- `DashboardAnalyticsService` — 645 lines / 11 query operations;
... [lean-ctx: omitted 3 lines]
Split by cohesive capability and stable interface, not arbitrary line limits. Good boundaries include template validation, placeholder compilation, applicant data projection, format-specific rendering, conversion, and delivery.
[… truncated at ~4136 of 7768 tokens — use ctx_read with lines= parameter to see specific sections]
