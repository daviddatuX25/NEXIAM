# Design: Applicant Portal Interface

**OpenSpec:** `2026-08-15-applicant-portal-interface`
**Status:** ACTIVE — sole implementation change since 2026-08-15
**Decisions:** D-085, D-086, D-087, D-092, D-093; UI kit locked start set; GF-22 §3.2/§9.1

## 1. Canonical stage model

```text
LADDER (sequential, Registrar/Guidance-owned)
Submitted → Review(accepted) → Exam → Results → Enrolling → Enrolled

PARALLEL CARDS (independent badges; unlocked at accepted; neither blocks the other)
  Consultation   — booked / attended / none
  Program choice — ✦ Guidance default active (pending_acknowledgement)
                   · applicant ack = explicit current selection
                   · changes allowed until enrolling

RELEASE MODE BRANCH (authoritative: ResultRelease::mode)
  MODE_ONLINE  → results card shows released artifact
  MODE_F2F     → results card shows claim-at-consultation state;
                  consultation card carries the reveal
  (consultation is NOT a release prerequisite — release placement in the
   spine follows the mode, not consultation completion)
```

Stage node vocabulary (ported from legacy `ApplicationStatusTimeline`):

```
✓ done   ◐ current (expanded by default)   ○ locked (dimmed + truthful hint)
⚠ action needed (e.g. needs_correction)    ✦ auto-active (no ack required)
```

The spine renders stage **order** as: Submitted, Review, Exam, Results, Consultation, Program choice, Enrolling, Enrolled — but Results and the two parallel cards carry independent state badges and may visually swap emphasis by mode (f2f: consultation card sits under the claim-state results card; online: results artifact then consultation). The server projection emits `stage_order` and per-stage state; the client never reorders.

## 2. Applicant shell contract

- **Tokens:** UI kit lock — IBM Plex Sans, `--radius: 0.5rem`, layered paper background, `glass-panel`/solid card, brand green; dark token block; no new fonts/radii (see `ui-kit-locked-start-set.md` §2).
- **Layout:** mobile-first single column; header = identity row (NEXIAM › applicant name), notification bell with unread badge, overflow menu; desktop `max-w-3xl` content, nav inline.
- **Navigation (server-authorized):** hub, My Application, Schedule, Consultation, Results (when applicable), Program choice, Notifications, QR, Admission support. Items render only when the capability/projection allows; hidden items are not rendered client-side (REQ-VS-027).
- **Breadcrumbs:** on deep pages (`Home › Schedule`); final crumb drives document title; mobile collapses; never rendered on the hub itself.
- **NexiDock:** locked chrome, bottom-right, disclosed assistant states; no chat widget revival.
- **Sign out** always visible (shared-device copy on hub).

## 3. Status-spine hub — page/state matrix

`GET /applicant/portal` → `ApplicantPortalController::dashboard` (replaces the redirect) → renders `Applicant/Portal.svelte` (or renamed Dashboard) with a single server projection.

| State | Trigger | Rendering |
|---|---|---|
| `loading` | initial Inertia visit | skeleton spine (aria-busy), no empty success |
| `ready` | projection available | full spine + current-stage card expanded |
| `empty` | authenticated, no attempt | truthful "No application found for this account — contact the Registrar" + login key hint |
| `denied` | not authenticated | redirect to login (never a bare spine) |
| `error` | projection failure | retry + support path; never blank |
| `conflict` | stale version after staff change | banner "Your status changed — tap to refresh" with preserve-scroll reload |

Projection payload (server-authored, redacted):

```json
{
  "identity": { "name", "applicant_no", "program_hint" },
  "stages": [
    { "key": "submitted", "label": "Submitted", "state": "done", "at": "…", "actions": ["view_application"] },
    { "key": "review", "state": "done", "at": "…" },
    { "key": "exam", "state": "current|done|locked", "session": {…}|null, "actions": ["reserve", "view_seat"] },
    { "key": "results", "state": "released|blocked|locked", "mode": "online|f2f", "actions": ["view_results"] },
    { "key": "consultation", "state": "booked|attended|none", "actions": ["book", "reschedule"] },
    { "key": "program_choice", "state": "default_active|acknowledged|chosen|none", "default": {…}|null, "actions": ["ack", "choose"] },
    { "key": "enrolling", "state": "…" },
    { "key": "enrolled", "state": "…" }
  ],
  "notices": { "results_blocked_reason": null | "f2f", "needs_correction": false },
  "quick_links": { "notifications", "qr", "admission_support", "help" }
}
```

Rules:
- Actions render **only** under the stage they serve; no floating action rail.
- `needs_correction` puts `⚠` on Submitted and opens it by default.
- `dismissed` renders a terminal card (truthful, not an empty spine).
- Every chip/count has a text alternative; charts (results) always pair with a table (REQ-VS-029).

## 4. Stage-card quick-link map

