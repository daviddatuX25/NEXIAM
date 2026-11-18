## ADDED Requirements

### Requirement: Unified release reconciles the effective live system
The release process SHALL record a reviewed bill of materials for the selected Git revision, approved owner deltas, effective app/queue/maintenance source files after mount precedence, service images and nonsecret configuration fingerprints. It SHALL assign every material live/source difference a disposition before release, preserve already-live behavior, and exclude unapproved or held changes. Existing Git history and unrelated concurrent work SHALL NOT be reset, rewritten, cleaned or silently staged.

#### Scenario: A deployed fix is not in the candidate source
- **WHEN** an effective live source file or behavior differs from the candidate and has no approved disposition
- **THEN** candidate publication is blocked until the relevant owner reviews and resolves it
- **AND** the existing deployment and concurrent working trees remain unchanged

#### Scenario: A concurrent release changes the baseline
- **WHEN** the live manifest, service identity, effective source or other pinned precondition changes after candidate preparation
- **THEN** activation fails closed and the candidate is reconciled against the new baseline
- **AND** it does not restore an older release over the newer change

#### Scenario: An owner has unfinished local work
- **WHEN** shared files contain a concurrent owner's uncommitted or held change
- **THEN** the release includes only an explicitly approved, dependency-complete delta
- **AND** the owner's remaining work and all existing commits are preserved

### Requirement: One isolated, complete and behavior-verified release
A candidate SHALL have one versioned source identity and coherent frontend closure for its app, queue and maintenance services, with explicitly recorded service-specific configuration. Released source and dependencies SHALL not depend on mutable checkout binds; service-specific variants and model/worker inputs SHALL be explicitly pinned. The process SHALL build and exercise the candidate using isolated database, storage, ports, output and integration endpoints, preserving all relevant current live features and their owner-approved boundaries. The closure SHALL include imported assets and dynamically loaded worker, WASM and other required files; prior hashed assets needed by open tabs SHALL remain available through cutover.

#### Scenario: A required dynamic asset is missing
- **WHEN** a worker, WASM binary, manifest import or other required asset is absent or points to incompatible bytes
- **THEN** the candidate is not publishable
- **AND** a manifest-only successful build is not represented as full asset verification

#### Scenario: New build differs from incumbent hashes
- **WHEN** a coherent candidate intentionally produces different hashed frontend files
- **THEN** its complete closure and approved behavior are independently verified
- **AND** the process does not claim incumbent-byte parity solely because compilation succeeded

#### Scenario: The shared checkout changes after release
- **WHEN** another session modifies a host checkout file or dependency
- **THEN** the released services continue using the immutable pinned candidate
- **AND** their effective source and dependency hashes do not change

### Requirement: Fresh demo seed has an explicit verified starting state
Before demo replacement, the release SHALL provide a versioned, owner-approved seed manifest identifying exact target/environment, synthetic personas and scenarios, institutional/reference inputs, template/material dependencies and expected state. It SHALL distinguish recreated fixtures from historical operational records and identify current benchmark, OMR, applicant, credential and file state not recreated. Seed execution for proof SHALL use a new isolated PostgreSQL database and private storage; shared/live target selection SHALL fail closed absent a separately approved cutover.

#### Scenario: A current benchmark is not reproducible
- **WHEN** the selected seed manifest does not recreate a current benchmark, assessment or approved template
- **THEN** the gap is explicitly reported before replacement
- **AND** synthetic fixture values are not represented as the historical or institution-approved state

#### Scenario: Thirteen-course CMI defaults are prepared for testing
- **WHEN** the founder-directed synthetic test profile is assembled
- **THEN** the CMI owner's guarded, source-qualified 13-course defaults and 7,332-pair proposal are reconciled and exercised only on an explicitly selected isolated target
- **AND** draft profiles and relationship proposals remain labeled unreviewed and do not imply institutional validity, model activation, live benchmark overwrite or authorization for real admissions
- **AND** unsupported or unapproved contexts remain unavailable rather than receiving fabricated scores

