# Proposal: Applicant Portal Interface

**Change:** `2026-08-15-applicant-portal-interface`
**Status:** ACTIVE — sole implementation change since 2026-08-15 (`2026-08-15-admission-model-program-head` archived PASS); founder approved the page inventory and slip-page DEFER
**Authority:** founder direction 2026-08-15; D-085/D-086/D-087/D-092/D-093; GF-22 §3.2/§9.1; FR-028/029/030; REQ-VS-003/016/018/023/026/027/028/030/031

## Why

The applicant interface is largely incomplete. `/applicant/portal` redirects to the exam schedule; the dashboard component exists but is unrouted; the shell has no navigation, no breadcrumbs, and no status timeline; consultation, program choice, QR, and admission support are disconnected deep pages. The legacy portal's most useful affordance — a vertical status timeline with contextual quick links ("what happens to me now?") — was lost in the rewrite.

This change restores that affordance as a mobile-first **status spine**: the timeline IS the applicant home, and every quick link hangs off the stage it serves. It also reconciles two ordering facts the legacy UI got wrong:

1. **Release placement is mode-dependent.** `ReleaseResult` ships `MODE_ONLINE` and `MODE_F2F`. Online: released results are visible in the portal; consultation remains a separate recommended step. F2F: the applicant claims results *at* consultation — release and consultation coincide, and the portal must show a truthful "results ready — claim at your consultation" state, never the scores.
2. **Guidance's advised default does not require acknowledgement to project.** The default already reserves quota and drives projections while `pending_acknowledgement`. The portal must present it as *active — no action needed*, with acknowledgement as an optional explicit upgrade ("This is my choice"), not as a blocking prompt.

## Canonical journey (mode-aware)

```text
Submitted → Review → Exam (schedule/seat) → RESULTS by mode:
   ONLINE:  results card shows released artifact
   F2F:     results card shows claim-at-consultation state;
            consultation card carries the reveal
   (release never waits for consultation — ReleaseResult invariant)
→ Consultation (independent card, unlocked at accepted)
→ Program choice (independent card; ✦ Guidance default active, ack optional)
→ Enrolling → Enrolled (Registrar-owned; cards only)
```

## Canonical truth — what this change may and may not touch

1. **No backend policy changes.** Release modes, quota/reservation semantics, `pending_acknowledgement`, capabilities, and Registrar/PH/Guidance ownership are shipped and authoritative (D-087/D-089–D-093). This change adds presentation projections (queries/controllers) and page chrome only. Any discovered backend gap is recorded and routed, not silently redesigned.
2. **Server-authored everything.** Navigation, breadcrumbs, stage order, and per-stage action visibility come from server projections (REQ-VS-027). UI is not authorization.
3. **The status spine is the hub.** `GET /applicant/portal` renders the hub; `/applicant/schedule` and `/applicant/consultation` remain deep-linkable pages with breadcrumbs back to the hub.
4. **Shared reservation vocabulary.** Exam slot reservation and consultation booking use one slot-row + confirm-sheet component contract (identical anatomy, states, and truthful wording).
5. **Admission slip page is DEFERRED.** Founder resolves the slip surface separately; this change ships no slip page and no slip link. Exam-stage cards may show session facts only.
6. **No legacy sounds/cooldowns (REJECT).** Notification presentation uses truthful in-app wording only; durable delivery states remain with the parked `notifications-delivery-outbox` change.

## Legacy evidence and disposition

| Legacy evidence | Disposition | Greenfield rule |
|---|---|---|
| `Portal/Dashboard.svelte` 3-column hub | **PRESERVE feel / REWRITE layout** | Status timeline + contextual quick links become the single-column status spine (GF-22 mobile-first); desktop gets sticky spine + detail pane |
| `ApplicationStatusTimeline.svelte` | **PRESERVE affordance** | Port as stage-node component: `✓ done · ◐ current · ○ locked · ⚠ action · ✦ auto` with accessible text alternative |
| `ApplicantTips.svelte` | **PRESERVE intent / MERGE** | "What's next" copy folds into the current-stage card |
| `PortalLayout.svelte` (bell, theme, logout) | **PRESERVE affordances** | Server-authorized nav + breadcrumbs + notification center per GF-22 §3.2 |
| `Portal/ApplicationShow.svelte` read-only record | **PRESERVE** | Own-scope read-only "My Application" page |
| `Portal/ApplicationEdit.svelte` free edit | **REWRITE (already shipped)** | `needs_correction`-gated correction form stays; edit prompt only in that state |
| `Portal/Dashboard` results-blocked f2f banner | **PRESERVE / EXTEND** | Mode-aware results card: online artifact vs f2f claim-at-consultation state |
| `Home/Index.svelte` hero + ProcessFlow | **PRESERVE lean** | Active-season badge + hero + process strip; no marketing bloat |
| `Portal` notification page + sounds/cooldowns | **REWRITE / REJECT** | Truthful queued/sent/failed wording; no sounds, no fake delivery claims (REQ-VS-023/030) |
| `Applications/AdmissionSlip*` | **DEFER** | Founder resolves later; excluded from this change |

Before implementation, open the matching legacy pages above and mimic their useful interaction shape. Authorization, privacy, state, audit, and trust boundaries remain rewrites.

## In scope

1. Applicant shell: server-authorized nav, breadcrumbs, identity header, sign out, NexiDock disclosure (UI kit tokens).
2. Status-spine hub (`GET /applicant/portal`): stage cards, mode-aware results placement, parallel consultation/program-choice cards, state-driven next steps.
3. My Application read-only page (own-scope).
4. Shared reservation UX: exam schedule + consultation booking (slot rows, confirm sheets, truthful states).
5. Program choice presentation: ✦ default-active copy, optional ack, quota/status truth.
6. Results presentation by release mode (online artifact vs f2f claim state).
7. Notification center page with truthful delivery wording.
8. Public landing: active-season badge, lean hero, process strip.
9. Login polish (D-085 copy: key + birthdate + device save + email backup).
10. Seeds, HANDOFF, Pest/Vitest/Playwright evidence (mobile 375px + desktop).

## Explicit non-goals

- Admission slip page/view/PDF (founder resolves later).
- Backend policy, mode, reservation, or quota semantics changes.
- Notification delivery outbox / mail provider claims (parked DEFERRED).
- Legacy sounds, cooldowns, theme toggle (staff kit keeps its own), or AI companion chat widget (NexiDock is the locked chrome).
- Any staff/PH/Registrar surface change.
- Missed-exam/reassignment policy (separate parity item, DECISION REQUIRED).

## Change safety

This planning package must not edit `system/` until `2026-08-15-admission-model-program-head` is archived, this change is promoted as the sole active implementation change, and the founder approves the page inventory in `design.md`. During implementation: targeted Pest/Vitest/Playwright/browser proof first, then full-suite/fresh-seed proof; all pages must use the locked UI kit tokens (IBM Plex Sans, `--radius: 0.5rem`, layered paper/glass) — see `docs/greenfield-foundation/evidence/ui-kit-locked-start-set.md`.
