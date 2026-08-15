# Spec: Nexi OMR Capture

## ADDED Requirements

### Requirement: OMR-1 Dedicated authority and resource scope

The system SHALL authorize every OMR list, capture, image-read, bind, edit, retry, remove, commit, and close operation with the dedicated `GUIDANCE_SCORING_OMR` capability plus GF-21 institution, academic-cycle, grading-session, sensitivity, and lifecycle scope. Holding `GUIDANCE_SCORING_MANUAL` alone SHALL NOT authorize OMR. HTTP middleware, Actions, queued jobs, and protected reads SHALL each enforce the applicable decision rather than trusting a prior layer.

#### Scenario: In-scope Test Administrator performs OMR work
- **WHEN** an active Test Administrator with `GUIDANCE_SCORING_OMR` targets an open grading session in scope
- **THEN** the authorized OMR operation proceeds and records the effective capability and scope in audit evidence

#### Scenario: Manual-scoring holder lacks OMR authority
- **WHEN** a user has `GUIDANCE_SCORING_MANUAL` but lacks `GUIDANCE_SCORING_OMR`
- **THEN** every OMR operation is denied without disclosing protected sheet or session data

#### Scenario: Worker actor loses authority
- **WHEN** an Auto analysis job starts after its initiating actor was deactivated, revoked, or moved out of scope
- **THEN** the job records a terminal/manual-recovery outcome and SHALL NOT invoke detection under elevated fallback authority

### Requirement: OMR-2 Immutable template and booklet pins

The system SHALL model a one-page composite answer-sheet template separately from a question-booklet version. An approved compatibility record SHALL pair versions that may be used together. The Test Administrator creating a capture batch SHALL explicitly select one active compatible pair; the batch pins both immutable version IDs, the participating aptitude areas, each `max_items` snapshot, every question/option geometry, and the complete answer key before the first sheet is accepted. Multiple active pairs may coexist, so the server SHALL NOT choose one implicitly. No separate permanent printed answer-sheet identifier is required.

#### Scenario: Valid version activation
- **WHEN** a Test Administrator activates a synthetic or approved production configuration
- **THEN** activation succeeds only when question positions are unique and contiguous `1..max_items`, all normalized regions are in bounds, every question has allowed options and exactly one first-cut answer, booklet mappings are complete, and conversion coverage spans `0..max_items` for every area

#### Scenario: Compatible pair is selected explicitly
- **WHEN** staff creates a batch while multiple active configuration pairs exist
- **THEN** the request must name one approved template-version/booklet-version pair and an omitted, inactive, retired, synthetic-for-production, or incompatible pair is rejected

#### Scenario: Pinned version is immutable
- **WHEN** capture has begun for a batch
- **THEN** changing its template version, booklet version, answer key, geometry, area set, or `max_items` snapshot is rejected as immutable

#### Scenario: Synthetic configuration cannot become production silently
- **WHEN** a configuration is marked synthetic
- **THEN** it remains visibly synthetic and cannot be activated for institutional production use

### Requirement: OMR-3 Batch and sheet state machines

The system SHALL keep one capture-active batch per grading session. Batch states SHALL be `open`, `closing`, `rejecting`, `confirmed`, or `rejected`. Sheet states SHALL be `received`, `queued`, `processing`, `needs_review`, `reviewed`, `committed`, or `failed_terminal`; removal hard-deletes only an uncommitted sheet after durable asset cleanup succeeds. Batch identity remains backend-only in UI copy.

#### Scenario: Auto processing does not close intake
- **WHEN** an Auto sheet moves through `queued`, `processing`, and `needs_review`
- **THEN** its batch remains `open` and another sheet can be captured

#### Scenario: Close rejects incomplete work
- **WHEN** session confirmation finds a queued, processing, failed-terminal, unbound, unresolved, or unreviewed Auto sheet
- **THEN** the batch remains open, no new OMR score is committed, and the response identifies the recoverable blocking sheets without leaking unrelated records

