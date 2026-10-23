# Laravel Seeding and Business-Rule-Compliant Cohort Simulation

**Research date:** 2026-10-04  
**Scope:** Laravel 11–13 seeding, factories, model lifecycle, action-driven fixtures, and NEXIAM exam/admission cohort simulation.

## Executive summary

A seeder is an execution entry point, not a privileged alternative to the domain. For reference catalogs and inert scaffolding, Eloquent `updateOrCreate`, factories, and carefully chosen bulk writes are useful. For meaningful lifecycle transitions—submission, acceptance, exam assignment, advised program pools, enrollment—seed through the same application/domain actions that enforce production rules. Factories generate valid starting models and useful attribute variations; they do not, by themselves, prove that a multi-entity workflow is valid.

`insert()` does **not** universally disable database constraints: configured database foreign-key, unique, check, and not-null constraints still apply. The actual concern is that a query-builder insert skips Eloquent model instantiation and its casts/mutators, model events, model-level conveniences, and application-level business rules. A room's capacity is not generally enforced by a foreign key; a row insert can remain referentially valid while creating an over-capacity session. Encryption casts are especially important: a plain value written to a column expected to contain ciphertext is not encrypted by the cast unless the model is used.

NEXIAM already has a sensible high-volume pattern: use `CohortPlanner` to calculate assignments from current remaining capacity and eligibility, and `CommitCohortDistribution` to revalidate under the planning lock/transaction, then bulk-insert assignment rows with session-version updates and audit data. This is intentionally different from an unguarded raw data dump. `AssignApplicantToExamSession` is the appropriate single-assignment path; 700–1,000 repeated calls are usually the wrong cohort-level orchestration if the commit pipeline is available.

For 700–1,000 candidates with 40-seat room/session capacity, plan for at least 18–25 **session slots** (700: 17 full and 20 in the 18th; 1,000: 25 full), with one room assigned to each concurrent session and distinct times if reusing rooms. If capacities are below 40, calculate from the actual sessions' capacities, not the headcount estimate. The planner already derives availability from `Room::capacity` and active assignment state.

## 1. What Laravel provides—and what it does not

Laravel 11, 12, and 13 all document seed classes in `database/seeders`, `run()` as the entry point, container-resolved `run()` dependencies, calls to other seeders, query-builder inserts, and factories. Laravel intentionally permits both direct inserts and model factories; the framework does not require all seed data to pass through a business service. Factory `create()` persists through Eloquent's `save()`. Factories support reusable states, relationship declarations, sequences, and `afterMaking` / `afterCreating` callbacks. Those mechanisms are first-party fixture-construction tools, not replacements for an application's lifecycle invariants.

The same docs also make two critical behaviors explicit:

- Laravel automatically disables mass-assignment protection during seeding and factory creation. This is convenient but means a factory/seeder attribute array should be treated as trusted, hand-authored input—not copied from a request or uncontrolled fixture. Mass-assignment protection is not a substitute for domain validation.
- Laravel allows seeders to use `WithoutModelEvents`. It is opt-in. If application behavior depends on a model observer/event, muting events can omit that behavior even when Eloquent factories are used.

### Raw `insert()` versus Eloquent `create()`

`DB::table(...)->insert(...)` and `Model::query()->insert(...)` are batch-oriented database writes. They don't create one model object per row; consequently they do not run each model's `saving` / `creating` / `created` / `saved` lifecycle, model casts/set mutators, observers, or model-specific behavior. Use of Eloquent's query builder does not make a bulk `insert()` equivalent to calling `Model::create()` repeatedly.

The distinction matters for encrypted attributes: Laravel's `encrypted` cast encrypts on model attribute assignment and decrypts on access. Supplying plaintext directly to an insert bypasses that cast. Likewise, timestamps or derived attributes normally filled by application/model behavior may need explicit values in a bulk row. Conversely, an insert is not intrinsically invalid: SQL/database constraints are enforced as configured, and a bulk insert can be the right implementation *inside* a validated domain operation.

Application invariants go beyond database integrity. A valid `room_id` foreign key doesn't demonstrate that the session has available seats, that the candidate is exam-eligible, that the candidate has no other active assignment, or that all concurrent schedulers observe the same capacity. Those checks require application-level validation, coordination/locking, and an atomic commit unless the database itself has an explicitly designed constraint for them. Do not claim that raw insert disables foreign keys; distinguish bypassed Eloquent behavior from retained database constraints.

## 2. Seeder design: use the right tool for each layer

### Reference and baseline data

