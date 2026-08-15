# Client Decision Review — NEXIAM OMR Capture

**Feature:** OMR scoring capture with OMRChecker evaluation  
**Purpose:** Client review and expert-software-engineer consultation  
**Status:** Client decisions recorded; Phase A fake-provider flow shipped; Phase B native-bundle/derived-manifest architecture locked; production OMRChecker activation blocked  
**Date:** 2026-08-12

## How to use this file

1. Open the artifacts in the review order below.
2. Answer only decisions that belong to the institution/client.
3. Mark technical questions `ENGINEER SPIKE` instead of guessing.
4. Write one answer per numbered item.
5. Send this file, together with the listed artifacts, to the consulting software engineer.
6. Do not approve implementation until every `DECISION REQUIRED` item has an answer or an explicit owner/date.

Use these markers:

- `[DECISION REQUIRED]` — client/institution must decide.
- `[PROVISIONAL]` — current working assumption; client may change it.
- `[ENGINEER SPIKE]` — requires a technical experiment or source review.
- `[JOINT]` — client defines the business rule; engineer recommends a safe implementation.
- `[DONE]` — agreed and recorded.

---

## 1. Review order: exact files and URLs

### Step 1 — Review the interface

Open the mockup in a browser:

- URL: `http://127.0.0.1:8734/omr-capture-preview.html`
- Absolute file: `/home/user/Projects/NEXIAM/omr-capture-preview.html`

Review these screens and interactions:

- session landing and `OMR capture` entry;
- camera screen;
- `Auto`, `Manual`, and `Batch` modes;
- scans gallery with `Draft` / `All` tabs;
- sheet review screen;
- green/red/yellow answer overlays;
- examinee binding dialog;
- per-question scores editor;
- `Retry` / `Save`;
- unsaved-changes `Discard` / `Save Draft`;
- destructive `Remove scan` flow.

Record what must remain visually or behaviorally unchanged under **Client decisions** below.

### Step 2 — Read the strategic research

- Absolute path: `/home/user/Projects/NEXIAM/openspec/changes/omr-scoring-capture/omrchecker-strategic-research.md`

Focus on:

- §1 — OMRChecker capabilities and limitations;
- §2 — architecture options;
- §3 — template/data ownership;
- §4 — offline/PWA boundaries;
- §5 — benchmark and go/no-go gates;
- §6 — open decisions;
- §7 — implementation sequence.

### Step 3 — Read the authorized feature contract

- Proposal: `/home/user/Projects/NEXIAM/openspec/changes/omr-scoring-capture/proposal.md`
- Design: `/home/user/Projects/NEXIAM/openspec/changes/omr-scoring-capture/design.md`
- Tasks: `/home/user/Projects/NEXIAM/openspec/changes/omr-scoring-capture/tasks.md`
- Handoff: `/home/user/Projects/NEXIAM/openspec/changes/omr-scoring-capture/HANDOFF.md`

The design is the main product/UI contract. The task list is implementation status, not proof that a feature is complete.

### Step 4 — Read the project rules before approving implementation

- Master authority: `/home/user/Projects/NEXIAM/docs/GREENFIELD-MASTER-REFERENCE.md`
- Permanent project context: `/home/user/Projects/NEXIAM/docs/project-context.md`
- Locked UI contract: `/home/user/Projects/NEXIAM/docs/greenfield-foundation/22-user-experience-and-interface-contract.md`
- Authorization design: `/home/user/Projects/NEXIAM/docs/greenfield-foundation/21-authorization-enforcement-design.md`
- Failure/recovery contracts: `/home/user/Projects/NEXIAM/docs/greenfield-foundation/07-failure-recovery-and-integration-contracts.md`
- Domain lifecycle: `/home/user/Projects/NEXIAM/docs/greenfield-foundation/05-domain-model-and-data-lifecycle.md`
- Workflow/state transitions: `/home/user/Projects/NEXIAM/docs/greenfield-foundation/06-workflows-and-state-transitions.md`
- Application/integration contracts: `/home/user/Projects/NEXIAM/docs/greenfield-foundation/20-application-and-integration-contract-catalog.md`

### Step 5 — Review the existing implementation boundary

These are evidence for the engineer, not authority to invent behavior:

- Detection seam: `/home/user/Projects/NEXIAM/system/app/Assessment/Omr/OmrDetectionPort.php`
- Detection request: `/home/user/Projects/NEXIAM/system/app/Assessment/Omr/OmrDetectionRequest.php`
- Detection result: `/home/user/Projects/NEXIAM/system/app/Assessment/Omr/OmrDetectionResult.php`
- Fake detector: `/home/user/Projects/NEXIAM/system/app/Assessment/Omr/FakeOmrDetection.php`
- OMR actions: `/home/user/Projects/NEXIAM/system/app/Assessment/Actions/Omr/`
- OMR models: `/home/user/Projects/NEXIAM/system/app/Models/Assessment/OmrCaptureBatch.php` and `/home/user/Projects/NEXIAM/system/app/Models/Assessment/OmrCaptureSheet.php`
- Sensitive asset store: `/home/user/Projects/NEXIAM/system/app/Support/SensitiveAssetStore.php`
- OMR configuration: `/home/user/Projects/NEXIAM/system/config/omr.php`
- Compose topology: `/home/user/Projects/NEXIAM/system/compose.yaml`
- Existing Python inference host: `/home/user/Projects/NEXIAM/system/docker/inference/`
- Grading session controller: `/home/user/Projects/NEXIAM/system/app/Http/Controllers/Assessment/GradingController.php`
- Current grading page: `/home/user/Projects/NEXIAM/system/resources/js/Pages/Assessment/GradingSession.svelte`
- Current score page: `/home/user/Projects/NEXIAM/system/resources/js/Pages/Assessment/GradingScoreInput.svelte`

### Step 6 — Review OMRChecker primary sources

The research report contains the source links. The most important ones are:

- Repository: `https://github.com/Udayraj123/OMRChecker`
- Inspected commit: `https://api.github.com/repos/Udayraj123/OMRChecker/commits/master`
- Template model: `https://raw.githubusercontent.com/Udayraj123/OMRChecker/de3f6982822895491be760325fd43e65e90fa293/src/template.py`
- Template schema: `https://raw.githubusercontent.com/Udayraj123/OMRChecker/de3f6982822895491be760325fd43e65e90fa293/src/schemas/template_schema.py`
- Core processing/overlay: `https://github.com/Udayraj123/OMRChecker/blob/de3f6982822895491be760325fd43e65e90fa293/src/core.py`
- Official guide: `https://github.com/Udayraj123/OMRChecker/wiki/User-Guide`

---

## 2. Client decisions required before implementation

### A. Product outcome and operating policy

#### A1. What does “successful OMR” mean? `[DONE — confirm]`

Client decision: OMR must reduce manual scoring effort, support high-volume batch work and near-real-time room operations, and preserve audit-quality evidence. Human-confirmed scoring is the first production outcome; OMR is not allowed to silently become authoritative.

**Client answer/change:**

#### A2. Is human confirmation mandatory for every sheet? `[DONE — confirm]`

Client decision: yes. The Test Administrator confirms the sheet before the score is accepted.

**Client answer/change:**


#### A3. What is the acceptable fallback? `[DONE — confirm]`

Client decision: identity conflicts and yellow/uncertain items do not become accepted OMR scores. The Test Administrator resolves them through the existing manual score-input/edit-score interface. The OMR review `Save` action must be disabled while required identity or answer-review warnings remain unresolved; the sheet stays pending/manual until the administrator resolves it.

**Client answer:**

### B. Answer sheets and templates

#### B1. Which forms are in the first release? `[DONE — clarify]`

The first-release answer sheet is one page containing the different aptitude areas. The exact form/template identity, question count, and answer-key fixture still need to be recorded when the client supplies the production sheet.

**Client answer:**

#### B2. Is the first release one page per sheet? `[DONE — confirm]`

Yes. One answer sheet is one page and contains the different aptitude areas. Do not add a separate permanent printed answer-sheet identifier. Future question-booklet/shuffle variants are represented by versioned booklet/template configuration, not by multiplying answer-sheet identities.

**Client answer:**

#### B3. Who owns template CRUD and activation? `[DONE — confirm]`

Current working rule: Test Administrators manage templates; active versions cannot be edited; edits create a new version.

**Client answer/change:**

#### B4. What must a template version contain? `[JOINT]`

