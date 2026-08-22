# NEXIAM Project Context (permanent agent working rules)

**Status:** ACTIVE — permanent AI/agent implementation context
**Entry point:** [`GREENFIELD-MASTER-REFERENCE.md`](GREENFIELD-MASTER-REFERENCE.md)
**Product PRD:** [`prd.md`](prd.md) (PROMOTED 2026-08-05; E2 cut-line = Tier A + REQ-VS)
**Current implementation OpenSpecs:** `institutional-public-experience` — implementation complete; founder review remains for simulated media/copy. `2026-08-15-applicant-portal-interface` — implementation complete; archive pending founder review. `omr-profile-test-readiness` — still in progress pending physical-device acceptance.
**Nexi architecture:** [`greenfield-foundation/architecture/ai-assistant-foundation/`](greenfield-foundation/architecture/ai-assistant-foundation/) (FINAL; AD-3 pack-as-lens; AD-13 AiGateway; AD-25 knowledge archived)
**Next:** founder review the institution-first public homepage, replace simulated media/copy, and archive `institutional-public-experience`; then archive `2026-08-15-applicant-portal-interface` after its pending review. Continue result-sheet printing, E2 exit evidence G2–G4/H4, the legacy parity ledger, OMR physical acceptance, pre-existing suite-parity restoration, and the deferred admission slip page. YuKee artifact/K-Means, deterministic/rule-based, BGE, and other real provider work remain later evidence-gated changes.
**Phase roadmap:** [`MASTER-DELIVERY-ROADMAP.md`](MASTER-DELIVERY-ROADMAP.md)
**Target code:** `system/`
**Legacy evidence:** `legacy_codebase/application/` + `docs/legacy-reference/`

This file is the durable reminder of *how* we work. Read it with the master reference at the start of every non-trivial task.

## 0.1 Nexi skill / capability model (founder lock 2026-08-10)

Non-negotiable for Assistant (and Inquiry) — also locked in active OpenSpec `nexi-exam-session-assistant/proposal.md` + `design.md`:

1. **One auth** — Inertia and `/nexi/*` share the same cookie/session principal. Prompt/provider fields cannot spoof actor, session, or institution.
2. **One core** — Controllers and Nexi unite on domain Actions/Queries (e.g. `Examination\Actions\*`). No AI-only domain write path.
3. **Capability matrix** — Kernel registry rows wrap those Actions/Queries (deterministic wall ≈ FormRequest+Policy). Scale by adding Action + registry row, not duplicate AI endpoints.
4. **Skill** — **not** “markdown as authority.” Skill = allowlist of capability versions + server engine/cursor (+ optional guidance digest for the model only). Off-allowlist tools are rejected even if the user holds that GF-21 cap globally.
5. **Multi-step** — engine advances cursor after Kernel success; model only recommends among legal tools for the cursor.
6. **Projections** — post-auth channel shaping (`http_page` / `nexi_safe` / provider), not a second rule engine.
7. **Persistence** — Reads: no domain write (run/disclosure/usage may still be durable). Propose: persist `nexi_proposals`. Confirm→Command: domain write via the ordinary Action.

**OS8 pilot:** `schedule.exam_session@1` → open/assign/publish confirm → same Actions as `/exams/sessions`. Applicant NL booking is **out of scope** (later `applicant.choose_slot`).

## 0.2 Capability sync (pages ↔ seeds ↔ Nexi) — founder lock 2026-08-10

One GF-21 capability matrix. Seeds (`CapabilityCatalog::roleHelperGrants`) only **grant** rows; they are not a second authz engine. Nexi Kernel rows must reuse the **same application capability keys** as the matching Inertia pages/Actions — never invent AI-only permissions.

| Concept | Authority |
|---|---|
| Test Administrator | Capability **set** (D-016), not a Spatie role — `CapabilityCatalog::testAdministratorCapabilities()` |
| Exam session pages + Nexi exam Assistant | Same key(s): `CapabilityCatalog::examSessionManagementCapabilities()` → today `guidance.exam.schedule` |
| Proctor | Session **duty** (D-015), assigned by holders of `guidance.exam.proctor.assign` |
| TA delegation | `guidance.ta.delegate` (outside TA set) grants/revokes full `testAdministratorCapabilities()` on clerk/support |
| Role helpers (`guidance_counselor`, …) | Seed/personnel assignment only — never route middleware authority |

Scale rule: new domain feature = Action + page gate + (optional) Kernel row on the **same** application capability. Do not fork a parallel “AI matrix.”

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
7. Ensure developer-ready seeded test data for the feature (or document exact seed/reset steps)
8. Update OpenSpec tasks + HANDOFF (include seed credentials/paths) + master-reference checkpoint
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


## 3.7 Developer-ready seeded data (required on every feature handoff)

Every feature cut must leave the developer able to **click through the happy path without inventing fixtures by hand**.

Required in the active OpenSpec `HANDOFF.md` (and evidence note when relevant):

1. **Who to log in as** — staff email/password and/or applicant access key + birthdate.
2. **What seed creates it** — seeder class / `foundation:reset --force` / compose rebuild notes.
3. **What state to expect** — e.g. accepted applicant waiting for reservation; draft session ready to open; empty calendar vs pre-open session.
4. **How to recreate** — one short command sequence from a clean Compose DB.
5. **URLs** — staff + applicant entry paths for the feature.

Rules:

- Prefer durable entries in `config/seed.php` + named seeders over one-off tinker scripts.
- If the feature needs a mid-journey state the default seed does not provide, either extend the seeder **or** document the minimal staff clicks that produce it (and prefer extending the seeder when the state is reused).
- Pest coverage alone is not enough — the handoff must still list human demo credentials.
- Do not ship a “feature done” checkpoint without this section.

## 3.8 Controller conventions — thin controllers (founder lock 2026-08-10)

Legacy's worst smell was fat controllers with inline validation and role branching. Greenfield rule:

1. **Form Requests own validation** — one request class per write shape under `app/Http/Requests/<Domain>/`; no inline `$request->validate()` in controllers.
2. **Domain resolution in support classes** — role/office/eligibility derivation lives in domain `Support/*` classes, not the controller.
3. **Actions own writes** — one action per write intent under `app/<Domain>/Actions/`.
4. **Controllers delegate** — read projections + action calls only; target < ~120 lines; no business branching.
5. **Explicit in plans** — every OpenSpec design must name the request class(es) and action(s) for each write path, so a fat controller cannot silently regrow.

## 3.9 Staffing hierarchy (founder lock 2026-08-10)

Office rank: **support (1) < clerk (2) < office lead (3: registrar_admin / guidance_counselor) < HR (4)**.

- Office actors manage **strictly-lower** ranks in **their own office only**; peers and higher ranks are unreachable (server-enforced in every `Staffing\Actions` + `PersonnelController::edit` 403).
- HR manages everyone (last-active-HR guard on deactivate/revoke).
- Self-edit / self-deactivate are **profile-page** concerns, never Staff-directory actions (`/staff/profile`, read-only for now).
- Password reset: **HR (anyone) + office leads (own office, lower rank)**. Self-service password → profile page (follow-up cut).
- Support-staff roles are office-derived, never pickable; role reassignment of permanent helpers is HR-only.

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
- tests cannot map back to FR/story acceptance;
- feature handoff lacks developer-ready seed credentials / recreate steps.

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
