# Tasks — Admission Support, Program Choice, and Program Head Interface

**Status:** APPROVED QUEUED PLAN — implementation not started; Phase 0 authority propagation complete
**Shared-worktree rule:** no `system/`, OMR, or `DatabaseSeeder` edits until this becomes the sole active implementation change. Then targeted verification first; full seed/suite after coordinated seeder ownership.

## Phase 0 — Propagate authority + capability contract

- [ ] Promotion preflight: OMR cut handed off; result-sheet printing/`PrepareResultDocument` contract stable; this change is sole active implementation authority; `DatabaseSeeder` ownership transferred.
- [x] Propagate D-ROLE-05/D-080/D-089–D-093 into GF-04, GF-06, GF-10, GF-12, GF-20, GF-22, PRD, page catalog, E2 stories, project context, and master checkpoint.
- [ ] Add capability rows: `program.head.view`, `program.head.follow_up.resolve`, `program.head.selection.decline`, `program.head.message`, `guidance.selection.manage`, `guidance.follow_up.manage`, `record.thread.participate`, `applicant.program.select`, `registrar.enrollment.stamp`, `applicant.qr.resolve`.
- [ ] Add allow/deny/cross-program/expired-scope capability tests.

## Phase 1 — Schema + domain foundation

- [ ] Migrations/models for immutable pool versions/options, provisional selections with `paper_finalized`, follow-ups/declines, append-only enrollment stamps carrying provisional/final program + external paper verification + quota variance, and record threads/messages/reads.
- [ ] Constraints: unique attempt/pool-version and pool/program/order; one non-terminal selection; one open follow-up per attempt/program/cycle; one effective PH decline per attempt/program/targeted pool version; stamp order/version; indexes for program/cycle lists. Lock attempt when allocating next pool version.
- [ ] Audit event catalog and notification event contracts.
- [ ] Wire every mutating action through the existing action-execution ledger: actor/command-version/aggregate namespace + canonical request hash; same-hash replay returns original result, cross-payload key reuse conflicts with no effects.
- [ ] Projection support for canonical lifecycle/status tags.

## Phase 2 — Guidance options + applicant selection + quota

- [ ] FormRequests + Actions: `SetGuidanceProgramPool` atomically writes initial pool/options + advised default reservation, `SelectApplicantProgram`, `AcknowledgeAssistedProgramSelection`, `TransferApplicantProgramSelection`, `CancelApplicantProgramSelection`.
- [ ] Implement design §12 lock protocol, atomic initial pool/default FCFS guard, transfer/release, lowered-quota conflicts, same-command replay, cross-payload idempotency conflict, commitment dedupe, and pool-update-vs-selection race; keep paper override out of ordinary selection.
- [ ] Consultation page: finalized versioned pool + required advised default, pending acknowledgement, quota/full conflict, applicant-choice explanation, and explicit pre-enrolling edge-follow-up routing.
- [ ] Applicant ProgramChoice page: self select/ack/change before enrolling, pool-only validation, and explanation that Registrar may record sheet-based confirmation.

## Phase 3 — Consultation-embedded admission-support visualization

- [ ] `ConsultationAdmissionSupportQuery`: server-authored applicant readiness, released per-domain score projection, preferences, approved structured consultation evidence, human decision/pool/follow-up/history, and source/version/freshness metadata.
- [ ] Consultation tabs/panels: overview, assessment chart + same-value accessible table, preferences/consultation evidence, independent future-analysis regions, human decision/pool, follow-up, and history.
- [ ] Truthful result-not-ready/no-analysis/stale-input/unavailable/invalid-output/denied states; manual consultation and `SetGuidanceProgramPool` remain complete.
- [ ] Define only the provider-neutral visualization boundary. Do not install YuKee/K-Means, deterministic/rule-based, BGE, SDK/provider, fake fixture engine, `ProgramSuggestion`, or proposal-review code.

## Phase 4 — PH dashboard + tiered visibility

- [ ] Queries: metrics, Potential, Upcoming, overlapping Follow-up work queue, Enrolling/Enrolled by provisional/final stamp program, candidate summary/protected detail, and quota over-capacity/not-configured variance.
- [ ] Pages: ProgramHead Index/ProgramShow/ApplicantShow; quota capacity/commitment/variance/history; basis + primary lifecycle + follow-up tags.
- [ ] Tier A summary vs Tier B protected record contract; Tier B reuses only assigned-program-safe read-only slices from `ConsultationAdmissionSupportQuery`, while Tier A receives no chart, engine input/output, or counselor note.
- [ ] Protected-view audit + applicant access history persisted before serialization, fail-closed evidence-write test, 24h notice-intent dedupe, and after-commit delivery-failure proof.
- [ ] Server-authored nav/breadcrumbs and complete GF-22 states.

