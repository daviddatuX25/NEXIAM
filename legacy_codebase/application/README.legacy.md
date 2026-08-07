# 🛡️ NEXIAM: Role-Based College Admission Testing System

> HISTORICAL README — this file describes the legacy runtime. The current repository orientation is at `../../README.md`; the target contracts are under `../../docs/greenfield-foundation/`.

[![Laravel 12](https://img.shields.io/badge/Laravel-12.x-red.svg)](https://laravel.com)
[![Svelte 5](https://img.shields.io/badge/Svelte-5.x-orange.svg)](https://svelte.dev)
[![Inertia.js v2](https://img.shields.io/badge/Inertia.js-v2-purple.svg)](https://inertiajs.com)
[![Tailwind CSS v4](https://img.shields.io/badge/Tailwind_CSS-v4-38bdf8.svg)](https://tailwindcss.com)
[![PHP 8.4](https://img.shields.io/badge/PHP-8.4-777bb4.svg)](https://php.net)

NEXIAM is a comprehensive, zero-trust web application specifically engineered to streamline the college admission testing pipeline for the **Guidance and Registrar Offices at the Ilocos Sur Polytechnic State College (ISPSC) Tagudin Campus**. The system handles the entire applicant lifecycle—from registration and dynamic room scheduling to proctor monitoring, automated scoring, result sheet generation, and AI-assisted counseling.

---

## Table of Contents
- [Architecture and Greenfield Plan](#architecture-and-greenfield-plan)
- [System Overview and Architecture](#system-overview--architecture)
- [Existing System Features (V2 Baseline)](#existing-system-features-v2-baseline)
- [Planned Advanced Capstone Features (Future Roadmap)](#planned-advanced-capstone-features-future-roadmap)
- [Database Architecture and ERD](#database-architecture--erd)
- [Technology Stack and Environment](#technology-stack--environment)
- [NEXIAM Dokploy Deployment Guide](#nexiam--dokploy-deployment-guide)
- [Project Identity and Naming Policy](../../docs/project-identity.md)
- [Greenfield Foundation Suite README](../../docs/greenfield-foundation/README.md)
- [Greenfield Foundation — Catch-up and Continue Progress](../../docs/greenfield-foundation/00-catch-up-and-continue-progress.md)

---

## Architecture and Greenfield Plan

> **Strategic pivot (2026-08-01):** NEXIAM is being rebuilt as a clean-slate greenfield project. The legacy codebase in this repository is a read-only domain reference, not the execution target.

Start here and read in order:

| # | Document | Purpose |
|---|---|---|
| 1 | [Architecture Learning Guide](../../docs/nexiam-architecture-learning-guide.md) | Understand the domain: admission lifecycle, architectural vocabulary, database/auth/frontend reasoning, and how to evaluate changes with senior oversight. Start here. |
| 2 | [Greenfield Architecture Plan](../../docs/greenfield-architecture-plan.md) | The execution authority: strategic pivot rationale, technology decisions, capability boundaries, architectural rules, quality contract, and decision gates. |
| 3 | [Greenfield Implementation Order](../../docs/greenfield-implementation-order.md) | Concrete 14-phase build sequence: every step traced to the specific audit finding (P0.x, P1.x) it addresses, with dependency graph and decision-gate resolution order. |
| 4 | [Master Architecture Audit Blueprint](../../docs/master-architecture-picture.md) | Deep technical findings from the legacy codebase: measured system map, P0/P1/P2 findings, data integrity risks, security ledgers, target architecture, and capstone feature directions. |
| 5 | [Audit-to-Greenfield Mission Plan](../../docs/audit-refactor-plan.md) | Decision gate tracker and OpenSpec change vocabulary. |
| 6 | [Controller Audit](../../docs/audit-controllers.md) | Legacy controller responsibilities and workflow mapping (reference). |
| 7 | [Access-Control Inventory](../../docs/access-control-inventory.md) | Role/permission requirements and the three-way catalog divergence (reference). |
| 8 | [Architecture Audit](../../docs/architecture-audit.md) | Historical reconnaissance (reference; corrections in the master blueprint take precedence). |

Documents 1-3 are the path from understanding to execution. Documents 4-8 are the evidence base.

### Greenfield Foundation — Catch-up and Continue Progress

The documents above are historical architecture/audit inputs. For the current greenfield rebuild, use the foundation suite below as the approval and implementation-readiness path.

**If returning after a break, start here:** [00 — Catch-up and Continue Progress](../../docs/greenfield-foundation/00-catch-up-and-continue-progress.md)

Read the suite in this order:

| # | Foundation document | Purpose |
|---:|---|---|
| Index | [Foundation Suite README](../../docs/greenfield-foundation/README.md) | Full suite authority model, reading order, status vocabulary, and review protocol |
| 0 | [Catch-up and Continue Progress](../../docs/greenfield-foundation/00-catch-up-and-continue-progress.md) | Current position, blocked work, PM/Lead Engineer next actions, and checkpoint template |
| 1 | [Recovery and Authority Charter](../../docs/greenfield-foundation/01-recovery-and-authority-charter.md) | Evidence authority, review, promotion, and stop gates |
| 2 | [Evidence and Contradiction Register](../../docs/greenfield-foundation/02-evidence-and-contradiction-register.md) | Sources, conflicts, confidence, and unresolved validation |
| 3 | [Product Scope and Success Contract](../../docs/greenfield-foundation/03-product-scope-and-success-contract.md) | Product boundary, capstone promise, success criteria, and non-goals |
| 4 | [Role, Capability, and Data Scope Matrix](../../docs/greenfield-foundation/04-role-capability-and-data-scope-matrix.md) | Role authority, negative permissions, and data visibility |
| 5 | [Domain Model and Data Lifecycle](../../docs/greenfield-foundation/05-domain-model-and-data-lifecycle.md) | Identity, admission attempts, ownership, lifecycle, and invariants |
| 6 | [Workflows and State Transitions](../../docs/greenfield-foundation/06-workflows-and-state-transitions.md) | Actor-owned journeys and canonical state transitions |
| 7 | [Failure, Recovery, and Integration Contracts](../../docs/greenfield-foundation/07-failure-recovery-and-integration-contracts.md) | Retry, idempotency, compensation, recovery, and external adapters |
| 8 | [Security, Privacy, and Audit Contract](../../docs/greenfield-foundation/08-security-privacy-and-audit-contract.md) | Authentication, authorization, privacy, retention, and audit obligations |
| 9 | [Platform Topology and Development Standards](../../docs/greenfield-foundation/09-platform-topology-and-development-standards.md) | Runtime, Compose topology, quality gates, and operations standards |
| 10 | [Capstone Research and Technical Validation](../../docs/greenfield-foundation/10-capstone-research-and-technical-validation.md) | OMR, RAG, inference, pilot, and research evidence contracts |
| 11 | [Connected Vertical Slice and Acceptance](../../docs/greenfield-foundation/11-connected-vertical-slice-and-acceptance.md) | Minimum admission-to-release working version and acceptance scenarios |
| 12 | [Requirements Traceability Matrix](../../docs/greenfield-foundation/12-requirements-traceability-matrix.md) | Evidence → requirement → decision → workflow → acceptance coverage |
| 13 | [Decision and Batch Approval Matrix](../../docs/greenfield-foundation/13-decision-and-batch-approval-matrix.md) | 80 founder decisions, recommendations, consequences, and Go/No-Go gates |
| 14 | [Readiness Gates and Reconciled Roadmap](../../docs/greenfield-foundation/14-readiness-gates-and-reconciled-roadmap.md) | Conditions and sequencing for implementation |
| 15 | [Architecture Decision Record Catalog](../../docs/greenfield-foundation/15-architecture-decision-record-catalog.md) | ADRs to promote after founder approval |

Supporting diagrams:

- [Proposed domain ERD (editable Mermaid source)](../../docs/greenfield-foundation/assets/proposed-domain-erd.mmd) · [rendered SVG](../../docs/greenfield-foundation/assets/proposed-domain-erd.svg)
- [Proposed deployment topology (editable Mermaid source)](../../docs/greenfield-foundation/assets/proposed-deployment-topology.mmd) · [rendered SVG](../../docs/greenfield-foundation/assets/proposed-deployment-topology.svg)

The foundation suite remains `DRAFT — REVIEW REQUIRED`. No schema, authentication, or domain implementation should begin until the P0 decisions are approved and the readiness gate is passed.

## 🏢 System Overview & Architecture

NEXIAM transitionally automates manual admission processes into a secured digital workflow. The core engine models a strict status pipeline for applicants.

```mermaid
graph TD
    A[Application Submitted] --> B[Admitted / Accepted]
    B --> C[Scheduled for Exam]
    C --> D[Attendance Confirmed]
    D --> E[Scores Processed]
    E --> F[Results Released]
```

- **Role-Based Access Control (RBAC)**: Enforces strict isolation between roles (Applicants, Registrar Staff, Registrar Admins, Test Admins, Proctors, and Super Admins) via backend policy gating and middleware route filters.
- **Application Window Boundaries**: Limits applicant registration according to active academic year configurations and scheduling windows.

---

## 🛠️ Existing System Features (V2 Baseline)

### 1. Applicant / Examinee Portal
- **Account Setup & Security**: Applicants activate accounts and configure passwords using time-limited, secure tokens.
- **Application Tracking**: A real-time visual tracker displays the milestone status of their application.
- **Admission Slip Generation**: Render and download high-quality, print-ready PDFs of customized admission slips containing room information, date, and schedule.
- **AI Companion Widget**: An embedded interactive chat helper powered by local vector document embeddings to guide examinees through test rules, requirements, and office locations.
- **Portal Notifications**: View real-time notification alerts, with support for individual mark-as-read options.
- **Self-Service Application Editing**: Update personal data and course preferences if the assigned session status allows edit access.

### 2. Registrar Portal (Staff & Admins)
- **Application Pipeline**: Streamlined review interface to accept, reject, or bulk-reopen incoming applications with custom rejection reason logging.
- **Academic Year, Room, & Course Setup**: Manage active school years, enroll courses, and map rooms with precise seat capacity tracking.
- **CSV Application Ingest Engine**: Validate and import hundreds of student applications using custom Excel/CSV templates with analysis, preview, and confirmation stages.
- **Interactive Scheduling Assistant**: An advanced algorithmic scheduling assistant helping registrars dynamically batch applicants into sessions based on room availability and proctor constraints.
- **Analytical Reporting**: Export comprehensive application analytics reports in CSV/Excel format.

### 3. Guidance Portal (Proctors & Test Admins)
- **Aptitude Component Setup**: Configure distinct testing areas (e.g., verbal, math, abstract reasoning) alongside scoring formulas.
- **Direct Score Entry**: Grade walk-in applicants instantly via custom Direct Assessment tools.
- **CSV Score Ingestion**: Ingest and map examinee test sheets using OMR machine-readable score imports with a full preview and validation flow.
- **Counseling & Result Management**: Record consultation summaries and recommendation comments based on test score percentiles.
- **Result Templates & Print Engine**: Design result sheets using customizable HTML templates, complete with dynamic watermarks and PDF/DOCX bulk downloads.
- **Custom Rating Scales**: Set up custom rating metrics mapped to specific results sheet templates.

### 4. Proctor Portal (Room Operations)
- **Session Roster & Monitoring**: View assigned test sessions, check student information, and manage rosters.
- **Proctor Attendance Dashboard**: Mark attendee presence (individually or in bulk) at the exam room door.
- **Session Lifecycle Controls**: Start, extend, close, and submit exam session lists directly to the Test Admin.

### 5. System Administration Portal
- **User & Role Administration**: Manage system user accounts, roles, and credentials.
- **Google OAuth Integration**: Secure Google sign-in config for administrative staff.
- **Security Audit Logs**: Track sensitive operations (score updates, proctor assignments, template modifications) with IP, browser metadata, and before/after database snapshots. Exportable to CSV/Excel.
- **System Settings Configuration**: Manage global settings such as default slip templates and normalized score conversions.
- **AI Knowledge Sync**: Upload and sync PDF/Markdown knowledge base files to build vector embeddings for the AI Companion.
- **Notifications Hub**: Broadcast notifications to system users and mark notifications as read in bulk.
- **Profile Self-Service**: Edit personal administrative account details and passwords.

---

## 🔮 Planned Advanced Capstone Features (Future Roadmap)

> [!NOTE]
> The advanced scope is planned for **Phase 5 of the Capstone Roadmap** to fulfill the BSIT research objectives. The target research contract is now maintained in [GF-10](../../docs/greenfield-foundation/10-capstone-research-and-technical-validation.md); historical capstone evidence remains under [legacy_codebase/capstone](../capstone/).

### Zero-Trust Data Governance (Planned Goal)
- **Cryptographic Score Integrity**: When score entries are submitted, a SHA-256 HMAC signature lock will be calculated using a server-side secret key combined with:
  $$\text{HMAC} = \text{SHA256}(\text{Applicant UUID} + \text{Test Score} + \text{Proctor UUID}, \text{Secret Key})$$
  Any direct database tempering will trigger warning flags on the dashboard.
- **Immutable Write-Only Logs**: Security-critical actions will be committed to a dedicated, append-only log table that cannot be edited or deleted by application users.

### Offline Resilience & Computer Vision (Planned Goal)
- **Automated OMR Answer Sheet Ingestion**: Allows test admins to take a photo of shaded physical bubble sheets. An integrated computer-vision pipeline grades and imports applicant scores automatically.
- **Offline Proctor PWA**: A Progressive Web App (PWA) that allows proctors to scan examinees offline during network drops, with IndexedDB caching and background synchronization.

### AI-Powered Office Operations (Planned Goal)
- **In-Office RAG Copilot**: A secure local AI assistant for guidance counselors. Staff can query statistics (e.g. *"Identify the percentage of applicants who failed the Math component but passed the English component in Room 101"*) using natural language.
- **Automated Scheduling Agent**: An optimization algorithm that schedules applicants into exam sessions without manual room allocation conflicts.

### Multi-Tenant Scalability (Planned Goal)
- **Database Segregation**: Preconfigured database multi-tenancy, ensuring the platform can scale to separate campuses or other SUCs while keeping student records isolated under the Philippine Data Privacy Act (DPA).

---

## 💾 Database Architecture & ERD

The core data structures are designed around the following models:

| Model | Table Name | Key Associations / Purpose |
|---|---|---|
| **User** | `users` | System staff, proctors, and administrators. Associated with `roles`. |
| **Applicant** | `applicants` | The student credential entity. Belongs to `Application` and belongs to many `ExamSessions` and `GradingSessions`. |
| **Application** | `applications` | Student registration profile (names, preferences, GWA, status). |
| **ExamSession** | `exam_sessions` | Testing sessions, associated with a `Room` and an `AcademicYear`. Has many proctors (`User`) and `Applicants`. |
| **GradingSession** | `grading_sessions` | Tracks sessions ready for scoring and release. |
| **ApplicantScore** | `applicant_scores` | Scores per aptitude component. |
| **AptitudeArea** | `aptitude_areas` | Specific exam sections (Math, Verbal, Abstract, etc.) with custom weights. |
| **ConsultationSummary** | `consultation_summaries` | Guidance counseling notes and recommendation files. |
| **AuditLog** | `audit_logs` | Write-only security audit trail. |
| **KnowledgeDocument** | `knowledge_documents` | Embeddings files uploaded for the AI Companion context. |
| **ResultSheetTemplate** | `result_sheet_templates` | Customize printed result document layouts. |

---

## 💻 Technology Stack & Environment

- **Backend**: [Laravel 12](https://laravel.com) / PHP 8.4 (using constructor property promotion and explicit return type declarations)
- **Frontend**: [Inertia.js v2](https://inertiajs.com) / [Svelte 5](https://svelte.dev) / [Tailwind CSS v4](https://tailwindcss.com) / [shadcn-svelte](https://shadcn-svelte.com)
- **Database**: MySQL / MariaDB
- **Ecosystem Tools**: Laravel Prompts, Laravel Pail, Laravel Pint, PHPUnit 11
- **Local Environment**: Laragon (Windows native hosting)

---



## 🚢 NEXIAM — Dokploy Deployment Guide

> Minimal deployment: App Container + MySQL. No Redis needed for demo.
> Everything is automated — migrations, seeders, and storage symlink all run on first boot.

### Stack
- **App:** Built from `Dockerfile` via Docker Compose (`compose.prod.yaml`)
- **Database:** MySQL 8.4 (provisioned automatically by Compose)
- **Persistent Storage:** Docker volume mounted at `/var/www/html/storage`

### Step 1 — Create a Compose Service in Dokploy
1. Go to your Dokploy dashboard → **Create Service** → **Compose**.
2. Connect your GitHub account and select this repository.
3. Set the **Compose File** to `compose.prod.yaml`.

### Step 2 — Set Environment Variables
In the Compose service's **Environment** tab, paste the contents of [`.env.dokploy.example`](.env.dokploy.example) and fill in these required values:

| Variable | What to put |
|---|---|
| `APP_URL` | Your public domain (e.g. `https://nexiam.yourdomain.com`) |
| `APP_KEY` | Generate with `php artisan key:generate --show` and paste the output |
| `DB_PASSWORD` | Any strong password for your MySQL root user |
| `SUPER_ADMIN_PASSWORD` | Your desired admin login password |
| `OPENROUTER_API_KEY` | *(Optional)* AI Scheduling Assistant |
| `MIXEDBREAD_API_KEY` | *(Optional)* AI Knowledge Companion |

### Step 3 — Deploy
Click **Deploy**. On first boot the container will automatically:
- ✅ Wait for MySQL to be healthy
- ✅ Run all database migrations
- ✅ Seed all default data (roles, courses, rooms, rating scale, Super Admin account)
- ✅ Create the storage symlink
- ✅ Rebuild config cache

Once the logs show the container is running, open `APP_URL` in your browser and log in with your `SUPER_ADMIN_EMAIL` and `SUPER_ADMIN_PASSWORD`.

### Notes
- **Redeployments are safe** — migrations only apply new changes; seeders are idempotent.
- **Uploaded files and logs persist** across redeployments via the Docker volume.
- **LibreOffice & Chromium** are pre-installed in the Docker image. No extra setup needed for PDF generation.
- For detailed architecture notes see [`legacy_codebase/superpowers/specs/2026-06-26-dokploy-deployment-design.md`](../superpowers/specs/2026-06-26-dokploy-deployment-design.md).