#### Scenario: Rejection waits for cleanup
- **WHEN** staff rejects a score-free open batch
- **THEN** the batch enters `rejecting`, blocks capture and finalization, and becomes `rejected` only after every encrypted asset cleanup completes; failures remain retryable and operator-visible

#### Scenario: Final state is immutable
- **WHEN** a batch is `confirmed` or `rejected`
- **THEN** uploads, binds, reviews, retries, removals, and a second close with a different payload are rejected

### Requirement: OMR-4 Manual and Auto capture semantics

The online first cut SHALL support `manual` and `auto` capture modes from file upload or browser camera input. Manual capture SHALL analyze the sheet and force navigation to review before another capture. Auto capture SHALL persist the encrypted asset, enqueue one per-sheet analysis after commit, return `AcceptedForProcessing` with a status URL, and immediately make capture ready again.

#### Scenario: Manual Save commits one sheet
- **WHEN** a Manual sheet has an in-roster binding, complete reviewed items, and no unresolved required issue and the Test Administrator selects Save
- **THEN** the server commits that sheet's OMR score records immediately and returns it as `committed`

#### Scenario: Auto review does not commit early
- **WHEN** a Test Administrator saves complete review output for an Auto sheet
- **THEN** the sheet becomes `reviewed` but no OMR score is written until session confirmation

#### Scenario: Batch and offline are unavailable
- **WHEN** staff opens the first-cut capture UI
- **THEN** zero-click Batch and offline/local-image persistence are absent or visibly unavailable and cannot be activated by a client-supplied value

### Requirement: OMR-5 Unbound capture and advisory identity

A sheet MAY be captured without an admission attempt. Detection MAY propose an applicant number, confidence, and cropped identity fields, but only the Test Administrator can bind a sheet to an in-roster attempt. A batch SHALL NOT contain two live sheets bound to the same attempt.

#### Scenario: Advisory identity is accepted
- **WHEN** the proposed applicant number maps uniquely to an eligible roster attempt and staff confirms the match
- **THEN** the sheet binds to that attempt and the action records the proposal, selected identity, actor, reason when changed, and expected version

#### Scenario: Foreign or ambiguous identity
- **WHEN** identity maps to no roster attempt, multiple attempts, or a foreign session
- **THEN** the sheet remains unbound with a required issue, OMR Save is disabled, and the UI offers roster search or the existing manual scoring fallback

### Requirement: OMR-6 Item-level provider envelope

`OmrDetectionPort` SHALL accept a versioned request containing correlation/run IDs, input hash, pinned template/booklet/config/key hashes, optional identity hint, and stable aptitude-area codes. It SHALL return a versioned typed outcome plus normalized item regions, selected option or uncertain state, confidence, identity/name regions, issue codes, diagnostics, engine identity, and timing. Provider output SHALL be validated before persistence, and providers SHALL NOT mutate NEXIAM state.

#### Scenario: Valid deterministic fake result
- **WHEN** `FakeOmrDetection` receives the same request bytes and pinned snapshot
- **THEN** it returns the same item proposals, regions, issues, hashes, and scores, with marked-item totals matching configured ground truth

#### Scenario: Invalid or unsupported provider output
- **WHEN** a provider omits required items, returns unknown area/question/option codes, duplicate items, out-of-range regions/confidence, mismatched hashes, or malformed diagnostics
- **THEN** NEXIAM rejects the output, retains the original asset, records a typed terminal/manual outcome, and writes no score

#### Scenario: Real provider is not silently substituted
- **WHEN** an OMRChecker adapter is unavailable or not activated
- **THEN** production requests do not silently fall back to fake detection

### Requirement: OMR-7 Per-sheet issues and review derivation

The system SHALL persist normalized issues for blank, ambiguous/multiple, unreadable, partial, crossed-out, erased, identity-conflict, provider, and integrity outcomes. First-cut marking policies SHALL NOT guess these meanings. Staff may save an incomplete draft, but OMR commit SHALL require every required issue to be resolved by an allowed explicit reviewer choice. The server SHALL derive item marks and area totals from the pinned answer key; client totals are never authoritative.