Use deterministic idempotent seeders for institutional catalogs, program references, configuration, roles, and other baseline records. Prefer natural keys and `updateOrCreate` where re-running is intended to converge rather than duplicate. A factory is appropriate when the dataset is intentionally generated or varied (fake identities, independent test records, workload volume), and should expose named states that correspond to meaningful and internally consistent fixture conditions.

### Action-driven lifecycle fixtures

A domain/action seed workflow should:

1. Establish prerequisites in dependency order (institution/cycle, catalog/configuration, required staff actors, source applicants, session capacity).
2. Create uncomplicated models via factories or a narrow creation action, with deterministic IDs/codes and repeatable random seeds where reproducibility matters.
3. Move each example through domain actions for business-significant states. Pass real domain models/actors and stable per-record idempotency keys; never fabricate status fields to imply a transition that bypasses required behavior.
4. Build workflows as named fixture profiles or stages (e.g. accepted exam candidate, result-ready consultation candidate, advised/enrolling/enrolled), so each stage has an intelligible set of preconditions and outcomes.
5. Wrap a logically atomic seed operation in a transaction. Avoid a transaction around an enormous entire database if it creates lock/rollback costs; also avoid committing each row independently where partial cohort visibility violates the use case.
6. Finish by asserting visible outcomes: expected counts, no duplicates, no over-capacity sessions, all eligible candidates accounted for as assigned/unassigned, correct distributions/audit records, and no invalid lifecycle states.

Seeder calls to actions are valid even when the user-facing application normally has an HTTP controller in front. The action accepts typed domain inputs rather than needing an HTTP request. Laravel resolves `run()` dependencies through the service container. Keep HTTP request parsing/response formatting in the adapter/controller; use the application/domain action for the operation. An invokable/single-action controller is a Laravel routing style, not a requirement that the domain action itself be an HTTP controller. ADR's original formulation places application interaction in Action / domain behavior in Domain / HTTP response in Responder; the useful seeding implication is to reuse the domain/application operation, not call a controller or manufacture an HTTP response from a seeder.

### Factories, states, sequences, and callbacks

- **Factory states** represent reusable attribute-level variants: e.g. active/disabled room, accepted applicant, or capacity class. Make a state internally consistent, but keep multi-step lifecycle policy out of a long opaque state chain.
- **`sequence()` / `Sequence`** gives deterministic round-robin values across a factory count. It can vary a known attribute set (for example, distribute synthetic profiles across the 11 program codes or deterministic names). It does not enforce a maximum count per program or room, nor validate the selected program's eligibility; for quota-like or constrained allocation, compute counts explicitly and assert them.
- **`afterCreating()`** is appropriate for supplementary fixture relationships that require a persisted parent/ID (or a small, uncomplicated child fixture). For a domain transition, call a domain action explicitly in the seeder or a named fixture builder so failures, actor identity, inputs, and order remain visible. Avoid hidden nested action cascades in a generic model factory; those create unexpected side effects in tests and make alternate states difficult to reason about.
- **Eloquent model events** may still fire with factories unless muted; don't rely on an event observer as the only clear declaration of a demo workflow. `WithoutModelEvents` should be used only where suppressing those effects is deliberate and safe.

### Transactions and throughput

For rule-sensitive work, put reads/checks/writes that must agree in one transaction and acquire locks in a consistent order. Laravel's transaction closure rolls back on exception; the framework supports retry attempts for deadlocks. A transaction alone does not provide the correct invariant if validation happened before the transaction or concurrent writers do not share the same lock protocol.

Batch at the domain boundary. For a large cohort, chunking factory creation or independent applicant bootstrap can control memory/query cost, but do not split a single logical assignment commit into separately visible chunks unless the product explicitly permits partial cohorts and a resumable orchestration contract exists. Build the plan once from a stable candidate/session snapshot; validate or lock that snapshot; then insert assignment rows in optimized batches and commit audit/version changes atomically. If retry/resume is required, make it explicit with a stable run identifier/idempotency strategy and staged/terminal state rather than silently re-running a non-idempotent fixture.

## 3. Comparison

