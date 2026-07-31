# NEXIAM — Master Architecture Picture
## The Full View: What Exists · What's Broken · What Best Looks Like

**Compiled:** 2026-07-30
**Sources:** Architecture Audit (#1) + Access Control Inventory (#2) + Controller Audit (#3)
**Purpose:** One place to see the whole system, so refactoring decisions have a single reference.

---

## PART 0 — THE SYSTEM AT A GLANCE

```
┌─────────────────────────────────────────────────────────────────┐
│  NEXIAM — College Admission Testing System (ISPSC Tagudin)      │
│  Predecessor: SecureCAT-v2 (working system, polished, reused)   │
├─────────────────────────────────────────────────────────────────┤
│  STACK                                                          │
│  PHP 8.4 · Laravel 12 · Inertia 2 · Svelte 5 · Tailwind 4       │
│  shadcn-svelte UI kit (50+ components)                          │
├─────────────────────────────────────────────────────────────────┤
│  SCALE                                                          │
│  41 controllers (9,122 lines) · 34 services (8,500 lines)       │
│  25 models · 81 Svelte pages (~25,000 lines) · 50+ migrations   │
│  100+ routes · ~220 controller methods                          │
│  Tests: 0 (no tests/ directory exists)                          │
├─────────────────────────────────────────────────────────────────┤
│  CURRENT ROLES (5):  super_admin, registrar_administrator,      │
│  test_administrator, staff, proctor                             │
│  TARGET ROLES (9+overlay):  system_it_admin, registrar_admin,   │
│  registrar_clerk, registrar_support_staff, guidance_counselor,  │
│  guidance_clerk, guidance_support_staff, program_head,          │
│  proctor (duty overlay), applicant                              │
└─────────────────────────────────────────────────────────────────┘
```

---

## PART 1 — HOW THE APP ACTUALLY WORKS TODAY (Current Map)

```
                        ┌──────────────────────────┐
                        │      routes/web.php      │
                        │  328 lines, 12 role      │
                        │  middleware groups       │
                        └───────────┬──────────────┘
                                    │
              ┌─────────────────────┼─────────────────────┐
              │                     │                     │
   ┌──────────▼──────────┐  ┌───────▼────────┐  ┌────────▼─────────┐
   │   STAFF SIDE        │  │  PORTAL SIDE   │  │   PUBLIC SIDE    │
   │  Auth:web guard     │  │ Auth:applicant │  │  (no auth)       │
   │  AuthController     │  │ guard          │  │  HomeController  │
   │  (email+password)   │  │ PortalAuthCtrl │  │  Application::   │
   │                     │  │ (email+pass,   │  │  create/store    │
   │  41 controllers     │  │  token setup)  │  │                  │
   │  2 GOD CONTROLLERS  │  │                │  │  GoogleAuth      │
   └──────────┬──────────┘  └───────┬────────┘  └──────────────────┘
              │                     │
              ▼                     ▼
   ┌──────────────────────────────────────────────┐
   │               SERVICE LAYER (34)             │
   │  Deep & good: ApplicationPipelineService     │
   │  (10-state state machine), AuditService      │
   │  (100+ events, redaction), ExamScheduling    │
   │  Assistant, GradingSessionService            │
   │                                              │
   │  Shallow/needs work: DashboardService,       │
   │  AiCompanionService (hardcoded AI fallback)  │
   └──────────────────────────────────────────────┘
              │
              ▼
   ┌──────────────────────────────────────────────┐
   │               DATABASE (25 models)           │
   │  Roles: DB-driven (extensible ✅)            │
   │  FKs: inconsistently defined ⚠️              │
   │  Bandaid migrations: nullable fixes ⚠️       │
   └──────────────────────────────────────────────┘
```

---

## PART 2 — EVERYTHING THAT'S WRONG (Consolidated, Deduplicated)

All findings from all 3 audits, grouped by system layer. **22 distinct issues.**

### A. TESTING & VERIFICATION (the biggest gap)

| # | Issue | Severity | Source |
|---|---|---|---|
| A1 | **Zero automated tests.** 41 controllers, 34 services, 100+ routes, 0 tests. No tests/ directory. | 🔴 | Arch §8 |
| A2 | No regression safety → every refactor is a leap of faith. Minimum suite: ~42 tests before P0 work. | 🔴 | Arch §8 |

### B. CONTROLLER LAYER

| # | Issue | Severity | Source |
|---|---|---|---|
| B1 | **ApplicationController god class** — 1245 lines, 30 methods, 5+ responsibilities (CRUD + workflow + portal + slips + public apply). Split plan exists but never executed. | 🔴 | Arch §4, Ctrl §1 |
| B2 | **ExamSessionController god class** — 1012 lines, 34 methods, 4+ responsibilities (CRUD + workflow + roster + monitoring). Split plan exists but never executed. | 🔴 | Arch §4, Ctrl §2 |
| B3 | **37 N+1 queries** in ExamSessionController; 16 in ApplicationController; 14 in ReleasePrintController. | 🔴 | Arch §6 |
| B4 | 9 controllers use `app(Service::class)` service locator instead of constructor DI (Application, ExamSession, PortalAuth, Release, SessionRoster, Grading, DirectAssessment, Setup, Auth). | 🟡 | Ctrl §9 |
| B5 | **27 of 41 controllers (66%) have zero service dependencies** — model queries inline in controller methods. | 🟡 | Ctrl §8 |
| B6 | PortalAuthController mixes 3 concerns: auth (9 methods) + dashboard (3) + admission slip (2) in 501 lines. | 🔴 | Ctrl §4 |
| B7 | SetupController: 599 lines of 11 identical `checkX()` health methods — should be SystemHealthService. | 🔴 | Ctrl §3 |
| B8 | SessionRosterController: 487 lines, only 8 methods, but each method 60-80 lines of inline logic. | 🟡 | Ctrl §6 |
| B9 | Missing return types on many methods despite PHP 8.4. | 🟡 | Arch §10.3 |

### C. AUTH & AUTHORIZATION

| # | Issue | Severity | Source |
|---|---|---|---|
| C1 | **Dual auth guards** (web for staff, applicant for portal) — duplication of login/logout/reset/setup across two controllers. Should share a base. | 🔴 | Arch §1 |
| C2 | **Proctor as permanent role, not duty overlay.** Checked via `role:...,proctor,...` middleware in 5 route groups. NEXIAM requires session-scoped assignment via `exam_session_user` pivot + Policy. | 🔴 | Arch §2.2, AC §5 |
| C3 | **12 separate role middleware groups** in routes/web.php with overlapping role lists — hard to reason about who-can-access-what. | 🟡 | Arch §3 |
| C4 | Role naming confusion: 3+ renames in history (test_administrator → registrar_administrator → back), current names don't match NEXIAM terminology. | 🟡 | Arch §2.3 |
| C5 | Portal token setup expiry enforced only in controller, not at DB level (`expires_at` column missing). | 🟡 | Arch §1.2 |
| C6 | **test_administrator is one merged role** covering both clerk + counselor functions — must split per settled model. | 🔴 | AC §5 |
| C7 | registrar_support_staff, guidance_support_staff, program_head roles completely absent (role, routes, policies, screens). | 🔴 | AC §5 |

### D. SERVICE LAYER

| # | Issue | Severity | Source |
|---|---|---|---|
| D1 | Hardcoded AI model fallback: `AiCompanionService` line 251 falls back to `openrouter/free` silently — fail-loud instead. | 🔴 | Arch §5 |
| D2 | `DB::table()` bypass in AiCompanionService::resolveCourseName() — skips Eloquent model behavior. | 🟡 | Arch §6.2 |
| D3 | Several shallow services — DashboardService, AiCompanionService — thin pass-throughs or mixed concerns. | 🟡 | Arch §5, Ctrl §8 |

### E. DATABASE

| # | Issue | Severity | Source |
|---|---|---|---|
| E1 | Foreign key constraints inconsistently defined (e.g. exam_session_applicant). Orphan risk. | 🟡 | Arch §7.2 |
| E2 | Bandaid migrations (`make_scores_nullable`, `make_room_nullable`) — schema corrections after the fact. | 🟡 | Arch §7.3 |
| E3 | Missing columns for NEXIAM scope: `audit_logs.chain_hash`, `applicants.identifier_key`. | 🟡 | Plan §5 |

### F. FRONTEND

| # | Issue | Severity | Source |
|---|---|---|---|
| F1 | **806-line Release/Index.svelte** monster component; 708-line Applications/Index.svelte; 5 components >600 lines. | 🔴 | Arch §9.1 |
| F2 | Create/Edit duplication: 10+ near-identical form page pairs (e.g. AptitudeAreas 660/659 lines). Should be shared `<Form mode="create|edit">`. | 🟡 | Arch §9.2 |

### G. SECURITY & COMPLIANCE (NEXIAM-scope items)

| # | Issue | Severity | Source |
|---|---|---|---|
| G1 | **No HMAC-SHA256 chaining on audit logs** — required by NEXIAM scope, currently flat log. | 🟡 | Arch §10.1 |
| G2 | No rate limiting, no API versioning, no error monitoring. | 🟡 | Arch exec |
| G3 | Applicant auth is email+password — NEXIAM requires **identifier key** login. | 🔴 | Plan decisions |

---

## PART 3 — WHAT'S ALREADY GOOD (Protect These)

| # | Good Pattern | Why It Matters |
|---|---|---|
| ✅1 | **DB-driven roles** (`roles` table + `role_user` pivot + `firstOrCreate`) — new NEXIAM roles need no code changes, just seed data. | Saves the whole RBAC refactor. |
| ✅2 | **ApplicationPipelineService** — proper 10-state state machine with forward-only guard, well-documented. The model for all services. | Already the "deep module" ideal. |
| ✅3 | **AuditService** — 100+ labeled events, secret redaction, polymorphic actors, auto-summary. Just needs HMAC chaining. | Foundation for G1. |
| ✅4 | **AI Companion security guards** — blocks code-gen, prompt injection, unsafe content. | Defense-in-depth done right. |
| ✅5 | **shadcn-svelte UI kit** — 50+ organized components. | Frontend consistency. |
| ✅6 | **DashboardController** — 47 lines, constructor DI, 2 services, all logic in services. The gold standard to replicate. | The target shape of every controller. |
| ✅7 | **Import controllers** (ScoreImport, ApplicationImport) — proper DI, analyze → preview → confirm workflow. | The pattern for new features. |
| ✅8 | **FormRequest classes** used widely — validation out of controllers. | Correct Laravel practice. |
| ✅9 | **Legacy split plans** for both god controllers exist with TDD steps (May 2026). | Refactor is already designed. |

---

## PART 4 — WHAT "BEST" LOOKS LIKE (Target Architecture)

```
┌────────────────────────────────────────────────────────────────┐
│                     TARGET: NEXIAM                             │
├────────────────────────────────────────────────────────────────┤
│                                                                │
│  ROUTES  →  thin, 3-4 auth groups only ("authenticated")       │
│            authorization via Policies (per-object), not        │
│            role lists in middleware                            │
│                                                                │
│  CONTROLLERS → all ≤ 150 lines, ≤ 8 methods                    │
│            constructor DI (no app() in controllers)            │
│            one responsibility per class                        │
│                                                                │
│  SERVICES   → deep modules, small interfaces                   │
│            ALL business logic lives here                       │
│            domain workflows as state machines                  │
│                                                                │
│  POLICIES   → per-resource: ExamSessionPolicy,                 │
│            ApplicationPolicy, GradingSessionPolicy,            │
│            ProctorPolicy (session-scoped via pivot)            │
│                                                                │
│  PERMISSIONS → Spatie, permission set = test_admin (not role)  │
│            role ↔ permission mapping in admin UI               │
│                                                                │
│  AUDIT      → HMAC-SHA256 chained, verification endpoint       │
│                                                                │
│  TESTS      → ~42 minimum: 24 feature, 9 policy, 9 unit        │
│            pyramid: unit (services) > feature (workflows)      │
│            > e2e (5-10 Playwright journeys)                    │
│                                                                │
│  FRONTEND   → shared form components (no Create/Edit dupe)     │
│            no component > 400 lines                            │
│                                                                │
│  AUTH       → shared auth base, two guards kept,               │
│            applicant login by identifier key                   │
│                                                                │
│  ROLES      → 9 permanent + 2 seasonal + proctor overlay       │
│            program_head dashboard built                        │
│            guidance_counselor = inherent test_admin perms      │
└────────────────────────────────────────────────────────────────┘
```

---

## PART 5 — THE GAP: CURRENT → TARGET (What Must Happen)

```
CURRENT STATE                     TARGET STATE
──────────────────────────────    ──────────────────────────────
2 god controllers (2200+ lines)   ~9 focused controllers (~1600 total)
12 role middleware groups         3-4 auth groups + Policies
5 roles, proctor-as-role          9 roles + overlay, proctor-as-duty
0 tests                           ~42 tests minimum
email+password portal auth        identifier key auth
flat audit log                   HMAC-SHA256 chained audit
app() service locator (9 ctrls)   constructor DI everywhere
27 ctrls w/ no services           services behind every controller
Create/Edit page duplication      shared form components
5 monster Svelte components       none > 400 lines
test_administrator merged role    split: counselor + clerk perms
no program_head anything          role + routes + policies + dashboard
```

---

## PART 6 — THE ROADMAP (Post-Audit Sequence)

```
PHASE B — SPECS
  • Spatie permission catalog (per settled access-control matrix)
  • ADRs for: roles, proctor overlay, identifier auth, HMAC chain
  • Refactor scope document (this picture → actionable tickets)

PHASE C — P0 REFACTORS (foundation)
  C1. Tests FIRST: ~42 tests (lifecycle, auth, policy, pipeline)
  C2. Split ApplicationController → 5 controllers (plan exists)
  C3. Split ExamSessionController → 4 controllers (plan exists)
  C4. N+1 fixes: eager loading + withCount
  C5. Proctor middleware fix → Policy-based, pivot-scoped
  C6. Role migration: rename/split to settled NEXIAM roles
  C7. Constructor DI across the 9 service-locator controllers

PHASE D — P1 FEATURES
  D1. HMAC audit chain (chain_hash + verify endpoint)
  D2. Identifier key auth for applicants
  D3. Email notifications (schedule, results, status)
  D4. In-app messaging

PHASE E — P2 NEW BUILDS
  E1. OMR engine (computer-vision scoring)
  E2. RAG chatbot (policy-only, existing guards reused)
  E3. Admission support engines (Yukee cluster, BGE interest)
  E4. Triage Dashboard (dual-panel counselor view)
  E5. Program Head dashboard

PHASE F — DELIVERY
  F1. Fresh GitHub repo (daviddatuX25/NEXIAM), clean history
  F2. E2E journeys, deployment docs, defense materials
```

---

## PART 7 — ONE-LINE SUMMARY

> **NEXIAM is a working, feature-rich system with a solid domain core (pipeline state machine, audit service, DB-driven roles) wrapped in a crust of god controllers, inline queries, zero tests, and role-middleware spaghetti. The refactor is not a rebuild — it's carving the existing good core into clean layers: thin controllers → deep services → policy-based authorization → tested everything.**

---

*Compiled from docs/architecture-audit.md, docs/access-control-inventory.md, docs/audit-controllers.md. Updates as audits #4-8 complete.*
