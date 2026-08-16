# applicant-status-spine Spec

## ADDED Requirements

### Requirement: The hub renders a server-projected status spine

`GET /applicant/portal` SHALL render a vertical status spine from one server projection: identity, ordered stages (`submitted`, `review`, `exam`, `results`, `consultation`, `program_choice`, `enrolling`, `enrolled`), per-stage state (`done|current|locked|action|auto`), timestamps, and per-stage allowed actions. The client SHALL NOT reorder stages or invent actions (FR-029; REQ-VS-026).

#### Scenario: Current stage expanded by default
- **WHEN** the projection marks Exam as current
- **THEN** Exam expands with its actions, done stages collapse, and locked stages dim with truthful hints

#### Scenario: needs_correction loops to Submitted
- **WHEN** the attempt status is `needs_correction`
- **THEN** the Submitted stage shows the ⚠ action badge, opens by default, and exposes the correction action; no later stage implies progression

#### Scenario: Dismissed is terminal
- **WHEN** the attempt is dismissed
- **THEN** the spine renders a terminal truthful card and no exam/results actions

### Requirement: Release placement follows the release mode

The Results stage SHALL render by `ResultRelease.mode`. `MODE_ONLINE` SHALL show the released artifact with an accessible table alternative and version/freshness. `MODE_F2F` SHALL show a claim-at-consultation state with a Book-consultation action and SHALL NOT serialize scores. Unreleased SHALL render a locked hint, never an empty chart (FR-028; REQ-VS-022).

#### Scenario: F2F release
- **WHEN** the release mode is `MODE_F2F`
- **THEN** the applicant sees "Results ready — claim them at your consultation session", the consultation card is emphasized, and no score value reaches the portal payload

#### Scenario: Online release
- **WHEN** the release mode is `MODE_ONLINE`
- **THEN** the Results stage shows the released artifact and the Consultation card remains an independent recommended step

### Requirement: Consultation and program choice are independent parallel cards

The Consultation and Program-choice cards SHALL carry independent state badges (`booked|attended|none` and `default_active|acknowledged|chosen|none`) and SHALL NOT gate each other or the Results stage. Unlock is acceptance-based; completion order is never assumed by the UI.

#### Scenario: Consultation before release
- **WHEN** the applicant booked consultation while results are unreleased
- **THEN** the Consultation card shows booked and the Results stage remains locked with a truthful hint

#### Scenario: Release before consultation
- **WHEN** results released online and consultation is unbooked
- **THEN** the Results stage shows the artifact and the Consultation card offers booking without implying it is required to see results

### Requirement: Every action hangs off its stage

No floating or global action rail SHALL exist on the hub. Each action SHALL render under the stage it serves, gated by the same projection that produced the stage state.

#### Scenario: Fix-request action placement
- **WHEN** `needs_correction` is active
- **THEN** the fix action renders inside the Submitted stage only

### Requirement: Explicit hub states

The hub SHALL render explicit loading, ready, empty (no attempt), denied (redirect to login), error (retry + support path), and stale/conflict (refresh banner) states. No failure SHALL render as an empty success screen (REQ-VS-026).

#### Scenario: Stale projection after staff change
- **WHEN** a staff action changed the applicant's stage between page visits
- **THEN** the hub shows a conflict banner offering a preserve-scroll refresh and never mutates state client-side