#### Scenario: Required issue blocks commit
- **WHEN** any required identity or item issue remains open
- **THEN** Save-to-commit is disabled or rejected, while Save Draft and manual score fallback remain available

#### Scenario: Force-set records a reason
- **WHEN** staff changes a detected answer, binding, or high-confidence green/red result
- **THEN** the mutation requires a non-empty reason and stores immutable before/after reviewer evidence

#### Scenario: Tampered client total
- **WHEN** a client submits an area total inconsistent with its item edits
- **THEN** the server ignores/rejects the total and uses only server-derived marks

### Requirement: OMR-8 Unified authoritative scoring and revisions

Accepted OMR results SHALL use the existing roster check, `ScoreConverter`, conversion coverage, score uniqueness, and grading finalization rules. `score_records` remains the current projection; immutable score revisions SHALL retain operation `write|correct|clear`, source, capture sheet, actor, reason, command, prior/new raw and converted values, and timestamps. Clearing a current score SHALL require an expected version and reason, append a tombstone revision containing the prior projection, and only then delete the projection; the revision survives with its session/attempt/area identity.

#### Scenario: OMR collides with an existing manual score
- **WHEN** the same session × attempt × aptitude-area already has a current manual score
- **THEN** OMR commit returns an explicit conflict and does not overwrite it

#### Scenario: Manual correction replaces OMR projection
- **WHEN** authorized staff explicitly corrects an OMR-sourced score through the manual editor with expected version and reason
- **THEN** the current projection becomes manual while the original OMR revision and evidence remain immutable and attributable

#### Scenario: Reasoned score clear preserves history
- **WHEN** authorized staff clears a current manual- or OMR-sourced score with the expected version and a non-empty reason
- **THEN** a tombstone revision preserves the prior projection and provenance before the current row is deleted, and completeness/finalization sees the score as absent

#### Scenario: Conversion coverage fails
- **WHEN** a derived raw score has no pinned conversion row
- **THEN** the sheet remains uncommitted, an actionable issue is recorded, and no partial area set is written

### Requirement: OMR-9 Transaction, lock, and filesystem boundaries

Mutations SHALL use the global lock order grading session → batch → sheets ordered by ID → score rows ordered by attempt/area. Confirm SHALL atomically close intake, lock/revalidate all candidates, write all score revisions/projections and audits, then mark the batch confirmed. Detection and physical file deletion SHALL run outside database lock transactions through durable intents and reconciliation.

#### Scenario: Review races with confirmation
- **WHEN** review and confirmation target the same sheet concurrently
- **THEN** expected versions and ordered row locks allow exactly one compatible transition; the loser receives a typed stale conflict and no partial write

#### Scenario: Upload resumes after a crash window
- **WHEN** the upload command is retried after its durable sheet/receipt starts or after the deterministic asset write succeeds but before finalization
- **THEN** the same scoped receipt, client-capture ID, sheet row, upload lease, and opaque asset path are resumed; a matching authenticated asset is reused, a conflicting asset fails safely, and no duplicate row, file, job, or audit is created

#### Scenario: Database rollback before dispatch
- **WHEN** upload finalization rolls back
- **THEN** no analysis job is dispatched and the staged asset remains recoverable by same-command replay or asset reconciliation rather than represented as a successful sheet

#### Scenario: Cleanup fails after removal request
- **WHEN** encrypted asset deletion fails
- **THEN** the sheet is not reported removed, the cleanup intent remains retryable/operator-visible, and the audit truthfully records the failure state

### Requirement: OMR-10 Scoped idempotency and optimistic concurrency

Every mutating HTTP or job command SHALL carry an actor-scoped idempotency key, canonical payload fingerprint, command name, aggregate identity, correlation ID, and expected aggregate version. A durable unique receipt SHALL distinguish replay from payload conflict and SHALL be correctness authority under concurrency.

#### Scenario: Same key and same fingerprint
- **WHEN** an identical command is replayed
- **THEN** the stored safe result is returned without duplicating assets, processing runs, score revisions, audits, or jobs

