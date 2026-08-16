# Proposal: Admission Support, Program Choice, and Program Head Interface

**Change:** `2026-08-15-admission-model-program-head`
**Status:** APPROVED QUEUED PLAN — founder decisions reconciled 2026-08-15; implementation not started; promotion waits for the active OMR cut to release sole-code-change authority
**Authority:** founder decisions recorded in D-087, D-ROLE-05, and D-080/D-089–D-093

## Why

The consultation/release cut is complete. The remaining capstone gap is the post-result admission-support journey: Guidance advises the applicant, the applicant chooses from bounded program options, program quotas become operational, Program Heads receive scoped visibility and edge-case authority, and Registrar closes the admission-system journey with `enrolling` / `enrolled` stamps.

This change also builds the per-applicant admission-support visualization backbone inside the existing consultation workspace: released score charts with accessible tables, preferences, structured consultation evidence, separate future-analysis regions, and the human decision/pool history. D-093 defers both the verified YuKee artifact/K-Means plugin and the separately named deterministic/rule-based plugin to later planning; this cut installs no provider, fake fixture engine, SDK, or `ProgramSuggestion` review workflow.

## Canonical truth — actor ownership

1. **Registrar reviews, corrects, ACCEPTS, or dismisses an application. Registrar acceptance is the gate to exam assignment.** Guidance and Program Head never perform application Accept/Dismiss.
2. **Guidance/Test Administrator owns exam scheduling, proctoring, scoring, result release, consultation/advising, the post-consultation program pool, and follow-up routing.**
3. **Applicant choice or Guidance's advised default owns the provisional pre-enrollment selection.** It reserves quota. Applicant may acknowledge through portal/Guidance, or Registrar may record sheet-based confirmation while starting `enrolling`.
4. **Program Head sees only assigned-program candidates and acts minimally.** Normal processing needs no PH system approval. Direct selection decline is pre-`enrolling`; an already-routed edge follow-up remains actionable during `enrolling` and blocks only `enrolled`.
5. **Registrar alone stamps `enrolling` and `enrolled`.** `Enrolling` starts when the sheet is submitted even with open follow-up. `Enrolled` requires PH resolution for the edge case and signed-sheet verification; Registrar records the paper's final program even when it differs from advice/pool/quota. No broader enrollment SIS is added.

## Canonical journey

```text
Application submitted
  → Registrar reviews/corrects
  → Registrar ACCEPTS (exam gate)
  → Guidance schedules/administers exam
  → Guidance scores/finalizes/releases result
  → Guidance consults/advises, separately sets the pool, and normally records the advised default (applicant self-selection remains available)
  → provisional selection reserves quota; no PH approval required
  → applicant submits the enrollment sheet; Registrar confirms the provisional choice as needed and stamps enrolling
  → normal path: Registrar later verifies signed paper, confirms/adjusts final program, stamps enrolled
  → optional edge follow-up: it remains visible beside Enrolling; assigned PH clears/declines/requests appearance
  → enrolled is blocked only while that follow-up remains open
  → signed paper is final program truth; Registrar may record an out-of-pool/full/unconfigured program, release the old reservation, preserve capacity, and report variance
```

## Program Head visibility — two tiers

### Potential overview (minimum necessary)

A program may show an applicant as **Potential** when the program appears in the applicant's ordered application preferences or active Guidance pool and the applicant is not committed elsewhere. The row shows only approved intake identity, preference/pool basis, and pipeline tags (`accepted`, `exam done`, `result released`, `consulted/advised`, etc.). Initial preference alone does not expose scores or counselor notes and never reserves quota.

### Protected admission-support record

Full PH access requires released results plus an applicant-acknowledged current selection or an explicit targeted follow-up routed to that program. Preference/pool membership and an unacknowledged Guidance default remain minimal. The protected record may show converted per-domain scores, result/remark sheet, consultation outcome, bounded counselor notes, assigned-program basis/selection/follow-up/reconsideration state, and Registrar stamps. A separately approved future plugin may add attributable assigned-program analysis evidence; the current cut exposes none. The record never serializes other pool options or cross-program choice history. Raw sheets, scans, conversion tables, and unrelated counseling notes never appear.

Every protected open is audited. The applicant receives a deduplicated access notice (first PH/program protected open in a 24-hour window); the portal retains access history.

## Program lists

- **Potential:** program occurs in application preferences or Guidance pool; applicant is not committed elsewhere. Rows show why (`preference #N`, `guidance pool`) and stage tags.
- **Upcoming:** applicant has an active, acknowledged selection for the program, a single remaining option, and no unresolved follow-up.
- **Follow-up queue:** Guidance created a targeted unresolved follow-up for the program.
- **Enrolling / Enrolled:** Registrar stamps attached to the applicant's current selected program.

## Quota truth

- Program Head writes quota for assigned program(s); Guidance Counselor/Test Administrator may write any authorized program quota (D-087).
- Preferences and Guidance pool membership consume **zero** quota.
- An applicant selection reserves one slot transactionally (FCFS, idempotent, capacity-guarded).
- A Guidance default reserves a slot and remains `pending_acknowledgement`; the applicant may acknowledge earlier, or Registrar records attributable sheet-based confirmation atomically with `enrolling`.
- PH decline of an applicant-acknowledged or explicitly follow-up-targeted selection, applicant replacement, or cancellation releases the old slot atomically.
- Dashboard quota fill counts active and pending-acknowledgement reservations, not Registrar-accepted applications.

## Messaging and universal applicant QR

