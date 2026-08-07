# 06 — Reconciled Legacy Reference and Residual Clarifications

**Purpose:** preserve traceability without reopening decisions already settled in prior NEXIAM review sessions.

This file is a legacy-reference aid, not a second decision matrix. The authoritative decision source is `docs/greenfield-foundation/13-decision-and-batch-approval-matrix.md` (GF-13). GF-13 records an outcome for every original D-001–D-080 row plus the D-081–D-084 founder amendments. GF-13B is historical rationale/recommendation material, not a fresh approval queue. GF-14 controls readiness and promotion.

The reconciliation used the recorded sessions:

- `Nexiam Project Truth Source Decisions` — 2026-08-02;
- `Project Foundation Status and Next Plan #4` — 2026-08-03;
- the earlier HLD checkpoint where David answered `go` to the proposed system shape/module baseline.

The legacy implementation is the nearest-working behavior and implementation reference. It does not override founder decisions, GF contracts, authorization rules, or readiness gates, but verified behavior should be deliberately reused and reimplemented unless an explicit target decision changes it.

## Reconciled status: do not re-ask these as fresh decisions

The old question tables below are retained as traceability prompts. The status in this section overrides their earlier “recommended default” wording.

| ID | Reconciled status | Authority/evidence |
|---|---|---|
| UI-01 | **Resolved working direction:** preserve verified information architecture, document identity, and useful page behavior, while reimplementing them with a controlled visual refresh and clean token/component system under the target architecture. | D-003; D-081; GF-22; GF-24 |
| UI-02 | **Resolved as the GF-22 working UX contract:** applicant portal mobile-first; staff workspace desktop/tablet-safe; proctor workflow touch-safe. Exact device/browser evidence is an implementation test concern. | D-003; GF-11; GF-22 |
| UI-03 | **Resolved:** retain grouped staff navigation with server-authorized omission of empty groups. | GF-22 shell/navigation contract |
| UI-04 | **Residual content-policy item:** plain English is the current working default and mixed labels are prohibited; no explicit founder outcome for localization was found in GF-13 or the reviewed session records. It is not an E0 blocker. | GF-04 / GF-22 working contract; content review still needed |
| UI-05 | **Resolved direction:** the admission slip/QR attachment is removed from the target answer-sheet identity flow; result-sheet generation, release, print semantics, versioning, and evidence remain required. Exact template package is downstream design/evidence. | D-078; GF-11; GF-22 |
| UI-06 | **Resolved:** loading, empty, validation, denied, conflict, queued, degraded, success, audit-sensitive, and recovery states are part of connected-slice acceptance. | GF-11; GF-22 |
| UI-07 | **Working implementation baseline:** evergreen browser on low/mid-range Android for applicants/proctors and desktop Chromium for staff; exact Playwright/device matrix is evidence, not a new founder question. | GF-22; D-059 |
| UI-08 | **Resolved:** tables/KPIs are authoritative; charts are explanatory projections with scope/freshness and accessible text/table alternatives. | GF-22; REQ-VS-029 |
| UI-09 | **Resolved with gate:** offline proctoring is a committed capstone capability, not a cache-only convenience; durable outbox/replay/conflict evidence gates promotion, while online/manual/paper fallback remains complete. | D-011; D-053; EV-011 |
| UI-10 | **Resolved contract:** notifications are concise, plain, actionable, redacted, and truthful about committed, queued, accepted, delivered, and failed states. | D-045; GF-22 |
| DOM-01 | **Resolved:** one durable ApplicantIdentity may own multiple cycle-scoped attempts/retakes; exact retake count/rule is a Test Administration policy input before E2. | D-024; D-030 |
| DOM-02 | **Resolved:** Test Administrator is a Guidance-scoped capability; Guidance owns exam/consultation operations through that capability; Proctor is a session-scoped duty overlay. | D-014–D-016; D-022; D-038 |
| DOM-03 | **Resolved direction:** F2F is the default consultation/release mode; online is a supported branch; release and reversal/supersession are separate auditable commands. Exact institutional prerequisites remain evidence-gated. | D-032; D-044; D-077 |
| DOM-04 | **Resolved:** applicant access challenge is issued after successful application submission; Registrar acceptance remains an independent decision. | D-025; D-073 |
| DOM-05 | **Corrected/superseded:** the earlier “admission slip, result sheet, and internal artifact” recommendation is stale. Admission slip/QR stapling is removed; result preparation/printing/release evidence remains required; the exact capstone evidence package is a planning artifact, not a fresh role/domain approval. | D-078; GF-11; GF-24 |
| DOM-06 | **Working default with institutional gate:** five years after cycle closure for applicant/admission/exam/score/consultation/result records, seven years for audit/security evidence, and 90 days for eligible raw scan purge after closure of applicable holds. These are not legal/pilot approval. | D-034; D-035; EV-013 |
| DOM-07 | **Resolved direction:** there is no “choose one differentiator” re-approval. OMR, RAG, model/decision support, and offline proctoring remain committed or conditional exactly as classified in GF-13; each has its own technical/evidence gate and manual fallback. | D-007–D-011; D-046–D-053; D-069 |
| DOM-08 | **Resolved for the capstone demonstration:** use deterministic synthetic seed data; historical migration is a separate, later-approved project and cannot contaminate the greenfield authority. | D-002; D-003; D-068; GF-24 |
| HLD-01 | **Resolved:** `go` was already given for the modular-monolith system shape and twelve capability-module working baseline. This authorizes continued HLD/LLD/schema/interface solutioning, not final HLD promotion or implementation. | GF-16 recorded review outcome; GF-17 HLD checkpoint 1 |
| STRAT-01 | **Resolved:** the greenfield is a scalable reimplementation, not a blank-slate rewrite. Reuse verified legacy workflows, fixtures, payloads, layouts, tests, and nearest-working implementation patterns through the approved contracts; redesign unsafe state, authorization, schema, integration, and concurrency boundaries. | D-081; GF-24 |
| ENG-01 | **Resolved with evidence gate:** the available trained YuKee `.joblib` artifact and engine specifications define the selected adapter source. The remaining work is checksum/loader/spec/fixture verification, not retraining or a new method decision. | D-082; GF-10; GF-20 |
| ENG-02 | **Resolved for planning:** OMR remains manual-first with human review/import authority, and the institutional workflow decisions already recorded in GF-04 through GF-11 and GF-21/GF-22 are sufficient constrained assumptions for E0/E1/E2. Remaining inputs are fixtures, configuration, and technical/privacy/pilot evidence. | D-083; GF-10; GF-11; GF-14 |
| PLAN-01 | **Resolved:** E0 scaffold work may begin now under the bounded E0-CONDITIONAL PASS; E1/E2 feature work remains separately gated by E0 evidence and fresh R5 review. | D-084; GF-14; GF-24 |

