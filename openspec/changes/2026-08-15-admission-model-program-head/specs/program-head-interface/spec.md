# program-head-interface Spec

## ADDED Requirements

### Requirement: Program Head scope is assigned-program only

Every Program Head dashboard, list, count, record query, command, thread, and destination returned by the universal applicant QR action hub SHALL resolve assigned-program scope server-side through GF-21. Route parameters, Inertia props, client navigation, QR payloads, or seeded role membership MUST NOT widen scope. Cross-program and unassigned-program access SHALL be denied without revealing record existence.

#### Scenario: Assigned program opens
- **WHEN** a PH assigned to BSIT opens the BSIT dashboard
- **THEN** only BSIT-scoped projections and actions are returned

#### Scenario: Cross-program record is denied
- **WHEN** the BSIT PH requests a BSN candidate by known identifier
- **THEN** the request is denied and no candidate fields or existence signal are returned

### Requirement: Program dashboard metrics are defined and truthful

The dashboard SHALL show program-scoped active-cycle counts for submitted, accepted, exam completed, result released, advised, Potential, Upcoming, Follow-up, Enrolling, Enrolled, and quota reserved/available/full where applicable. Each metric SHALL expose definition/source and an `as of` timestamp. The implementation SHALL distinguish true zero from no-data, stale, error, and denied states and SHALL provide accessible table alternatives for any chart.

#### Scenario: Zero is not no-data
- **WHEN** the active cycle exists and no applicants have reached Enrolled
- **THEN** Enrolled displays zero with source/freshness rather than an empty or error state

#### Scenario: Projection query fails
- **WHEN** the metric projection cannot be computed
- **THEN** the page reports an error/degraded state and does not display cached or invented counts as current

### Requirement: Potential overview is minimal

A Potential row MAY be grounded by accepted application preference or current Guidance pool. Before the protected-record gate passes, the PH SHALL see only stable case reference, applicant name, basis tag, and lifecycle status tags. Preference alone MUST NOT reveal scores, result sheet, counselor notes, contact details beyond the approved list contract, internal messages, or permit outreach.

#### Scenario: Preference-only candidate
- **WHEN** an accepted applicant listed the PH program but has no Guidance pool/current selection/follow-up
- **THEN** the PH sees the minimal Potential row and cannot open protected results or message the applicant

### Requirement: Protected record requires release and a strong relationship

A PH MAY open the bounded protected record only when the result is released and the applicant has an acknowledged current selection or explicit Guidance-routed targeted follow-up for that PH's assigned program. Preference/pool membership and an unacknowledged Guidance default MUST remain minimal. The view MAY include converted per-domain scores and accessible chart/table projection, released result/remark sheet, consultation outcome summary, assigned-program basis/current selection/follow-up/reconsideration outcome and stamp state, separately attributed future analysis evidence for the assigned program when approved, and program-relevant counselor notes permitted by D-080. It MUST NOT serialize other program names/options or cross-program choice history, raw sheets/scans, conversion tables, unrelated notes, hidden engine internals, or other programs' analysis output.

#### Scenario: Acknowledged selection unlocks released record
- **WHEN** a released applicant has acknowledged the current BSIT selection and the assigned BSIT PH opens the record
- **THEN** the bounded protected view is returned and prohibited artifacts are absent from server props

#### Scenario: Pool membership remains minimal
- **WHEN** a released applicant is in the BSIT Guidance pool but has not selected/acknowledged BSIT and has no targeted follow-up
- **THEN** the PH sees only the minimal Potential row and cannot open protected results or counselor notes

#### Scenario: Result is not released
- **WHEN** a selected applicant's result is finalized but not released
- **THEN** the protected PH record remains denied even though the program relationship exists

### Requirement: Every protected record open is attributable and applicant-visible

Opening a protected PH record SHALL emit an audit event containing actor, attempt, program, relationship basis, purpose, route/request correlation, and timestamp. The applicant access-history projection SHALL show the authorized open. A user-facing `record opened` notice SHALL be emitted after commit and deduplicated per PH/program/24-hour window; deduplication MUST NOT remove audit events.

