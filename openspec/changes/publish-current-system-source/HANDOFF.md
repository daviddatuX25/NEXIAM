# Current-system source publication handoff

## Authority and boundaries

Founder-approved goal: browsable current application source on existing GitHub `master`, with usable README, source/migrations/seeders/data/assets/tests/lockfiles, without model weights, credentials, databases, builds or local artifacts. No branch rename, force push, database reset or deployment.

The candidate is prepared in an isolated worktree based on published master `d1f1a10a1ae6336e28fa79c49d3f606f02a35541`. It does not merge the unpublished development history containing a 212,804,803-byte model checkpoint. Existing remote ancestry is retained. The live bind-mounted working directory is not the candidate, and none of its application files/configuration/data/assets/workers are changed by this source-publication cut.

Existing encrypted backup branch: `backup/system-source-20261023-020702`, commit `83b4d1f870bc91c2661ac645d02e9d67db6136fc`. Its decryption key remains private/off GitHub. Verified private incremental history and working-state backups are also retained; no private backup is introduced in plaintext.

## Source changes relative to the captured working source

- Web Push: remove embedded VAPID fallback constants. Explicit configured matching keys are required; missing/invalid config yields HTTP503 at the public-key endpoint, and existing delivery failure behavior handles unavailable push. Tests generate ephemeral keys instead of embedding the active key. No current live key is rotated or changed.
- Intake: restore `form.strand` binding in the real shared correction fields and expose suggestion buttons as listbox options. The regression renders the actual shared form and asserts saved history plus typed/selected parent values.
- OMR: expose cancellation for eligible Mock runs with no active/unresolved capture; translate existing owner/scope failures through the controller's established safe HTTP mapping rather than leaking an HTTP500.
- Public-intake tests: correct missing imports and current required request fields; isolate an old migration roundtrip onto a minimal dedicated in-memory schema instead of dropping a table referenced by later current-schema foreign keys. No applied migration is rewritten.
- Documentation/ignores: replace root/application README with current installation/runtime/seeding/model/key/verification guidance and explicitly ignore runtime configurations, backups, database variants, generated test output and external weights.

## Developer/demo entry points

See `system/README.md` for the fresh-checkout, uniquely named `nexiam-dev` Compose project. Run migration and the explicit existing `DatabaseSeeder::testSeeders()` manifest only against its newly provisioned disposable database. Default demo staff include `hr.admin@nexiam.test`, `registrar.clerk@nexiam.test`, `guidance.counselor@nexiam.test` and `bsit.head@nexiam.test`, with local-only password `password`; onboarding/MFA requirements still apply. These are source seed defaults, not public-runtime credentials.

Expected initial state after that explicitly selected minimal seed: reference catalogs/current synthetic institution and cycle, synthetic staff, canonical material fixture, basic content/communication templates and suggestion lists. Do not describe it as all operational scenarios, existing production data, current-cycle operational acceptance, or all unfinished domain work being complete. The broader plain `db:seed` legacy demo chain failed fresh smoke in `OmrDemoSeeder` under current physical-sheet identity constraints; it is not recommended or silently repaired by this source cut. The separate institution-only seed refactor remains open.

Relevant local URLs are `/login`, `/apply`, `/applicant/login`, `/setup` and `/setup/application-suggestions`, subject to real authentication/capability rules. No seed/reset is required to publish this repository. Models and approval flags remain separately provisioned.

## Exercised evidence before final integration

- Isolated runtime route registration: 815 non-vendor route records; required applicant-login and push routes resolved from source.
- Intake frontend: 23 tests passed in 3 files after the two source fixes and actual shared-form regression; private disk TMPDIR and one worker avoid host temporary-cache quota pressure.
- Credential signatures: 4,556 source text files scanned, with zero provider-token/private-key-payload or exact old-active-VAPID-secret matches. Nondevelopment-looking assignments were reviewed: synthetic guarded demo fixtures or nonsecret storage/config literals. This is a bounded signature review, not proof that all possible secret/PII forms are absent.
- Initial isolated PHP run exposed missing private Vite build plus cancellation/test-fixture failures; those failures were not treated as a release pass. Final commands/results are recorded below after integration.

