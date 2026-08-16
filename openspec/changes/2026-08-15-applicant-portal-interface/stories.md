# Stories — Applicant Portal Interface

**Status:** ACTIVE — sole implementation change since 2026-08-15
**Primary requirements:** FR-028/029/030; REQ-VS-003/016/018/023/026/027/028/030/031; GF-22 §3.2/§9.1; D-085/D-086/D-087/D-092/D-093

## S1 — The applicant home is a status spine

- **Value:** one glance answers "what happens to me now?" on mobile.
- **Disposition:** PRESERVE legacy timeline feel; REWRITE layout (single column, mobile-first).
- **GWT:** Given an accepted applicant with a reserved exam, when they open `/applicant/portal`, then a vertical spine renders Submitted ✓, Review ✓, Exam ◐ (expanded, with seat + change-slot), Results ○ locked, Consultation and Program-choice cards with independent badges, and no floating action rail; the current stage is expanded by default and each action hangs off its own stage.

## S2 — Release placement follows the mode

- **Value:** applicants never see scores in f2f mode and never wait for consultation in online mode.
- **GWT:** Given a released result with `MODE_ONLINE`, when the applicant opens the hub, then the Results stage shows the artifact with table alternative. Given `MODE_F2F`, then the Results stage shows "Results ready — claim them at your consultation session" with a Book-consultation action and no scores. Given unreleased, then a locked truthful hint renders (no empty chart).

## S3 — Guidance default projects without acknowledgement

- **Value:** the applicant sees their projection is active and acknowledgement is optional, not a blocker.
- **GWT:** Given a Guidance default `pending_acknowledgement`, when the applicant opens Program choice, then the card shows `✦ set by Guidance · projection active · No action needed` with optional `[This is my choice]`; no copy implies the projection is missing without ack; ack upgrades to explicit current selection with truthful confirmation.

## S4 — Exam and consultation share one reservation feel

- **Value:** applicants learn the pattern once and reuse it.
- **GWT:** Given slot rows on Schedule and on Consultation, both show date/time/venue/capacity bar and the same confirm sheet (what/when/consequence + Cancel/Confirm); full rows disable with "Full", closed windows explain why, and a changed-by-staff conflict shows a refresh banner — identical anatomy on both pages.

## S5 — Server-authorized navigation and breadcrumbs

- **Value:** applicants reach every surface without dead ends; staff can't hide UI to protect routes.
- **GWT:** Given the applicant shell, then nav renders only projection-allowed items; deep pages show `Home › …` breadcrumbs with the current page as the last crumb and the document title; hub has no breadcrumb; mobile collapses the trail; hidden items are never rendered client-side.

## S6 — My Application is read-only and own-scope

- **Value:** the applicant can always re-check what they submitted without editing it freely.
- **GWT:** Given the own attempt, the applicant sees status, personal info, preferences, digital grades, and submitted_at; `needs_correction` alone shows the fix prompt; another applicant's attempt 403s; staff-only fields never serialize.

## S7 — Notification center tells the truth

- **Value:** applicants know what is in-app, queued, or failed — never fake "sent".
- **GWT:** Given unread notifications, the bell badge matches the center; each entry shows truthful status wording (delivered/queued/failed) and mark-read/read-all work; an empty center cannot mask stored unread rows (REQ-VS-023).

## S8 — Public landing preserves the journey feel

- **Value:** first-time visitors understand the admissions process before applying.
- **GWT:** Given a configured active season, `/` shows the institution name, an open-admissions badge, a lean hero, the process strip, and the three sign-in CTAs; no marketing widgets; UI kit tokens only.

## S9 — Login stays access-key simple

- **Value:** D-085 remains the only applicant credential model.
- **GWT:** Given the login page, applicants sign in with access key + birthdate, see device-save and email-backup copy, and receive non-enumerating errors; no password field exists.

## S10 — Every page state is explicit

- **Value:** no blank screen is ever mistaken for success.
- **GWT:** Given hub, schedule, consultation, program choice, results, and notifications pages, each renders loading/ready/empty/denied/error/conflict states per design §3/§6; errors offer retry or a support path (REQ-VS-026).
