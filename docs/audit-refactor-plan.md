# NEXIAM Audit → Architecture → OpenSpec Mission Plan

**Authoritative synthesis:** `docs/master-architecture-picture.md`
**Current phase:** source-backed audit verified; section-by-section human decision review is next
**Operating doctrine:** specifications → P0 safety/refactors → P1 features → P2 builds

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

Resolve these as ADRs/OpenSpec requirements before schema or role migrations.

---

## 4. Gate sequence

### Gate 0 — preserve and measure

- protect the current uncommitted tooling work;
- correct ESLint generated-file scope;
- narrow `jsconfig.json` / `svelte-check` to maintained source so vendor/build/storage/legacy files cannot pollute or abort checks;
- commit Pint/Prettier formatting alone;
- keep the current untracked `quality.yml` draft disabled/unmerged until formatting and deliberate no-growth/touched-code gates prevent a permanently red workflow;
- publish ledger reports as CI artifacts after those gates are valid;
- review and accept the master blueprint.

This sequence intentionally overrides the compressed `features.md` P0 ordering: hardening, characterization, and role/auth specification are prerequisites for OMR and broader feature construction.

### Gate 1 — stop-ship safety and characterization

- upgrade/harden PhpSpreadsheet;
- create factories and stable fixtures;
- characterize application, exam/proctor, grading/release, and document flows;
- add policy matrix tests;
- add baselined/no-growth architecture tests;
- rehearse fresh migration/seed and upgrade migration.

### Gate 2 — P0 architecture seams

- centralize application commands;
- centralize exam/proctor commands;
- centralize grading/release commands;
- make transaction/audit/projection ownership explicit;
- implement assignment-scoped proctor policy;
- introduce scoped read models/dashboard query facade;
- expose mandatory dependencies through injection.

### Gate 3 — P1 capstone foundations

- approved permission/role migration;
- identifier-key login flow;
- specified chained audit storage and verifier;
- notification preferences, idempotency, after-commit delivery;
- approved applicant-context RAG boundary with confidence refusal and messaging fallback; placement recommendation remains a separate decision-support capability.

### Gate 4 — P2 research contribution

- asynchronous OMR ingestion, CV worker, and human review;
- admission-support engine registry and immutable inference provenance;
- Yukee and BGE adapters;
- counselor triage dashboard;
- Program Head scoped dashboard.

### Gate 5 — polish/delivery

- messaging and offline sync;
- retained-code ledger reduction;
- E2E defense journeys;
- performance, security, queue failure, backup/restore tests;
- clean repository/history only after specifications and migration plan are locked.

---

## 5. OpenSpec change train

Foundation:

1. `quality-ledgers-and-generated-asset-scope`
2. `characterize-critical-admission-lifecycle`
3. `characterize-exam-grading-release-lifecycle`
4. `upgrade-and-harden-spreadsheet-imports`
5. `harden-applicant-setup-and-reset-token-lifecycle`
6. `secure-google-oauth-account-linking`
7. `settle-role-permission-and-proctor-duty-model`
8. `define-applicant-identifier-auth-semantics`
9. `define-audit-chain-threat-model`

Refactors:

10. `centralize-application-command-workflows`
11. `centralize-exam-session-command-workflows`
12. `centralize-grading-and-release-workflows`
13. `introduce-scoped-read-models-and-dashboard-query`
14. `decompose-document-and-report-rendering`
15. `typed-svelte-boundaries-for-touched-features`

Features:

16. `applicant-context-rag-with-confidence-and-fallback`
17. `omr-ingestion-review-and-score-import`
18. `admission-engine-registry-and-provenance`
19. `yukee-and-bge-engine-adapters`
20. `counselor-triage-dashboard`
21. `program-head-scoped-dashboard`
22. `applicant-and-interoffice-messaging`
23. `offline-proctor-sync`

No umbrella “rewrite everything” implementation change. Each change must define current behavior, desired behavior, invariants, authorization, schema migration, failure/retry behavior, observability, tests, rollout/rollback, and ledger impact.

---

## 6. Completion criteria for the audit phase

- master blueprint passes source-backed adversarial review;
- all reproduced metrics and commands are recorded;
- factual corrections to audits #1–#3 are accepted;
- decision gates are visible and not mislabeled as settled;
- rewrite versus refactor recommendation is explicit by subsystem;
- OpenSpec changes are dependency ordered;
- no application source has been semantically refactored during the audit.

After those criteria, review `docs/master-architecture-picture.md` section by section before creating the first OpenSpec change.

Independent adversarial review completed on 2026-07-31. Its blockers and corrections—plaintext applicant tokens, Google OAuth account-linking risk, three-way role-catalog divergence, red-draft CI sequencing, RAG/manuscript scope, quality-count drift, and the vacuous global-function architecture-test example—were incorporated before this status was advanced.