## What remains as implementation input or evidence gate

These are not lost decisions and are not a new approval queue. They are bounded implementation inputs or evidence gates; they only reopen a founder decision if new evidence changes authority, lifecycle, privacy, or release semantics:

1. Final applicant delivery channel and credential model: email/SMS/institutional channel and password/passkey/OTP choice; no fabricated fallback address. (`D-025`, `D-073`)
2. Exact Test Administration retake count/rule and grace duration. (`D-030`, `D-040`)
3. Canonical admission terminology fixtures and legacy mappings for implementation tests; D-074 already selects the target vocabulary. (`D-074`, `EV-015`)
4. Retention, legal-hold, and deletion/restriction configuration/evidence before real-data pilot; the working defaults do not block E0. (`D-034`, `D-035`, `EV-013`)
5. F2F/online result-release operating fixtures and evidence; the release authority and default mode are already selected. (`D-044`, `D-077`, `EV-001`/`EV-002`)
6. OMR template, device/input, ground truth, threshold, uncertainty, and throughput evidence for E3 promotion; the manual-first authority contract is already selected. (`D-046`–`D-047`, `D-083`, `EV-008`)
7. External YuKee artifact handoff and verification: immutable artifact identity/checksum, loader/runtime, specification manifest, program-mapping owner/source/version, and expected-output fixtures; no retraining decision remains open, but no artifact/specification package is present in this repository. (`D-048`, `D-049`, `D-082`, `EV-005`/`EV-006`/`EV-009`)
8. Approved RAG corpus, citation/refusal thresholds, privacy boundary, and provider/outage tests. (`D-051`, `D-079`, `EV-006`/`EV-007`)
9. Audit threat model, key custody/rotation, serialization, checkpoint, and verification spike. (`D-052`, `EV-012`)
10. Offline outbox/replay/conflict-resolution pilot/system-ready evidence. (`D-053`, `EV-011`)
11. Remaining HLD/LLD/schema/application-contract/authorization/UI/operations promotion checks. These are design review gates, not unanswered product decisions. (`GF-16`–`GF-24`, `R4`, `R5`)
12. Legacy runtime exact-version reproduction: lockfile versions and declared container tags are recorded in this note, but the selected runtime image/digest and live `php`/`composer`/`node`/`npm` version output have not yet been captured as one reproducible evidence package. (`composer.json`, `Dockerfile`, `compose.yaml`, `docker-compose.yml`, `composer.lock`, `package-lock.json`)

