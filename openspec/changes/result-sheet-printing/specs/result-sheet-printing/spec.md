# result-sheet-printing Spec

## ADDED Requirements

### Requirement: Versioned result-sheet templates with one active authority

The system SHALL manage `result_sheet_templates` rows with mode `html` or `docx`, paper size (`a4`/`legal`/`letter`), orientation, logical unit (`full` or half crosswise variants), optional watermark text, active flag, and private document storage for Word/ODT templates. Exactly one template SHALL be active at a time; activating a template deactivates the previous one atomically. Uploaded documents SHALL be stored privately with a checksum and versioned by template id. Template management requires the `guidance.assessment.catalog` capability.

#### Scenario: Template CRUD with single-active swap
- **WHEN** a Test Administrator activates template B while template A is active
- **THEN** A becomes inactive and B becomes active in the same transaction

#### Scenario: Word template upload stored privately with checksum
- **WHEN** a Test Administrator uploads a `.docx`/`.odt` template
- **THEN** the file is stored on private disk, a `document_checksum` is recorded, and the raw bytes are never served publicly

### Requirement: Template validation and preview

The system SHALL analyze a template against the placeholder catalog and report found, missing (required/recommended), and extra placeholders. Required missing placeholders SHALL block save unless the staff explicitly confirms. The system SHALL render a live preview of a template with sample applicant data, respecting paper size, orientation, and logical unit, without requiring a queued job.

#### Scenario: Analyzer reports found/missing/extra
- **WHEN** a template is analyzed
- **THEN** the response lists placeholder groups, counts, and any missing required/recommended placeholders

#### Scenario: Preview renders with sample data
- **WHEN** staff preview an HTML or uploaded document template
- **THEN** the server returns rendered markup with sample applicant values at the configured paper width

### Requirement: Legacy placeholder contract with alias mapping

The renderer SHALL resolve the legacy placeholder set — applicant identity/reference, exam details, per-domain scores with descriptive ratings, `scores_rows` table injection, overall percentile, course checkmarks, institution settings, personnel/signatories, and `_2` dual-slot variants — and SHALL map legacy domain names (e.g. `{{verbal_aptitude}}`) to current aptitude-area codes through an explicit alias catalog. Ratings SHALL come from the frozen `score_records.converted_score`, never recomputed from live conversion tables.

#### Scenario: Legacy domain alias resolves to a score record
- **WHEN** a template contains `{{verbal_aptitude}}` and the area `VERBAL` has a frozen converted score
- **THEN** the placeholder renders that converted rating

#### Scenario: Scores rows inject per-area raw + converted
- **WHEN** a template uses `{{scores_rows}}`
- **THEN** the placeholder expands to a table of area, raw score, and converted rating rows from released scoring data

### Requirement: Fragmented DOCX macro repair

DOCX templates with split Word runs SHALL be repaired before placeholder substitution so `{{...}}` macros fragmented across runs resolve correctly, matching legacy behavior.

#### Scenario: Split macro renders filled
- **WHEN** a `.docx` template stores `{{applicant_name}}` across multiple runs
- **THEN** the filled document contains the applicant name in place of the macro

### Requirement: Queued result-sheet rendering with pinned inputs

Result sheets SHALL render through `print_jobs` (`queued → rendering → ready | failed`) with artifact type `result_sheet`, sensitive-disk storage, checksum verification on download, and capability re-authorization (`guidance.results.release`). Jobs SHALL pin their inputs (release version, document checksum, template version/hash) at creation; retry SHALL refuse with a typed error when pinned inputs are stale. Duplicate requests for the same scope with a live job SHALL return the existing job.

#### Scenario: Single result sheet requested
- **WHEN** staff request a result sheet for one released applicant
- **THEN** a print job is created and the response returns immediately with the job id

#### Scenario: Result sheet download re-authorizes and verifies checksum
- **WHEN** an authorized staff member downloads a ready result sheet
- **THEN** the file streams `private, no-store` only after capability + scope check and checksum match

#### Scenario: Unauthorized or stale retry refused
- **WHEN** a staff member lacks the release capability, or retries a job whose pinned release version was superseded
- **THEN** the request is denied with a typed error, without existence leakage

### Requirement: Bulk result-sheet jobs with progress and partial states

Bulk jobs SHALL support up to 200 applicants with copies 1–10, expose progress counters, persist a per-item manifest (applicant id → path + checksum), reach `ready` with partial states when some items fail (successful sheets downloadable, failures listed with safe errors), and optionally produce a merged bulk PDF for stack printing.

#### Scenario: Bulk with one unrenderable applicant
- **WHEN** one applicant in a bulk job cannot render
- **THEN** the job completes with a partial status listing the failed item and its safe error, while other sheets remain downloadable

### Requirement: Printed-status tracking

`result_printed_at` SHALL live on `result_releases` per release version (a re-release after correction starts unprinted). When a result-sheet job for released records completes, `result_printed_at` SHALL be set idempotently. Staff SHALL be able to manually mark/unmark released records printed independently of rendering, capability-gated and audited.

#### Scenario: Ready render records printed status
- **WHEN** a result-sheet job completes for released records
- **THEN** `result_printed_at` is set on those release records and visible in the release surface

#### Scenario: Manual toggle for physical prints
- **WHEN** staff mark or unmark released records after an external print run
- **THEN** the printed flag changes and the action is audited

### Requirement: Document preparation seam

`PrepareResultDocument` SHALL produce a template-backed, checksummed, privately stored `ResultDocument` (status `ready` only after successful generation) that `ReleaseResult` can consume unchanged; failures SHALL leave a typed failed state rather than a fake-ready artifact.

#### Scenario: Prepare produces a real artifact
- **WHEN** staff prepare a result document for a scored applicant with an active template
- **THEN** a ready `ResultDocument` with checksum and private storage path exists before release is offered

### Requirement: Download audit and retention

Every print job creation, ready/failed transition, and download SHALL be audited with actor, artifact type, scope, and checksum. Ready artifacts SHALL be deleted by a scheduled retention pass after `config('result-sheet.retention_days')` days.

#### Scenario: Download is audited
- **WHEN** any download succeeds or is attempted
- **THEN** an audit event records the actor, artifact, scope, and checksum

#### Scenario: Retention pass
- **WHEN** a ready artifact passes its retention window
- **THEN** a scheduled command deletes it and records the cleanup

### Requirement: Release-mode printing gate

Printing result sheets SHALL be disabled when the institutional release mode is online-only; staff see an explanatory disabled state. F2F/Both modes enable printing (legacy parity).

#### Scenario: Online mode disables printing
- **WHEN** release mode is `online`
- **THEN** print actions are disabled with an explanatory message and no print job is created
