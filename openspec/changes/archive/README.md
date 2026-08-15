# Archived OpenSpec changes

Completed foundation changes archived so the active `openspec/changes/` tree stays clear for the next implementation change.

| Archive folder | Outcome |
|---|---|
| `2026-08-05-e0-greenfield-foundation` | E0 PASS — scaffold/runtime/quality baseline |
| `2026-08-05-e1-identity-reference-foundation` | E1 PASS — identity/capability/reference |
| `2026-08-05-e1b-svelte-ui-kit` | E1b nearly locked — Svelte UI kit + Nexi chrome; J4 Stitch parked |
| `2026-08-05-e2-connected-admission-release-slice-INCOMPLETE` | Parked incomplete — journey code kept |
| `2026-08-05-staff-personnel-lifecycle` | PASS — HR personnel lifecycle |
| `2026-08-06-admission-attempt-detail` | PASS — P-REG-02 detail, corrections, post-accept amend/reconsider |
| `2026-08-06-institutional-setup-readiness` | PASS — Setup hub + shared catalogs + Program quotas (D-087) |
| `2026-08-10-exam-session-scheduling` | PASS — Option A session `booking_mode`; applicant Reserve→Assignment; SlotPlan removed |
| `2026-08-10-ai-assistant-foundation` | PASS — Nexi degraded shell + SurfaceProfile/PageCapabilityPack + `NexiSessionView`; pack-as-lens AD-3 |
| `2026-08-10-ai-capability-kernel` | PASS — Capability Registry/Kernel + `setup.readiness.read@1` HTTP parity |
| `2026-08-10-nexi-run-and-usage-governance` | PASS — conversations/runs/SSE/usage/TaskGrants + FakeAiGateway |
| `2026-08-10-nexi-knowledge-governance` | PASS — AD-25 knowledge lifecycle + FakeKnowledgeIndexPort; no live embeddings |
| `2026-08-10-ai-provider-live-spike` | PASS — laravel/ai v0.10.3 behind AiGateway; default fake |
| `2026-08-10-nexi-policy-inquiry-rag` | PASS — policy Inquiry retrieve/cite/refuse; local lexical index |
| `2026-08-10-nexi-applicant-inquiry` | PASS — applicant Inquiry policy + own-data; isolation/clear |
| `2026-08-10-nexi-exam-session-assistant` | PASS — staff exam Assistant skill allowlist+engine; shared `guidance.exam.schedule` gate |
| `2026-08-10-exam-proctor-duty-assign` | PASS — session Show proctor assign/revoke; D-015/D-022; eligible clerk/support |
| `2026-08-10-ta-capability-delegation` | PASS — TA set grant/revoke via `guidance.ta.delegate`; unified Staff page; rank hierarchy + password ceiling + profile page (founder locks) |
| `2026-08-11-applicant-choose-slot` | PASS — Nexi applicant slot skill `applicant.choose_slot@1`: kernel rows `exams.slots.list@1`/`exams.slot.reserve@1`, pack `applicant.schedule@1`, propose→confirm→`ReserveExamSlot` (8 tests / 93 assertions) |
| `2026-08-11-grading-workflow` | PASS — E2 manual grading core: idempotent open, roster derivation, transactional multi-domain `RecordScores`/`ClearScores`, roster×domains finalize completeness + version lock + immutable-after; `GradingDemoSeeder` (19 tests / 261 assertions incl. GF-06 open preconditions amendment) |
| `2026-08-11-grading-roster-eligibility` | PASS — attendance + submission gate the grading roster (`GradingRoster` support class); close guarded on attendance completeness; absent/no-submission excluded from scoring (GF-06 §5.2); dashboard + session stats (22 tests / 280 assertions in GradingWorkflowTest) |
| `2026-08-11-notifications-delivery-outbox` | **DEFERRED (parked 2026-08-11)** — founder priority shifted to scoring conversion; design/tasks preserved, zero code shipped; revisit after scoring + OMR cuts |

Next: **scoring-conversion ACTIVE 2026-08-11** — confidential conversion-table engine (guidance-locked; normalized/formula REJECTED; encrypted at rest; `notifications-delivery-outbox` parked DEFERRED above). Then **OMR scoring interface** (uses the `ScoreConverter` seam + OMR photo encryption design), **result-sheet printing pipeline** (separate from release/consultation), then **notifications + release completion**, then E2 exit evidence G2–G4/H4 + legacy parity ledger. Known pre-existing suite failures (Nexi availability + capability catalog parity) recorded in `grading-workflow-2026-08-11.md` evidence remain open.
| `2026-08-11-exam-navigation-organization` | PASS — exam navigation organization (archived 2026-08-15) |
| `2026-08-12-consultation-plan-aggregate` | PASS — consultation plan aggregate (archived 2026-08-15) |
| `omr-configuration-usability` | **PASS** (36/36) — OMR config hub + preview==print + overlays + booklet authoring v2 + builder v2 + retirement panels + birthdate identity (archived 2026-08-15) |
| `omr-scoring-capture` | **PARKED** (57/98) at 2026-08-15 — superseded/absorbed by `omr-layout-solver`; in-flight task state preserved |
| `omr-configuration` | **PARKED** (18/24) at 2026-08-15 — superseded/absorbed by `omr-layout-solver`; in-flight task state preserved |
| `omr-multipage-answer-sheets` | **PARKED** (25/28) at 2026-08-15 — superseded/absorbed by `omr-layout-solver`; in-flight task state preserved |
| `omr-printing-pipeline` | **PARKED** (11/26) at 2026-08-15 — superseded/absorbed by `omr-layout-solver`; in-flight task state preserved |