## Current audit evidence notes

### Legacy runtime exact-version baseline — OPEN

The legacy source provides exact dependency locks, but its declared runtime is split across multiple Compose variants and is not yet a single verified image/digest baseline:

| Evidence source | Declared or locked baseline |
|---|---|
| `composer.json` / `Dockerfile` | PHP `^8.4`; production image `serversideup/php:8.4-fpm-nginx`; asset builder `node:20-alpine`; runtime NodeSource setup `20.x` |
| `compose.yaml` | Laravel Sail PHP `8.5` image; MySQL `8.4` in the alternate development topology |
| `docker-compose.yml` | Laravel Sail PHP `8.4` image; MySQL `mysql/mysql-server:8.0`; Node `20-alpine` |
| `composer.lock` | Laravel `v12.64.0`; Inertia Laravel `v2.0.24`; Larastan `v3.10.0`; PHPStan `2.2.7` |
| `package-lock.json` | Inertia Svelte `2.3.24`; Svelte `5.55.9`; Vite `7.3.6`; TypeScript `5.9.3`; Tailwind `4.3.0`; Vitest `4.1.10` |

The exact lockfile graph is authoritative for the preserved snapshot. Before legacy behavior is promoted as reproducible technical evidence, capture the selected runtime image digest and live version output from that chosen topology. Do not treat generic README badges or the alternate Compose files as one exact environment.

### External YuKee artifact handoff — OPEN

The repository search found no `.joblib` file and no YuKee-named artifact/specification package. The selected artifact-based path remains a planning decision, not verified artifact adoption. The external handoff must provide an immutable source reference/custodian, SHA-256 and size, compatible Python/joblib/scikit-learn loader versions, feature/preprocessing contract, labels/cluster behavior, mapping-input owner/version, deterministic expected-output fixtures, and provenance/retention terms. Until that handoff is verified, the target must use the complete manual counselor workflow and may use only an explicitly named deterministic aptitude baseline; it must not claim YuKee/K-Means reproduction. (`D-082`, `ENG-01`, `GF-10`, `RES-G4`)

## Current go/no-go state

- Founder decision matrix: **RECORDED WITH CONDITIONS** — D-001–D-080 plus D-081–D-084 have recorded outcomes.
- HLD module/system shape: **GO AS WORKING BASELINE** — final HLD promotion remains gated.
- Planning recovery and downstream propagation: **IN PROGRESS** — exact runtime and external YuKee evidence remain open.
- Greenfield scaffold: **E0 QUALITY BASELINE VERIFIED WITH CONDITION** — `system/` is bounded, isolated, and domain-feature-free; E0 exit evidence and E1/E2 remain gated.
- Legacy runtime exact-version evidence: **OPEN** — lockfile/tag baseline recorded; single-topology runtime/digest proof pending.
- External YuKee artifact handoff: **OPEN** — artifact/spec/checksum/fixture package not present in the repository.
- Domain/schema implementation: **NO-GO** until readiness gates close.
- Controlled capstone demonstration: **NO-GO** until the connected slice and evidence gates pass.
- Real pilot/production: **BLOCKED** pending privacy, stakeholder, technical, operational, backup, and restore gates.