#### Scenario: Repeat views inside the notice window
- **WHEN** the same PH opens the same applicant/program record three times in 24 hours
- **THEN** three audit events exist while the applicant receives at most one deduplicated notice for that PH/program window

#### Scenario: Access evidence cannot be persisted
- **WHEN** authorization passes but protected-view audit/access-history persistence fails
- **THEN** the protected payload is not serialized, the view fails closed, and no delivered-notice claim is made

### Requirement: PH normal-path action is minimal

An ordinary Guidance default/applicant selection SHALL require no PH system approval. Before `enrolling`, PH MAY directly decline an acknowledged current selection within assigned program; a pending default without targeted follow-up is non-declinable. For an already-open targeted follow-up, assigned PH MAY clear, decline, or request appearance before or during `enrolling`; clear/decline is required before `enrolled`, while request appearance remains open. All mutations use optimistic versioning, reason where required, one atomic state/audit transaction, and after-commit notifications. PH MUST NOT application-Accept/Dismiss, score, finalize grading, release/unrelease results, edit Guidance pool, stamp enrollment, alter the Registrar's paper-confirmed final program, or override quota outside own program.

#### Scenario: No PH action required
- **WHEN** an applicant follows the normal Guidance-default/selection route with no targeted follow-up
- **THEN** Registrar may start `enrolling` and later verify the signed paper without any PH system acceptance event

#### Scenario: Stale PH decline
- **WHEN** PH submits a decline against an obsolete selection version
- **THEN** the command reports conflict and writes no state, quota, audit, or notification effect

#### Scenario: Follow-up and direct decline converge
- **WHEN** a follow-up decline and direct selection-decline command target the same attempt/program/current pool version
- **THEN** locking and uniqueness permit one canonical program-decline outcome, any eligible selection/quota transition occurs once, and the losing/replayed command returns the authoritative result without duplicate audit-success or notification

### Requirement: Program Head owns assigned-program quota only

PH MAY update configured quota only for assigned programs using the existing audited quota command. Guidance/TA MAY adjust quota under D-087 with the same command contract and a PH notice after commit. Quota UI SHALL show configured capacity, pre-enrollment reservations, deduplicated commitments, remaining, over-capacity/not-configured variance, definition, freshness, and immutable change history. Paper-confirmed enrollment never silently raises configured capacity.

#### Scenario: PH changes own quota
- **WHEN** assigned PH raises BSIT capacity with valid reason/version
- **THEN** the quota changes atomically, history/audit identify actor and old/new values, and availability is derived from authoritative commitments

#### Scenario: Quota cannot drop below current commitments
- **WHEN** authorized staff lowers capacity below deduplicated reservation + enrolling/enrolled commitments
- **THEN** the command reports conflict and preserves capacity, selections/stamps, quota history, audit-success, and notifications

#### Scenario: PH changes another program
- **WHEN** the BSIT PH submits a BSN quota change
- **THEN** the command is denied with no mutation or information leakage

#### Scenario: Paper truth creates an over-capacity report
- **WHEN** Registrar records a signed-paper BSIT enrollment beyond configured BSIT capacity
- **THEN** the PH dashboard shows the unchanged configured capacity and explicit over-capacity variance; PH receives no power to reject or rewrite the enrollment fact

### Requirement: Program Head pages follow the approved legacy shape

The PH experience SHALL use the server-authored staff shell and management-list pattern: program index, one program dashboard with metrics/quota/lists, and a candidate summary/protected-record page. Existing Admin/Reports/Dashboard density and responsive behavior are feel evidence; legacy Chart.js and quota copy are not target domain authority. Required states include loading, zero, empty, ready, stale, error, denied, conflict, unread, no-analysis, and future-provider-degraded where relevant.

#### Scenario: Mobile candidate list
- **WHEN** PH opens a program list at the supported mobile width
- **THEN** the same authorized rows/actions remain usable without exposing hidden desktop-only data or relying on horizontal clipping