#### Scenario: Same key and different fingerprint
- **WHEN** a key is reused with a different payload, actor, command, session, or aggregate
- **THEN** the server returns a typed `409 IdempotencyConflict` and applies nothing

#### Scenario: Stale expected version
- **WHEN** a command presents a version older than the locked aggregate version
- **THEN** the server returns `409 StaleVersion` with safe refresh guidance and applies nothing

### Requirement: OMR-11 Queue delivery and recovery

Auto analysis SHALL use one durable current processing run and one unique queued job per sheet/run version. A sheet points to its current run; at most one `pending`, `claimed`, or `retryable_failed` run exists per sheet. Dispatch occurs after commit. The job SHALL acquire a tokenized expiring claim transactionally, invoke detection outside locks, then persist only after current-run, claim-token, version, and authority rechecks. A typed retryable outcome SHALL persist `retryable_failed` with `next_retry_at` and release the same job/run for the configured delay; reconciliation requeues pending or expired-current claims. First-cut policy SHALL set the claim lease and timeout below queue `retry_after`, three attempts, bounded backoff, overlap prevention, and a terminal/manual state after retry exhaustion.

#### Scenario: Duplicate delivery
- **WHEN** the same job is delivered concurrently or after worker restart
- **THEN** the database claim permits one provider invocation/persistence outcome and duplicates return without additional effects

#### Scenario: Retryable dependency failure
- **WHEN** detection returns a typed retryable outcome
- **THEN** the processing run records the attempt and next retry, the sheet remains visibly processing/queued, and no score is written

#### Scenario: Retry exhaustion
- **WHEN** all allowed attempts fail
- **THEN** the sheet enters `failed_terminal`, a redacted diagnostic and audit are stored, and an authorized Retry or manual fallback is visible

### Requirement: OMR-12 Authenticated sensitive assets

Captured bytes SHALL be stored only on the private sensitive disk in a versioned envelope containing format version, key ID, nonce/IV, ciphertext, and an authentication tag or ciphertext MAC produced by separate derived encryption and MAC keys. Integrity SHALL be verified before plaintext is released. Every read requires the expected plaintext SHA-256 HMAC, supports active/retired key IDs, and never logs bytes, keys, paths, or identity crop content.

#### Scenario: Valid private image read
- **WHEN** authorized in-scope staff requests a live sheet image
- **THEN** protected-view evidence is appended before bytes are streamed with the validated MIME type, `Cache-Control: private, no-store`, and no public URL

#### Scenario: Missing, malformed, or tampered asset
- **WHEN** the envelope, tag/MAC, expected HMAC, key ID, or file is invalid
- **THEN** no plaintext is returned, the response exposes only a safe correlation error, and `security.omr_asset_integrity_failed` evidence is recorded

#### Scenario: Key rotation
- **WHEN** an authorized rekey operation moves an asset to a new key ID
- **THEN** ciphertext changes, plaintext HMAC/provenance remains stable, the swap is audited, and an old key cannot be retired while referenced

### Requirement: OMR-13 Retention, removal, and reconciliation

An uncommitted sheet MAY be removed only with a reason and no dependent score revision. Removal SHALL delete the encrypted asset and row through a recoverable cleanup workflow and record `grading.omr_sheet_removed`. Confirmed evidence SHALL NOT be purged in this change; retention and legal hold remain institutional gates. An operator reconciliation command SHALL detect missing, orphaned, or cleanup-pending assets without exposing plaintext.

#### Scenario: Uncommitted removal succeeds
- **WHEN** cleanup succeeds for an uncommitted sheet
- **THEN** the asset and sheet row are gone, the removal audit remains, and no score exists

#### Scenario: Committed removal is attempted
- **WHEN** a sheet has a score revision or committed state
- **THEN** destructive removal is rejected as immutable

### Requirement: OMR-14 HTTP, binding, validation, and errors