- PH ↔ Guidance internal thread is anchored to the consultation/program case; no PH permission is required for Guidance's normal work.
- PH ↔ applicant messages are bounded to the current provisional selection or targeted follow-up during the admission/enrollment window, audited, and visible in the same admission-support case—not a general inbox.
- Applicant sees PH access, decline, follow-up, and message notices.
- NEXIAM exposes one applicant QR concept across portal and print. Its opaque locator contains no business action or destination.
- Authenticated scan opens a role-neutral applicant action hub. The server derives permitted destinations from ordinary capability, duty, office/program/cycle relationship, and case state: Registrar may receive application/eligibility/enrollment routes; Guidance/TA may receive exam, grading, result, consultation, and follow-up routes; PH receives only D-080/D-089 assigned-program routes.
- Scan never grants authority or performs a mutation. An actor with no permitted destination receives no applicant data.

## Consultation-embedded admission-support visualization

- The existing `/consultation` selected-attempt workspace owns the interface; there is no standalone model/suggestion page.
- Tabs/panels show applicant readiness, released converted per-domain score charts plus same-value accessible tables, ordered preferences, approved structured consultation/interest evidence, independently sourced future analysis outputs, human consultation decision, Guidance pool/advised default, follow-up, and history.
- Charts use server-authored typed projections with source/version/freshness and never derive or mutate business truth in the browser.
- Manual consultation and `SetGuidanceProgramPool` remain complete with no plugin installed.
- Authorized PH Tier B may receive a bounded read-only projection for the assigned program; this does not transfer Guidance advising authority.
- Future YuKee artifact/K-Means and transparent deterministic/rule-based plugins remain separate evidence objects and separate later changes. They must never be combined into a synthetic recommendation or write authoritative state.

## Legacy evidence and disposition

| Legacy evidence | Disposition | Greenfield rule |
|---|---|---|
| `ApplicationController` + `Applications/Index`/`Show`/`Apply` | **PRESERVE feel / REWRITE authority** | Keep ordered P1–P3 preference evidence and list→record staff vocabulary; use GF capability/scope queries and canonical page states. |
| `ConsultationSummary`, `ReleaseController`, `Release/Index` | **REJECT aggregate / PRESERVE workflow evidence** | Legacy merges recommendation, consultation, and release. Target keeps `ConsultationDecision`, `ResultRelease`, Guidance pool, applicant selection, and future analysis evidence separate. |
| `PortalAuthController` consultation/release widgets | **REWRITE** | Preserve a visible applicant journey, but project server-authored release/advice/choice/follow-up/stamp facts rather than the legacy summary status. |
| Legacy `courses.quota` column (later dropped) | **REJECT authority** | Existing greenfield `ProgramQuota`/history from D-087 is authoritative; missing current-cycle quota fails closed. |
| `Admin/Reports/Index` / legacy REQ-VS-025 report evidence | **REWRITE** | PH views are assigned-program, definition-bearing Potential/Upcoming/Follow-up/Enrolling/Enrolled projections; no broad legacy report access or unlabeled chart counts. |
| `ReleasePrintController` + `Release/ResultSheet*` | **PRESERVE print integration / EXTEND** | Keep result-sheet document flow; print the same universal applicant-locator concept exposed by the portal. Admission-slip identity is not revived. |
| Capstone Admission Support Engine/Triage Dashboard concepts + GF-10 | **PRESERVE visualization intent / REWRITE delivery boundary** | Put applicant-specific charts/tabs in consultation; render real released-score evidence now; defer YuKee and deterministic plugins separately under D-093; never combine their future signals. |
| Program-choice reservations, targeted follow-up, protected PH record, bounded threads, enrollment stamps | **NEW** | Implement only from D-080/D-089–D-093 and this OpenSpec; no invented legacy authority. |

Before implementation, open these matching legacy controllers/pages and mimic their useful list/detail/print layout. Authorization, privacy, state, audit, idempotency, and concurrency are rewrites.

## In scope

1. Decision/capability/schema foundation.
2. Guidance program pool + assisted default.
3. Applicant self-selection + reconsideration + quota reservation.
4. PH dashboard, Potential/Upcoming/Follow-up lists, protected record, quota panel.
5. Internal threads, PH-applicant bounded messages, access-history notices.
6. Consultation-embedded admission-support visualization backbone, with no provider installed.
7. Registrar `enrolling` / `enrolled` stamps and corrections.
8. Universal applicant QR, role-neutral staff action hub, and ordinary role-gated destinations.
9. Seeds, HANDOFF, capability/transition/browser evidence.

## Explicit non-goals

- YuKee/K-Means artifact integration, deterministic/rule-based plugin, BGE/interest engine, provider SDK, fake fixture engine, `ProgramSuggestion` workflow, or predictive-validity claim (D-093; BR-022/025).
- Autonomous admission, placement, or enrollment decisions (BR-024).
- PH application Accept/Dismiss, score writes, result release, or raw artifact access.
- Full chat/inbox, applicant-to-any-role general messaging, or marketing automation.
- Enrollment operations beyond the two Registrar stamps (BR-026).
- Preferences or pool entries consuming quota.

## Change safety

The OMR session owns the sole active implementation change, current OMR files, and `DatabaseSeeder`. This planning package must not edit `system/` until OMR work hands off, the result-sheet printing/`PrepareResultDocument` contract is stable for the printed universal-QR representation, and this change is promoted as the one active implementation OpenSpec. During implementation: targeted tests/Pint/PHPStan/browser proof first, then full-suite/fresh-seed proof after coordinated seeder ownership.