#### Scenario: Catalog-aware starting state is requested
- **WHEN** the fresh demo requires schools, strands/tracks and previous-college courses
- **THEN** it uses the newer catalog's owner-approved, source-qualified data and cutover after authorization, identity and seed-readiness gates pass
- **AND** legacy suggestion lists are not silently presented as equivalent stable catalog records or used to merge source collisions

#### Scenario: Approved catalog choices are ready for the applicant
- **WHEN** the approved catalog-aware demo profile is seeded
- **THEN** the exact owner-approved schools, strands/tracks and previous-college-course entries are selectable where applicable through their intended applicant paths and persist their required identity/version snapshots
- **AND** approved lifecycle actions, not source membership alone, determine which records are active; unreviewed entries remain inactive
- **AND** school free text/address, Other text, SHS/transferee/ALS distinctions and source-qualified collisions remain supported under the owning contracts

#### Scenario: Catalog security or cutover is unfinished
- **WHEN** catalog grant provenance, shared writer locking, PostgreSQL seed readiness or applicant identity integration remains unapproved
- **THEN** the catalog-aware replacement is held rather than exposing unsafe routes, bypassing seeder guards or silently substituting legacy behavior

#### Scenario: The institution-only baseline is selected
- **WHEN** the owner-approved institution-only demo-personnel profile is seeded
- **THEN** its reviewed configuration and clearly fictional staff are created with no operational pipeline records
- **AND** a separate scenario-filled demo is not introduced unless its owning contract explicitly authorizes it

#### Scenario: A seed targets the shared or live database during proof
- **WHEN** a private proof is configured with an unexpected database, storage root, runtime or environment
- **THEN** it refuses before performing a seed, migration or file write

### Requirement: Final handoff qualifies both current domain and private OVH
The release SHALL preserve the current public service while preparing a separately isolated OVH candidate. A fresh-applicant starting state SHALL be reconciled against an explicit retained/replaced record and file disposition, including staff, approved institutional configuration, current TAM responses, custody and keys. A separate exact-target approval and tested current paired recovery are required before production replacement or domain cutover.

#### Scenario: The target or preservation boundary is not established
- **WHEN** target identity, current source, writer closure, fresh-applicant disposition or a protected data dependency is unresolved
- **THEN** the release remains private and does not reset, overwrite, publish or switch the domain

#### Scenario: Historical staging is available
- **WHEN** an older OVH recovery snapshot exists
- **THEN** it is treated as a historical proof, not a current synchronized production candidate or current recovery point

### Requirement: CMI batch qualification is explicit and bounded
The release SHALL demonstrate all 13 course defaults and a multi-pair CMI preparation workflow in an isolated synthetic target, using ordinary preview, confirmation, durable claims and the dedicated worker. Synthetic/unreviewed content, predictor provenance, execution rights, unsupported contexts and academic approval SHALL remain distinguishable.

#### Scenario: Preparation is verified
- **WHEN** authorized synthetic pairs are explicitly prepared
- **THEN** acceptance observes worker execution, persisted outcome, duplicate/stale/denied behavior and interrupted-command recovery
- **AND** no read, seed or health check silently enqueues work or claims a real-model result

### Requirement: Production promotion and recovery are reproducible and guarded
CI SHALL verify the selected source and build an immutable release artifact; staging SHALL use isolated credentials and resources. Production promotion SHALL require an explicit reviewed target and approval, current preimages, complete writer fencing, paired recovery proof and a tested rollback path. The handoff SHALL identify commands, roles, secrets custody, monitoring, retention, recovery objectives and verified starting states without distributing credentials.

#### Scenario: A database-only backup is offered for a storage-backed release
- **WHEN** the backup omits matching application storage, current TAM data, required key identity or protected custody
- **THEN** it is not accepted as a complete recovery point for production cutover