| Concern | Raw bulk insert / query builder | Eloquent factories | Domain action pipeline |
|---|---|---|---|
| Throughput | Highest; one set-based write can avoid per-model work. | Lower for very large graphs; creates and saves models, callbacks and relationships add work. | Depends on action granularity; per-record actions may be expensive, while a validated batch action can be efficient. |
| Eloquent casts, mutators, events | Bypassed for inserted records. | `create()` calls model `save()`; casts and normal model events run unless events are muted. | Uses whatever Eloquent operations the action defines; actions can also deliberately batch internal writes. |
| Database constraints | Retained when enabled in the database; invalid FK/unique/check values fail. | Retained. | Retained. |
| Domain invariants | Only if the caller explicitly computes/checks them; insert doesn't infer room capacity or lifecycle eligibility. | Factory attributes alone don't enforce workflow invariants. | Best place to apply operation-specific validation, locking, authorization policy, idempotency, audit and state transition rules. |
| Ciphertext and derived fields | Caller must provide correct stored representation. | Eloquent casts/mutators can transform attributes. | Action can validate inputs and persist the values via correct model behavior. |
| Suitable use | Truly simple reference rows, prevalidated batch writes, or a domain action's internal optimized commit. | Synthetic starting records, isolated test models, one-to-many fixture shape. | Business state changes and cross-record invariants; large batches where a purpose-built commit action validates before bulk persistence. |
| Main risk | Fast creation of semantically corrupt states; lifecycle hooks/casts silently omitted. | Mistaking structurally plausible rows for a valid lifecycle; hidden callback/event side effects. | Overusing one-item actions for workloads better expressed as an atomic planner/commit action; requires genuine production prerequisites/actors. |

## 4. NEXIAM findings and recommended cohort workflow

### Existing scheduling behavior

The current source already demonstrates the required split between planning and persistence:

- [`CohortPlanner`](../app/Scheduling/CohortPlanner.php) orders target sessions by scheduled time then ID, derives each room's physical capacity and `AssignmentInvariant::remainingCapacity()`, and calculates a bounded assignment plan. It supports FCFS, alphabetical, and program candidate order, plus sequential and balanced capacity packing. It returns assigned and unassigned attempt IDs, session mappings, capacities, and a deterministic plan fingerprint/hash. It plans; it does not persist assignments.
- [`AssignmentInvariant`](../app/Scheduling/AssignmentInvariant.php) centralizes candidate/session rules and remaining capacity. Use its definition as authoritative rather than computing `Room::capacity - rows` independently in fixture code.
- [`AssignmentPlanningMutex`](../app/Scheduling/AssignmentPlanningMutex.php) executes within a database transaction and uses a PostgreSQL transaction-scoped advisory lock by domain and academic cycle. This coordinates the planning operation on that driver; session/attempt row locks and invariant checks remain part of commit correctness.
- [`CommitCohortDistribution`](../app/Scheduling/Actions/CommitCohortDistribution.php) is the cohort-level commit: it authorizes actor/domain, takes the planning mutex, locks sorted target sessions, checks expected session versions and session eligibility, obtains the candidate pool, recomputes and hash-checks the plan, locks planned attempts, rechecks eligibility and active assignments, then inserts the assignment rows in bulk. The operation also advances touched session versions and records a batch audit event before the mutex transaction returns. This is a controlled, high-throughput bulk write inside the domain action—not an unvalidated direct seeder dump.
- [`AssignApplicantToExamSession`](../app/Examination/Actions/AssignApplicantToExamSession.php) is the per-attempt manual assignment path. It shares the planning mutex, locks the session and attempt, checks manual assignment invariants, detects/reuses an existing assignment or creates one, and records the appropriate audit event. Use it for one-off/manual demo cases; repeated hundreds of times can add avoidable transaction/lock/audit overhead and isn't a substitute for the cohort commit operation.

**Recommendation:** Have cohort simulation seed the cohort's upstream state, create and configure all exam sessions, obtain/preview the eligible pool, run `CohortPlanner` or the existing preview action, then commit using `CommitCohortDistribution` with its current plan hash, session versions, strategy, domain, and seeded actor. Do not call the commit action with made-up current versions or skip the candidate pipeline; build a fresh plan from the seeded dataset. Seeders may call the action via the container, but must supply the capabilities/actor and status preconditions it expects. If a demo-only capability is needed, seed an authorized staff identity in the normal baseline order; do not bypass authorization by directly inserting `ExamAssignment` rows.

For one or a few handpicked examples, call `AssignApplicantToExamSession` directly. Do not call the single-item action 700–1,000 times unless simulating individual staff operations is itself the test intent. The planner's batch action is the existing NEXIAM design for cohort-wide assignment.

### 700–1,000 candidates and room/session scaling

