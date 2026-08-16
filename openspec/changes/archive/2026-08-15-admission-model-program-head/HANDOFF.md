# Handoff — Admission Support, Program Choice, and Program Head Interface

**Status:** ACTIVE — implementation started 2026-08-15 after founder promotion (OMR layout-solver archived HANDED OFF, commit `e586e86`). Backend + UI shipped; Phase 9 evidence in progress.

## Authority and start gate

- Change: `2026-08-15-admission-model-program-head`
- Decisions: D-087, D-ROLE-05, D-080, D-089, D-090, D-091, D-092, D-093
- Sole active implementation change; this change owns `system/` and `DatabaseSeeder`.
- OMR follow-up work (layout-solver closeout) must not edit the tree while this is active.

## Reset and demo data

```bash
cd system
php artisan foundation:reset --force          # fresh schema + base seeders
php artisan db:seed --class=Database\\Seeders\\AdmissionSupportDemoSeeder
npm run build                                  # frontend assets
```

`AdmissionSupportDemoSeeder` (idempotent) builds three released+advised journeys:

| Scenario | Applicant | State |
|---|---|---|
| Normal advised-default route | `demo.santos@nexiam.test` (Maria Santos) | pool v1 → default ack → `enrolling` → `enrolled` (paper BSN) |
| Enrolling + open PH follow-up | `demo.cruz@nexiam.test` (Juan Dela Cruz) | pool v1 → `enrolling` + open targeted follow-up (assigned PH must resolve) |
| Paper-different final program | `demo.reyes@nexiam.test` (Ana Reyes) | pool v1 → `enrolling` → `enrolled` with final program BSPSY (differs from provisional BSN; quota variance recorded) |

Applicant portal access uses the access-key challenge (no password): log in at `/applicant/login` with the applicant email + birthdate `2001-01-01`. Access keys are printed by the intake flow (`reference_code` + key are visible to staff on the admission detail page).

## Staff credentials (from StaffIdentitySeeder, password `password`)

| Role | Email | Scope |
|---|---|---|
| Guidance Counselor | `guidance.counselor@nexiam.test` | pool + advised default, consultation, follow-up open, threads |
| Registrar Clerk | `registrar.clerk@nexiam.test` | enrollment eligibility + `enrolling`/`enrolled`/reverse stamps |
| Program Head BSN | `program.head.bsn@nexiam.test` | `/staff/programs` dashboard, tiered records, follow-up resolve, decline |

## Feature URLs (UX revision 2026-08-15)

| Surface | Route | Notes |
|---|---|---|
| Consultation DASHBOARD | `/consultation` | stat tiles (today's scheduled / pending advise / not-yet-scheduled / sessions open-closed), scan + search quick actions, today's schedule grouped by consultation SESSION (time range + assigned applicants) |
| Shared admission-support inner page | `/consultation/applicants/{attempt}` | ONE page per applicant: Guidance tabs (Overview/Assessment/Prefs/Pool/Follow-up) + PH tabs (Record/Threads/Follow-up) + Models tab; progress base shaped per office |
| Models visualization slots | Models tab | 2 reserved slots (YuKee-compatible / deterministic rule-based) with truthful `no_provider` states; engines deferred |
| PH dashboard | `/staff/programs/{code}` | metrics + lists + quota; applicant rows open the shared inner page (redirect) |
| Scan → action modal | `/staff/scan` | resolve shows a modal of role-filtered actions; choosing one navigates |
| Applicant QR | `/applicant/qr` | PERMANENT code (deterministic HMAC, never expires/regenerates); print recommended; same code at scan/registrar/consultation front desk |
| Applicant program choice | `/applicant/program-choice` | acknowledge default, select/transfer/cancel within pool |
| Registrar eligibility | `/registrar/enrollment-eligibility` | dedicated status-check + stamp interface (Registrar is NOT on the shared inner page) |

## Key backend contracts

- Actions: `SetGuidanceProgramPool`, `SelectApplicantProgram`, `AcknowledgeAssistedProgramSelection`, `TransferApplicantProgramSelection`, `CancelApplicantProgramSelection`, `OpenProgramFollowUp`, `ResolveProgramFollowUp`, `DeclineApplicantProgramSelection`, `SendAdmissionSupportMessage`, `MarkAdmissionSupportMessageRead`, `StampAttemptEnrolling`, `StampAttemptEnrolled`, `ReverseEnrollmentStamp`.
- Lock protocol: attempt root → stamp/pool/selection/follow-up rows → quota rows by ascending program id; `CommandIdempotency` ledger (actor+command+aggregate namespace, canonical request hash; same-key replay returns original, cross-payload conflicts with no mutation).
- Partial unique indexes: one non-terminal selection per attempt, one open follow-up per attempt/program (created after the SQLite FK-rebuild; see migration comment).
- QR: deterministic permanent opaque locator (HMAC over attempt id — the same code forever); payload carries no name/scores/action/destination; validity is server-side cycle state; scan never mutates and opens an action modal.
- No engine: analysis region truthfully reports `no_analysis`/`result_not_ready`; YuKee/K-Means + deterministic/rule-based plugins remain separate deferred changes.

## Tests

- `tests/Feature/Admission/ProgramSelectionTest.php` — pool/default, ack, transfer, quota FCFS fail-closed, enrolling block, idempotency replay + cross-payload conflict.
- `tests/Feature/Admission/AdmissionSupportWorkflowTest.php` — follow-up clear/decline, enrolled blocked by open follow-up, direct decline while enrolling, reverse restores selection, support query projection, PH lists, QR verify/expiry, Tier A/B audit.
- `tests/Feature/Authorization/AdmissionSupportCapabilityTest.php` — capability rows, role grants, cross-program scope denial.

## Known gaps / next

- Result-sheet printing completion, E2 exit evidence G2–G4/H4, legacy parity ledger, and pre-existing suite-parity restoration remain open (project context).
- PH Tier B applicant-support messaging UI (threads) has backend actions + routes; the applicant thread page renders messages; internal-thread creation UI is minimal (route present).