#### Scenario: Automation encounters a stale generation
- **WHEN** a target, source, artifact, configuration, worker or data precondition differs from the reviewed plan
- **THEN** automated promotion fails closed without resetting data, bypassing guards or restoring a stale generation


### Requirement: Photo use remains conditional on owner-approved current assent
The unified release SHALL NOT activate photo print-use without the ICAT owner's approved purpose disclosure, versioned policy, current-revision assent and deliberate sanitized-preview selection. For existing applicants whose original consent predates that purpose, photo-use eligibility SHALL require an implemented, accepted owner-approved re-assent journey that preserves the original consent receipt; otherwise the affected photo-use path SHALL remain truthfully unavailable. Synthetic proof does not grant real-data, provider-transfer or retention approval.

#### Scenario: Existing applicant has an older privacy receipt
- **WHEN** an existing applicant's original assent predates the photo-print purpose
- **THEN** the candidate either uses the accepted current-revision re-assent journey without overwriting the original receipt or clearly keeps the affected photo-use path unavailable
- **AND** uploading or selecting a preview alone does not establish current consent

### Requirement: Live demo replacement is exact-target and separately approved
Any live demo replacement SHALL revalidate the target app/database/storage identities, active writers, fresh data and file disposition inventory, owner approvals, capacity and an exercised recovery route before mutation. It SHALL show the founder exactly which demo data and files are to be removed and what the seed will not restore; explicit approval SHALL be bound to that scope. It SHALL NOT run the current blanket `foundation:reset` as a shortcut, mutate unrelated databases/projects or infer disposal authority for preservation-only custody.

#### Scenario: The eleven preservation-only objects remain unresolved
- **WHEN** the proposed replacement would erase, move or rewrite the 11 custody objects or their ledger without a separate authorized disposition or recovery binding
- **THEN** that replacement is blocked
- **AND** the objects, ledger and provenance are not silently deleted or relabeled

#### Scenario: Approved fresh-demo replacement completes
- **WHEN** the approved fresh-demo cutover completes
- **THEN** HTTP, queue and maintenance use the same accepted replacement database/storage binding and its starting state matches the approved seed manifest
- **AND** no service continues writing the superseded pair
- **AND** a binding or starting-state mismatch fails closed and invokes the rehearsed recovery procedure

#### Scenario: Live data changes after recovery rehearsal
- **WHEN** live data or custody state changes after an earlier recovery rehearsal
- **THEN** final recovery evidence is bound to the exact target and approved loss boundary at the writer-fenced cutover checkpoint, or cutover stops
- **AND** an older recovery point and its resulting loss require separate explicit disposition consistent with the storage contract

#### Scenario: A writer cannot be fenced
- **WHEN** app, queue, maintenance, direct database access or another consumer may mutate the target during paired recovery or cutover
- **THEN** the operation stops without resetting data or publishing the candidate

#### Scenario: Seed or migration fails after a destructive step
- **WHEN** an approved replacement encounters a migration, seed or activation failure
- **THEN** the process reports the actual state and uses only its previously verified, scope-appropriate recovery procedure
- **AND** it never reports success or silently invents a replacement for lost records

### Requirement: This cut excludes the external 3 TB drive
The current release SHALL use only currently available, approved storage and SHALL pass capacity gates without the declined external 3 TB drive. Future external staging or recovery requires a separate scope amendment and authorization, then verification of actual device/mount identity, available space, filesystem semantics, permissions, lifecycle, confidentiality and recovery behavior. An unmounted host drive or volatile memory filesystem SHALL NOT be treated as durable guest capacity. Formatting, mounting, moving existing data or changing drive contents requires separate explicit approval.

#### Scenario: Current capacity is insufficient
- **WHEN** this cut's capacity plan cannot meet staging, recovery and reserve requirements without the declined external drive
- **THEN** staging and cutover remain blocked
- **AND** the process neither attaches or formats that drive nor weakens the reserve

