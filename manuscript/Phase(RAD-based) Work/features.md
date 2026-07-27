# NEXIAM Feature Inventory — From SecureCAT Codebase to NEXIAM Scope

**Date:** 2026-07-14
**Source:** SecureCAT v2 codebase (routes, models, controllers, Svelte pages) + NEXIAM Scope & Limitations (Manuscript Working Sections)
**Purpose:** Exhaustive mapping of what's built, what to extend/correct, and what to build from scratch. This is NOT an interview guide — it's a reference for the development team.

---

## SECTION A: BUILT — Carry Forward As-Is

These SecureCAT features match NEXIAM's scope directly. Minimal or no changes needed.

### A.1 Staff Authentication & Profile

| Feature | SecureCAT Code | NEXIAM Status |
|---|---|---|
| Staff login (email + password) | `AuthController@create/store`, `auth.login` route | CARRY FORWARD |
| Staff logout | `AuthController@destroy` | CARRY FORWARD |
| Google OAuth login | `GoogleAuthController@redirect/callback` | CARRY FORWARD |
| Profile management (self-service) | `ProfileController@edit/update`, `/profile` routes | CARRY FORWARD |
| Password change | `ProfileController@updatePassword` | CARRY FORWARD |

### A.2 Public Application Form

| Feature | SecureCAT Code | NEXIAM Status |
|---|---|---|
| Public application submission | `ApplicationController@create/store`, `/apply` route | CARRY FORWARD |
| Success confirmation page | `ApplicationController@success` | CARRY FORWARD |
| Privacy policy display | `PrivacyPolicyController@active` API endpoint | CARRY FORWARD |

### A.3 Applications Management

| Feature | SecureCAT Code | NEXIAM Status |
|---|---|---|
| Applications index (table view) | `ApplicationController@index`, `Applications/Index.svelte` | CARRY FORWARD |
| Create application (staff-side) | `ApplicationController@create/storeAdmin` | CARRY FORWARD |
| Edit application | `ApplicationController@edit/updateAdmin` | CARRY FORWARD |
| View application details | `ApplicationController@show` | CARRY FORWARD |
| Accept application | `ApplicationController@accept` | CARRY FORWARD |
| Dismiss application | `ApplicationController@dismiss` | CARRY FORWARD |
| Reopen application | `ApplicationController@reopen` | CARRY FORWARD |
| Bulk accept/dismiss/reopen | `ApplicationController@bulkAccept/bulkDismiss/bulkReopen` | CARRY FORWARD |
| Bulk applicant import (CSV) | `ApplicationImportController` — analyze, preview, confirm, template | CARRY FORWARD |
| Admission slip generation (single) | `ApplicationController@admissionSlip/admissionSlipPrint` | CARRY FORWARD |
| Admission slip generation (bulk) | `ApplicationController@admissionSlipBulkPrint/admissionSlipBulkPdf` | CARRY FORWARD |
| Resend setup email | `ApplicationController@resendSetupEmail` | CARRY FORWARD |

### A.4 Institutional Administration

| Feature | SecureCAT Code | NEXIAM Status |
|---|---|---|
| User management CRUD | `UserController` — index, create, edit, destroy | CARRY FORWARD |
| Course management CRUD | `CourseController` — index, create, edit, activate, deactivate, restore | CARRY FORWARD |
| Room management CRUD | `RoomController` — index, create, edit, activate, deactivate, restore | CARRY FORWARD |
| Academic year CRUD | `AcademicYearController` — index, create, edit, activate, deactivate | CARRY FORWARD |
| Privacy policy CRUD | `PrivacyPolicyController` — index, create, edit, activate, deactivate | CARRY FORWARD |
| Institution branding | `InstitutionController@index/update/resetDefaults` | CARRY FORWARD |
| System settings | `SettingsController@index/update` | CARRY FORWARD |
| Reports index + export | `ReportController@index/export` | CARRY FORWARD |

---

## SECTION B: BUILT — Extend or Correct for NEXIAM

These features exist in SecureCAT but need changes to align with NEXIAM's Scope & Limitations.

### B.1 Applicant Portal

