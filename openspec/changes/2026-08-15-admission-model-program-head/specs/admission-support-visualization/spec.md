# admission-support-visualization Spec

## ADDED Requirements

### Requirement: Consultation embeds the applicant-specific admission-support workspace

The existing consultation selected-attempt workspace SHALL present one applicant-specific admission-support interface containing overview/readiness, released assessment profile, ordered application preferences, approved structured consultation evidence, independent future-analysis regions, human consultation decision, Guidance pool/advised default, follow-up, and history. The system MUST NOT require navigation to a standalone model or suggestion page to conduct ordinary consultation.

#### Scenario: Counselor opens a result-ready applicant
- **WHEN** authorized Guidance opens consultation for an applicant whose grading is finalized, result document is prepared, and result is released
- **THEN** the workspace presents the applicant's real released-score profile, preferences, consultation evidence, and human decision/pool controls in the same selected-attempt context

#### Scenario: Applicant result is not ready
- **WHEN** Guidance opens an attempt without the required released assessment evidence
- **THEN** the workspace shows the missing readiness facts, does not fabricate charts or findings, and keeps ineligible consultation/pool actions unavailable

### Requirement: Assessment graphics are attributable and accessible

Every assessment chart SHALL use a server-authored typed projection containing stable chart identity, purpose, source/evidence version, freshness, unit, ordered labeled values, null markers distinct from zero, and data/error state. The same values SHALL be available in an accessible table. The browser MUST NOT derive a new cluster, recommendation, placement, quota fact, or admission decision merely to draw a graphic.

#### Scenario: Per-domain score profile renders
- **WHEN** a released converted-score projection contains the approved domain values
- **THEN** the chart and table show the same ordered labels and values with source/version/freshness visible

#### Scenario: Source data is stale
- **WHEN** the underlying released evidence version no longer matches the rendered projection
- **THEN** the workspace shows a stale-input state and does not present the prior visualization as current

### Requirement: Manual consultation remains complete without an engine

The current change SHALL NOT install or simulate an admission-support engine, provider SDK, fake fixture engine, `ProgramSuggestion`, or proposal-review workflow. `RecordConsultationDecision`, manual pool/default finalization through `SetGuidanceProgramPool`, follow-up, and history SHALL remain complete when no analysis plugin is installed.

#### Scenario: No plugin is configured
- **WHEN** Guidance opens the admission-support analysis region in this change
- **THEN** it truthfully reports that no analysis is available while released-score/application/consultation evidence and all authorized human workflow remain usable

### Requirement: Future plugin outputs remain independent evidence

The visualization boundary SHALL support separately attributed analysis regions without combining independent outputs into one synthetic score or recommendation. The future YuKee artifact/K-Means compatibility plugin and future transparent deterministic/rule-based plugin SHALL remain separate later integrations; BGE/interest embedding remains a separate research option. Every future output MUST identify source/engine/version, evidence/input version, generated-at, limitations, and scientifically qualified strength/confidence only when evidence permits it.

#### Scenario: Multiple future analyses exist
- **WHEN** two approved plugins later produce outputs for the same applicant
- **THEN** the interface presents each result under its own source/version and does not calculate or display an ensembled recommendation

#### Scenario: Future engine output is present
- **WHEN** an approved future plugin returns a valid attributed analysis
- **THEN** the output remains read-only evidence and cannot write consultation decision, pool/default, selection, quota, follow-up, PH outcome, release, or enrollment state

### Requirement: Program Head reuse remains relationship-scoped

An authorized Program Head Tier B protected record MAY reuse an assigned-program-safe read-only projection of the admission-support visualization only when D-080 release and strong case-relationship rules pass. Tier A preference/pool membership SHALL expose no scores, analysis inputs, analysis outputs, other program options, or counselor notes. Program Head reuse MUST NOT transfer ordinary consultation/advising authority.

#### Scenario: Potential-only Program Head opens candidate
- **WHEN** the program appears only in the applicant's preference or Guidance pool
- **THEN** the Program Head receives the minimal Tier A summary and no admission-support chart or analysis data