The first-release answer-sheet template is a one-page visual form containing the different aptitude areas. Its immutable OMRChecker-native bundle is the sole detection-geometry authority; activation derives NEXIAM's review manifest and relational projections. The question booklet remains separate: a grading session pins the approved template/booklet pair and exact answer-key mapping. The system stores internal capture IDs, asset hashes, template version, and booklet version for digital provenance, but does not require a separate permanent printed sheet identifier.

Template-version fields:

- template name, purpose, effective/retirement dates, and lifecycle;
- native `template.json`, `config.json`, required marker/reference assets, page size/orientation, and exact file/bundle hashes;
- pinned engine/patch identity;
- activation-derived bubbled applicant-number, aptitude-area, question, option, crop, and alignment manifest;
- benchmark/development-qualification status.

Separate booklet/scoring fields:

- question-booklet/version mapping and answer key;
- scoring/conversion policy.

Name/date/age/time may remain printed or visible in forensic crops, but Phase B does not detect them or use free-form OCR for binding.

The first-release form may be mock-generated until the actual client supplies the production answer sheet. The mock must be clearly marked as a fixture and never treated as a production template.

**Client answer/additions:**

#### B5. Can an active template be used by multiple exams? `[DONE — default]`

Yes, the same immutable answer-sheet template version may be reused across exams when its visual layout and aptitude-area/question geometry are identical. Each grading session must still pin the exact answer-sheet template version and question-booklet version used for scoring.

**Client answer:**

#### B6. What changes require a new version? `[DONE — clarify]`

Any change to answer key, question order, question-booklet mapping, bubble coordinates, page layout, identity fields, aptitude-area mapping, scoring policy, or shuffle variant requires a new immutable version. A used version must never be edited in place.

**Client answer:**

#### B7. Should the physical answer sheet have its own evidence identifier? `[DONE — no separate identifier]`

No separate permanent printed answer-sheet identifier is required. Digital provenance uses the internal capture-sheet ID, encrypted asset path/HMAC, grading session, pinned answer-sheet template version, and question-booklet version. The physical paper remains available in the institution's real-world file cabinet; server maintenance must not assume that a digital printed identifier is needed.

**Client answer:**


### C. Scoring and review semantics

#### C1. What does green/red/yellow mean operationally? `[DONE — confirm]`

Client decision:

- green: detected answer matches the pinned answer key;
- red: detected answer does not match the key;
- yellow: uncertain, multi-mark, unreadable, faint/unclear, or identity-warning state;
- yellow requires Test Administrator intervention;
- a green or red definite result may still be overridden, but the override requires a reason; real-engine confidence is not displayed in Phase B;
- unresolved yellow/identity warnings disable the OMR review `Save` action and route the administrator to manual score input/editing.

**Client answer/change:**

#### C2. What should happen to a yellow item? `[DONE — clarify]`

Client decision: yellow items fall back to manual intervention by the Test Administrator through the manual score-input/edit-score interface. The OMR review must not accept the sheet while required yellow or identity warnings remain unresolved. The administrator may later enter the authoritative manual score according to the applicable scoring policy.

**Client answer:**

#### C3. Are negative or partial marks allowed? `[DONE — first release policy boundary]`

The first release does not automatically decide the meaning of blank answers, multiple marks, erased marks, crossed-out marks, partial shading, negative marking, or multiple correct answers. OMRChecker surfaces these as warning/uncertain categories. The Test Administrator resolves them through manual score input.

The data model must leave room for future institution-defined clickable rules, for example:

- partial shade → no score;
- two shades → manual choice or invalid;
- crossed-out option → ruled out;
- multiple correct answers → policy-specific resolution;
- negative/partial marking → versioned scoring rule.

Future rules must be versioned, auditable, and attached to the relevant answer-sheet/question-booklet configuration. They must not be silently inferred by the engine in the first release.

**Client answer:**

#### C4. Can staff override a green or red result? `[DONE — clarify]`

Yes. The Test Administrator may override any green or red definite result judged incorrect during review, but every override requires an explicit reason and immutable audit evidence. A second-review requirement remains a separate policy decision.

**Client answer:**

#### C5. Can an OMR score be overridden later in the manual score editor? `[DONE — clarify]`