OMR routes SHALL use `{gradingSession}` consistently and resolve each `{sheet}` through an explicit grading-session-scoped query. Upload SHALL accept only decoded JPEG, PNG, or WebP within configured byte, width, height, pixel, and orientation limits. Each mutation SHALL have its own Form Request and typed Action outcome.

#### Scenario: Foreign nested sheet ID
- **WHEN** a sheet ID belongs to another grading session
- **THEN** the route returns a safe `404` and performs no protected-view audit or mutation on the foreign record

#### Scenario: Invalid image payload
- **WHEN** bytes are oversized, malformed, unsupported, decompression-hostile, or violate dimensions/orientation policy
- **THEN** upload returns `422` field errors and stores no durable asset or sheet

#### Scenario: Typed error projection
- **WHEN** an Action returns validation, denial/not-found, stale/idempotency conflict, immutable/finalized, dependency-unavailable, or missing/malformed/tampered evidence
- **THEN** HTTP maps it respectively to safe `422`, `403/404`, `409`, `423`, `503`, or integrity-safe `500` behavior without exposing internals; `410 Gone` is reserved for a future explicit purged-evidence state and is not emitted by this cut

### Requirement: OMR-15 Capture and review interface

The Svelte interface SHALL mimic the approved mockup and locked NEXIAM UI contract: a centered phone-column on desktop and full-bleed mobile capture app with its own camera → scans gallery → review stack. The session landing remains in the staff shell. The camera control deck places gallery left, shutter center, and Manual/Auto selector right. The gallery uses Draft/All tabs and truthful queued/processing/needs-review/reviewed/committed/failed states.

#### Scenario: Review screen
- **WHEN** staff opens a sheet
- **THEN** the page shows the decrypted image, normalized overlays, examinee summary, Detected → Final area summaries, and Retry/Save/Remove actions permitted by current state

#### Scenario: Score editor
- **WHEN** staff opens the score editor
- **THEN** each question row starts with a cropped bubble image from its normalized region, followed by detected answer/confidence/issue and server-derived mark controls

#### Scenario: Unsaved navigation
- **WHEN** staff navigates back with dirty review edits
- **THEN** the accessible Unsaved Changes bottom sheet offers Discard or Save Draft, traps/restores focus, and protects both Inertia and browser navigation

#### Scenario: Page-state contract
- **WHEN** loading, empty, denied, stale, offline, queued, degraded, dependency-failed, or validation states occur
- **THEN** the interface renders truthful accessible status, preserves recoverable input, prevents duplicate submission, and never claims a score or file exists without server confirmation

### Requirement: OMR-16 Finalization and manual fallback

A finalized grading session SHALL reject new OMR batches and every sheet mutation. Grading finalization SHALL require authoritative score completeness and zero `open`, `closing`, or `rejecting` OMR batches, even when an active batch is empty or contains only committed Manual sheets. Staff must explicitly confirm or finish rejection before finalization; the finalization Action never auto-closes OMR state. Uncommitted OMR drafts never satisfy score completeness. The ordinary manual score editor remains the fallback for unsupported marks, unresolved identity, provider failure, or staff choice.

#### Scenario: Active OMR batch blocks finalization
- **WHEN** authoritative manual/OMR scores are complete but any batch remains `open`, `closing`, or `rejecting`, including an empty batch or one containing only committed Manual sheets
- **THEN** finalization is rejected with safe batch/sheet recovery guidance and no grading or OMR state changes

#### Scenario: Mutation after finalization
- **WHEN** any OMR mutation targets a finalized grading session
- **THEN** it returns the immutable/finalized outcome and writes nothing

### Requirement: OMR-17 Developer-ready proof

The delivered cut SHALL include a synthetic one-page template/booklet, encrypted unscored review fixture, deterministic warning fixture, exact staff credentials, recreation and worker commands, concrete URLs/IDs, expected states, retry/reconciliation/key procedures, and automated proof for upgrade, constraints, Actions, HTTP, authorization, queue, assets, UI behavior, and regressions.