## Deployment prerequisite — not executed

Before deploying this source over the existing runtime, export/preserve the existing VAPID pair through a private operator process and configure the app and push-delivery workers with the same pair. Do not generate a replacement key for an existing installation: existing browser subscriptions depend on its identity. `APP_KEY`, database/private storage, queued messages and live asset closure must also be preserved independently.

The TabPFN checkpoint must be obtained under the publisher's authorized access/license and match the pinned identity/SHA-256 in `.env.example`/Compose. It is not reconstructed by building this project. No institutional/hosted/production model-use approval, real-provider execution, physical OMR acceptance, production deployment, full-suite acceptance or database backup is inferred from this source publication.

## Git integration boundary

Future public-source work should start from a fresh clone of published `master` or this isolated worktree. The original live workstation's local `master` still retains unpublished development history, including oversized model/old credential objects. Do not merge or push that history into the clean public branch; the verified private history backup preserves it. No local branch reference or live source checkout is rewritten by this publication.

## Final integrated verification

- Final isolated PHP suite: **50 passed / 575 assertions** across Web Push, application-suggestion persistence, Mock cancellation and public-intake authority. Disposable Docker runtime, one CPU/768MiB, no network, SQLite in memory; no public/shared database connection. Scoped Pint passed.
- Private full frontend build: **passed in 63.83 seconds** with a bounded 3GiB Node heap. The earlier 1.5GiB cap exhausted heap; it was not a source/build pass. Existing Svelte reactivity/accessibility and large-chunk warnings remain unsuppressed.
- Frontend regression: **23 passed / 3 files**. Actual shared-form saved-history, typed-value and explicit keyboard-selection propagation is covered.
- Actual fresh migration plus TAM migration and the documented minimal `DatabaseSeeder::testSeeders()` path completed on separate disposable SQLite files. The broader legacy demo chain fails at `OmrDemoSeeder` physical identity constraints and remains explicitly unsupported in quick start; no institutional seeder policy was weakened.
- Actual Chromium: normal local synthetic Registrar login → `/staff` → Applications → New Application; guided-tour dismissal; history type → ArrowDown/Enter selected the accessible named option. Desktop1440 and mobile390 were exercised; mobile document width390 matched viewport390. No page exceptions recorded. No application submission was performed.
- Authenticated real HTTP push-key request with no keys returned **503 / `vapid_configuration_unavailable`**. Configured PEM/raw-key success, mismatched-key rejection, delivery/revocation and unknown/foreign Mock cancellation are covered by the focused PHP suite.
- Bounded security and integration reviews identified raw-private-key matching and missing-run mapping issues; both were corrected and regression-tested. Raw scalar import now lets OpenSSL derive its public point rather than trusting an injected point; EC scalar/coordinates are padded to the required32-byte width.
- Verification containers/browser were stopped after smoke; private test databases/configuration/build output are ignored and excluded from Git. No live deployment, migration, seed, reset, key rotation or asset activation occurred.

No all-project type/lint/full-suite, PostgreSQL concurrency, external provider, actual-device push or physical OMR acceptance is claimed. Existing domain planning gaps remain their owning change's responsibility, not completed by a source publication.

## Publication receipt

Source commit **`89d5d95f78e185752c7b6b5f147cb2249caa5cb8`** was pushed normally to GitHub `master`, advancing it from `d1f1a10a1ae6336e28fa79c49d3f606f02a35541`; no force push. GitHub API confirmed the exact remote revision, unchanged default branch `master`, and browsable root README, current `DatabaseSeeder.php` and hardened `WebPushService.php`. The encrypted backup branch remains separate.

Final live health/login probes returned HTTP200 in0.201s/0.207s; host load3.37, root disk96%used with6.4GiBfree. These are single-request observations, not a load test. The original local branch/worktree and public deployment remain unchanged. This receipt-only documentation commit follows the verified source commit.