Yes. The manual score-input/edit-score interface is the fallback and correction path. The OMR review `Save` button is disabled when unresolved warnings mean the sheet cannot be accepted as an OMR-confirmed score. The later manual score must preserve the original image, detection result, OMR provenance, and reason for the manual decision.

**Client answer:**


### D. Identity and binding

#### D1. What is the authoritative examinee-binding method? `[DONE — confirmed]`

Detection signals are advisory. Phase B may suggest only a bubbled applicant number; name/date crops are visual evidence and never free-form OCR inputs. The Test Administrator confirms the match against the in-session roster. When identity is uncertain, the sheet is sent to manual score input/editing and cannot be accepted by the OMR `Save` action.

**Client answer:** Test Administrator confirmation against the in-session roster is authoritative; every detected identity field remains advisory.

#### D2. What happens when identity signals disagree? `[DONE — clarify]`

The sheet is not accepted as an OMR-confirmed score. The Test Administrator resolves the identity and score through the manual edit-score interface; the conflict, selected identity, and reason are audited.

**Client answer:**

#### D3. May a sheet remain unbound? `[DONE — clarify]`

Yes, as a pending/draft capture. It cannot be accepted or included in the authoritative confirmed grading result until the Test Administrator resolves the identity and any answer warnings manually.

**Client answer:**

### E. Capture operations

#### E1. Who may capture, review, bind, force-set, remove, and confirm? `[DONE — clarify]`

All OMR capture, review, identity binding, force-set/override, removal, and confirmation actions are Test Administrator-only. Other actors may not be granted these actions unless the client later approves a separate capability decision.

**Client answer:**

#### E2. Which capture mode is the default? `[PROVISIONAL — engineer validate]`

Recommended default: `Auto` for normal operation because it queues processing while the Test Administrator captures the next sheet. `Manual` remains the controlled fallback that waits for detection and opens review. `Batch` is future work after OMRChecker capture-quality gates are proven.

- Manual for maximum control and immediate review;
- Auto for normal queued work;
- Batch for future high-volume capture after stability/focus/distance/blur gates are validated.

**Client answer:**

#### E3. What is the physical capture setup? `[PROVISIONAL — follow OMRChecker]`

The first implementation should follow OMRChecker's supported capture assumptions as closely as possible: correct framing, adequate distance, sufficient focus, acceptable blur, stable lighting, and the answer sheet fully inside the capture region. Exact device and lighting requirements remain an engineer benchmark decision.

Batch-specific gates such as too close, too far, too blurred, unstable, or incorrectly framed are future work and must be validated before enabling automatic zero-click capture.

**Client answer:**

#### E4. What is the expected volume? `[PROVISIONAL — best effort first release]`

Operational example: approximately four exam sessions per day, with about 30 sheets per session, or roughly 120 sheets/day in that pattern. The institution handles approximately 1,000 or more applicants per year. There is no strict first-release throughput rule; the engineer should measure the best reliable queue and review performance for this scale and identify the growth limit.

**Client answer:**

### F. Offline and synchronization

#### F1. What exactly must work offline? `[DONE — confirm]`

Capture and local drafts should be able to remain offline without an artificial short time limit. Detection, identity authority, and score commit remain server-side. The interface must show unsynced-change warnings and must not imply that an offline draft is an accepted score.

**Client answer/change:**

#### F2. How long may local drafts remain unsynced? `[DONE — no artificial short limit]`

The client does not want a fixed short offline limit at this stage. Local drafts may remain offline as long as practical storage permits, but the UI must warn about unsynced changes, storage pressure, stale drafts, and possible conflict. The engineer must recommend quotas, recovery, and maintenance rules after measuring realistic image sizes.

The system must distinguish local-only, uploaded, processing, conflicted, and confirmed states. Cleanup after successful server acknowledgment must be explicit and recoverable.

**Client answer:**

#### F3. What happens when two devices edit the same sheet? `[DONE — manual conflict resolution]`

The interface must warn about unsynced changes and show enough server/local evidence for the Test Administrator to resolve the conflict. It must not silently overwrite either side.

**Client answer:**

#### F4. May a browser/device store readable answer-sheet images? `[ENGINEER SPIKE — permitted if feasible]`

IndexedDB or equivalent local storage may be used if image size, browser quota, privacy, encryption, recovery, and cleanup are acceptable. No unlimited storage or automatic security assumption is approved. Minimum file-size and storage standards are to be established after the first processing benchmark.

