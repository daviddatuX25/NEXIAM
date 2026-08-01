# NEXIAM Audit → Architecture → Greenfield Mission Plan

**Authoritative synthesis:** `docs/master-architecture-picture.md`
**Greenfield execution plan:** `docs/greenfield-architecture-plan.md`
**Current phase:** audit complete; greenfield scaffold is next
**Strategic pivot (2026-08-01):** strangler-style modernization replaced by clean-slate greenfield rebuild. Legacy codebase is a read-only reference, not the execution target.

---

## 1. Audit coverage

| # | Lens | Evidence | Status |
|---|---|---|---|
| 1 | Codebase architecture | `docs/architecture-audit.md`, current source | Complete; earlier claims corrected in master §2 |
| 2 | Access control/RBAC | `docs/access-control-inventory.md`, routes, policies, manuscript | Complete; role catalog remains a decision gate |
| 3 | Controllers | `docs/audit-controllers.md`, all 41 controllers | Complete; service-first dogma softened |
| 4 | Services/modules | 35 PHP service files, caller/dependency map, largest modules | Complete in master §§5–7 |
| 5 | Database/data integrity | 86 migrations, 25 models, workflow writes | Complete in master §5; schema verification remains Gate 1 work |
| 6 | Frontend | 199 Svelte files, source-only ESLint/Prettier, Svelte check, duplication analysis | Complete in master §§4–5 |
| 7 | Feature completeness | manuscript/features versus routes/controllers/services/pages | Complete in master §8 |
| 8 | Security/API/operations | auth, policies, uploads, dependency audits, queues, audit design | Complete in master §§4–5 |

The old “3 of 8” status referred to separate reconnaissance documents. The eight lenses are now synthesized into one auditable blueprint rather than five more disconnected reports.

---

## 2. Quality instruments

Current reproducible ledgers:

- PHPStan level 6: 988 suppressed occurrences; 0 unsuppressed with baseline.
- Source ESLint: 309 errors in 94 files. The old 3,200 figure is inflated by compiled `public/build` assets.
- Svelte check: 1,073 errors and 206 warnings; 961 contain the dominant `$$ComponentProps` inference class and 113 error lines fall outside it.
- Source Prettier: 232 files need formatting.
- Pest: 2 example tests pass; 0 meaningful domain tests.
- npm production audit: 0 advisories.
- Composer audit: 9 PhpSpreadsheet 4.5.0 advisories, including reachable critical SSRF/RCE risk; upgrade target is 5.9+.

Contract:

1. formatting gets an isolated commit before semantic frontend changes;
2. no pull request grows a scoped ledger;
3. every refactor removes debt or an explicit architecture violation;
4. new/touched modules are green;
5. generated artifacts are excluded from source quality counts;
6. the new clean baseline is zero—old debt is not copied into replacement modules.

---

## 3. Decision gates before implementation

These are **not settled** merely because an older document called them settled.

1. Permanent role and permission catalog; live code, manuscript, and access inventory expose three divergent catalogs.
2. Proctor as session-assignment duty overlay, including who may assign and revoke it.
3. Identifier-key authentication semantics; identifier-only login is not acceptable without a credential/recovery design.
4. Google OAuth trusted-domain, verified-email, account-linking, throttling, and audit policy before enabling the new flow.
5. Applicant lifetime and whether a person can apply/test in later academic cycles.
6. Pipeline source of truth and allowed backtracking/force transitions.
7. HMAC audit threat model, canonicalization, locking, key rotation, and external checkpoints.
8. OMR sheet/input constraints, confidence threshold, review workflow, and operational batch size.
9. RAG scope—including authorized own-result/schedule/assignment retrieval versus separate recommendation—and its confidence/citation/refusal contract and fallback office.
10. Program Head scope/quota definitions.
11. Deployment target: recommended Laravel Compose stack plus one Python inference worker/sidecar.
12. Retention/reversal semantics for reopen, application deletion, applicant deletion, and reassignment after exam history exists.

Resolve these as ADRs/OpenSpec requirements before schema or role migrations.

---

## 4. Gate sequence

> **Superseded 2026-08-01.** The gates below were designed for strangler-style modernization of the legacy codebase. The greenfield gate sequence is defined in `docs/greenfield-architecture-plan.md` section 5. These legacy gates are preserved for reference only — they document what the audit found needed fixing and remain useful as a checklist of domain concerns the greenfield build must address correctly from the start.

