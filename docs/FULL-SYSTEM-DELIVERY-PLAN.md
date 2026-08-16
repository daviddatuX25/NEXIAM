# NEXIAM — Full-System Delivery Plan (task view)

**Purpose:** one working view of everything required to consider the full system done — legacy-parity interface fill, founder overrules, NFR hardening, and the Nexi/AI expansion — as milestone-bunched task lists.
**Status:** ACTIVE working plan · created 2026-08-16
**Sources:**
- 4-pass full-fidelity legacy scan (2026-08-16): charts/dashboards, shells/marketing, 14 feature surfaces, greenfield completeness audit
- Founder direction 2026-08-16 (import design overrule, UI nuances, NFRs, AI roadmap)
- [`MASTER-DELIVERY-ROADMAP.md`](MASTER-DELIVERY-ROADMAP.md) phase gates (E0–E6) and architecture spine
- [`GREENFIELD-MASTER-REFERENCE.md`](GREENFIELD-MASTER-REFERENCE.md) open items (§6, §8)

**How this relates to authority:** legacy code is behavior evidence only; founder overrules in this doc win over legacy; each milestone ships through the one-active-OpenSpec process (`openspec/changes/`), not direct coding. Dashboards/reports are pulled earlier than roadmap E4 by founder request.

---

## 1. Current state at a glance (2026-08-16)

### Shipped / live
- E0 foundation, E1 identity + reference + authz (capabilities, scopes, duties), Nexi foundation (kernel, skills, proposals)
- Applicant portal cut (status-spine hub, mode-aware results, reservation UX, My Application, truthful notifications) — archive pending founder review
- Admissions review + actions (accept/dismiss/correct/request-correction/reconsider/reopen/archive/reissue-key)
- Exam sessions lifecycle + reservation, monitoring (conduct roster), proctor duty assign
- Grading: manual scoring + finalize, OMR capture stack (layout solver, templates, booklets, pairs, batches)
- Release: prepare/release/unrelease + consultation decision; Program Head workspace; Registrar enrollment stamps
- Setup hub (cycles, programs, rooms, grades, aptitude + scoring conversion, quotas, privacy, OMR config)
- Staff personnel (HR hierarchy, TA delegation), profile, applicant QR + staff scan
- UI kit: 20 shadcn primitives (100% legacy parity), kit composites, server-authored nav, both shells
- Notifications: own-row projection + bell (30s poll)

### In flight / open
- Active OpenSpec `omr-profile-test-readiness` (OMR one-page profile seed — created today)
- Result-sheet printing pipeline (hardcoded stub today)
- E2 exit evidence G2–G4/H4, legacy parity ledger, pre-existing suite-parity restoration (`ReleaseFlowTest` release-notify failure)
- Deferred admission slip page (portal cut)

### Confirmed missing vs legacy (scan verdicts)
Charts/dashboards, Reports hub, Audit log UI, Settings, Institution editor, Rating scales, Direct assessment, Score import, Applicant import, Result templates, proctor QR/bulk/extend, marketing home/About, notification sounds, Test-Admin override start, AI-admin persona editor + knowledge CSV import.

---

## 2. Founder decisions recorded (2026-08-16)

> These override legacy where they conflict. Record into GF-13 decision matrix when convenient.

### D-A. Unified import framework — one pattern, bulk-not-per-session
- Imports (applicant AND scoring) share ONE analyze → staged preview → confirm framework; not two bespoke flows.
- Scoring import MUST NOT be per-exam-session ("select exam session → import → repeat" is rejected).
- The score import file carries/derives the exam session per row (session code column or applicant→session lookup), and a single bulk run covers ALL sessions.
- Post-import validation rejects rows whose exam-session state does not permit scoring (e.g. session not yet closed/graded-ready), with per-row reasons; partial commit with quarantine + error export.
- Applicant import likewise bulk once, with the same row-validation semantics.

### D-B. Interface excellence pulled earlier
- Dashboards, charts, and reports move ahead of roadmap E4 timing (founder wants the interface "aced" now).
- Charts always paired with a text/table alternative and scope/freshness note (GF-22 baseline — legacy lacked this; do not copy that bug).
- Further intricate UI touches will be layered incrementally by founder direction.

### D-C. Nexi/AI expansion is committed scope
- Study and choose: **Vercel AI SDK** vs **Laravel AI SDK** (ADR-gated; Laravel-first server side vs streaming-first client side — Inertia/Svelte fit is the deciding lens).
- Use **beautifului.dev** for AI surface UI (chat shells, streaming markdown, tool-call/thinking states).
- Target capabilities: robustified **exam scheduling assistant**, **Inquiry** (RAG over Knowledge sources), then **consultation scheduling assistant**, then more AI-assisted surfaces (proposal-only, human-confirm, per spine §2.4).