**Client answer:**

### G. Privacy, retention, and audit

#### G1. How long are original sheet images retained? `[DONE — lifecycle policy pending]`

The original image is stored with its score/provenance as one linked digital evidence bundle. Server cleanup is a separate maintenance process with its own retention rules; it must not delete the image independently while leaving misleading provenance. When cleanup is eventually approved, the bundle must be marked/purged transactionally and the audit record retained. The physical paper remains in the institution's file cabinet for long-term reference.

Exact retention periods remain an institutional policy decision for confirmed, rejected, unresolved, corrected, and legal-hold records.

**Client answer:**

#### G2. Who may view original images and overlays? `[DONE — default]`

Test Administrators only may view original OMR images, overlays, and warning evidence. Applicants do not receive direct image access in this feature. Any later support/break-glass access requires a separate capability, narrow scope, and audit trail.

**Client answer:**

#### G3. What requires a reason? `[DONE — clarify]`

At minimum, an explicit reason is required for:

- overriding any green or red definite result;
- force-setting an answer;
- changing examinee binding;
- removing a scan;
- replacing an OMR score manually;
- reopening a confirmed batch;
- changing a template version.

**Client answer:**

#### G4. Is a second reviewer required for corrections? `[DONE — first-release default]`

No second reviewer is required in the first release. The Test Administrator is the required reviewer and must provide reasons for overrides/corrections. A later policy may add second review for high-confidence overrides, identity conflicts, post-confirmation changes, or result-affecting changes.

**Client answer:**

### H. Result and institutional policy

#### H1. When may grading be finalized? `[DONE — default]`

The Test Administrator finalizes grading after every present/submitted applicant has an authoritative score for every required aptitude area. OMR review `Save` is available only after identity and required warning states are resolved. A sheet with unresolved warnings must use manual score input before it can satisfy finalization.

**Client answer:**

#### H2. Can confirmed OMR scores be reversed? `[DONE — safe default]`

Yes, but never by mutating history silently. A correction creates a new manual/corrected score event with actor, reason, timestamp, and prior-score reference. The original OMR proposal, image, template/booklet versions, and prior score remain immutable evidence. Test Administrator authority applies unless a later correction policy adds another approval tier.

**Client answer:**

#### H3. What evidence is needed for disputes or appeals? `[DONE — clarify]`

The long-term evidence set should include:

- original encrypted image;
- detection proposal;
- overlay;
- answer-sheet template version;
- question-booklet version;
- reviewer edits;
- actor/timestamps;
- before/after score history;
- device/sync information where relevant;
- internal capture-sheet ID and asset hash;
- linked physical-paper file-cabinet reference where available.

No separate printed answer-sheet identifier is required. Confirm exact retention duration and access policy separately.

**Client answer:**

---

## 3. Data-design decisions for questions, aptitude areas, and templates

### I1. Maximum-items invariant `[DONE — planning rule]`

Every question used by an OMR answer-sheet/question-booklet version must belong to exactly one aptitude area. The versioned template-area row stores an immutable `max_items` snapshot for that aptitude area. Versioned question rows reference that template-area row and use question numbers `1..max_items`.

For the first release:

- `aptitude_areas.max_items` remains the setup/scoring upper bound used by manual score validation and conversion-table coverage;
- each answer-sheet template version snapshots the applicable area `max_items`;
- activation rejects a template when its area snapshot does not match the configured aptitude-area `max_items`;
- activation rejects a template when the question count is not exactly `max_items`;
- OMR-derived raw scores and manual scores are both bounded by the same maximum;
- a future form with a different item count requires a new scoring/template policy, not a silent per-form override.

### I2. Recommended relational shape `[JOINT — engineer validate]`

Use relational versioned tables rather than storing the question catalog only inside one JSON blob:

- `omr_answer_sheet_templates`: stable visual-form identity;
- `omr_answer_sheet_template_versions`: immutable one-page native bundle + engine/patch/config/file/bundle hashes + activation-derived manifest/hash;
- `omr_template_areas`: immutable activation-derived template area + `aptitude_area_id` + display order + `max_items`;
- `omr_template_questions`: immutable activation-derived stable field/question + aligned region + booklet position;
- `omr_template_question_options`: immutable activation-derived option code/label/order/native bubble;
- `omr_question_booklets`: stable booklet identity;
- `omr_question_booklet_versions`: immutable question order/answer-key variant;
- `omr_question_booklet_questions`: booklet version + aptitude area + question position + source mapping + correct option/key metadata;
- `omr_processing_runs`: current-run claim/version, contract/engine/patch/bundle/manifest/input hashes, output status, timings, failures, and provenance;
- `omr_capture_sheet_issues`: normalized warning category and Test Administrator resolution state.

