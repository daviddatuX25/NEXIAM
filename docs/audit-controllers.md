# NEXIAM Controller Architecture Audit
## Audit #3 of 8 — Method-Level Analysis & Decomposition Plan

**Date:** 2026-07-30
**Scope:** 41 controllers, 9,122 lines, ~220 methods
**Prior audits:** Architecture (#1 ✅), Access Control (#2 ✅)

---

## EXECUTIVE SUMMARY

| Metric | Value |
|---|---|
| Total controllers | 41 (excluding Controller base + trait) |
| Total lines | 9,122 |
| Total methods | ~220 |
| Controllers >400 lines | 6 (fat) |
| Controllers with proper DI | 5 of 41 (12%) |
| Controllers using `app()` anti-pattern | 9 of 41 (22%) |
| Controllers with no service dependencies | 27 of 41 (66%) |
| **Critical findings** | **5** |
| **Medium findings** | **6** |
| **Good patterns** | **5** |

---

## TOP 5 PRIORITY ITEMS

| Pri | Finding | Controller(s) | Severity |
|---|---|---|---|
| P0 | Two god controllers: 1245-line ApplicationController (30 methods, 5+ responsibilities) and 1012-line ExamSessionController (34 methods, 4+ responsibilities) | ApplicationController, ExamSessionController | 🔴 CRITICAL |
| P0 | Service locator anti-pattern — 9 controllers use `app(Service::class)` instead of constructor DI | ApplicationController, ExamSessionController, PortalAuthController, ReleaseController, SessionRosterController, AuthController, GradingController, DirectAssessmentController, SetupController | 🔴 CRITICAL |
| P0 | PortalAuthController: 501 lines mixing auth (login/logout/reset/setup) + dashboard + admission slip viewing — 3 concerns in 1 class | PortalAuthController | 🔴 CRITICAL |
| P0 | SetupController: 599 lines of 11 `checkX()` methods — pure view logic that should be a Service + lightweight controller | SetupController | 🔴 CRITICAL |
| P1 | 27 of 41 controllers have zero service dependencies — models queried inline | Various | 🟡 MEDIUM |
| P1 | SessionRosterController: 487 lines, 8 methods — well-scoped but inline business logic bloats each method | SessionRosterController | 🟡 MEDIUM |

---

## 1. GOD CONTROLLER #1: ApplicationController (1245 lines, 30 methods)

### Current State

**File:** `app/Http/Controllers/ApplicationController.php`
**Lines:** 1245
**Methods:** 30
**Services (DI):** NONE — uses `app()` locator: `ApplicationPipelineService`, `AuditService`, `AdmissionSlipService`, `PrintTemplateCssService`
**Uses:** `ResolvesAcademicYear` trait, `SendApplicantSetupEmail` job, `ApplicationStatusChanged` notification

### Method Classification

| Group | Methods | Count | Lines (est.) |
|---|---|---|---|
| **Admin CRUD** | `index`, `show`, `create`(admin form), `storeAdmin`, `edit`, `updateAdmin`, `destroy`, `ensureApplicantForAcceptance` | 8 | ~300 |
| **Workflow/Actions** | `accept`, `dismiss`, `reopen`, `bulkAccept`, `bulkDismiss`, `bulkReopen`, `resendSetupEmail` | 7 | ~300 |
| **Public Apply** | `create`(public), `store`, `success` | 3 | ~120 |
| **Portal Views** | `portalShow`, `portalEdit`, `portalUpdate` | 3 | ~120 |
| **Admission Slips** | `admissionSlip`, `admissionSlipPrint`, `admissionSlipBulkPrint`, `admissionSlipBulkPdf` | 4 | ~150 |
| **Utility** | `getCourses`, `getAppointments` | 2 | ~50 |

### Proposed Decomposition (matches legacy plan)

```
ApplicationController (1245L, 30 methods)
    │
    ├── Admin/ApplicationCrudController (~300L, 7 methods)
    │   index, show, edit, updateAdmin, storeAdmin, destroy, ensureApplicantForAcceptance
    │
    ├── Admin/ApplicationWorkflowController (~250L, 7 methods)
    │   accept, dismiss, reopen, bulkAccept, bulkDismiss, bulkReopen, resendSetupEmail
    │
    ├── AdmissionSlipController (~150L, 4 methods)
    │   show(admissionSlip), print, bulkPrint, bulkPdf
    │
    ├── Portal/PortalApplicationController (~100L, 3 methods)
    │   portalShow, portalEdit, portalUpdate
    │
    └── PublicApplicationController (~80L, 3 methods)
        create(public form), store(public), success
```

**Verification:** Legacy split plan at `legacy_codebase/plans/2026-05-27-scaling-readiness-phase2-application-controller-split.md` already designed this decomposition. The plan is 564 lines with TDD steps for each new controller.

### Specific Issues

1. **Missing constructor DI:** Uses `app(ApplicationPipelineService::class)` 5+ times. Should inject via `__construct()`.
2. **`getCourses()` / `getAppointments()`** — JSON endpoints living on a god controller. These should be on dedicated lookup controllers.
3. **`ensureApplicantForAcceptance()`** — private helper at L1198-1244, 47 lines. This is business logic that belongs in a Service or Action class, not a controller.
4. **Inline DB queries:** Direct `DB::table()` calls in workflow methods instead of delegating to services.

---

## 2. GOD CONTROLLER #2: ExamSessionController (1012 lines, 34 methods)

### Current State

**File:** `app/Http/Controllers/Admin/ExamSessionController.php`
**Lines:** 1012
**Methods:** 34 (22 public, 1 private static)
**Services (DI):** NONE — uses `app()` locator: `ApplicationPipelineService`, `AuditService`, `GradingSessionService`

### Method Classification

| Group | Methods | Count | Lines (est.) |
|---|---|---|---|
| **CRUD** | `index`, `create`, `store`, `show`, `edit`, `update`, `destroy`, `statusesList`, `scheduleAssistantPayload`, `redirectIfCompleted` | 10 | ~300 |
| **Workflow Transitions** | `publish`, `unpublish`, `cancel`, `start`, `complete`, `reopen`, `backtrack` | 7 | ~300 |
| **Roster Management** | `assignApplicants`, `removeApplicant` | 2 | ~150 |
| **Test Admin Views** | `testAdminIndex`, `testAdminRoster` | 2 | ~150 |
| **Monitoring** | `monitoring` | 1 | ~45 |

### Proposed Decomposition (matches legacy plan)

```
ExamSessionController (1012L, 34 methods)
    │
    ├── Admin/ExamSessionCrudController (~250L, 8 methods)
    │   index, create, store, show, edit, update, destroy, statusesList
    │
    ├── Admin/ExamSessionWorkflowController (~250L, 7 methods)
    │   publish, unpublish, cancel, start, complete, reopen, backtrack
    │
    ├── Admin/ExamSessionRosterController (~150L, 2 methods)
    │   assignApplicants, removeApplicant
    │
    └── Admin/ExamSessionMonitoringController (~150L, 3 methods)
        monitoring, testAdminIndex, testAdminRoster
```

**Verification:** Legacy split plan at `legacy_codebase/plans/2026-05-27-scaling-readiness-phase3-exam-session-controller-split.md`.

### Specific Issues

1. **No constructor DI** — uses `app()` for all 3 services.
2. **`redirectIfCompleted()`** — 8-line private helper that's a guard pattern. Should be middleware.
3. **37 N+1 queries** identified in architecture audit — `index()` loads exam sessions then loops through applicants without eager loading.
4. **`assignApplicants()`** at 103 lines (L396-498) — does validation, DB queries, notifications, and audit logging all inline. Needs extraction to an `AssignApplicantsAction`.
5. **`scheduleAssistantPayload()`** at 84 lines (L122-205) — builds a massive array payload for the scheduling AI. Belongs in `ExamSchedulingAssistantService`.
6. **Inline notifications:** 7 notification classes imported directly. Notification dispatch should be event-driven.

---

## 3. FAT CONTROLLER #3: SetupController (599 lines, 18 methods)

### Analysis

**File:** `app/Http/Controllers/Admin/SetupController.php`
**All methods follow the SAME pattern:**

```php
public function checkAcademicYears(): array {
    $active = AcademicYear::active();
    $count = AcademicYear::count();
    // ... compute status
    return ['status' => '...', 'count' => N, ...];
}
```

11 identical `checkX()` methods + `computeHealth()` + `countUsersWithRole()` + `index()`. This is a health-check dashboard, not a controller problem. The issue is that all the checking logic lives in the controller rather than in dedicated health-check service classes.

### Proposed Fix

```
SetupController (599L, 18 methods)
    │
    ├── SetupController (~30L, 1 method: index)
    │   delegates to SystemHealthService
    │
    └── NEW: SystemHealthService (~400L, 13 methods)
        computeHealth(), checkAcademicYears(), checkCourses(),
        checkRooms(), checkAptitudeAreas(), checkResultSheetTemplates(),
        checkAdmissionSlipTemplates(), checkPrivacyPolicies(),
        checkStaffAccounts(), checkInstitution(), checkRatingScales(),
        countUsersWithRole()
```

---

## 4. FAT CONTROLLER #4: PortalAuthController (501 lines, 17 methods)

### Dual-Concern Problem

This controller handles TWO completely separate concerns:

| Concern | Methods | Count |
|---|---|---|
| **Authentication** | `create`(login form), `store`(login), `setupShow`, `setupStore`, `forgotPasswordCreate`, `forgotPasswordStore`, `resetShow`, `resetStore`, `destroy`(logout) | 9 |
| **Portal Dashboard + Slips** | `dashboard`, `admissionSlipPdf`, `admissionSlipView`, `buildStatusTracker`, `buildExamSchedule`, `buildScoreRelease` | 6 |

### Proposed Split

```
PortalAuthController (501L, 17 methods)
    │
    ├── PortalAuthController (~250L, 9 methods) — keep auth only
    │   create, store, setupShow, setupStore,
    │   forgotPasswordCreate, forgotPasswordStore,
    │   resetShow, resetStore, destroy
    │
    └── Portal/PortalDashboardController (~250L, 6 methods)
        dashboard, admissionSlipPdf, admissionSlipView,
        buildStatusTracker, buildExamSchedule, buildScoreRelease
```

### Specific Issues

1. **No constructor DI** — uses `app(AuditService::class)` and `app(AdmissionSlipService::class)` with service locator.
2. **`dashboard()`** at 74 lines (L234-307): massive inline query building. Should delegate to a `PortalDashboardService`.
3. **`buildStatusTracker()`** at 82 lines (L364-445): builds a complex status array. Should be a ViewModel or Service method.
4. **`buildExamSchedule()` / `buildScoreRelease()`** — formatting logic in controller.

---

## 5. FAT CONTROLLER #5: ReleasePrintController (507 lines, 17 methods)

### Analysis

**Good:** Proper constructor DI with 6 services injected.
**Bad:** 17 methods is too many for a single class. Split by concern.

### Proposed Split

```
ReleasePrintController (507L, 17 methods)
    │
    ├── ReleasePrintController (~250L, 8 methods) — per-applicant printing
    │   index, markPrinted, resultSheet, resultSheetPdf,
    │   downloadDocx, printBulk, printBulkPdf, formatName
    │
    └── ReleaseBulkPrintController (~180L, 6 methods) — bulk/agnostic printing
        printBulkAgnostic, printBulkAgnosticPdf,
        downloadBulkDocx, downloadBulkAgnosticDocx,
        buildBulkDocxZip, buildBulkDocxMerged
```

---

## 6. FAT CONTROLLER #6: SessionRosterController (487 lines, 8 methods)

### Analysis

Well-scoped — only 8 methods, all about proctor session management. But each method is large.

| Method | Lines | Issue |
|---|---|---|
| `show` | 67 | Heavy inline query building for roster view |
| `storeAttendance` | 78 | Validation + DB + pipeline + audit all inline |
| `storeSubmission` | 64 | Same pattern |
| `storeSubmissionBulk` | 72 | Same pattern |
| `start` | 58 | Same pattern |
| `close` | 38 | Same pattern |

**Fix:** Extract business logic to a `ProctorSessionService`. Controller should be ~150 lines max.

---

## 7. WELL-STRUCTURED CONTROLLERS (Keep / Minor Polish)

These controllers demonstrate the pattern we want:

| Controller | Lines | Methods | DI? | Notes |
|---|---|---|---|---|
| `DashboardController` | 47 | 2 | ✅ | Gold standard — thin controller, 2 injected services |
| `ScoreImportController` | 208 | 8 | ✅ | Good DI, focused import workflow |
| `ApplicationImportController` | 278 | 8 | ✅ | Good DI, mirrors ScoreImport pattern |
| `ExamSchedulingAssistantController` | 369 | 9 | ✅ | Single injected service, AI chat orchestration |
| `AuthController` | 87 | 3 | ✗ | Small enough, but should inject AuthService |
| `ProfileController` | 91 | 3 | ✗ | Small enough |

---

## 8. MEDIUM CONTROLLERS — Service Dependency Gap

27 of 41 controllers have ZERO service dependencies. They query models directly in controller methods.

### Pattern Seen (Anti-Pattern):

```php
// Currently in many controllers:
$courses = Course::where('is_active', true)->orderBy('name')->get();
$users = User::with('roles')->paginate(20);
// ... inline query, inline filtering, inline pagination
```

### What It Should Be:

```php
// After refactor:
public function __construct(private CourseService $courseService) {}
// ...
$courses = $this->courseService->activeCourses();
```

### Affected Controllers (all need service extraction):

| Controller | Lines | Direct Model Queries |
|---|---|---|
| `ReleaseController` | 312 | Course, ConsultationSummary, GradingSession, SystemSetting — all inline |
| `KnowledgeDocumentController` | 265 | Direct model queries with complex filtering |
| `ResultSheetTemplateController` | 314 | Template CRUD with inline computations |
| `AdmissionSlipTemplateController` | 194 | Same pattern |
| `AptitudeAreaController` | 204 | CRUD with inline scoring config |
| `RoomController` | 140 | Simple CRUD, low priority |
| `InstitutionController` | 137 | Simple CRUD, low priority |
| `CourseController` | 100 | Simple CRUD, low priority |
| `AcademicYearController` | 106 | Simple CRUD, low priority |
| `SettingsController` | 121 | Key-value settings with inline caching |
| `PrivacyPolicyController` | 157 | Simple CRUD |
| `RatingScaleController` | 125 | Simple CRUD |
| `UserController` | 147 | User CRUD — could benefit from UserService |
| `GradingController` | 79 | Delegates to GradingSessionService but still uses `app()` |
| `GradingScoreController` | 155 | Score CRUD with inline computations |
| `GradingSessionController` | 92 | Session CRUD with inline queries |
| `AuditLogController` | 113 | Read-only audit viewer |

---

## 9. CONSTRUCTOR DI vs SERVICE LOCATOR

### Current State

```
DI (proper constructor injection):  5 controllers (12%)
Service locator (app() calls):      9 controllers (22%)
No services at all:                27 controllers (66%)
```

### Service Locator Users (must fix):

| Controller | `app()` Calls |
|---|---|
| ApplicationController | 5+ (PipelineService, AuditService, AdmissionSlipService, PrintTemplateCssService) |
| ExamSessionController | 3+ (PipelineService, AuditService, GradingSessionService) |
| PortalAuthController | 2 (AuditService, AdmissionSlipService) |
| ReleaseController | 3 (ConsultationSummaryService, PipelineService, AuditService) |
| SessionRosterController | 2 (PipelineService, AuditService) |
| AuthController | 0 (but uses `Auth` facade — acceptable for auth) |
| GradingController | 1 (GradingSessionService) |
| DirectAssessmentController | 1 |
| SetupController | 0 (queries models directly) |

### Why This Matters

1. **Untestable:** Can't mock dependencies — `app()` is a global.
2. **Hidden coupling:** Reading the constructor doesn't reveal what the class needs.
3. **Inconsistent:** 5 controllers do it right. Why not the others?

---

## 10. CONTROLLER SIZE DISTRIBUTION

```
Size Range          Count   Controllers
─────────────────────────────────────────────
1000+ lines          2     ApplicationController, ExamSessionController  🔴
500-599 lines        3     SetupController, ReleasePrintController, PortalAuthController  🟡
400-499 lines        1     SessionRosterController  🟡
300-399 lines        2     ExamSchedulingAssistantController, ReleaseController  🟢
200-299 lines        3     ResultSheetTemplateController, ApplicationImportController, KnowledgeDocumentController  🟢
100-199 lines       10     ScoreImportController, AptitudeAreaController, AdmissionSlipTemplateController, etc.  🟢
<100 lines          20     DashboardController, AuthController, HomeController, etc.  ✅
```

---

## 11. RECOMMENDED REFACTOR SEQUENCE

### P0 — Week 1-2: God Controllers

| Step | Controller | Action | New Lines (est.) |
|---|---|---|---|
| 1 | ApplicationController | Split into 5 (Crud + Workflow + Slip + Portal + Public) | ~900 total |
| 2 | ExamSessionController | Split into 4 (Crud + Workflow + Roster + Monitoring) | ~750 total |
| 3 | All split controllers | Add constructor DI, replace `app()` with injection | — |

### P1 — Week 2-3: Fat Controllers

| Step | Controller | Action |
|---|---|---|
| 4 | PortalAuthController | Split auth from dashboard (+ DI) |
| 5 | SetupController | Extract SystemHealthService (+ DI) |
| 6 | ReleasePrintController | Split individual from bulk printing |
| 7 | SessionRosterController | Extract ProctorSessionService |

### P2 — Week 3-4: Service Extraction

| Step | Target | Action |
|---|---|---|
| 8 | 27 controllers with no services | Create Service classes for: CourseService, RoomService, InstitutionService, AcademicYearService, AptitudeAreaService, TemplateService, SettingService, GradingScoreService, UserService |
| 9 | All controllers | Standardize on constructor DI pattern |
| 10 | All controllers | Add method-level PHPDoc with `@see` references to services |

---

## 12. GOOD PATTERNS FOUND (Preserve & Replicate)

### 1. DashboardController — The Gold Standard

```php
class DashboardController extends Controller
{
    public function __construct(
        private DashboardService $dashboardService,
        private DashboardAnalyticsService $analyticsService
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Dashboard', [
            'analytics' => $this->analyticsService->compute($request->user()),
            'quickStats' => $this->dashboardService->quickStats(),
        ]);
    }
}
```

47 lines. 2 methods. 2 injected services. All business logic in services. **Every controller should look like this.**

### 2. ScoreImportController / ApplicationImportController — Import Pattern

Both share the same structure with proper DI:
- `importForm()` → show form
- `analyze()` → parse + validate without committing
- `preview()` → show what will be imported
- `confirm()` → commit
- `import()` → one-shot import
- `template()` → download template

Well-structured, DI-ready, focused. **Model for new feature controllers.**

### 3. ReleasePrintController — Proper Multi-Service DI

```php
public function __construct(
    private PrintBatchService $printService,
    private ResultSheetTemplateService $templateService,
    private ResultSheetPdfService $pdfService,
    private DocxToPdfService $docxToPdfService,
    private ResultSheetDocxService $docxService,
    private DocxMergeService $docxMergeService,
) {}
```

Even though it's too big, the DI pattern is correct. 6 injected services = clear dependency declaration.

### 4. ExamSchedulingAssistantController — Single Responsibility

369 lines, 9 methods, but all about ONE thing: the AI scheduling assistant. Single `ExamSchedulingAssistantService` injected. Private helpers `inferAction()`, `normalizeStructuredSchedule()`, `scrubContradictingMessages()` are properly scoped.

### 5. FormRequest Usage

Multiple controllers use dedicated FormRequest classes (`StoreApplicationRequest`, `UpdateApplicationRequest`, `DismissApplicationRequest`, `AssignApplicantsRequest`, etc.). This keeps validation out of controllers — **correct pattern, continue.**

---

## 13. NEXIAM-SPECIFIC CONCERNS

### 13.1 New Roles Need New Controller Paths

| New Role | Controllers Needed |
|---|---|
| `guidance_counselor` | Guidance dashboard, consultation CRUD, triage view |
| `program_head` | Program dashboard, slot management, applicant review |
| `registrar_clerk` | Scoped application CRUD (subset of current admin) |
| `proctor` (duty overlay) | Already exists (`Proctor/SessionRosterController`, `ProctorSessionController`) |

### 13.2 Missing Controller Patterns for NEXIAM Features

| Feature | Status |
|---|---|
| OMR Engine | No controller exists — needs `OmrProcessingController` |
| RAG Chatbot | Partially exists as `AiCompanionController` (portal) + `AiCompanionAdminController` (admin) |
| HMAC Audit Chain | AuditService exists, but no controller for verification UI |
| Email Notifications | Notification classes exist, but no notification management UI controller |
| Identifier Key Auth | PortalAuthController currently uses email+password — needs refactor |

---

## APPENDIX A: COMPLETE CONTROLLER INVENTORY

| # | Controller | Lines | Methods | DI | Services Used |
|---|---|---|---|---|---|
| 1 | ApplicationController | 1245 | 30 | ✗ | Pipeline, Audit, AdmissionSlip, PrintTemplate |
| 2 | ExamSessionController | 1012 | 34 | ✗ | Pipeline, Audit, GradingSession |
| 3 | SetupController | 599 | 18 | ✗ | none (direct model queries) |
| 4 | ReleasePrintController | 507 | 17 | ✅ | PrintBatch, Template, Pdf, DocxToPdf, Docx, DocxMerge |
| 5 | PortalAuthController | 501 | 17 | ✗ | Audit, AdmissionSlip |
| 6 | SessionRosterController | 487 | 8 | ✗ | Pipeline, Audit |
| 7 | ExamSchedulingAssistantController | 369 | 9 | ✅ | ExamSchedulingAssistant |
| 8 | ResultSheetTemplateController | 314 | 14 | ✗ | none |
| 9 | ReleaseController | 312 | 8 | ✗ | Pipeline, Audit, ConsultationSummary |
| 10 | ApplicationImportController | 278 | 8 | ✅ | ApplicantImport, SpreadsheetParser |
| 11 | KnowledgeDocumentController | 265 | 14 | ✗ | none |
| 12 | AdmissionSlipPrintController | 209 | 7 | ✗ | none |
| 13 | ScoreImportController | 208 | 8 | ✅ | ScoreImport, SpreadsheetParser |
| 14 | AptitudeAreaController | 204 | 8 | ✗ | none |
| 15 | AdmissionSlipTemplateController | 194 | 8 | ✗ | none |
| 16 | PrivacyPolicyController | 157 | 10 | ✗ | none |
| 17 | GradingScoreController | 155 | 5 | ✗ | none |
| 18 | UserController | 147 | 6 | ✗ | none |
| 19 | RoomController | 140 | 10 | ✗ | none |
| 20 | InstitutionController | 137 | 3 | ✗ | none |
| 21 | AiCompanionController (Portal) | 130 | 4 | ✗ | none |
| 22 | RatingScaleController | 125 | 6 | ✗ | none |
| 23 | SettingsController | 121 | 6 | ✗ | none |
| 24 | AuditLogController | 113 | 4 | ✗ | none |
| 25 | ReportController | 108 | 2 | ✗ | none |
| 26 | AcademicYearController | 106 | 8 | ✗ | none |
| 27 | CourseController | 100 | 9 | ✗ | none |
| 28 | NotificationController | 96 | 4 | ✗ | none |
| 29 | GradingSessionController | 92 | 4 | ✗ | GradingSession (via app()) |
| 30 | ProfileController | 91 | 3 | ✗ | none |
| 31 | AuthController | 87 | 3 | ✗ | none (uses Auth facade) |
| 32 | DirectAssessmentController | 85 | 3 | ✗ | none |
| 33 | GradingController | 79 | 4 | ✗ | GradingSession (via app()) |
| 34 | PortalNotificationController | 78 | 3 | ✗ | none |
| 35 | ProctorSessionController | 56 | 1 | ✗ | none |
| 36 | DashboardController | 47 | 2 | ✅ | Dashboard, DashboardAnalytics |
| 37 | GoogleAuthController | 39 | 2 | ✗ | none |
| 38 | AiCompanionAdminController | 35 | 2 | ✗ | none |
| 39 | HomeController | 31 | 2 | ✗ | none |
| 40 | Controller (base) | 10 | 0 | — | — |
| 41 | ResolvesAcademicYear (trait) | 53 | 2 | — | — |

---

## APPENDIX B: LEGACY SPLIT PLANS STATUS

| Plan | File | Status |
|---|---|---|
| ApplicationController split | `legacy_codebase/plans/2026-05-27-scaling-readiness-phase2-application-controller-split.md` | 564 lines, TDD steps ready |
| ExamSessionController split | `legacy_codebase/plans/2026-05-27-scaling-readiness-phase3-exam-session-controller-split.md` | 410 lines, TDD steps ready |

Both plans were written May 27, 2026 for the "Scaling Readiness" initiative. They provide step-by-step TDD approaches but were never executed.

---

*Audit #3 complete. Next: #4 — Service Layer Quality Audit (`docs/audit-services.md`)*