### D-D. Offline-first + admission intelligence (2026-08-16)
- **PWA offline-first** for the **proctor conduct roster** and **OMR capture** — record attendance/submission/QR and queue scanned sheets offline, sync via durable outbox on reconnect. Exam start/extend/close remain server-authority (clear degraded state offline). Manual/paper reconciliation fallback stays complete.
- **Admission decision-support model** — a custom, **rule-based model that needs NO training** (grades + aptitude conversion + quota fit + window rules) producing auditable accept/dismiss/recommend proposals; human confirms through existing admission commands. ML (YuKee/K-Means, BGE) remains evidence-gated later.

---

## 3. Milestone map

| ID | Milestone | Goal | Exit gate |
|---|---|---|---|
| M0 | Close in-flight work | Portal archive + OMR profile shipped, suite parity restored | All three green + master reference updated |
| M1 | Interface fill | Dashboards, charts, reports, marketing home, shell polish | Chart kit + staff dashboard + Reports hub live, Playwright-covered |
| M2 | Legacy feature completion | Settings, institution, audit log UI, rating scales, direct assessment, test-admin, proctor ops, bulk actions, knowledge admin | Every M2 checklist item shipped with seed data + HANDOFF |
| M3 | Unified import framework | One import framework; bulk score import across all sessions with session-state validation | Import suite green incl. session-state rejection tests |
| M4 | Result sheets & printing | Template management + template-backed result docs + bulk print jobs | Print pipeline replaces hardcoded stub; PDF/DOCX evidence |
| M5 | NFR hardening | Performance budgets, rate limiting, system protection, observability, a11y sweep | Security + perf evidence report |
| M6 | Offline & PWA (proctor + OMR) | Offline-first conduct roster and OMR capture with durable outbox + sync; manual reconciliation fallback stays complete | Offline-mode Playwright + replay/conflict tests green |
| M7 | Nexi/AI expansion | SDK ADR, engine hardening, scheduling assistant, Inquiry RAG, consultation assistant, admission decision-support (no-training model), follow-up automation, beautifului surfaces | Per-capability OpenSpec with evidence gates (E3 rules) |
| M8 | Full-system verification | E2 exit evidence, parity ledger, full suite sweep, founder demo | Pilot clearance decision (still NOT GRANTED until evidence) |

Dependencies: M1 → nothing (parallel-safe with M2–M5). M3 and M4 independent. M6 (offline) builds on M2 proctor ops + M4 OMR capture stability. M7 SDK spike can start now (research-only). M8 closes the loop.

---

## 4. Milestones with task lists

### M0 — Close in-flight work
- [ ] Founder review + archive `openspec/changes/2026-08-15-applicant-portal-interface`
- [ ] Finish `omr-profile-test-readiness`: seeded profile pair, answer key, capture states, tests, visual PDF verification, HANDOFF
- [ ] Restore pre-existing suite parity: fix `ReleaseFlowTest` release-notify failure (picks seeded EX-AS-1 via `firstOrFail`)
- [ ] Update `GREENFIELD-MASTER-REFERENCE.md` checkpoint + `HANDOFF-TEAMMATE.md`

### M1 — Interface fill (per-page build-out)

> Build per PAGE, in pipeline order (public → admission → exam → grading → release → portal). Each page's tasks are the full goal inventory for that surface. Shared chart components live under "Charts & data display" — a foundation, not a standalone deliverable. Data legacy computed but never rendered is marked REPRODUCE — treat as new features, not dead code.

**Public home page (`/`)**
- [ ] Hero: season badge + "Secure Your Future" headline + admissions CTA — a visitor understands how to apply in one glance
- [ ] How-it-works timeline: 5-step colored process (apply → exam → results), desktop horizontal / mobile vertical (legacy ProcessFlow)
- [ ] Public footer: institution name/campus/contact + quick links
- [ ] Decision: full marketing sections (Benefits/Perspective) on home, or hero + timeline only

**About page (`/about`)**
- [ ] Why NEXIAM: 3 benefit cards
- [ ] One system, three views: staff / applicant / proctor perspective articles
- [ ] For applicants: 4 cards (apply, track, monitor, results)
- [ ] Future vision: 4 roadmap cards ("coming soon")

**Staff dashboard page (`/staff`)**
- [ ] Replace diagnostic `Staff/Home` with operational dashboard (role-gated sections)
- [ ] Application KPI row: Total / In Pipeline / Completed / Dismissed + delta pills, links into Admissions
- [ ] Application growth: 6-month trend line chart
- [ ] Pipeline distribution: 11-stage funnel (pending→dismissed) doughnut + table — REPRODUCE (legacy computed it, never showed it)
- [ ] Top course preferences: top-5 doughnut + table (which programs applicants want)
- [ ] Exam sessions panel: upcoming / attendance-due / submissions-due tiles + weekly scheduled-vs-completed trend + status doughnut
- [ ] Attendance trends: 12-week present/absent chart — REPRODUCE (legacy `DashboardAnalyticsService` computed it, never rendered it)
- [ ] Grading & release panel: pending grading / pending release tiles + turnaround trend + grading status doughnut
- [ ] My Activity: last-10 audit events with timestamps + empty state
- [ ] Per-chart scope/freshness note + table alternative (GF-22 rule)