The one-page OMRChecker-native template bundle is the sole authority for where bubbles are; NEXIAM derives its review projections at activation. The question-booklet version separately describes which question/key is assigned to each position. A grading session pins both. No permanent printed answer-sheet identifier is required.

### I3. Database constraints `[JOINT — engineer validate]`

The engineer must enforce:

- one template-area row per `(template_version_id, aptitude_area_id)`;
- one question number per template area;
- one option code/order per question;
- one or more answer-key rows only where the selected policy permits multiple correct answers;
- question number and question count within `max_items`;
- booklet question positions unique within a booklet version;
- booklet/template compatibility checked before activation;
- grading session cannot change its pinned template/booklet versions after capture begins;
- score conversion coverage must include `0..max_items`;
- historical processing runs retain the exact template/booklet/config hashes used.

### I4. Future warning-policy readiness `[DONE — foundation, deferred implementation]`

First release stores engine warnings and unresolved item issues as manual-review states. It does not automatically score blanks, crossed-out marks, partial shading, or multiple marks. The schema should allow future versioned issue-resolution policies with selectable outcomes, reasons, and audit events without changing historical results.

### I5. First-release mock form `[PROVISIONAL]`

Until the actual client answer sheet is supplied, use a clearly marked synthetic one-page fixture:

- three mock aptitude areas;
- each area has a configured `max_items`;
- sequential question numbers per area;
- four answer options per question;
- applicant-number and name/date regions;
- one answer-sheet geometry version;
- one question-booklet version;
- deterministic answer key;
- clean, ambiguous, blank, multi-mark, faint, erased, crossed, unreadable, and identity-conflict fixture cases; confidence remains null.

The synthetic fixture is for UI, database, overlay, queue, and review testing only. It must not be activated as an institutional production template.

---

## 4. Questions specifically for the consulting software engineer

Give the engineer these files first:

1. `/home/user/Projects/NEXIAM/openspec/changes/omr-scoring-capture/CLIENT-DECISION-REVIEW.md`
2. `/home/user/Projects/NEXIAM/openspec/changes/omr-scoring-capture/omrchecker-strategic-research.md`
3. `/home/user/Projects/NEXIAM/openspec/changes/omr-scoring-capture/proposal.md`
4. `/home/user/Projects/NEXIAM/openspec/changes/omr-scoring-capture/design.md`
5. `/home/user/Projects/NEXIAM/omr-capture-preview.html`
6. `/home/user/Projects/NEXIAM/system/compose.yaml`
7. `/home/user/Projects/NEXIAM/system/docker/inference/`
8. `/home/user/Projects/NEXIAM/system/app/Assessment/Omr/`
9. `/home/user/Projects/NEXIAM/system/app/Assessment/Actions/Omr/`
10. `/home/user/Projects/NEXIAM/system/resources/js/Pages/Assessment/`

Ask the engineer to answer these explicitly:

### Architecture

1. Can OMRChecker run headlessly and reproducibly in `python:3.12-slim`?
2. Should the first implementation use a long-lived worker, internal HTTP, or a local process?
3. How should Laravel queue jobs handle timeouts, retries, duplicate requests, dead letters, and worker restarts?
4. What is the smallest stable provider envelope that protects NEXIAM from OMRChecker schema changes?
5. Can the existing optional `inference` Compose service host this safely, or should it be changed?

### OMRChecker integration

6. What exact OMRChecker commit/package should be pinned?
7. Does the pinned version expose item-level coordinates, selected options, ambiguity, and confidence reliably enough for the review overlay?
8. Which output values are authoritative, and which must be recomputed by NEXIAM?
9. How should OMRChecker templates be generated from NEXIAM’s versioned template data?
10. What upstream behavior must be patched, isolated, or wrapped, especially GUI/headless and file-output behavior?

### Data and templates

