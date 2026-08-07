# 01 — Legacy Runtime Inventory

**Runtime root:** `../../legacy_codebase/application/`  
**Purpose:** identify the complete old application boundary and the evidence each area can provide

## 1. Moved runtime boundary

The old application was moved together rather than split by framework layer:

```text
legacy_codebase/application/
├── app/                    Laravel domain/application code
├── artisan                 CLI entry point
├── bootstrap/              Laravel bootstrap
├── config/                 Runtime configuration
├── database/               Legacy migrations, factories, seeders
├── public/                 Web entry point and public assets
├── resources/              Inertia/Svelte pages, layouts, components, styles
├── routes/                 HTTP/console route definitions
├── tests/                  Existing legacy test material
├── result_sheet_templates/ Runtime document/template assets
├── storage/                Legacy storage structure and evidence files
├── composer.json/lock      PHP dependency authority for the old runtime
├── package.json/lock       Frontend dependency authority for the old runtime
├── compose*.yaml           Historical local/deployment topology
├── Dockerfile              Historical container build
├── scripts/                Historical tooling and helper scripts
├── INSTALL.md              Historical installation guide
├── README.md               This runtime's orientation
└── README.legacy.md        Original historical project README
```

Root-level greenfield documents, OpenSpec changes, repository guidance, and Git metadata intentionally remain outside this runtime.

## 2. Recorded source inventory

The current moved runtime contains the following measured source groups:

| Area | Count | Evidence use |
|---|---:|---|
| Controllers | 41 PHP files | Historical route/actor/page responsibilities; not target module boundaries |
| Models | 25 PHP files | Legacy fields/relations and data vocabulary; not target aggregate authority |
| Services | 35 PHP files | Integration/behavior clues; inspect for transaction and ownership risks |
| Migrations | 86 PHP files | Schema history and data-mapping evidence; re-express the required invariants in one fresh PostgreSQL history |
| Svelte pages | 85 files | User journey, copy, layout, and state evidence; not UI authority |
| Svelte components | 109 files | Existing interaction/component patterns; reconcile with GF-22 |

Counts are a snapshot, not a quality claim. Re-run the manifest/inventory command after any legacy change.

## 3. Relocation verification

Executed from `legacy_codebase/application/` on 2026-08-03:

- `npm run test` — passed; Vitest reported no test files.
- `npm run build` — passed; Vite produced `public/build` in the moved runtime.
- `npm run check` — failed with 1,073 existing Svelte diagnostics and 206 warnings.
- `npm run lint` — failed with 3,200 existing problems; generated build output was included in the legacy lint scope.
- `npm run format:check` — failed with 243 existing formatting warnings.
- PHP/Composer verification — not run because this host has no `php` or `composer` binary.

These failures are recorded as legacy baseline debt. They are not treated as greenfield acceptance evidence and were not repaired during relocation.

## 4. Key evidence areas

| Legacy area | Representative paths | Evidence question |
|---|---|---|
| Admission intake | `app/Http/Controllers/ApplicationController.php`, `app/Services/ApplicationPipelineService.php`, `app/Models/Application.php`, `app/Models/Applicant.php` | What fields, milestones, correction/reopen behavior, and applicant-facing status were previously expected? |
| Registrar intake/import | `app/Http/Controllers/Admin/ApplicationImportController.php`, `resources/js/Pages/Applications/` | What validation, preview, bulk-action, and error feedback did staff rely on? |
| Examination | `app/Http/Controllers/Admin/ExamSessionController.php`, `app/Services/ExamSchedulingService.php`, `app/Models/ExamSession.php` | What scheduling/session/room/proctor workflows existed, and which must be rewritten for capacity/deadline safety? |
| Proctor operations | `app/Http/Controllers/Proctor/`, `resources/js/Pages/Proctor/` | What assigned-session actions, attendance, submission, and degraded states are needed? |
| Assessment/grading | `app/Http/Controllers/Grading/`, `app/Services/GradingSessionService.php` | Which manual/import experiences should be preserved while score ownership/versioning is rebuilt? |
| Release/documents | `app/Http/Controllers/Release/`, `app/Services/PrintBatchService.php`, `result_sheet_templates/` | Which result modes, artifacts, print layouts, and notification outcomes are real user expectations? |
| Notifications | `app/Notifications/`, `app/Http/Controllers/NotificationController.php` | What content intent exists, and what delivery/read semantics must be made durable? |
| AI/RAG/decision support | `app/Http/Controllers/Portal/AiCompanionController.php`, provider services, legacy research | What is useful as a bounded proposal, and what must never mutate authority? |
| Authorization | `app/Http/Middleware/`, `app/Policies/`, `routes/web.php` | Where did role strings, policies, and scope assumptions diverge? |
| Frontend shells | `resources/js/Layouts/`, `resources/js/Components/`, `resources/js/Pages/` | Which visual/interaction patterns should inform GF-22, and which must be redesigned? |

## 5. What is intentionally outside the runtime move

- `docs/greenfield-foundation/` remains the canonical target reference;
- `docs/legacy-reference/` remains the replication/crosswalk layer;
- `openspec/changes/` remains the executable target-change layer;
- `.github/` and root `CLAUDE.md` remain repository/agent governance;
- `manuscript/` remains research/manuscript evidence;
- Git metadata and ignored local environment files were not inspected or rewritten.

This separation prevents historical code, research files, and target contracts from becoming one undifferentiated “source of truth.”