#### Scenario: Fresh demo reset
- **WHEN** a developer runs the documented Compose reset and seed commands and signs in with the recorded Test Administrator account
- **THEN** the documented session opens with one active capture batch and a genuinely unscored sheet that can complete the Manual/Auto review journey without hidden setup

#### Scenario: Production activation remains gated
- **WHEN** the client sheet, labeled corpus, pinned OMRChecker commit/patch, headless benchmark, native-bundle/aligned-review validation, confidence policy, and retention policy are absent
- **THEN** the synthetic/fake cut remains demonstrable but cannot be represented or configured as production OMRChecker-ready

### Requirement: OMR-18 Real-provider trust boundary

The OMRChecker integration SHALL remain behind `OmrDetectionPort`. Detection SHALL receive only image bytes, the immutable native template bundle or exact preloaded identity, derived-manifest stable labels, declared bubbled-identity fields, hashes, engine/patch identity, and a deadline. The provider SHALL NOT receive or return answer keys, correctness, scores, roster records, database/storage access, or encryption material. NEXIAM SHALL compare validated observed marks with the pinned booklet and derive all correctness and scores.

#### Scenario: Provider attempts to supply correctness
- **WHEN** a real-provider response contains a correct option, correctness flag, score, or area total
- **THEN** the response is rejected as invalid provider output and no proposal or score is written

#### Scenario: Selected option is scored
- **WHEN** a validated provider result reports one allowed selected option for every definite item
- **THEN** NEXIAM joins those selections to the pinned booklet, derives correctness and area totals server-side, and preserves the provider only as detection provenance

### Requirement: OMR-19 Detection envelope v2

The real-provider path SHALL use one clean `nexiam.omr.detection.v2` request/result contract across fake provider, OMRChecker adapter, validator, tests, runs, and persistence. The request SHALL include decoded image metadata, immutable native template-bundle/config identity, derived review-manifest identity and expected stable labels, declared bubbled-identity fields, expected engine/commit/patch, and deadline. It SHALL NOT carry a second hand-authored geometry table. The response SHALL echo contract, run, engine, bundle, manifest, patch, and input identity; return exactly one observed state for every expected item; and include bounded canonical aligned-image evidence.

#### Scenario: Contract or engine mismatch
- **WHEN** the worker responds with another contract version, run ID, input/bundle/config/manifest/patch hash, engine name, or upstream commit
- **THEN** Laravel rejects it as a terminal configuration/output failure, retains the asset, and exposes only a safe manual-recovery action

#### Scenario: Incomplete or extra coverage
- **WHEN** the response omits, duplicates, adds, reorders incompatibly, or misidentifies an item, option, identity field, or issue reference
- **THEN** validation rejects the entire result before proposal persistence

### Requirement: OMR-20 Native geometry and derived review manifest

The immutable OMRChecker-native template bundle SHALL be the sole detection-layout authority. Activation SHALL load it through the exact pinned engine/patch, expand native field blocks and bubbles, validate label/option uniqueness, contiguity, bounds, overlap, and deterministic output, and persist a hash-pinned NEXIAM review manifest plus relational projections derived from those bubbles. Derived coordinates SHALL NOT be independently edited or compiled back into OMRChecker input.

#### Scenario: Native bundle cannot produce a safe manifest
- **WHEN** an expanded native bubble is missing, duplicated, out of bounds, overlapping, assigned to an unknown label/option, or non-deterministic for the same engine/patch
- **THEN** activation, fixture generation, and OMRChecker detection are rejected with `template_invalid` or `manifest_derivation_failed`

#### Scenario: Deterministic manifest derivation
- **WHEN** the same immutable native bundle is expanded repeatedly by the exact pinned engine/patch
- **THEN** the derived manifest and its SHA-256 hash are byte-identical and every expected NEXIAM option maps to exactly one native bubble

#### Scenario: A caller attempts reverse compilation
- **WHEN** code attempts to generate native OMRChecker geometry from independently maintained NEXIAM coordinates
- **THEN** the operation is unsupported; no reverse compiler or second geometry authority exists