## A. Frontend and UI/UX traceability prompts

| ID | Historical prompt | Historical recommendation / current pointer | Blocks |
|---|---|---|---|
| UI-01 | Should the greenfield retain the old visual identity or receive a visual refresh? | Retain familiar information architecture and document identity, but create a clean token/component system and remove legacy inconsistencies | Broad page recreation; not E0 |
| UI-02 | Which experience is the primary responsive target? | Applicant mobile-first, staff desktop-first/tablet-safe, proctor session touch-safe | Page acceptance; not E0 |
| UI-03 | Should the staff navigation remain grouped as Admissions, Examination, Assessment, Consultation/Release, Reports, and System/Audit? | Yes, with server-authorized omission of empty groups | Staff shell and route catalog |
| UI-04 | What is the approved language policy? | Plain English first; no mixed labels; add Filipino/Ilocano localization only after a separate content decision | Copy/content acceptance |
| UI-05 | Should legacy result/admission document layouts be preserved visually? | Preserve required institutional fields and print semantics; permit a controlled visual refresh with stakeholder sign-off | Document/print acceptance |
| UI-06 | Which UI states must be demonstrated in the first connected slice? | loading, empty, validation, denied, conflict, queued, provider-degraded, success, and audit-sensitive states | E2 browser acceptance |
| UI-07 | What is the minimum browser/device support for applicant and proctor use? | Current evergreen browser on low/mid-range Android plus desktop Chromium for staff | Playwright/device acceptance |
| UI-08 | Are charts required for the capstone demo, or are scoped tables/KPIs sufficient initially? | Tables/KPIs are authoritative; charts are optional explanations with table alternatives | Reports scope |
| UI-09 | Is offline proctoring a pilot requirement or a later spike? | **Superseded:** offline proctoring is committed and EV-011-gated; online/manual/paper fallback remains complete | E3/system-ready evidence |
| UI-10 | Which notification tone is institutionally acceptable? | Concise, plain, actionable, truthful about queued versus delivered outcomes | Communication copy and acceptance |

## B. Domain/operations traceability prompts

| ID | Historical prompt | Historical recommendation / current pointer | Blocks |
|---|---|---|---|
| DOM-01 | Should one applicant identity support multiple cycle-scoped attempts/retakes? | Yes; attempts are distinct and references are unique per attempt | E1/E2 identity/admission model |
| DOM-02 | Who is the authoritative Test Administrator for slot reservation, assignment, scoring, consultation, and release? | Guidance-owned capability with explicit scope; Proctor is contextual duty only | E1/E2 authorization |
| DOM-03 | What are the allowed result release modes and reversal conditions? | Release and reversal are separate auditable commands; require stakeholder policy before implementation | E2 release |
| DOM-04 | Are applicant accounts created after submission, after acceptance, or both? | Challenge after successful submission; acceptance remains separate | E1/E2 access |
| DOM-05 | What exact generated documents are mandatory for the capstone demonstration? | **Superseded:** admission slip/QR is removed; result preparation/printing/release evidence remains required | E2 document evidence |
| DOM-06 | What retention/legal-hold period is institutionally approved? | Keep operational duration configurable; do not invent a legal period | Production/pilot clearance |
| DOM-07 | Which AI/OMR/decision-support feature is mandatory for evaluation? | **Superseded:** no new choose-one approval; use GF-13's committed/conditional feature classifications and individual evidence gates | E3/research gates |
| DOM-08 | Is historical data migration required for the capstone demo? | No; use deterministic synthetic seed data unless a separate migration decision is approved | E0/E1 safety |

## C. Historical question order (do not use as a new approval queue)

The original review order is retained only to show how the questions were once organized. Do not restart it from UI-01 or DOM-02; use GF-13 and the reconciled status above.

1. UI-01: preserve familiar identity versus controlled visual refresh;
2. UI-02: responsive/device baseline;
3. DOM-02: Test Administrator and Proctor authority boundary;
4. DOM-03: release/reversal policy;
5. DOM-05: mandatory capstone documents;
6. DOM-07: first research differentiator.

Any genuinely new or explicitly reopened answer must be recorded in GF-13/ADR/OpenSpec, not only in chat.
