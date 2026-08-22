# HANDOFF — Applicant Portal Interface (`2026-08-15-applicant-portal-interface`)

## Status
ACTIVE — applicant interface implementation complete; tests green; browser-verified. Archive pending founder review. The public landing is now owned by `institutional-public-experience`.

## What ships

- **Status-spine hub** at `/applicant/portal` (was a redirect): server-projected journey spine — Submitted → Review → Exam → Results → Consultation → Program choice → Enrolling → Enrolled. Stage states `✓ done · ◐ current · ○ upcoming · ⚠ action · ✦ auto`; actions hang off their stage; quick links row (Notifications / My QR / Admission support).
- **Mode-aware results placement**: `MODE_ONLINE` renders the released score artifact (descriptor-safe bars + table facts); `MODE_F2F` renders "claim at your consultation" + Book-consultation action and serializes **no scores**. Pre-release renders a locked hint.
- **Guidance default ✦**: `pending_acknowledgement` default shows as active projection ("No action needed") with optional **This is my choice** acknowledgement; Program Choice page copy updated to match (non-blocking).
- **My Application** read-only page (`/applicant/applications/{attempt}`, own-scope 403 for others) with personal info, preferences, digital intake grades, correction prompt gated on `needs_correction`.
- **Applicant shell**: server-authorized nav (8 items) + breadcrumbs on deep pages + doc titles from crumbs; NexiDock unchanged.
- **Shared reservation UX**: `ReservationSlotList` + `ReservationConfirmSheet` components adopted by Schedule and Consultation (identical slot anatomy + confirm sheet).
- **Landing**: active-season badge + 5-step process strip + 3 CTAs.
- **Login** now lands on the hub (`/applicant/portal`) instead of the schedule.

- **Public landing ownership:** `/` is now the institution-first surface documented in `openspec/changes/institutional-public-experience/`; the legacy process-strip notes below are historical baseline only.

## Temporary login default (2026-08-15)

The applicant login form now pre-fills **`PORT-DEFL` · `2003-09-25`** (richest hub: released online + consultation booked + Guidance default ✦ with optional ack) and lists all three portal keys on the page. Other keys are typed manually. Default is `local`/`testing`-environment only; revisit when the slip page is resolved.

## Who to log in as (all birthdate `2003-09-25`)

| Scenario | Access key | What you see |
|---|---|---|
| Released online | `PORT-ONLN` | Full spine; Results DONE with released score profile inline; consultation booked |
| Released f2f | `PORT-F2F2` | Results NEEDS ACTION — claim at consultation; no scores anywhere |
| Guidance default ✦ | `PORT-DEFL` | Program choice AUTO — "set by Guidance · projection active" + optional This-is-my-choice |
| Accepted (existing) | `DEMO-ACPT` | Spine through Review; exam reservation states |
| Pending (existing) | `DEMO-PEND` | Spine through Submitted/Review current |

## Seed / recreate

```bash
cd system
docker compose exec -T app php artisan foundation:reset --force
```

`DatabaseSeeder` → `ApplicantPortalDemoSeeder` (idempotent; distinct identities/rooms per journey; pinned access keys; released via the real `ReleaseResult` action with mode).

## Expected starting state

Fresh DB: 3 released demo applicants at the states above, each with exam seat + released result (mode-specific), the ✦ applicant with an advised default selection reserving quota.

## URLs

- Landing `/` (season badge + process strip)
- `/applicant/login` → `/applicant/portal` (hub)
- `/applicant/applications/{attempt}` (My Application)
- `/applicant/schedule` · `/applicant/consultation` (shared slot UX)
- `/applicant/program-choice` (✦ default card)
- `/notifications` (truthful queued/delivered/failed wording)

## Verification

- Pest: `tests/Feature/Applicant/ApplicantPortalInterfaceTest.php` (9 tests: empty/needs_correction/online/f2f/dismissed/auto-default/stage-order/own-scope/nav) + `ApplicantAccessTest` updated (login lands on hub).
- Vitest: `tests/frontend/applicant-portal.test.ts` (8 tests: stage order/statuses, mode serialization, ✦ contract, slot states, nav).
- Browser (mobile 390px + desktop 1280px): landing badge+strip; hub spine for all three demo keys; f2f blocks scores; ✦ card; My Application + breadcrumbs; nav 8 items; no horizontal overflow; zero console errors.

## Deferred (founder)

- Admission slip page — excluded; founder resolves later.
- Missed-exam/reassignment policy — separate parity item.
- Notification delivery outbox — parked DEFERRED change.

## Landing baseline — superseded by institutional public experience (2026-08-17)

The public `/` page was previously a port of legacy `Home/Index.svelte` (one-screener hero, season badge, institution eyebrow, Admissions CTA, and 5-phase process strip). That baseline remains recorded for traceability; it is not the current public composition.

- **Applicant sign in** → `/applicant/login` (D-085 access key + birthdate)
- **Staff sign in** → `/login`
- **Auth-aware portal pill** in the header: staff → `/staff` ("Staff Portal"), applicant → `/applicant/portal` ("View Portal"), guests → Login
- Access-key hint copy under the CTAs

### Verify (fresh seed)

```bash
cd system && docker compose exec -T app php artisan foundation:reset --force
```

- Historical verification only: the former landing showed the season badge, gradient headline, process cards/timeline, footer, and applicant/staff CTAs. Current homepage verification and replacement inventory live in `institutional-public-experience/HANDOFF.md`.

### Files

- `resources/js/Pages/Home.svelte` (rewrite)
- `resources/js/Layouts/HomeLayout.svelte` (new — legacy `HomeLayout` port)
- `resources/js/Components/blocks/ProcessFlow.svelte` (new — legacy block port)
- `resources/css/app.css` + `package.json` (`tw-animate-css` — activates `animate-in` utilities already referenced by kit components)
- `tests/Feature/FoundationHealthTest.php` (seeded `activeSeason` payload assertion)
