# Stories — Admission Support, Program Choice, and Program Head Interface

**Status:** APPROVED QUEUED PLAN — reconciled 2026-08-15; implementation waits for sole-change promotion
**Primary requirements:** FR-003/008–029/033/036–038/048–054/077–085; REQ-VS-003/005/011–015/023/025–031; REQ-REP-001/003–009; BR-017/018/022–026/084–092

## S1 — Registrar acceptance gates the exam

- **Value:** only Registrar-approved applications proceed to examination.
- **Disposition:** PRESERVE journey; REWRITE authz/transactions.
- **Authz:** existing Registrar Accept/Dismiss capability; Guidance/PH denied.
- **GWT:** Given submitted attempt, when Registrar accepts, then exam-assignment eligibility becomes true with audit; Guidance cannot accept; PH follow-up outcome is never called application acceptance.

## S2 — Guidance confirms advice and program pool

- **Value:** counselor converts preferences, scores, interests, and discussion into bounded options.
- **Authz:** `guidance.selection.manage`; existing consultation-decision capability.
- **GWT:** Given released result + confirmed advice, when counselor finalizes options and advised default, then `SetGuidanceProgramPool` atomically writes immutable pool/options plus a separate pending default selection/reservation; provider output only pre-fills, later versions retain standing choice, and nothing silently acknowledges it.

## S3 — Applicant selects and reserves a quota slot

- **Value:** normal advised default reserves a provisional slot immediately; applicant may acknowledge it or choose another pool option before paper enrollment.
- **GWT:** Given active options + available default quota, when Guidance finalizes pool/default, then one slot is reserved atomically; later applicant change uses the same FCFS/idempotent transfer guard; outside-pool choice is denied; one last-slot winner; no PH approval.

## S4 — Assisted/no-device default remains applicant-controlled

- **Value:** counter service works for applicants without devices without silently placing them.
- **GWT:** Given Guidance creates the normal advised default, then quota is held with `pending_acknowledgement`; applicant may acknowledge through portal/Guidance, or Registrar may record sheet-based confirmation while atomically starting `enrolling`; no silent acknowledgement; applicant may change within pool before enrolling.

## S5 — Reconsideration transfers choice and quota safely

- **Value:** declined/changed choice never strands or overbooks applicant.
- **GWT:** Given a current choice that is acknowledged or explicitly Guidance-follow-up-targeted, when applicant changes or PH declines pre-enrolling, then old selection becomes terminal, old slot releases, new valid choice reserves atomically, history remains; if no valid option, Guidance may extend pool.

## S6 — PH sees program dashboard and canonical lists

- **Value:** PH sees their program's demand and admission-support workload.
- **Authz:** `program.head.view`, assigned program.
- **GWT:** dashboard shows metric definitions/source/as-of; Potential and Upcoming follow canonical definitions; primary Enrolling/Enrolled lists use provisional/final stamp program; open Follow-up is an overlapping work queue; quota shows configured capacity, deduplicated commitments, and over-capacity/not-configured variance; cross-program denied.

## S7 — Potential overview protects sensitive data

- **Value:** preferences remain useful visual ground without exposing every applicant record.
- **GWT:** accepted candidate with program as P1/P2/P3 or pool option appears with identity + basis + stage tags; pre-accept candidate contributes aggregate only; preference alone never exposes scores/notes/result sheet.

## S8 — PH opens a protected admission-support record

- **Value:** PH verifies relevant released evidence only for an applicant-acknowledged current selection or Guidance-routed targeted follow-up.
- **GWT:** given released result + strong assigned-program relationship, PH sees converted scores, result sheet, outcome, bounded program notes, assigned-program basis/state/provenance; other program names/options/history, raw sheets/tables, and unrelated notes are absent; every open is audited; applicant access history updates and notice deduplicates per 24h.

## S9 — Guidance routes an edge follow-up

- **Value:** uncertain requirements receive explicit, traceable resolution.
- **GWT:** Guidance opens targeted follow-up before enrolling; Registrar may still start `enrolling`; the follow-up stays in PH work queue and blocks only `enrolled`; assigned PH may clear/decline/request appearance, Guidance cannot silently settle it, and all transitions are audited/version-checked.

## S10 — PH decline respects processing stage

- **Value:** PH resolves real edge cases without becoming the normal admission approver.
- **GWT:** direct PH selection decline is allowed only pre-enrolling; assigned PH may clear/decline an already-open follow-up during `enrolling`, releasing any provisional reservation without erasing the stamp; `enrolled` waits for terminal PH resolution; Registrar still owns signed-paper final-program truth.

## S11 — Internal and applicant-support threads stay bounded

- **Value:** PH/Guidance resolve requirements without a general inbox.
- **GWT:** internal thread visible only PH+Guidance case participants; applicant thread available only for current selection/targeted follow-up; potential preference alone cannot message; append-only/read/audit; applicant never sees internal notes.

## S12 — Consultation embeds the admission-support visualization backbone

- **Value:** staff advise from one applicant-specific workspace with real charts/evidence now and a safe extension boundary for later engines.
- **GWT:** `/consultation` shows released per-domain score charts plus same-value accessible table, preferences, structured consultation evidence, independent future-analysis regions, human decision/pool/follow-up/history, and truthful no-analysis/stale/unavailable states; manual workflow remains complete; no provider or fake engine ships; PH Tier B receives only the assigned-program-safe read-only projection.

## S13 — Registrar stamps enrolling / enrolled

- **Value:** NEXIAM records external enrollment-paper truth without becoming an enrollment SIS.
- **GWT:** sheet submission + accepted/exam/released/advised facts permit Registrar `enrolling` even with open follow-up; `enrolled` requires current enrolling + terminal PH follow-up + signed-sheet verification; Registrar may confirm/adjust an out-of-pool/full final program, paper-finalize the provisional selection, leave capacity unchanged, and report variance; history/reversal are append-only.

## S14 — One applicant QR opens the actor's authorized action hub

- **Value:** one portal/printed applicant QR supports institutional interactions without encoding feature routes or weakening controls.
- **GWT:** digital and printed representations resolve the same opaque applicant-attempt locator; payload contains no PII, business action, destination, or authority; authenticated Registrar, Guidance/TA, and PH receive different server-authored destinations from ordinary capability/duty/scope; scan never mutates; invalid/expired/no-destination states expose no applicant data.

## S15 — Developer can demo the complete cut

- **Value:** no hand-built fixtures.
- **GWT:** seeded accounts cover Registrar accept gate, Guidance advice/pool/default/follow-up plus embedded score-profile visualization, applicant self/assisted choice, normal sheet-to-enrolling flow, enrolling-with-open-follow-up PH resolution, signed-paper different/full final program, PH lists/record/messages, universal QR actor-specific action hubs, no-analysis state, and denied cross-program paths; HANDOFF lists credentials/URLs/reset steps.