| SecureCAT Has | NEXIAM Needs | Action |
|---|---|---|
| Token-based account setup (`/portal/setup/{token}`) | Identifier key authentication (unique ID given at registration) | **MODIFY**: Replace token setup with identifier key login |
| AI Companion widget (RAG with course recommendations) | RAG Chatbot — policy Q&A ONLY, declines below confidence, no course recs | **REPLACE**: AI Companion widget becomes policy-only RAG Chatbot |
| Portal dashboard (status tracker, admission slip) | Same status tracker + admission slip + ADD: email notification preferences, mobile-first responsive | **EXTEND**: Add notification prefs, ensure mobile layout |
| Application editing (profile data) | Same — applicant can edit own application | CARRY FORWARD |
| Admission slip PDF download | Same | CARRY FORWARD |

### B.2 Exam Sessions & Scheduling

| SecureCAT Has | NEXIAM Needs | Action |
|---|---|---|
| Exam session lifecycle (create → publish → start → complete → reopen → cancel) | Same lifecycle | CARRY FORWARD |
| AI Schedule Assistant (chat-based, propose → human confirm) | Human-gated AI scheduling (same pattern) | CARRY FORWARD — rename from "Schedule Assistant" to "Scheduling Assistant" |
| Session assignment (assign/remove applicants) | Same | CARRY FORWARD |
| Exam monitoring dashboard | Same | CARRY FORWARD |
| — | Proctor as DUTY OVERLAY (not distinct role) — any staff can be assigned proctor temporarily | **ADD**: Proctor assignment mechanism that doesn't change role |
| — | Schedule visibility for Program Heads (see their program's exam sessions) | **ADD**: Program Head schedule view |

### B.3 Proctoring

| SecureCAT Has | NEXIAM Needs | Action |
|---|---|---|
| My Sessions list for proctors | Same | CARRY FORWARD |
| Session Roster (attendance, submissions) | Same + offline resilience via service-worker | **EXTEND**: Add PWA service-worker caching for offline attendance |
| Attendance recording (individual + bulk) | Same | CARRY FORWARD |
| Session start/close/extend | Same | CARRY FORWARD |

### B.4 Grading & Scoring

| SecureCAT Has | NEXIAM Needs | Action |
|---|---|---|
| Grading sessions with workflow states | Same session structure | CARRY FORWARD |
| Manual score input (per-applicant) | Same — counselor can manually enter scores | CARRY FORWARD |
| CSV score import (`ScoreImportController` — analyze, preview, confirm) | **Computer-vision OMR** — scan actual answer sheets, detect bubbles, grade automatically | **REPLACE**: CSV import becomes real OMR image scanning |
| Score editing/deleting | Same — with audit trail | CARRY FORWARD |
| Aptitude areas (sub-test definitions) | Keep for ICAT sub-test mapping (Math, Logic, Reading, Subject-specific) | CARRY FORWARD |
| Rating scales | Same — used for score interpretation | CARRY FORWARD |

### B.5 Release Management

| SecureCAT Has | NEXIAM Needs | Action |
|---|---|---|
| Release/unrelease summaries (individual + bulk) | Same | CARRY FORWARD |
| Result sheet printing (PDF/DOCX, individual + bulk) | Same | CARRY FORWARD |
| Result sheet templates (DOCX merge) | Same | CARRY FORWARD |
| — | Email notification on release | **ADD**: Trigger email to applicant when results are released |

### B.6 Audit Trails

| SecureCAT Has | NEXIAM Needs | Action |
|---|---|---|
| Audit log viewer (`AuditLogController@index/export`) | Same | CARRY FORWARD |
| Event logging (actor, IP, user agent, before/after states) | **HMAC-SHA256 cryptographic chaining** — each event hash includes previous event hash, making tampering structurally detectable | **UPGRADE**: Add HMAC-SHA256 chaining to existing audit log model |
| — | Counselor placement decisions logged with engine name + version | **ADD**: Engine-specific audit metadata |

### B.7 Notifications

| SecureCAT Has | NEXIAM Needs | Action |
|---|---|---|
| In-app notifications (staff + portal) | Same in-app notifications | CARRY FORWARD |
| — | **Email notifications** — schedule, results, status updates | **ADD**: Email notification channel |
| — | In-app fallback messaging (applicant → staff when chatbot can't answer) | **ADD**: Lightweight messaging thread |

### B.8 Roles & RBAC

| SecureCAT Has | NEXIAM Needs | Action |
|---|---|---|
| `super_admin` | `System IT Administrator` | **RENAME** |
| `registrar_administrator` | `Registrar Administrator` — same scope | CARRY FORWARD |
| `test_administrator` | Split into `Guidance Counselor` + `Guidance Administrator` | **SPLIT**: Two distinct roles with different permissions |
| `proctor` | Proctor as duty overlay (assigned per session, not permanent role) | **REFACTOR**: Remove as permanent role, add assignment mechanism |
| `staff` | `Registrar Staff` | **RENAME** |
| `applicant` | Applicant | CARRY FORWARD |
| — | **Program Head** (new role) — view quota pipeline for their program, review cluster matches | **ADD** |
| — | **Account creation delegated per office** — Guidance Admin creates guidance accounts, Registrar Admin creates registrar accounts | **ADD**: Scoped user creation |

### B.9 Direct Assessment (Walk-in Scoring)

| SecureCAT Has | NEXIAM Needs | Action |
|---|---|---|
| Direct assessment creation + scoring (`DirectAssessmentController@create/store`) | Same — walk-in applicant scoring, polished for faster pipeline | **EXTEND**: Polish the flow, integrate with improved one-go import pipeline. Assign applicants during import for faster batch processing. |

---

## SECTION C: NOT BUILT — Build from Scratch

These features exist in NEXIAM's Scope & Limitations but have NO equivalent in SecureCAT.

### C.1 Computer-Vision OMR Scoring

| What it is | Manuscript reference | Dependencies |
|---|---|---|
| Scan actual answer sheets via computer vision. Detect marked bubbles, compare against answer key, flag low-confidence items for human review. | "A computer-vision engine scores scanned answer sheets in batches. Bubbles below a configurable confidence threshold are flagged for human verification." (Scope: OMR) | OpenCV/Python service or JS image processing library. Answer sheet template with anchor corners (for perspective correction). Guidance Office scanner or camera. |

**Building blocks:**
- Image ingestion endpoint (upload scanned sheet)
- Perspective correction (detect 4 anchor corners, warp to flat)
- Bubble grid detection (contour → cell → filled/unfilled)
- Answer key matching + scoring
- Low-confidence flagging (borderline fill detection)
- Integration with existing `ApplicantScore` model

### C.2 RAG Chatbot (Policy-Only)

| What it is | Manuscript reference | Dependencies |
|---|---|---|
| Retrieval-augmented generation chatbot. Retrieves from ISPSC institutional documents ONLY. Below confidence → declines to answer. Explicitly does NOT do course recommendations. | "Retrieves applicant-specific information from the scheduling database and ISPSC institutional documents. Below retrieval confidence, it declines to answer." (Scope: AI Orchestration) | Vector database (Mixedbread embeddings already in SecureCAT). RAG pipeline (LangChain or direct implementation). Confidence threshold logic. Policy document corpus. |

**Building blocks:**
- Vector ingestion pipeline (reuse SecureCAT's `KnowledgeDocument` model)
- RAG query endpoint (embed query → retrieve chunks → generate response from context only)
- Confidence gating (if top chunk similarity < threshold → fallback response)
- Fallback → "I don't have that information, please contact Guidance Office"
- Separate from course recommendations — chatbot is information-only
- Replaces SecureCAT's AI Companion (which did course recs)

### C.3 Admission Support Engine Registry

| What it is | Manuscript reference | Dependencies |
|---|---|---|
| Model-agnostic engine interface (`AdmissionSupportEngine`). Models register via JSON manifest. Dashboard renders each engine's output in its own panel using declared visualization components. | "An extensible interface wrapper loads an unsupervised model for Historical Cohort Profiling and a baseline supervised model for Preliminary Program Matching." (Scope: Admission Support Models) | `model_registry` table (JSON column for manifests). `model_inference_results` table. `opis/json-schema` for manifest validation. Engine Manager admin page. Signal component library (Svelte). |

**Building blocks:**
- `AdmissionSupportEngine` PHP interface (see `NEXIAM_Registry_Architecture.md`)
- `model_registry` migration (id, name, version, type, signal, manifest JSON, status, endpoint)
- `model_inference_results` migration (model_id, applicant_id, result JSON, metadata JSON, timestamp)
- Manifest JSON Schema + validator
- Admin panel: Engine Manager (register, enable/disable, promote sandbox → active, health check)
- Dashboard engine loader: query active engines → call inference → render per visualization type
- Signal component library: `bar-chart-ranked`, `scatter-plot`, `table-probability`

### C.4 Yukee Cluster Engine (Aptitude Profiling)

| What it is | Manuscript reference | Dependencies |
|---|---|---|
| Adopts K-Means cluster definitions from Yukee et al. (2025). Static rule-matrix matching ICAT percentile ranks to 4 cluster labels. Deterministic cluster-to-program mapping table. | "Admission Support Models: The engine surfaces aptitude patterns for counselor review through an Admission Support Dashboard." (Scope: Admission Support Models) | Yukee cluster definitions (K=4 labels + centroid rules). ICAT sub-test percentile data. Cluster-to-program mapping table (to be validated by Program Heads). |

**Building blocks:**
- Rule-matrix class implementing `AdmissionSupportEngine`
- Cluster definition: Math-Dominant, Language-Dominant, Spatial-Dominant, Mixed/High-Across
- Feature mapping: ICAT Math percentile, Logic percentile, Reading percentile, Subject percentile
- Cluster-to-program mapping reference table (validated by Program Heads — see interview guide)
- Scatter plot visualization component (reuse from SecureCAT's existing counselor console concept)

### C.5 BGE Interest Engine (Preference Matching)

| What it is | Manuscript reference | Dependencies |
|---|---|---|
| Adopts `BAAI/bge-base-en-v1.5` sentence embedding model from Hugging Face (MIT license). Encodes applicant preferences + ISPSC program descriptions → cosine similarity ranking. | (New — added to Scope per 2026-07-14 architecture decision) | Python/FastAPI microservice with `sentence-transformers`. `BAAI/bge-base-en-v1.5` model (768-dim). ISPSC program descriptions corpus. `program_embeddings` table (pre-computed). |

**Building blocks:**
- Python microservice endpoint exposing `/similarity` (encode + cosine similarity)
- One-time program description embedding (batch encode all ISPSC programs → store in DB)
- At inference: encode applicant preference → compute similarity against stored embeddings → return top-N
- Bar chart visualization component (horizontal bars, sorted by similarity score)
- Configuration via engine manifest (same registry as Yukee Engine)

### C.6 Triage Dashboard

| What it is | Manuscript reference | Dependencies |
|---|---|---|
| Counselor decision-support screen showing TWO separate panels: (1) Aptitude cluster from Yukee Engine + recommended programs, (2) Interest similarity scores from BGE Engine. Counselor integrates both signals and makes the call. Every action logged to audit trail. | "The engine surfaces aptitude patterns for counselor review through an Admission Support Dashboard." (Scope: Admission Support Models) | Both engines active. Signal component library. HMAC-SHA256 audit logging. |

**Building blocks:**
- Dashboard page querying all active engines from registry
- Dual-panel layout (left: aptitude, right: interest)
- Dynamic component resolution per engine's declared visualization type
- Counselor action buttons: Approve, Modify, Override, Defer
- Engine label display (show which engine produced each result)
- Audit log integration (log engine name + version + action per counselor decision)

### C.7 Program Head Dashboard

| What it is | Manuscript reference | Dependencies |
|---|---|---|
| New role: Program Head. Sees their program's applicant pipeline — how many applied, scored, matched to their program. Quota monitoring. Pre-enrollment visibility. | "Role-Based Interfaces cover ... the RBAC-governed staff interface serving ... Program Head personnel." (IPO: Output → Role-Based Interfaces) | Program Head role (new). Quota data per program. Cluster-to-program mapping. |

**Building blocks:**
- Program Head role with scoped view (only own program's data)
- Dashboard metrics: applicants matched, quota remaining, score distribution
- Course slots monitoring (reuse SecureCAT's existing course quota concept)
- Reports view (read-only — Program Heads don't modify data)

### C.8 Guidance Counselor Dashboard

| What it is | Manuscript reference | Dependencies |
|---|---|---|
| Separate from Guidance Administrator. Counselor accesses: Triage Dashboard, OMR scoring, result release, consultation notes. Cannot manage user accounts or system settings. | IPO: Output → Role-Based Interfaces | Guidance Counselor role (split from test_administrator). |

**Building blocks:**
- Guidance Counselor role with scoped permissions
- Dashboard link to Triage Dashboard
- Access to grading/release workflows
- Consultation notes (reuse SecureCAT's `ConsultationSummary` model)

### C.9 Email Notifications

| What it is | Manuscript reference | Dependencies |
|---|---|---|
| Automated email delivery: exam schedule, result release, status updates. Triggers on system events (session publish → notify assigned applicants; result release → notify applicant). | "Email delivers schedule and result notifications." (Scope: System Coverage) | SMTP relay (ElasticEmail via Stalwart — already configured). Email templates (Laravel Mail). Trigger hooks on session publish, result release, status change. |

**Building blocks:**
- Laravel Mailable classes (ScheduleNotification, ResultNotification, StatusUpdate)
- Trigger hooks in existing controllers (session publish → queue email; result release → queue email)
- Email preference toggle in applicant profile
- Uses existing email infrastructure (Stalwart + ElasticEmail)

### C.10 In-App Messaging

| What it is | Manuscript reference | Dependencies |
|---|---|---|
| Lightweight messaging: (1) Inter-office coordination between Guidance and Registrar, (2) Applicant fallback channel when RAG chatbot can't answer. | "In-app messaging covers fallback inquiries from applicants to staff and a consultation dashboard for cross-staff communication." (Scope: System Coverage) | Message model + table. Real-time or polling for new messages. |

**Building blocks:**
- `Message` model (sender, recipient, subject, body, related applicant, read status)
- Applicant fallback: chatbot → "I don't know" → offer "Message Guidance Office" → create message thread
- Cross-staff: Guidance ↔ Registrar messages
- Notification on new message
- Logged to audit trail

### C.11 HMAC-SHA256 Chained Audit Trail

| What it is | Manuscript reference | Dependencies |
|---|---|---|
| Every audit event's hash includes the previous event's hash. Tampering with any entry breaks the chain, making unauthorized modification structurally detectable. Upgrades SecureCAT's existing flat audit log. | "All system events — data access, role actions, placement decisions, schedule modifications — are logged with HMAC-SHA256 cryptographic chaining." (Scope: Audit Trail) | Existing `audit_logs` table. Server-side secret key. |

**Building blocks:**
- Add `chain_hash` column to existing `audit_logs` table
- On insert: compute HMAC-SHA256(event data + previous chain_hash + server secret)
- Verification endpoint: validate entire chain integrity
- Tamper flag on dashboard if chain breaks
- Reuses SecureCAT's existing `AuditLog` model and logging infrastructure

---

## SECTION D: REMOVE — SecureCAT Features NOT in NEXIAM Scope

| Feature | Reason |
|---|---|
| AI Companion course recommendations | Replaced by policy-only RAG Chatbot + separate Admission Support Engines |
| Multi-tenant database segregation | NEXIAM is single-campus (ISPSC Tagudin only) |
| Admission Slip Templates (deprecated in SecureCAT) | Already deprecated — removed during migration |

---

## SECTION E: Summary — Build Effort by Category

| Category | Count | Effort |
|---|---|---|
| **Carry forward as-is** | ~30 features | Near-zero — already built and tested |
| **Extend / correct** | ~16 features | Medium — modify existing code, add new fields/models, adjust UI |
| **Build from scratch** | 11 features | High — new code, new microservice, new database tables |
| **Remove** | ~3 features | Low — delete or deactivate |

### Build Priority for RAD Rapid Construction

| Priority | Features | Rationale |
|---|---|---|
| **P0 — Week 1-3** | OMR computer-vision engine, RBAC role refactor, Applicant Portal (identifier key + mobile), Email Notifications | Core pipeline without which the system doesn't function |
| **P1 — Week 3-5** | RAG Chatbot (policy-only), Audit Trail (HMAC chaining), Program Head + Guidance Counselor roles and dashboards | High visibility, defense-critical |
| **P2 — Week 5-7** | Admission Support Engine Registry, Yukee Cluster Engine, BGE Interest Engine, Triage Dashboard | Central to the research contribution but technically separable |
| **P3 — Week 7-8** | In-App Messaging, Proctoring offline resilience, Engine Manager admin panel | Quality-of-life features, polish |

---

*This inventory is derived from actual SecureCAT v2 code (routes/web.php, 25 models, 50+ Svelte pages) cross-referenced against the NEXIAM Phase 3 Manuscript Working Sections (Scope & Limitations, IPO, Objectives).*