| Stage | Sub-actions (gated by projection) | Page |
|---|---|---|
| Submitted | View my application · Fix requested info (⚠ only) | `/applicant/application/{attempt}` · correction |
| Review | — (status copy only) | — |
| Exam | Reserve/change slot · My seat | `/applicant/schedule` |
| Results | View results (online) · Book consultation (f2f claim hint) | results card inline or `/applicant/results` |
| Consultation | Book · Reschedule · View booking | `/applicant/consultation` |
| Program choice | This is my choice (optional) · Choose/change | `/applicant/program-choice` |
| Enrolling/Enrolled | status copy (Registrar-owned) | — |

## 5. Release-mode presentation

- **ONLINE:** results card shows the released artifact (score chart + accessible table + version/freshness), per REQ-VS-022/FR-028. No scores before release.
- **F2F:** results card shows `🔒 Results ready — claim them at your consultation session.` with a Book-consultation action; no scores render in the portal for f2f mode. Copy never implies online availability.
- Pre-release: `○ locked — not yet released; we'll notify you here.` (REQ-VS-026: no empty chart).

## 6. Shared reservation UX (exam + consultation)

One component contract, two pages:

```text
SLOT ROW:  date · time · venue · capacity bar (seats ▓▓▓▓░░) · [Select]
CONFIRM SHEET: what · when · consequence copy · [Cancel] [Confirm]
STATES: open · full (disabled, "Full") · closed (window closed) ·
        already-booked (shows current booking) · changed-by-staff (conflict banner)
```

- Confirm posts carry the existing idempotency keys; the sheet must never claim publication or delivery beyond evidence (REQ-VS-028/030).
- Exam: `reserve` posts to `/applicant/schedule/reserve` (existing). Consultation: `/applicant/consultation/reserve` (existing). No new commands.

## 7. Program choice presentation (✦ semantics)

- Guidance default (`pending_acknowledgement`) renders as: `✦ BSCS — set by Guidance · projection active · No action needed` with optional `[This is my choice]`.
- Acknowledgement is **optional for the projection** (already reserved/visible to authorized staff); ack upgrades it to the applicant's explicit current selection. Copy must not imply the projection is missing without ack (D-093/`program-choice-and-quota` spec).
- Options list shows quota truth (`reserved/available/full`, "not configured → unavailable", no counters invented client-side).
- Changes allowed until enrolling; transfer/deny states copy from shipped backend rules.

## 8. My Application (read-only)

Own-scope detail (`ApplicantScope::attemptBelongsToUser`): status, personal info, preferences, digital intake grades (D-086), submitted_at, correction prompt only when `needs_correction`. Desktop: breadcrumb `Home › My application`; mobile: `‹ Back`.

## 9. Notification center

- Page at `/notifications` (existing `NOTIFY_READ_OWN` route) styled for applicant shell.
- Truthful status wording: `in-app · delivered` vs `queued` vs `failed`; never "sent" without provider evidence (REQ-VS-023/030); mark-read/read-all preserved.
- Bell badge = unread count from the same projection (REQ-VS-023: empty UI cannot mask stored unread).

## 10. Public landing

`GET /` (`Home.svelte`): institution name, active-season badge (server prop `activeSeason`), lean hero, process strip (Submitted→Exam→Results→Enroll), three CTAs (Apply · Applicant sign in · Staff sign in). No marketing bloat; UI kit tokens; GuestLayout.

## 11. Route/page inventory

| Route | Page | New/Changed | Notes |
|---|---|---|---|
| GET `/` | `Home` | CHANGED | activeSeason + process strip |
| GET `/applicant/login` | `Applicant/Login` | CHANGED | D-085 copy polish |
| GET `/applicant/portal` | `Applicant/Portal` (hub) | NEW | replaces redirect; spine projection |
| GET `/applicant/application/{attempt}` | `Applicant/MyApplication` | NEW | read-only own record |
| GET `/applicant/schedule` | `Applicant/Schedule` | CHANGED | shared slot UX |
| GET `/applicant/consultation` | `Applicant/Consultation` | CHANGED | shared slot UX |
| GET `/applicant/program-choice` | `Applicant/ProgramChoice` | CHANGED | ✦ presentation |
| GET `/applicant/qr` | `Applicant/MyQr` | UNCHANGED | nav link added |
| GET `/applicant/admission-support` | `Applicant/AdmissionSupport` | UNCHANGED | nav link added |
| GET `/notifications` | `Notifications/Index` | CHANGED | applicant-shell styling + truthful wording |

No new write routes, no new capabilities, no new actions. New server work = one hub projection query + controller method + minor prop additions; controllers stay thin (project-context §3.8).

## 12. Evidence contract

- Pest: hub projection per release mode (online/f2f/pre-release), own-scope denial (REQ-VS-003/FR-030), needs_correction ordering, guidance-default ✦ payload, notification unread consistency (REQ-VS-023).
- Vitest: spine component states (✓/◐/○/⚠/✦), slot-row states (open/full/closed/booked), confirm-sheet wording.
- Playwright/browser: mobile 375px + desktop walkthrough of hub → schedule reserve → consultation book → program choice ack; breadcrumbs on deep pages; no console errors (REQ-VS-018/031).
- HANDOFF.md: applicant access key + birthdate seed credentials, reset command, URLs.
