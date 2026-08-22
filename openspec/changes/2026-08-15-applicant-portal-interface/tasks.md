# Tasks — Applicant Portal Interface

**Status:** ACTIVE — implementation complete 2026-08-15 (Pest 9+7, Vitest 8, browser-verified mobile+desktop); archive after full-suite confirmation.

## Phase 0 — Promotion preflight

- [x] `2026-08-15-admission-model-program-head` archived PASS (moved to `openspec/changes/archive/` + README row); this change is sole active code authority.
- [x] Founder approved design page inventory (design §11) and slip-page DEFER note ("lets go").
- [x] No new capability rows required — reuse `NOTIFY_READ_OWN`, schedule/consultation reserve caps, program-choice caps, own-scope routes.

## Phase 1 — Server projections

- [x] `ApplicantPortalController::dashboard` renders the hub instead of redirecting; new `ApplicantPortalProjection` query (thin controller, project-context §3.8) emitting design §3 payload: identity, stages with states/actions, release mode, notices, quick links.
- [x] `MyApplication` projection: own-scope read-only record (`ApplicantScope::attemptBelongsToUser`), redaction of staff-only fields.
- [x] Prop additions for landing (`activeSeason`), notifications truthful status, and ✦ program-choice presentation (reuse existing shipped actions; no new commands).
- [x] Pest: hub projection × mode (online/f2f/pre-release), own-scope 403, needs_correction ordering, guidance-default payload, unread-count consistency.

## Phase 2 — Applicant shell + navigation

- [x] Shell rework on locked kit tokens: identity header, server-authorized nav, bell badge, overflow menu, sign out; NexiDock disclosure untouched.
- [x] Breadcrumbs on deep pages (design §2); document titles from final crumb; mobile collapse.
- [x] Vitest: nav renders only allowed items; breadcrumb semantics.

## Phase 3 — Status-spine hub page

- [x] `Applicant/Portal.svelte` (replace unrouted Dashboard): stage nodes `✓ ◐ ○ ⚠ ✦`, current stage expanded, actions under their stage only, terminal dismissed card.
- [x] Mode-aware results card (online artifact + table alternative vs f2f claim state vs locked hint).
- [x] Consultation + program-choice parallel cards with independent badges.
- [x] Explicit states: loading skeleton / empty / denied / error+retry / stale-conflict refresh banner (design §3).
- [x] Vitest: spine states; results card × mode; quick-link gating.

## Phase 4 — Shared reservation UX

- [x] Extract shared slot-row + confirm-sheet components (open/full/closed/booked/changed states); adopt in `Schedule.svelte` and `Consultation.svelte` with existing idempotent posts untouched.
- [x] Truthful confirm copy: reserve ≠ publish ≠ delivered.
- [x] Vitest: slot states + confirm wording.

## Phase 5 — Program choice, My Application, notifications, landing, login

- [x] `ProgramChoice.svelte`: ✦ default-active card ("No action needed"), optional ack, quota truth (reserved/available/full/not-configured), change-until-enrolling copy.
- [x] `MyApplication.svelte`: read-only record + correction prompt gated on `needs_correction`.
- [x] `Notifications/Index` applicant-shell styling + truthful queued/failed wording; mark-read/read-all preserved.
- [x] `Home.svelte`: active-season badge + process strip + three CTAs (kit tokens, GuestLayout).
- [x] `Login.svelte`: D-085 copy polish (key + birthdate + device save + email backup; non-enumerating errors).
- [x] Vitest per page; Pest for any new controller edge.

## Phase 6 — Seeds, HANDOFF, browser proof, closeout

- [x] Seed one applicant at each spine state (fresh submit, accepted+calendar open, booked+released-online, released-f2f, guidance-default ✦) via `config/seed.php`/named seeder.
- [x] HANDOFF.md: applicant access key + birthdate credentials, reset command, expected starting state per scenario, feature URLs.
- [x] Playwright/browser: mobile 375px + desktop walkthrough (hub → reserve → consult → ack → results by mode); zero console errors (REQ-VS-018/031).
- [x] Full suite + fresh-seed proof; archive change and update master-reference checkpoint + archive README.

## Phase 7 — Legacy home page port (founder-directed 2026-08-17)

- [x] Traced legacy `Pages/Home/Index.svelte` + `Layouts/HomeLayout.svelte` + `Components/blocks/ProcessFlow.svelte` (disposition: PRESERVE interaction shape; REWRITE chrome on locked kit tokens; greenfield routes only).
- [x] `Layouts/HomeLayout.svelte`: public shell (sticky header, Home/Application nav, auth-aware pill — `/staff` for staff, `/applicant/portal` for applicants, `/login` fallback; footer © ISPSC Tagudin).
- [x] `Components/blocks/ProcessFlow.svelte`: 5-phase glass cards (desktop) + vertical timeline (mobile); Registrar/Guidance offices, phase colors from legacy baseline.
- [x] `Pages/Home.svelte`: one-screener hero (glassmorphic wash, season badge + window, institution eyebrow, gradient headline, Admissions CTA) extended with greenfield features: applicant sign-in (`/applicant/login`), staff sign-in (`/login`), D-085 access-key hint.
- [x] `tw-animate-css` added (also activates latent `animate-in` utilities used by existing kit components); entrance animations now render.
- [x] Pest: seeded `/` asserts `activeSeason.name` + `activeSeason.window` payload (FoundationHealthTest); existing home contract unchanged.
- [x] Browser proof (Chrome, zero console errors): desktop 1280 — badge/headline/CTAs/5 cards, no overflow; mobile 390 — vertical timeline, no horizontal scroll; applicant login (PORT-DEFL/2003-09-25) → "View Portal" pill; staff login (registrar.clerk@nexiam.test/password) → "Staff Portal" pill; Admissions CTA → `/apply`.