### Legacy Gate 0 — preserve and measure (reference only)

- protect the current uncommitted tooling work;
- correct ESLint generated-file scope;
- narrow `jsconfig.json` / `svelte-check` to maintained source so vendor/build/storage/legacy files cannot pollute or abort checks;
- commit Pint/Prettier formatting alone;
- keep the current untracked `quality.yml` draft disabled/unmerged until formatting and deliberate no-growth/touched-code gates prevent a permanently red workflow;
- publish ledger reports as CI artifacts after those gates are valid;
- review and accept the master blueprint.

### Legacy Gate 1 — stop-ship safety and characterization (reference only)

- upgrade/harden PhpSpreadsheet;
- escape applicant-controlled document placeholders and neutralize spreadsheet/CSV formulas;
- repair and characterize the broken Start-session buttons plus verified Inertia runtime API misuse;
- preserve applicant/exam/grading history across reopen/delete operations;
- create factories and stable fixtures;
- characterize application, exam/proctor, grading/release, and document flows;
- add policy matrix tests;
- add baselined/no-growth architecture tests;
- rehearse fresh migration/seed and upgrade migration, then reconcile fresh-versus-upgraded FK/index/delete-action drift.

### Legacy Gates 2–5 (reference only)

See the original gate details in version control history. The greenfield plan carries forward the domain concerns (command centralization, role migration, audit chain, RAG scope, OMR, dashboards) as clean-build gates rather than refactoring gates.
---

## 5. OpenSpec change train

> **Adapted 2026-08-01.** Under the greenfield pivot, the "Foundation" changes (characterization, hardening of legacy code) are no longer needed — those problems are solved by not carrying the legacy code. The "Refactor" changes become part of the initial greenfield architecture. The "Feature" changes remain as build targets. The OpenSpec change names below are preserved as a vocabulary for tracking which domain concerns have been addressed.

Legacy-only (solved by greenfield — no longer needed):

1. ~~`quality-ledgers-and-generated-asset-scope`~~ — greenfield starts at zero
2. ~~`characterize-critical-admission-lifecycle`~~ — built from requirements, not characterized
3. ~~`characterize-exam-grading-release-lifecycle`~~ — built from requirements, not characterized
4. ~~`upgrade-and-harden-spreadsheet-imports`~~ — greenfield uses latest dependencies from the start
5. ~~`harden-rendered-templates-and-tabular-exports`~~ — greenfield builds typed placeholder system from scratch
6. ~~`harden-applicant-setup-and-reset-token-lifecycle`~~ — greenfield implements hashed tokens from the start

Specification work (still required as ADRs before building):

7. `secure-google-oauth-account-linking` — trust/linking/audit policy must be settled
8. `settle-role-permission-and-proctor-duty-model` — authoritative catalog required
9. `define-applicant-identifier-auth-semantics` — identifier + password design required
10. `define-audit-chain-threat-model` — cryptographic design required

Built into greenfield architecture (no separate refactor changes):

11–16. Command centralization, read models, document decomposition, typed Svelte — these are the **default architecture** of the greenfield build, not separate refactoring efforts.

Feature builds (greenfield implementation):

17. `applicant-context-rag-with-confidence-and-fallback`
18. `omr-ingestion-review-and-score-import`
19. `admission-engine-registry-and-provenance`
20. `yukee-and-bge-engine-adapters`
21. `counselor-triage-dashboard`
22. `program-head-scoped-dashboard`
23. `applicant-and-interoffice-messaging`
24. `offline-proctor-sync`

Each feature change still requires: desired behavior, invariants, authorization matrix, schema, failure/retry, observability, tests, and rollout plan.

---

## 6. Completion criteria for the audit phase

- master blueprint passes source-backed adversarial review; **done**
- all reproduced metrics and commands are recorded; **done**
- factual corrections to audits #1–#3 are accepted; **done**
- decision gates are visible and not mislabeled as settled; **done**
- rewrite versus refactor recommendation is explicit by subsystem; **superseded by greenfield pivot**
- OpenSpec changes are dependency ordered; **done (adapted for greenfield)**
- no application source has been semantically refactored during the audit. **done**

The audit phase is complete. Independent adversarial review plus focused database/frontend audits were completed on 2026-07-31. The strategic pivot to greenfield was decided on 2026-08-01. Next step: scaffold the clean project per `docs/greenfield-architecture-plan.md`.