### Requirement: OMR-21 Generated acceptance fixture pack

The repository SHALL provide a deterministic fixture generator that loads the seeded native template bundle, activation-derived review manifest, and pinned booklet snapshot and emits printable SVG/PDF/PNG, a blank alignment reference, a versioned ground-truth manifest, and representative marked/transformed images. The generator SHALL NOT contain a second coordinate table. Synthetic fixtures SHALL be visibly non-production and SHALL NOT satisfy production-corpus approval.

#### Scenario: Regenerate the pack
- **WHEN** a developer runs the documented generator with the recorded seed and unchanged native bundle/derived manifest/booklet
- **THEN** the manifest, source layout, expected marks/issues, and committed acceptance-image hashes match

#### Scenario: Uncertain mark variant
- **WHEN** a fixture contains a faint, erased, crossed-out, multi-mark, unreadable, or out-of-region case
- **THEN** its manifest expects an uncertain state and required issue rather than an arbitrary selected answer

#### Scenario: Geometry changes
- **WHEN** native bundle, derived manifest, identity declaration, engine/patch, later calibration, or generator input changes
- **THEN** affected artifact and manifest hashes change explicitly and any prior qualification is invalidated

### Requirement: OMR-22 Durable queue orchestration for both modes

Manual and Auto capture SHALL create the same durable per-sheet processing run and dispatch only after commit. Only the queued job SHALL invoke `OmrDetectionPort`. Manual SHALL wait/poll the run and normally open review when ready; Auto SHALL restore capture immediately. Persistence SHALL require the current run ID, claim token, expected sheet version, live authority, and unexpired claim after the provider returns.

#### Scenario: Manual browser disconnects
- **WHEN** the browser disconnects while Manual detection is running
- **THEN** the durable run continues once, can be resumed from gallery/status polling, and creates no duplicate provider call, proposal, issue, or score

#### Scenario: Stale provider result
- **WHEN** retry, removal, review, batch close, authority revocation, or another current run supersedes the provider call
- **THEN** the stale result cannot persist and the current sheet/run state remains authoritative

#### Scenario: Duplicate queue delivery
- **WHEN** the same sheet/run job is delivered concurrently or after restart
- **THEN** the database claim permits one active invocation/persistence path and duplicates exit without effects

### Requirement: OMR-23 Authenticated bounded inference worker

The optional Compose inference service SHALL run the pinned OMRChecker commit and owned patch in a headless, non-root, read-only, resource-bounded image. Laravel SHALL send a signed multipart manifest plus image bytes over the internal service boundary with bounded request/response sizes, no redirects, and one transport attempt per job attempt. The worker SHALL have no NEXIAM database, sensitive-disk, score, or roster access.

#### Scenario: Invalid or stale signature
- **WHEN** a request signature is missing, invalid, replay-stale, or computed over mismatched run/input/manifest hashes
- **THEN** the worker rejects it without decoding the image or disclosing contract details

#### Scenario: Detection hangs or crashes
- **WHEN** the isolated inference child exceeds its deadline, exits, or crashes
- **THEN** the supervisor remains live, terminates/replaces the child, returns a safe retryable outcome, and leaves no image or temp artifact behind

#### Scenario: Rolling version mismatch
- **WHEN** Laravel and worker contract, native bundle, derived manifest, commit, patch, or image identity disagree
- **THEN** readiness/provider selection fails closed and no request silently uses fake detection

### Requirement: OMR-24 Observed marks, identity, aligned review, and confidence policy

OMRChecker SHALL retain ownership of preprocessing, alignment, thresholding, and native bubble classification. The adapter SHALL map observed field output into `marked`, `blank`, `ambiguous`, or `unreadable` without changing those algorithms. In this cut every real-engine item and identity proposal SHALL return `confidence: null` and `calibration_status: unqualified`; review and explicit applicant binding remain mandatory. Identity SHALL use declared bubbled symbols/positions only. Review crops SHALL use the encrypted canonical aligned-sheet derivative, the activation-derived manifest, and bounded returned field-block shifts; the original photo remains immutable forensic evidence.