**Reports hub page (`/reports`)**
- [ ] Academic-year selector that preserves filters
- [ ] KPI row: total applications, exam sessions, pending review, released, dismissed
- [ ] Pipeline breakdown doughnut (11 statuses, color-coded)
- [ ] Demographics: by-sex + by-age-bracket charts
- [ ] Course demand vs counselor recommendation table with alignment badges (green ≥60 / amber ≥30 / red <30)
- [ ] Downloads: 5 registrar reports (master list, pipeline summary, course preferences, demographics, dismissed) — xlsx + pdf, async, role-gated
- [ ] Downloads: 4 guidance reports (scores, session roster, consultation summary, release tracking) — xlsx + pdf
- [ ] Export jobs: queued + private artifact + download link (no blocking request)

**Applicant portal pages**
- [ ] Portal dashboard: status timeline + edit/locked notices + exam schedule card + admission slip card (existing hub — fill remaining cards)
- [ ] Results release status visible to applicant — REPRODUCE (legacy passed `score_release` to the page but never displayed it)
- [ ] Applicant tips helper in portal sidebar

**Grading dashboard page**
- [ ] Sessions list: room, date/time, direct badge, "{n} of {m} scored" progress (existing page — add dashboard-level stats on top)

**Charts & data display (shared foundation)**
- [ ] Line chart component (single + multi-series) with empty/loading/error states, theme-aware
- [ ] Doughnut chart component with count legend + empty state
- [ ] a11y: aria-label + embedded data-table alternative + reduced-motion (legacy had none — required)
- [ ] Metric dictionary: each chart's definition, scope (active cycle), freshness, denominator
- [ ] Vitest for data transforms + Playwright smoke per page