11. Is the proposed template/version schema sufficient for page geometry, fields, areas, options, answer keys, and engine configuration hashes?
12. How should a grading session pin a template version so later template edits cannot reinterpret old sheets?
13. Which fields should be relational columns and which should be versioned JSON/config artifacts?
14. How should template activation be validated and benchmarked?

### Review UI

15. How can normalized OMRChecker regions be transformed into accurate client-side overlays across image dimensions, zoom, and responsive layouts?
16. How should green/red/yellow state be computed for blank, multi-mark, unreadable, and low-confidence items?
17. What accessibility behavior is required for overlay-only information?
18. How should the UI behave while processing, retrying, offline, conflicted, denied, or degraded?

### Offline/PWA

19. Is capture/local draft storage safe for the target browsers and devices?
20. What encryption/key lifecycle is realistic for browser-stored images?
21. What sync envelope and conflict-resolution UI should be used?
22. What should expire locally, and how can recovery work without retaining plaintext files?

### Security and operations

23. How should encrypted image access be authorized and streamed?
24. What audit fields are necessary for detection, review, force-set, binding, removal, and sync?
25. What monitoring/health metrics are required for the worker and queue?
26. How should the system degrade when OMRChecker is unavailable?

### Proof and delivery

27. What representative fixture set is required before choosing the engine?
28. Which accuracy, false-green, uncertainty, latency, and throughput thresholds are technically defensible?
29. What tests prove item-level overlays, idempotency, retry recovery, asset cleanup, and manual fallback?
30. What is the smallest production-safe vertical slice, and what must explicitly remain out of scope?

The engineer must return written answers, assumptions, risks, proposed experiments, and a recommendation—not only an implementation estimate.

---

## 5. Client answer sheet

Copy this section into a separate working copy if desired.

| ID | Decision | Answer / policy | Owner | Date | Status |
|---|---|---|---|---|---|
| A1 | Product success | Reduce manual effort; high-volume/near-real-time; human-confirmed; auditable | Client | 2026-08-12 | RECORDED |
| A2 | Human confirmation | Test Administrator confirms every accepted sheet | Client | 2026-08-12 | RECORDED |
| B1 | First-release forms | One-page composite answer sheet; actual client form pending; synthetic fixture permitted | Client | 2026-08-12 | RECORDED / FIXTURE PENDING |
| B2 | One-page vs multi-page | One page containing all aptitude areas | Client | 2026-08-12 | RECORDED |
| B3 | Template owner/lifecycle | Test Administrator; immutable active versions | Client | 2026-08-12 | RECORDED |
| B4 | Template/booklet split | Answer-sheet template is visual; question booklet owns versioned answer key | Joint | 2026-08-12 | RECORDED / ENGINEERING PLAN LOCKED |
| B7 | Printed sheet identifier | No separate permanent printed identifier | Client | 2026-08-12 | RECORDED |
| C2 | Yellow-item handling | Manual Test Administrator score input; OMR Save disabled until resolved | Client | 2026-08-12 | RECORDED |
| C3 | Marking rules | First release surfaces warnings; future policies become versioned clickable resolutions | Joint | 2026-08-12 | RECORDED / POLICY LATER |
| D1 | Identity authority | Detection advisory; Test Administrator decides | Client | 2026-08-12 | RECORDED |
| E1 | Actors/capabilities | Test Administrator only | Client | 2026-08-12 | RECORDED |
| E2 | First-cut modes | Auto recommended; Manual forced-review fallback; Batch excluded | Joint | 2026-08-12 | LOCKED FOR ACTIVE CHANGE |
| E3 | Capture equipment | Follow OMRChecker-supported conditions; benchmark exact devices | Joint | 2026-08-12 | PROVISIONAL / ENGINEER SPIKE |
| E4 | Scale/SLA | Approx. 4 × 30 sheets/day example; best effort first release | Joint | 2026-08-12 | PROVISIONAL |
| F2 | Offline draft expiry | Institutional preference recorded; offline image persistence is a separate gated change | Joint | 2026-08-12 | OUT OF ACTIVE CHANGE |
| F4 | Device storage policy | IndexedDB may be evaluated only in a separate security/feasibility spike | Joint | 2026-08-12 | OUT OF ACTIVE CHANGE |
| G1 | Image retention | No committed purge; retain linked evidence until retention/legal-hold policy is approved | Joint | 2026-08-12 | SAFE DEFAULT LOCKED / POLICY PENDING |
| G2 | Image access | Test Administrator only by default | Client | 2026-08-12 | RECORDED |
| G3 | Required correction reasons | Overrides, binding, removal, manual replacement, reopening, template changes | Client | 2026-08-12 | RECORDED |
| G4 | Second-review policy | Not required first release | Client | 2026-08-12 | RECORDED / FUTURE POLICY |
| H1 | Finalization gate | All required areas authoritative; unresolved warnings must be manually resolved | Joint | 2026-08-12 | RECORDED |
| H2 | Reversal policy | New correction event; original OMR evidence immutable | Joint | 2026-08-12 | RECORDED |
| H3 | Dispute evidence | Image, proposal, overlay, versions, edits, history, hashes, sync metadata | Joint | 2026-08-12 | RECORDED / RETENTION PENDING |