#### Scenario: The host drive is not visible in the VM
- **WHEN** the operating system running NEXIAM cannot see the proposed external drive as an attached device or mounted filesystem
- **THEN** the release reports the drive unavailable and does not claim its capacity
- **AND** no format, mount or host-configuration change is attempted automatically

#### Scenario: Filesystem semantics are unsuitable
- **WHEN** a candidate external filesystem cannot support the required ownership, locking, atomicity, access or durability guarantees
- **THEN** it is not used for that database, live volume or release operation
- **AND** another verified storage location is required

### Requirement: Obsolete artifacts are retired by exact reviewed disposition
After the integrated release and recovery boundary are verified, the operator SHALL identify obsolete release copies, evidence and backup artifacts by exact identity, size, owner, mount/reference and recovery dependency. The founder MAY approve individual cleanup; history alone SHALL neither require indefinite retention nor authorize deletion. The operator SHALL recheck current dependencies before removal and report actual reclaimed space.

#### Scenario: An old source copy is still mounted
- **WHEN** a supposedly obsolete copy is still referenced by a current container, active session or required rollback
- **THEN** it is not removed until its dependency is retired and disposition is approved

#### Scenario: Rebuildable artifacts are removed
- **WHEN** an exact no-longer-needed artifact is approved and removed
- **THEN** unrelated project data and protected Serbizyu resources remain untouched
- **AND** actual free-space change is reported separately from the artifact's apparent size

### Requirement: Authorized private OVH staging preserves the outage checkpoint
The founder-approved OVH staging cut SHALL use the verified purchased VPS and private recovery assets, new persistent database/storage resources, internal service networks and loopback-only HTTP. It SHALL preserve questionnaire questions, schema and answer contents independently from applicant starting-state decisions; use a disposable copy for synthetic collection proof; and leave public DNS, incumbent resources and parallel work unchanged. Historical queues, schedules, external notification/provider delivery and destructive storage maintenance SHALL remain fenced until explicitly safe. OMR is deferred.

#### Scenario: Existing questionnaire answers are restored
- **WHEN** the separate TAM SQLite snapshot is restored into the private target
- **THEN** its schema, record count and canonical content digest match the preserved source checkpoint without disclosing response content
- **AND** collection proof uses a separate disposable database rather than adding synthetic answers to the preserved research baseline

#### Scenario: The emergency snapshot lacks atomic paired proof
- **WHEN** database and file backups were captured without a common writer fence
- **THEN** staging reports that limitation and reconciles required identities/references before claiming storage growth or cleanup readiness
- **AND** it never treats copied custody identities or successful HTTP alone as healthy paired recovery

#### Scenario: A staged service could replay historical effects
- **WHEN** restored pending jobs, schedules or provider credentials could trigger historical external effects
- **THEN** they remain fenced and explicit proof uses an empty staging queue and disabled external delivery
- **AND** historical jobs and receipts are preserved rather than deleted to make the test pass

### Requirement: Live survey restoration is scoped and answer-preserving
The live TAM cut SHALL restore the existing introduction/assets and required-name behavior without changing survey items, routing, OVH or CMI work. It SHALL preserve SQLite contents in private writable storage, retain a checkpoint, require configured research-key authority for management/export/reset and refuse paused submissions before writes.

#### Scenario: Staff authentication is not research authority
- **WHEN** ordinary staff or the former default key requests management/export/reset without the configured research key
- **THEN** access is denied before reading or deleting answers
- **AND** configured research-key access remains available

#### Scenario: Collection is paused during activation
- **WHEN** a submission arrives while the trusted pause file exists
- **THEN** it receives temporary 503 before storage
- **AND** reopening follows preserved data and scoped public checks

#### Scenario: Recovery follows possible new submissions
- **WHEN** code recovery is needed after collection may have accepted answers
- **THEN** the current response store is retained and collection paused for compatible code recovery
- **AND** an older database is never restored over those answers by implication