A session maps to a single `Room` in the current `ExamSession` model; capacity belongs to that room, while the target-session slot is what consumes capacity. Configure real `Room` models with the actual intended 24–40 capacity values and create as many scheduled sessions as needed. A room can be reused for a later non-overlapping time slot; don't schedule the same room concurrently. Target each room/session once per schedule window, and let the planner respect current active assignments and remaining seats.
A useful baseline distinction: the existing [`CohortDistributionDemoSeeder`](../database/seeders/CohortDistributionDemoSeeder.php) creates only two 40-capacity rooms, four exam sessions, and ten exam candidates. Its sample preferred-program codes include `BSCS` and `BSEE`, which are not in either the benchmark's 11-code list or `DepartmentSeeder`'s current catalog. Treat this as a small scheduling demo fixture, not a 700–1,000-applicant canonical catalog example; a scaled simulation should generate candidates only from the selected catalog and provision target sessions from calculated capacity.


For 40-capacity session slots:

| Eligible cohort | Minimum slots | Example balanced occupancy |
|---:|---:|---|
| 700 | 18 | 17 sessions × 39, plus 1 × 37 (or 17 × 40 plus 1 × 20 sequentially) |
| 800 | 20 | 20 × 40 |
| 1,000 | 25 | 25 × 40 |

Counts are `ceil(applicants / capacity)`. The 700/800/1000 examples assume all slots are 40; if actual session capacities are 24, 30, or mixed, calculate from their sum. For example, 700 at 24 requires 30 slots; at 30 requires 24 slots. Give each concurrent slot its own room and unique time; a smaller number of rooms can be reused in later periods. With already assigned candidates or reserved capacity, plan against **remaining** capacity; add sessions when planner output reports unassigned candidates. Don't solve a shortfall by expanding `Room::capacity` or disabling the invariant.

Use `PACKING_BALANCED` when the aim is even room loads, and `PACKING_SEQUENTIAL` when the desired operational policy fills earlier sessions first. The planner balances counts, not academic programs: if occupancy needs program-level separation or accommodations, encode that intentionally in candidate ordering/session pools and validate the resultant capacity, rather than assuming `balanced` means balanced per program. A single exam sitting may legitimately contain multiple program candidates; do not create one overloaded “program session” merely to make demo records easy to find.

### Representing the 11-program Tagudin benchmark cohort

The repository has two related but non-identical lists that must not be confused:

- [`AdmissionBenchmarkSeeder`](../database/seeders/AdmissionBenchmarkSeeder.php) labels its calibrated ladder as an “Authentic 11-Program” benchmark and defines these 11 codes: **BSIT, BSEd, BEEd, BPEd, BSBA, BSEntrep, BSMath, BA-EL, BA-Psych, BA-SocSci, BPA**.
- [`DepartmentSeeder`](../database/seeders/DepartmentSeeder.php) currently seeds a broader 15-entry department/program structure: CTE includes BSEd plus BSEd-Eng and BSEd-Math, CBME includes BSBA and BSEntrep, and CAS includes BSMath, BA-EL, BA-Psych, BA-SocSci, BSIT, BPA, BSN, and BSPSY.

For the requested **11-program benchmark simulation**, use the stable codes and college grouping present in the current seed data: CTE = BSEd, BEEd, BPEd; CBME = BSBA, BSEntrep; CAS = BSMath, BA-EL, BA-Psych, BA-SocSci, BSIT, BPA. This maps the benchmark's 11 entries without inventing two BSEd majors or conflating BA-Psych with distinct BSPSY. Exclude BSEd-Eng, BSEd-Math, BSN, and BSPSY from that specific 11-code benchmark mix unless the intended sample explicitly changes. The 15-entry department catalog is a real repository discrepancy relative to the benchmark subset, not evidence that one list should silently overwrite the other; keep the simulation's cohort profile explicit and preserve the program catalog records.

Across all sessions, generate program volumes at the **cohort** level, then distribute candidates through the planner. Use deterministic sequence/explicit quotas to guarantee representation and a documented mix, not unconstrained Faker randomness; avoid making any session a proxy for a single college/program. No source reviewed establishes required equal representation or quotas among those programs, so use either a clearly labeled synthetic/equal distribution or a workload profile calibrated to the benchmark; do not present invented counts as authentic applicant statistics. If quotas are configured in NEXIAM, honor them through the quota/domain actions rather than treating an exam schedule's free seat as a program reservation.

### Guidance pools and enrolled state are separate workflows

The admission actions are not generic shortcuts for populating exam cohorts:

- [`SetGuidanceProgramPool`](../app/Admission/Actions/SetGuidanceProgramPool.php) requires the attempt to be accepted, its result released, and consultation advice confirmed; it rejects changes after enrollment starts. It validates program codes/default-option consistency, uses attempt/program-quota locking and `QuotaReservation` checks, supports idempotency, and creates a versioned pool, options, selection/reservation effects, and audit trail. Seed all of those prerequisites first and use a distinct stable idempotency key per attempt. Do not create `ProgramPool`/`ProgramSelection` rows directly to depict guidance behavior.
- [`StampAttemptEnrolled`](../app/Admission/Actions/StampAttemptEnrolled.php) requires an active `enrolling` stamp and no unresolved program follow-up, loads the final program, finalizes a standing selection as appropriate, creates an enrollment stamp with paper-reference/hash/verifier details, captures quota variance, and records audit. It is a final admission/enrollment state, not part of exam scheduling. To demonstrate it, seed the actual prior transitions (including the enrolling state) and use a deterministic but synthetic paper-evidence reference/hash explicitly identified as demo data.
- [`QuotaReservation`](../app/Admission/Support/QuotaReservation.php) locks quota rows in ascending program ID, counts distinct nonterminal selections and active enrollment stamps, fails closed for missing/full quotas on reservation, and exposes quota variance. It explains why creating program/selection/stamp rows with raw inserts can make quota projections and future choices incorrect even when foreign keys are valid.

The repository's staged seeders demonstrate the desired action reuse: `app/Testing/Seeders/Stages/SeedProgramStage.php` uses admission/exam actions, while `SeedEnrollmentStage` composes result release, consultation decision, guidance-pool creation, and enrollment-stamp actions. Use a similar named stage/action pipeline for a large synthetic fixture rather than embedding one-off SQL across a generic seeder.

## 5. Practical decision rule

- **Reference/static row?** Deterministic Eloquent upsert/create; raw bulk insert is acceptable for simple rows if its stored fields are explicit and database constraints are active.
- **Large, uncontroversial synthetic starting set?** Factory `create()` with named states/sequences; callbacks only for simple related fixture creation.
- **Domain state change or invariant across rows?** Call the domain action.
- **Large cohort assignment?** Plan with the domain planner, then use the existing locked, revalidated, audited batch commit action. An optimized `insert()` belongs inside that action after its guards, not in the seeder before or instead of it.

## Sources

### Laravel first-party documentation

- [Laravel 11: Database Seeding](https://laravel.com/docs/11.x/seeding) — seed classes, allowed query-builder and factory choices, container-resolved dependencies, muting events.
- [Laravel 11: Eloquent Factories](https://laravel.com/docs/11.x/eloquent-factories) — factory states, callbacks, `create()` through `save()`, sequences and relationships.
- [Laravel 12: Database Seeding](https://laravel.com/docs/12.x/seeding) and [Factories](https://laravel.com/docs/12.x/eloquent-factories).
- [Laravel 13: Database Seeding](https://laravel.com/docs/13.x/seeding) and [Factories](https://laravel.com/docs/13.x/eloquent-factories).
- [Laravel 13: Eloquent events and model lifecycle](https://laravel.com/docs/13.x/eloquent#events).
- [Laravel 13: Attribute casting / encrypted casts](https://laravel.com/docs/13.x/eloquent-mutators#encrypted-casting).
- [Laravel 13: Database transactions](https://laravel.com/docs/13.x/database#database-transactions) and [database operations](https://laravel.com/docs/13.x/database#running-queries).
- [Laravel 13: Single-action controllers](https://laravel.com/docs/13.x/controllers#single-action-controllers).

### Pattern primary source and NEXIAM implementation evidence

- Paul M. Jones, [Action-Domain-Responder original project](https://github.com/pmjones/adr) — separates HTTP Action, Domain entry point/logic, and HTTP Responder; seeding uses the domain entry point and has no responder.
- [CohortPlanner](../app/Scheduling/CohortPlanner.php), [AssignmentInvariant](../app/Scheduling/AssignmentInvariant.php), [AssignmentPlanningMutex](../app/Scheduling/AssignmentPlanningMutex.php), [CommitCohortDistribution](../app/Scheduling/Actions/CommitCohortDistribution.php), and [AssignApplicantToExamSession](../app/Examination/Actions/AssignApplicantToExamSession.php).
- [QuotaReservation](../app/Admission/Support/QuotaReservation.php), [SetGuidanceProgramPool](../app/Admission/Actions/SetGuidanceProgramPool.php), and [StampAttemptEnrolled](../app/Admission/Actions/StampAttemptEnrolled.php).
- Local cohort/catalog examples: [CohortDistributionDemoSeeder](../database/seeders/CohortDistributionDemoSeeder.php), [DepartmentSeeder](../database/seeders/DepartmentSeeder.php), [AdmissionBenchmarkSeeder](../database/seeders/AdmissionBenchmarkSeeder.php), and `app/Testing/Seeders/Stages/SeedProgramStage.php` / `SeedEnrollmentStage.php`.