**Shell & notification polish** (decision #1 pending founder on sounds)
- [ ] Applicant shell: flash toasts, footer, theme toggle
- [ ] Notification: new-item toast + optional chime (restore or formally drop)
- [ ] Staff nav icons: BadgeCheck (Enrollment Eligibility) + GraduationCap (Program Head)
- [ ] Breadcrumb + mobile parity across shells, Playwright cases

### M2 — Legacy feature completion

**Settings hub** (evidence: `Admin/SettingsController.php`, `Admin/Settings/Index.svelte`)
- [ ] Toggles: `notify_on_publish`, `release_mode` (online/F2F — maps to existing consultation logic), `allow_direct_assessment`, `allow_flexible_applications`, `ai_exam_companion_enabled`
- [ ] Admission slip: enable toggle + HTML template editor with live iframe preview + reset
- [ ] Do NOT reintroduce `enable_normalized_scores` (founder rejected formula/normalized basis; conversion table is locked)

**Institution profile** (evidence: `Admin/InstitutionController.php`)
- [ ] Institution profile: edit 8 keys, env-override badges, reset defaults, audit

**Audit log UI** (evidence: `Admin/AuditLogController.php`, `Admin/Logs/Index.svelte`; greenfield `AuditEvent` model exists)
- [ ] Audit log viewer: filters (event/category/actor/date), payload JSON drawer, pagination
- [ ] Audit log: CSV export

**Rating scales** (evidence: `Admin/RatingScaleController.php`)
- [ ] Rating scales: decision on percentile→label layer over the locked conversion table, or explicit REJECT (CapabilityCatalog currently defers)
- [ ] Rating scales: if kept — CRUD, default flag, ordering, audit

**Direct assessment** (evidence: `DirectAssessmentController.php`)
- [ ] Direct assessment: accepted applicant without exam session → creates grading session, gated by setting (ties to master-ref §6.6)

**Test Admin sessions** (evidence: `Admin/TestAdmin/Index.svelte`, `ExamSessionController::testAdminIndex`)
- [ ] Test admin: Today/Upcoming/Past session list + override start (outside window) + close dialog

**Proctor roster operations** (evidence: `Components/SessionRoster.svelte`, `Proctor/SessionRosterController.php`)
- [ ] Proctor roster: QR scan attendance + submission (reuse ApplicantScanModal/scan-modal.js)
- [ ] Proctor roster: bulk submit, extend 1–120 min, remove applicant, straggler warnings
- [ ] Proctor roster: analytics stat tiles (total/present/absent/pending/submitted)

**Bulk actions** (evidence: legacy `bulk-accept/bulk-dismiss/bulk-reopen` routes; greenfield `kit/BulkActionBar.svelte` exists)
- [ ] Admissions list: wire bulk accept / dismiss / reopen + revert-to-pending (reconsider/reopen exist per-attempt)

**Admissions seasonal behavior**
- [ ] Pending applications expire when the cycle window closes, with notices (legacy `window_not_expired` checks)

**Knowledge / AI admin** (evidence: `Admin/AiCompanionAdminController.php`, `KnowledgeDocumentController.php`)
- [ ] Knowledge source CSV import + retry-sync
- [ ] Assistant persona editor

### M3 — Unified import framework (founder D-A)

- [ ] OpenSpec: one bounded import framework contract (analyze → staged preview → confirm; template download; invalid-row expansion + show-only-invalid; partial commit; quarantine; error export; idempotency; audit)
- [ ] Import UI pages (list / analyze / preview / confirm) shared by Admissions + Assessment
- [ ] Applicant import adapter (fields from legacy `ApplicationImportController`, alternate-header detection)
- [ ] Score import adapter — **bulk across all exam sessions**; rows carry session code or resolve via applicant→session
- [ ] Post-validation: reject rows whose session is not ready (not closed / grading not opened / session cancelled) with per-row reasons
- [ ] Confirm = full preview page (legacy shape), not a bare modal
- [ ] Tests: happy path, invalid rows, session-state rejection, partial commit, idempotent re-run, duplicate handling

### M4 — Result sheets & printing

- [ ] ResultSheetTemplate CRUD (html/docx modes, paper/orientation/watermark, placeholder groups + dual-slot, single-active, validate-document, preview) — evidence: `Admin/ResultSheetTemplateController.php`
- [ ] `DocxTemplateAnalyzer` port for docx template validation
- [ ] Replace hardcoded `PrepareResultDocument` stub (`template_version e2-v1`) with template-backed render
- [ ] Print jobs: bulk (copies 1–10, 200 cap), mark-printed, PDF + DOCX + ZIP, checksummed/versioned artifacts (evidence: `PrintBatchService.php`, `ReleasePrintController.php`)
- [ ] Result sheet print preview with paper + scale controls (evidence: `Pages/Release/ResultSheetBulk.svelte`)
- [ ] HANDOFF: seeded template + credentials + reproduce steps

### M5 — NFR hardening

**Performance (speed)**
- [ ] Query/N+1 audit across every module (scout reports first, fix top 20)
- [ ] Pagination everywhere lists render (SimplePagination exists — enforce)
- [ ] Inertia deferred props / partial reloads on heavy pages (dashboard, reports, rosters)
- [ ] Reference-data caching (cycles/programs/rooms/areas) with invalidation
- [ ] DB index review vs migration catalog; slow-query log
- [ ] Perf smoke: page-weight budget + Lighthouse CI gate on key journeys

**Rate limiting & protection**
- [ ] Audit existing throttle coverage (login 5, portal 5:1) — extend: forgot/reset, AI/nexi turns, imports, exports, QR resolve, OMR batch endpoints
- [ ] Login lockout/backoff beyond throttling; session hardening
- [ ] Upload validation: mime + size + content sniffing; quarantine (filesystem seams exist)
- [ ] Security headers + CSP review; secrets hygiene (env, no keys in client props)
- [ ] Request size limits; audit coverage for every mutation (AuditEvent exists)

**Observability & ops**
- [ ] Correlation IDs (`request_id`/`command_id`/`operation_id`) verified end-to-end
- [ ] Structured logs + error taxonomy; queue visibility; health/readiness endpoints exercised
- [ ] Backup/restore rehearsal evidence; retention holds

**Accessibility**
- [ ] WCAG 2.2 AA sweep on shipped surfaces; reduced-motion honored; keyboard/focus on dialogs

### M6 — Offline & PWA (proctor + OMR)

Offline-first surfaces per founder: **proctor conduct roster** and **OMR capture**. Architecture spine §2.4 + roadmap E3 (EV-011 durable outbox) govern; fallback = online/paper/manual reconciliation stays complete.

- [ ] OpenSpec: offline-first scope contract — proctor roster + OMR capture (+ applicant QR read); explicit non-goals (no offline exam start/extend/close — window authority stays server-side)
- [ ] Service worker + app-shell caching; PWA manifest + install; offline boot state
- [ ] IndexedDB local store: roster mutations + captured sheet images; storage-quota + browser-support decision
- [ ] Durable outbox (EV-011) with replay; reuse existing idempotency keys (`lib/utils newIdempotencyKey`) — sync is exactly-once
- [ ] Conflict policy: server authority wins; duplicate attendance/submission marks surface for manual reconcile — decision D pending founder
- [ ] Sync UX: pending queue, retry/backoff, degraded banner, conflict-resolution UI
- [ ] Offline OMR: queue scans locally → upload on reconnect → existing batch confirm; capture fully works offline
- [ ] Offline proctor: attendance/submission/QR recorded offline → replay online; extend/start blocked with clear degraded state
- [ ] Tests: Playwright offline-mode scenarios, outbox replay, conflict, quota; manual-reconciliation evidence

### M7 — Nexi/AI expansion

**Admission intelligence & follow-ups (founder 2026-08-16)**
- [ ] Deterministic admission decision-support model — **no training needed**: grades + aptitude conversion + quota fit + window rules → accept/dismiss/recommend proposal with explanation; fully auditable
- [ ] Human confirm via existing `AdmissionAction` commands (spine §2.4: models propose, never write authority)
- [ ] Follow-up engine (program-pool + follow-ups + threads already ported): scheduled follow-up prompts, reasons, resolution audit; wire into consultation/decision flow
- [ ] Custom-model verdict recorded: rule-based/zero-training is the default for admission support; ML (YuKee artifact/K-Means, BGE) stays evidence-gated later (master ref §8.1, roadmap E3)

**SDK evaluation (ADR-gated, E3 rules)**
- [ ] Spike: **Vercel AI SDK** vs **Laravel AI SDK** — criteria: Inertia/Svelte streaming fit, provider routing, retries/timeouts/rate limits, tool-calling, test doubles, outage behavior, data residency, redaction, migration path
- [ ] Record ADR with winner + fallback; no provider lock-in before ADR

**Engine hardening (foundation first)**
- [ ] Retries/timeouts/rate limits on nexi turns; emergency stop (exists) verified under load
- [ ] Capability-registry coverage + redaction + usage-meter accuracy
- [ ] Degraded-mode evidence: provider down → manual core fully operable

**Scheduling assistant (robustify OS8 `schedule.exam_session@1`)**
- [ ] Constraint/feasibility spike; proposal-only with human confirm via existing actions
- [ ] Beautiful UI: tool-call chips, thinking state, confirm sheet

**Inquiry — RAG over Knowledge sources**
- [ ] Ingest pipeline for approved KnowledgeSources (greenfield lifecycle draft→approve→active exists)
- [ ] Auth-before-retrieve, citation + refusal mandatory, corpus versioning, prompt-injection tests
- [ ] Embedding store decision (pgvector eval) + provider-agnostic port (spine §2.4)

**Consultation scheduling assistant**
- [ ] New skill: proposes open consultation slots; human confirm via `ConsultationSession` actions

**Future AI-assisted surfaces (slots only, later gates)**
- [ ] Applicant NL booking (`applicant.choose_slot` — explicitly out of OS8 scope today)
- [ ] OMR/model proposals via same typed boundary

### M8 — Full-system verification

- [ ] E2 exit evidence G2–G4 + H4 (result sheets + release trace)
- [ ] Legacy parity ledger persisted (this scan → `docs/legacy-reference/08-…` + crosswalk update)
- [ ] Full Playwright sweep: staff + applicant + proctor + public journeys
- [ ] 32 `REQ-VS-*` trace completion (tests or runtime evidence, not happy-path renders)
- [ ] Performance + security evidence report (M5 outputs)
- [ ] Founder demo + pilot clearance decision

---

## 5. Full-system module matrix

| Module | Greenfield now | Remaining | Milestone |
|---|---|---|---|
| Admissions (apply/review/actions) | apply, review, accept/dismiss/correct/reconsider/reopen/archive | bulk actions wiring, seasonal expiry, imports | M2, M3 |
| Examination (sessions/monitoring) | full lifecycle + reservation + monitoring | Test-Admin override start, date-grouped view | M2 |
| Proctor duty | duty assign, attendance/submission record | QR scan, bulk submit, extend, remove, stats | M2 |
| Assessment (grading/OMR) | manual + OMR capture stack | score import (bulk), rating-scale decision | M3, M2 |
| Release / Consultation | prepare/release/unrelease, consultation decision | result templates + printing, F2F block parity | M4 |
| Program Head | workspace, decisions, follow-ups, threads | follow-up automation + scheduled prompts | M7 |
| Registrar | enrollment eligibility + stamps | — | — |
| Setup | full hub + OMR config | settings hub, institution editor | M2 |
| Staff / Personnel | HR hierarchy, TA delegation, profile | — | — |
| Knowledge / Nexi | lifecycle + kernel/skills | persona editor, CSV import, RAG Inquiry, assistants | M2, M7 |
| Notifications | own-row projection + bell | sounds/new-toast (decision) | M1 |
| Dashboards / Reports | none (Staff/Home diagnostic) | staff dashboard, Reports hub + export | M1 |
| Imports | none | unified framework (applicant + score) | M3 |
| Admission intelligence | program-pool + follow-ups + threads ported | deterministic decision-support model (no training), follow-up automation | M7 |
| Offline / PWA | none | service worker + outbox for proctor + OMR, sync UX, conflict reconcile | M6 |
| Home / Marketing | single hero | full landing + About + footer | M1 |
| NFR / Ops | foundation seams, correlation IDs | perf, rate limits, protection, a11y sweep | M5 |
| Verification | per-change evidence | E2 exit, parity ledger, suite sweep | M7 |

---

## 6. Open decisions needing founder

1. Notification sounds + new-notification toast — restore or formally drop?
2. Rating-scale label layer on top of the locked conversion table — build or REJECT?
3. Direct assessment — in scope now (M2) or deferred (§6.6)?
4. Import confirm shape — full preview page (legacy, recommended) or dialog?
5. Applicant shell — add flash toasts/footer/theme toggle, or keep minimal?
6. Marketing copy scope — full blocks rebuild vs streamlined hero + About?
7. Google OAuth for staff login (legacy had it; greenfield plain login) — disposition?
8. AI SDK winner (after M7 spike) — Vercel AI SDK vs Laravel AI SDK.
9. Chart library — Chart.js port (legacy parity, known contract) vs lighter alternative? (recommend: port Chart.js — contract already documented)
10. PWA offline scope — confirm proctor roster + OMR capture as the offline-first surfaces; exam start/extend/close stay server-authority (blocked offline with clear degraded state)?
11. Admission decision-support model — confirm deterministic no-training model now; ML (YuKee/K-Means, BGE) later, evidence-gated?
12. Offline conflict policy — server-authority-wins vs manual reconcile for duplicate attendance/submission marks?

---

## 7. How to start

1. Founder reviews this plan; adjust milestone priorities/scope.
2. M0 closes first (in-flight OpenSpecs + suite parity) — no new cuts until clean.
3. Then one OpenSpec per milestone slice, in order: M1 (interface fill, biggest visibility) → M2 → M3 (import framework) → M4 → M5 → M6 (offline/PWA) → M7 (AI; SDK spike + admission-model design can start in parallel) → M8.
4. Each slice: legacy trace → disposition → story → OpenSpec → `system/` code → tests + seed HANDOFF → crosswalk update (project-context §2 loop).
5. Record D-A/D-B/D-C into GF-13 decision matrix.

---

## Appendix A — Legacy specifics to preserve (verified 2026-08-16)

> Binding detail for the checklist above. Every item cites legacy source paths. When an OpenSpec for a milestone is written, carry these specifics (with disposition + tests), not just the milestone heading. Anything here that conflicts with a founder decision in §2 loses to the founder decision.

### A1. Staff dashboard (`Pages/Dashboard.svelte`, `DashboardController.php`, `DashboardAnalyticsService`)
- KPI row: 4 cards — Total Applications / In Pipeline / Completed / Dismissed, accents ok/warn/ok/critical, href `/admin/applications`; KpiCard supports `delta`, `deltaLabel`, `secondaryItems[]`, TrendingUp/Down/Minus delta pills, 3xl bold value.
- Role gating: applications section → super_admin/admin/registrar_administrator; sessions → super_admin/proctor/registrar/test_admin; grading → super_admin/registrar/test_admin.
- Charts on dashboard: Application Growth (line, #14b8a6, h240), Session Trends (2-series line: Scheduled #3b82f6 fill:false / Completed #22c55e, h160), Session Status doughnut (h180), Pipeline Breakdown doughnut (h240), Top Course Preferences doughnut (top-5, h240), Grading Turnaround (line, #a855f7, h160), Grading Status doughnut (h180).
- Data: application trends = last 6 months zero-filled; session trends = 12 weeks (MySQL YEARWEEK — greenfield must define its own SQLite/Postgres-safe equivalent); grading turnaround = avg days finalized_at − exam date, 12 weeks.
- My Activity: last 10 audit rows {event, summary, category, created_at}, max-h 400 scroll, empty state "No activity recorded yet."
- REPRODUCE (computed in legacy but never displayed — treat as new features): `pipelineDistribution` (11-stage funnel), attendance trends (12-week present/absent), `score_release` (applicant results status). Surface all three in greenfield with scope/freshness notes + table alternatives.

### A2. Chart components (`Components/LineChart.svelte`, `DoughnutChart.svelte`)
- LineChart: `values` (single-series, fill 0.15 dark/0.1 light, tension .35, pointRadius 2/hover 5) or `datasets` (multi, per-dataset color/fill); y beginAtZero; x maxTicksLimit 8; legend bottom only when >1 series; theme-aware text (#e2e8f0 dark / #334155 light) + MutationObserver rebuild on `<html>` class; fixed-height wrapper (maintainAspectRatio:false).
- DoughnutChart: `{label, value, color?}` data; default 8-color palette [#3b82f6,#22c55e,#f59e0b,#a855f7,#ef4444,#14b8a6,#6366f1,#ec4899]; cutout 65%; legend labels append count "{label} ({value})"; tooltip "{label}: {val} ({pct}%)"; empty state dashed "No data available".
- Both: no a11y/tables in legacy — greenfield MUST add aria-label + data-table alternative (GF-22; do not copy the bug).
- BarChart.svelte: dead code — do not port.

### A3. Reports hub (`Admin/ReportController.php`, `Admin/Reports/Index.svelte`, `Services/ReportExportService.php`)
- 5 registrar reports: Applications Master List, Pipeline Status Summary, Course Preference Analysis, Demographics Report (sex, age bracket, province, GWA range), Dismissed Applications. 4 guidance: Exam Scores Report, Exam Session Roster, Consultation Summary, Release Tracking. Domain-gated (super_admin sees all; registrar→registrar; test_admin→guidance).
- KPIs: Total Apps, Exam Sessions, Pending Review (violet), Released (emerald), Dismissed (rose).
- Pipeline doughnut color map (11 statuses): Pending #94a3b8, Accepted #3b82f6, Draft Scheduled #818cf8, Scheduled #6366f1, Printed #8b5cf6, Attended #a78bfa, Submitted #7c3aed, Scored #c084fc, Graded #a855f7, Released #22c55e, Dismissed #ef4444.
- Course Demand vs Recommendation table: pref1/2/3 columns, total_demand (blue), recommended (emerald), alignment % badge — emerald ≥60 / amber ≥30 / rose <30.
- Demographics: inline doughnuts By Sex (#3b82f6/#ec4899/#94a3b8) and By Age (6 violet shades #6366f1→#ede9fe), h-[200px], cutout 65%.
- Export: AY filter (preserveState/preserveScroll), per-report .xlsx + .pdf buttons with per-button spinner, disabled without AY; export blocked for roles without domain access.

### A4. Setup readiness hub (`Admin/SetupController.php`, `Admin/Setup/Index.svelte`)
- Overall banner: "{score} of {total} checks passing", big %, label tiers (100 "All systems ready" / ≥90 "Almost there" / ≥60 "Needs attention" / else "Setup required"), animated progress bar emerald ≥90 / amber ≥60 / red, badges "{n} critical" + "{n} need attention".
- 10 categories with severity semantics — critical fail → category warning; any critical → critical icon; important fail → warning; else check icon:
  - academic_years (5): ay_exists crit, ay_active crit, ay_window_configured imp, ay_window_not_expired imp, ay_window_open opt
  - courses (2): exist crit, active crit · rooms (2): exist crit, active imp
  - aptitude_areas (3): exist crit, active crit, scoring imp
  - result_templates (2): exist imp, active imp · admission_templates (2): enabled opt, template opt
  - privacy_policies (2): exist imp, active imp · staff (3): super_admin crit, registrar imp, test_admin imp
  - institution (3): name imp, exam_name imp, counselor opt · rating_scales (2): exist imp, default opt
- Checklist behavior: header row (icon, label, passed/total, mini progress bar, chevron), expanded pane lists checks {label, passed, severity, message}, failed criticals get "Required" badge + "Configure {label}" link to category href; **first failing category auto-expands**; AI Companion card gets emerald "Active" badge; per-card health mini-bar "{passed}/{total} ({pct}%)".

### A5. Settings (`Admin/SettingsController.php`, `Admin/Settings/Index.svelte`)
- 8 toggles: `ai_exam_companion_enabled`, `notify_on_publish`, `release_mode`, `allow_direct_assessment`, `allow_flexible_applications`, `enable_normalized_scores` (do NOT reintroduce), `admission_slip_enabled`, `admission_slip_html_template`; plus `ai_companion_persona`.
- Admission slip template: textarea + placeholder reference + live iframe preview (POST `/admin/settings/admission-slip-preview`, HTML run through HTMLPurifier `admission_slip` config) + reset.
- Autosave toggle UX; audit `settings.updated` on every change.

### A6. Institution profile (`Admin/InstitutionController.php`)
- 8 profile keys: name, campus, address, contact_number, email, website, exam_name, exam_acronym.
- 7 personnel roles × {name, title, credentials}; env-override badges; collapsible role cards; reset defaults (clears guidance_counselor/testing_coordinator personnel overrides); audit.

### A7. Audit log (`Admin/AuditLogController.php`, `Admin/Logs/Index.svelte`)
- Columns: Time / Event / Category / Summary / Actor / IP / Details; payload JSON drawer; filters event/category/actor/date range, auto-apply; paginate 25; CSV export.

### A8. Rating scales (`Admin/RatingScaleController.php`) — subject to open decision #2
- Shape: name + ranges[{min, max, label}] covering 0–100 + `is_default`; default-first ordering; form seeded with 5-range example, add/remove rows, default switch; audit.

### A9. Direct assessment (`DirectAssessmentController.php`) — subject to open decision #3
- Gated by `allow_direct_assessment`; targets accepted applicants WITHOUT an exam session; creates a grading session; role-based redirect/dialog; "Direct" badge on grading rows.

### A10. Test Admin sessions (`Admin/TestAdmin/Index.svelte`, `ExamSessionController`)
- Today / Upcoming / Past groups; per-session: date, time range, room/building, applicants_count, status badge (draft muted / published success / in_progress warning / completed outline / cancelled danger).
- Actions: Start (Play), Start (override) amber when `can_override_schedule` outside window, Close (confirmation dialog), View → roster; no-applicants/past-date/outside-window helper texts.

### A11. Session roster (`Components/SessionRoster.svelte`, `Proctor/SessionRosterController.php`)
- QR scan attendance + submission (camera), present/absent, per-applicant submission, bulk submit, extend (1–120 min; legacy UI 30-min quick), remove applicant, live window recompute, straggler warnings.
- Analytics panel (test_admin+): stat tiles Total / Present / Absent / Pending attendance / Submitted / Present-not-submitted; proctors get compact inline Stats bar.
- Applicant table with search; QR camera states.

### A12. Result sheet templates (`Admin/ResultSheetTemplateController.php`) — M4
- Modes html | docx; paper size, orientation, logical unit; watermark; single active (activate/deactivate); preview + validate-document endpoints; placeholder groups incl. `_2` dual-slot placeholders.

### A13. Score import (`Grading/ScoreImportController.php`, `Pages/Grading/Import*.svelte`) — M3, founder D-A overrides per-session
- Legacy shape: analyze checks → template CSV download → session-stored preview → valid/invalid counts, per-area score columns, invalid badges, row selection, 50-row cap → confirm; guide panel + ImportFileAnalyzer.
- Greenfield overrule: no session selector; file carries/derives session per row; post-validation rejects rows whose session is not scoreable; bulk across ALL sessions.

### A14. Applicant import (`Admin/ApplicationImportController.php`, `Pages/Admin/Applications/Import*.svelte`) — M3
- Analyze → preview → confirm; template CSV with sample row; alternate header detection; error expansion; show-only-invalid toggle; show-more pagination; "Import N selected"; ImportFileAnalyzer component.

### A15. Release printing (`ReleasePrintController.php`, `Pages/Release/*.svelte`) — M4
- PrintBatch: select-all, copies (1–10), bulk print / download PDF / DOCX, mark printed / unmark; 200-cap.
- ResultSheetBulk: paper + scale controls, fit-to-bounds, print all, mark all printed; per-applicant result sheet view + PDF + DOCX; mark-printed per grading session.
- Private checksummed artifacts (greenfield seam exists); DOCX merged/ZIP.

### A16. Notifications (`NotificationDropdown.svelte`, `lib/notifStore.js`, `lib/notification-sound.js`) — decision #1
- 45s poll, red-dot badge, new-notification toast with 5s cooldown, mark read/all; chime tiers (background 0.35 / action 0.85), audio preload.

### A17. Marketing home (`Pages/Home/Index.svelte`, `Pages/Home/About.svelte`, `Components/blocks/*`)
- `/`: glassmorphic blobs, activeSeason badge, "Secure Your Future" hero, Admissions CTA, ProcessFlow timeline.
- About: header band + Benefits (3 feature cards) + PerspectiveSection (one system three views) + StudentPerspectiveSection (4 left-border cards: apply/track/monitor/results) + FutureVision (4 "coming soon" roadmap cards).
- HomeLayout: Home / Application / About nav + Login/View Portal pill + ISPSC footer; CallToAction block has 3-col office info strip.
- ProcessFlow: 5-phase colored timeline, desktop horizontal / mobile vertical.

### A18. Applicant portal dashboard (`PortalAuthController::dashboard`, `Pages/Portal/Dashboard.svelte`)
- Status timeline tracker with current-step emphasis; Edit Application card when editable, locked notice when published; results-blocked banner when release_mode f2f; Exam Schedule card (date/time/room/building); Admission Slip card (Download PDF + Print Slip) gated on admitted stage; AI Companion card gated on consultation released; ApplicantTips sidebar.

### A19. Grading dashboard (`Grading/GradingController.php`, `Pages/Grading/Dashboard.svelte`)
- Amber warning card "No aptitude areas configured" + "Go to Setup" when zero areas; "Import Scores" outline button; SwitchableListView table|cards; rows "#{exam_session_id} — {room_name}" + "Direct" badge + date/time (12h) + progress badge "{n} of {m} scored" (success when finalized else warning); actions Input scores / View; empty "No grading sessions yet."