---

## 6. Current blockers and warnings

The online Manual + Auto fake-provider vertical slice may be implemented against the active OpenSpec. It must remain visibly synthetic and cannot be represented as production OMRChecker. Production activation remains blocked by all of the following:

- The actual client one-page answer sheet and approved answer key have not been supplied.
- The canonical OMRChecker commit/container digest and license/dependency review are not recorded.
- Headless execution with bounded resources in the Compose inference container is not proven.
- A representative labeled corpus and ground truth for clean and adverse capture conditions do not exist.
- Native item mapping, aligned-review crops, null-confidence behavior, and issue mapping are not yet verified against the pinned source and fixture pack.
- The actual client native bundle, representative corpus, and named production approval owner have not been supplied.
- Exact institutional retention and legal-hold duration remain undecided; committed purge therefore stays unavailable.
- Backup/restore, key rotation, reconciliation, worker restart, dependency outage, and benchmark drills have not passed.

Batch capture and offline/PWA image persistence are not blockers for the active fake-provider cut because they are explicitly excluded. They require separate approved OpenSpec changes and may not be exposed through crafted client values.

**Do not approve production OMRChecker activation while any blocker is silently assumed away.**

## 7. Engineering disposition after full-plan audit

| Concern | Locked disposition for `omr-scoring-capture` |
|---|---|
| Delivery slice | Phase A shipped online Manual + Auto with fake/synthetic provider; Phase B adds the pinned development-qualified OMRChecker path; Batch/offline/production remain gated |
| Authority | New `GUIDANCE_SCORING_OMR`; Test Administrator set only; GF-21 scope rechecked in Actions/jobs/reads |
| Write timing | Manual Save commits one sheet; Auto review stays non-authoritative until atomic session confirmation |
| State | Batch `open/closing/rejecting/confirmed/rejected`; per-sheet received/queue/process/review/commit/failure states |
| Concurrency | Session → batch → ordered sheets → ordered scores; expected versions; no provider/filesystem work under row locks |
| Idempotency | Actor/command/aggregate-scoped receipt + canonical payload fingerprint + stored safe replay result |
| Scores | Existing roster + `ScoreConverter`; no silent manual-score overwrite; immutable score revisions |
| Templates | Immutable OMRChecker-native bundle as sole detection geometry; activation-derived NEXIAM review manifest/projections; separate versioned booklet/answer key; approved pair pinned before first capture |
| Assets | Versioned authenticated encryption, mandatory plaintext HMAC, key IDs/rotation, durable cleanup/reconciliation |
| Queue | Per-sheet after-commit job; database claim; 3 tries; bounded backoff; terminal/manual recovery |
| HTTP | Explicit session-scoped sheet resolution, one Form Request per mutation, finite safe error mapping |
| Interface | Mockup-aligned camera → gallery → review; cropped bubble first; accessible dirty-back and state handling |
| Retention | Indefinite/no committed purge until policy and legal hold are approved |
| OMRChecker | Selected replaceable pixels-to-observations provider behind answer-key-free envelope v2; one isolated child per sheet; aligned derivative review; null unqualified confidence; synthetic development qualification only until production gates pass |

The normative contract is `specs/nexi-omr/spec.md`; implementation mechanics are in `design.md`; executable order is `tasks.md`. Existing OMR PHP is a replace/forward-fix input, not acceptance evidence.