#### Scenario: Uncalibrated provider output
- **WHEN** OMRChecker returns an apparently definite mark or identity while no separately qualified calibration profile exists
- **THEN** NEXIAM records the observation with null confidence, requires human review, and never displays a fabricated percentage or silently confirms it

#### Scenario: Identity is missing or conflicts
- **WHEN** bubbled identity is incomplete, multi-marked, invalid, unknown, ambiguous, or conflicts with roster scope
- **THEN** the sheet remains unbound, no automatic binding occurs, and staff receive roster-search/manual fallback

#### Scenario: Aligned review evidence is invalid
- **WHEN** the canonical aligned image is missing, malformed, oversized, hash-mismatched, dimension-mismatched, or a returned block shift maps a crop outside approved bounds
- **THEN** the result is rejected or marked misaligned; the UI never highlights another question as evidence

#### Scenario: A later confidence feature is proposed
- **WHEN** maintainers want confidence percentages or threshold-derived abstention
- **THEN** they SHALL add a separately reviewed instrumentation patch, representative labeled corpus, versioned calibration mapping, qualification, and invalidation rules before exposing confidence

### Requirement: OMR-25 Fail-closed qualification and provider selection

`OMR_PROVIDER=omrchecker` SHALL require an explicit enable gate, complete endpoint/auth/timeout/contract/commit/image configuration, healthy worker readiness, an OMRChecker-capable pinned template, and a matching durable qualification record. `fake` SHALL remain explicit and non-production/demo-gated. No provider unavailability or misconfiguration SHALL cause fallback from OMRChecker to fake.

#### Scenario: Synthetic development qualification
- **WHEN** the exact synthetic native-bundle/derived-manifest/booklet/commit/patch/image/fixture-manifest combination passes the reproducible fixture benchmark
- **THEN** it may receive only a `development` qualification and power the seeded demo

#### Scenario: Production environment has only synthetic evidence
- **WHEN** production selection finds no matching `production` qualification for the exact immutable combination
- **THEN** OMRChecker remains disabled, capture shows a truthful degraded/manual path, and no synthetic result is presented as production evidence

#### Scenario: Qualified artifact changes
- **WHEN** native bundle, derived manifest, dependency lock, upstream commit, owned patch, image digest, contract, fixture manifest, or any later calibration changes
- **THEN** the prior qualification no longer matches and provider readiness fails until re-benchmarked and re-approved

### Requirement: OMR-26 Reproducible benchmark and operational proof

Development qualification SHALL require a hash-addressed benchmark report that records fixture counts, confusion counts, false-green rate, definite accuracy, uncertain-case behavior, identity precision and coverage, aligned-review crop error, latency percentiles, throughput, CPU/memory, determinism, and recovery results. Confidence remains null and is not a qualification metric in this cut. Production SHALL additionally require the approved client corpus, named approvers, retention/legal-hold decision, and operational drills.

#### Scenario: Synthetic benchmark passes
- **WHEN** the acceptance pack records zero false-green results, at least 99% clean definite-answer accuracy, measured uncertain-case outcomes with mandatory review, at least 99.5% identity precision with at least 95% clean suggestion coverage, no automatic binding, aligned-review crop p95 within 2% of the smaller aligned-page dimension, Manual warm p95 within five seconds at declared pilot concurrency, deterministic repeats, and bounded resources
- **THEN** the exact combination may be marked development-qualified with the report and manifest hashes

#### Scenario: Percentage lacks denominator
- **WHEN** a benchmark reports percentages without sample counts, confusion counts, hardware, warmup, concurrency, or artifact hashes
- **THEN** qualification fails because the result is not reproducible

#### Scenario: Dependency outage drill
- **WHEN** the worker is stopped, restarted mid-request, returns malformed output, times out, or exhausts retries
- **THEN** the sheet reaches the specified recoverable/manual state, no score is written, health/readiness and safe diagnostics are truthful, and the fake provider is never substituted