## Phase 5 — Follow-up, decline, reconsideration

- [ ] Actions: `OpenProgramFollowUp`, `ResolveProgramFollowUp`, `DeclineApplicantProgramSelection`; outcomes are PH clear/decline/request appearance, with no silent Guidance settlement of a PH-routed edge case.
- [ ] Enforce direct decline/new-follow-up pre-enrolling boundary; allow only existing-follow-up PH resolution during enrolling; race `StampAttemptEnrolled` against unresolved/terminal follow-up under design §12 locks.
- [ ] Pre-enrolling reconsideration/pool extension/transfer plus enrolling-time follow-up decline: one canonical decline outcome, reservation release once, `enrolling` preserved, paper-final program left to Registrar.
- [ ] Registrar projection separates “may start enrolling” from “may finalize enrolled” and shows provisional choice, acknowledgement source, paper state, follow-up, final program, and quota variance without notes.

## Phase 6 — Threads + bounded applicant messages

- [ ] Internal case thread (PH↔Guidance) with subject scope, append-only messages, reads, audit.
- [ ] Applicant support thread only current selection/targeted follow-up; potential-only outreach denied.
- [ ] Applicant admission-support page: messages, access history, notices; universal QR lives in its own portal surface/common affordance rather than this feature page.

## Phase 7 — Registrar completion stamps

- [ ] FormRequests + Actions: `StampAttemptEnrolling` accepts sheet-submission reference, provisional program, and optional attributable pending-default confirmation; `StampAttemptEnrolled` requires terminal PH follow-up + signed-sheet verification and records final paper program; `ReverseEnrollmentStamp` remains ordered/reasoned.
- [ ] Implement atomic paper finalization: `paper_finalized` provisional selection, old reservation release, final commitment, immutable verifier/reference/hash/source; out-of-pool/declined/full/unconfigured final program records truth without changing capacity and reports variance.
- [ ] Registrar search/card and Registrar action-hub destination distinguish start-processing vs final-ready; portal/PH projections show primary status and overlapping follow-up truth.
- [ ] Apply split freeze: `enrolling` blocks core prerequisite reversal/new follow-up/ordinary selection changes but permits existing PH follow-up resolution + Registrar finalization; `enrolled` requires ordered reversal for all corrections.

## Phase 8 — Universal applicant QR and action hub

- [ ] One universal opaque applicant-attempt locator contract with digital 15-minute portal representation and admission-window printed representation; neither encodes a feature destination or authority.
- [ ] Generic `/staff/scan` camera/manual-code surface and `ResolveApplicantQrQuery` / `ApplicantActionMenuQuery`.
- [ ] Server-author actor-specific destinations from ordinary capability, duty, office/program/cycle relationship, and case state: Registrar application/eligibility/enrollment; Guidance/TA exam/grading/result/consultation; PH bounded assigned-program support.
- [ ] `/applicant/qr` portal surface/common affordance and printed-result integration.
- [ ] Invalid/expired/copied/no-destination/cross-scope tests, role-different-menu tests for the same QR, no-auto-mutation proof, and printed document proof.

## Phase 9 — Seeds, browser proof, handoff

- [ ] Add idempotent `AdmissionSupportDemoSeeder`; after sole-change promotion transfers seeder ownership, wire it into `DatabaseSeeder`/`foundation:reset`: normal advised-default → sheet → enrolling → enrolled; enrolling + open PH follow-up; PH clear/decline; paper-different full/unconfigured final program; assigned/cross-scope actors; embedded score-profile/no-analysis states; and one applicant QR exercised by Registrar, Guidance/TA, and PH.
- [ ] Targeted Pest + PHPStan + Pint clean, including start-vs-final gates, follow-up overlap/races, paper override, commitment dedupe, quota variance, chart projection parity/source freshness, and QR role-menu isolation.
- [ ] Browser-drive normal Registrar sheet route, enrolling edge follow-up/PH resolution, paper final-program adjustment/variance, Guidance consultation visualization, applicant/PH surfaces, and the same universal QR through Registrar/Guidance/PH action hubs.
- [ ] `HANDOFF.md`: credentials, URLs, reset steps, expected state, full-suite blocker/status.
- [ ] Full fresh reset + full Pest when shared OMR seeder is stable.
- [ ] Update master checkpoint and mark tasks/evidence paths truthfully.
