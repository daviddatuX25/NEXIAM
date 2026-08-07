# NEXIAM Project Context (permanent agent working rules)

**Status:** ACTIVE — permanent AI/agent implementation context  
**Entry point:** [`GREENFIELD-MASTER-REFERENCE.md`](GREENFIELD-MASTER-REFERENCE.md)  
**Product PRD:** [`prd.md`](prd.md) (PROMOTED 2026-08-05; E2 cut-line = Tier A + REQ-VS)  
**Active OpenSpec:** [`../openspec/changes/exam-session-scheduling/`](../openspec/changes/exam-session-scheduling/) (institutional-setup-readiness archived PASS 2026-08-06; admission-attempt-detail archived PASS; E2 parked incomplete under `openspec/changes/archive/`)  
**Phase roadmap:** [`MASTER-DELIVERY-ROADMAP.md`](MASTER-DELIVERY-ROADMAP.md)  
**Target code:** `system/`  
**Legacy evidence:** `legacy_codebase/application/` + `docs/legacy-reference/`

This file is the durable reminder of *how* we work. Read it with the master reference at the start of every non-trivial task.

## 1. What authorizes work

| Layer | Role |
|---|---|
| Master reference | Where we are, blockers, stop rules |
| Master roadmap / GF-14 | Phase gates (E0→E1→E2→E3) |
| GF contracts | Product/domain/authz/UX/ops design |
| **One active OpenSpec change** | Only authorized code work |
| Tests/runtime evidence | Proof |
| Legacy reference + code | Behavior evidence only — never target authority |

**OpenSpec is the implementation handoff.** BMAD skills are optional helpers for PRD/epics/readiness — not a second process.

## 2. Deterministic work loop

```text
1. Open docs/GREENFIELD-MASTER-REFERENCE.md
2. Pick ONE next blocker / story / OpenSpec task
3. If planning gap → targeted BMAD skill or edit GF/OpenSpec (do not restart BMAD)
4. If feature work → run the Legacy → Target Trace (below) before coding
5. Implement only in system/ under the active OpenSpec change
6. Prove with Pest / Vitest / Playwright / ops evidence
7. Update OpenSpec tasks + master-reference checkpoint
```

Never implement from memory, chat history alone, or “it looked like that in legacy.”

## 3. Legacy → requirements → stories → UI → OpenSpec (required)

Legacy is the closest feel of the product. Greenfield must recover that feel **without** copying unsafe architecture. Every feature/UI behavior follows this chain:

```text
Legacy evidence
  → disposition (PRESERVE / REWRITE / SPIKE / DEFER / REJECT)
  → FR/NFR/BR + GF contract
  → story (user value + Given/When/Then)
  → route/page/state/breadcrumb/modal contract
  → OpenSpec task
  → system/ implementation + tests
  → crosswalk update
```

### 3.1 Before writing code for a behavior

Complete or update a trace row that names:

1. legacy source path(s);
2. observed actor/scope;
3. visible states (loading/empty/error/conflict/denied/offline/success);
4. disposition;
5. FR/BR IDs;
6. target command/query + page/component;
7. capability + resource scope + duty;
8. transaction / idempotency / audit / after-commit side effects;
9. recovery/retry/reversal rules;
10. Pest/Vitest/Playwright evidence targets.

If any of those is unknown → label `UNVERIFIED` or `DECISION REQUIRED` and stop coding that path.

### 3.2 Why this prevents repeating legacy bugs

Studying legacy is mandatory **as evidence**, not as copy-paste:

- **Preserve** journeys, fields, vocabulary, useful affordances (setup readiness bars, breadcrumbs, import preview, toasts).
- **Rewrite** state ownership, authorization, transactions, concurrency, trust boundaries.
- **Do not** reintroduce role middleware as authz, client-side permission checks, silent omissions, or “confirm()” as the only destructive UX without an accessible contract.

Bugs we specifically avoid by tracing first:

- missed-exam “retry” inferred ad-hoc instead of an explicit policy;
- import confirm UX invented differently than staged analyze→preview→confirm;
- toast claiming provider success before after-commit evidence;
- Program Head seeing raw sheets / conversion tables;
- AI/Nexi mutating domain state outside typed commands.

### 3.3 Stories and UI/UX connection

Stories are the bridge between requirements and screens:

- each story cites FR/BR IDs;
- each UI story cites GF-22 states + breadcrumb/modal/toast rules;
- each UI story cites legacy disposition and observed baseline;
- acceptance criteria must include happy, denied, invalid, conflict, and recovery paths;
- a story without legacy disposition for reused behavior is not ready.

Missing stories = missing implementation authority. Roadmap phases are not stories.

**Parallel recording (founder preference 2026-08-05):** when implementing a feature before its story was fully detailed, expand the matching epic/story *in the same change* with the field contract, disposition, and GWT you just proved. Do not leave product decisions only in chat.

### 3.4 Legacy UI/UX mimic for existing features (founder preference 2026-08-06)

For features that already existed in legacy, **do not invent page layout from scratch**. Open the matching legacy pages under `legacy_codebase/application/resources/js/Pages/` (and the legacy controller) **before** writing greenfield UI.

Default disposition for staff-facing layout of known features: **PRESERVE the interaction shape**:

| Prefer (legacy shape) | Avoid inventing |
|---|---|
| List/index first: what exists, active/inactive, key columns | Create/edit forms dumped on the index |
| Create and Edit as separate inner pages | Inline mega-forms on the hub/list |
| Explicit Activate / Deactivate (or equivalent) on the list | Hidden activation only inside a form |
| Setup hub: per-category create then activate checks, one Configure link each | Merged categories or one button for two catalogs |
| Vocabulary staff already know (e.g. Academic year + Semester) | Internal jargon on labels (e.g. bare “cycle”) without the staff terms |

Still **REWRITE** authz, transactions, concurrency, and trust boundaries. Still use the locked UI kit (fonts, radius, shell, kit chrome). Mimic means journeys, page structure, and affordances — not copying unsafe architecture or role middleware.

Do not wait for the founder to correct layout every time. If legacy Index/Create/Edit exist for that feature, greenfield should land that shape on the first pass unless an OpenSpec/decision explicitly changes it.

## 3.4 How this imparts into day-to-day work

Yes — legacy study, requirements, stories, and UI contracts are **baked into the working process**, not optional research:

| Concern | How it shows up in process |
|---|---|
| Legacy feel (closest product memory) | Study `legacy_codebase/` + `docs/legacy-reference/` first; disposition every reused behavior |
| Avoid repeating legacy bugs | Preserve journeys/UI affordances; **rewrite** authz, transactions, concurrency, trust boundaries |
| Requirements → UI | Stories cite FR/BR IDs **and** GF-22 page/state/breadcrumb/modal rules |
| Missing stories | Means **no implementation authority** for that behavior — generate stories before coding |
| OpenSpec | Only code handoff after the trace above exists |
| BMAD | Optional helper to close PRD/epic/story gaps — not a second ceremony track |

If a feature cannot name legacy disposition + FR + story + page states, agents must stop and record the gap in the master reference instead of inventing from chat memory.


## 3.5 Locked applicant auth (D-085)

Applicant portal login is **access key + birthdate** (both required). Confirmation shows the key once and may save it on-device; email is backup. No free-form applicant password in E2. Full decision: [`greenfield-foundation/decisions/D-085-applicant-access-credential.md`](greenfield-foundation/decisions/D-085-applicant-access-credential.md).

## 3.6 Intake grades + Privacy Policy consent (D-086)

Apply collects digital grades from catalog `intake_grade_fields` (seeded GWA/subjects; CRUD-ready like privacy policies) into `admission_attempt_grade_values`; hard-copy Form 138/TOR still required at Registrar. Consent checkbox must link to the full active Privacy Policy dialog (legacy preserve). Decision: [`greenfield-foundation/decisions/D-086-intake-grades-and-privacy-consent.md`](greenfield-foundation/decisions/D-086-intake-grades-and-privacy-consent.md).

## 4. Runtime and tooling rules

- Runnable greenfield app is **`system/`** only.
- Host often has no PHP — use Compose (`system/compose.yaml`), not Sail as runtime.
- Laravel Boost guidelines in `CLAUDE.md` apply; Boost MCP is project-wired via `.cursor/mcp.json` → `system/bin/boost-mcp.sh`.
- Prefer Boost `search-docs` when available before inventing Laravel/Inertia APIs.
- Spatie Permission = grant persistence helper only; GF-21 `CapabilityAuthorizer` decides.
- No legacy role middleware as authorization authority.
- **Staff UI lock:** IBM Plex Sans, `--radius: 0.5rem`, layered paper + glass, legacy responsive shell, management-list kit chrome, and **Nexi universal assist** (wrap island + minimal header). Authority: [`greenfield-foundation/evidence/ui-kit-locked-start-set.md`](greenfield-foundation/evidence/ui-kit-locked-start-set.md) (§4.1 = Nexi). Live suite: `/ui-kit`. Offline twins: [`greenfield-foundation/evidence/ui-kit-reference/`](greenfield-foundation/evidence/ui-kit-reference/). Do not invent fonts/radius per page; do not place meta banners above the sticky header; do not reintroduce dense Nexi toolbars or absolute frames that paint over chat.

## 5. Future features (Nexi, AI, embeddings, Program Head)

Reserve seams early; implement only behind gates:

- E0–E2: ports, flags, degraded slots, typed-command hooks only.
- E3+: one OpenSpec per capability after R4/G-* gates.
- Nexi is presentation + typed assistance — never hidden DB/auth bypass. Locked chrome: `NexiAssistPanel` wrap island (latest agent beside frame), Assistant/Inquiry mode, usage meter, Thinking/Analyzing fillers, Apply gate overlay. See locked start set §4.1.
- Manual scheduling and manual scoring remain complete fallbacks.

## 6. Stop rules

Stop and return to the master reference when:

- no active OpenSpec task covers the change;
- legacy behavior has no disposition;
- page/route/state/authz contract is missing;
- a provider/SDK is being added before its ADR/gate;
- tests cannot map back to FR/story acceptance.

## 7. Permanent file map for agents

| Need | File |
|---|---|
| Where am I / what’s next | `docs/GREENFIELD-MASTER-REFERENCE.md` |
| This process reminder | `docs/project-context.md` (this file) |
| Phase gates | `docs/MASTER-DELIVERY-ROADMAP.md` |
| Readiness findings | `docs/greenfield-foundation/evidence/implementation-readiness-report-2026-08-04.md` |
| Legacy bridge | `docs/legacy-reference/README.md` |
| Active code change | `openspec/changes/<active>/` |
| Staff UI / Nexi lock | `docs/greenfield-foundation/evidence/ui-kit-locked-start-set.md` |
| Cursor always-on rule | `.cursor/rules/nexiam-greenfield.mdc` |
