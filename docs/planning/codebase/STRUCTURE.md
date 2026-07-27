# Codebase Structure

**Analysis Date:** 2026-04-13

## Directory Layout

```
SecureCAT-v2/
├── app/
│   ├── Console/Commands/       # Artisan commands
│   ├── Http/
│   │   ├── Controllers/       # HTTP controllers
│   │   ├── Middleware/        # HTTP middleware
│   │   ├── Requests/          # Form request validation
│   │   └── Controllers/Admin/ # Admin controllers
│   ├── Models/                # Eloquent models
│   ├── Policies/              # Authorization policies
│   ├── Providers/             # Service providers
│   ├── Services/              # Business logic services
│   └── Support/               # Support classes (configs)
├── bootstrap/app.php          # Application bootstrap
├── config/                    # Configuration files
├── database/
│   ├── migrations/            # Database migrations
│   └── seeders/                # Database seeders
├── resources/
│   ├── js/
│   │   ├── Components/        # Svelte components
│   │   ├── Layouts/           # Inertia layouts
│   │   ├── Pages/             # Inertia pages
│   │   └── app.svelte        # Root Svelte app
│   └── views/                 # Blade views
├── routes/
│   ├── web.php                # Web routes
│   └── console.php            # Console routes
└── tests/                     # PHPUnit tests
```

## Directory Purposes

**app/Http/Controllers/:**
- Purpose: Handle HTTP requests
- Contains: Base Controller, AuthController, ApplicationController
- Key files: `app/Http/Controllers/Controller.php`

**app/Http/Controllers/Admin/:**
- Purpose: Admin panel controllers
- Contains: UserController, RoomController, ExamSessionController, etc.
- Key files: `app/Http/Controllers/Admin/ExamSessionController.php`

**app/Http/Controllers/Grading/:**
- Purpose: Grading workflow controllers
- Contains: GradingController, GradingSessionController, GradingScoreController

**app/Http/Controllers/Portal/:**
- Purpose: Applicant portal controllers
- Contains: AiCompanionController, NotificationController

**app/Http/Controllers/Proctor/:**
- Purpose: Proctor session management
- Contains: SessionRosterController

**app/Models/:**
- Purpose: Eloquent ORM models
- Contains: User, Applicant, Application, ExamSession, GradingSession, Room, Course, etc.
- Key files: `app/Models/User.php`, `app/Models/Application.php`, `app/Models/ExamSession.php`

**app/Services/:**
- Purpose: Business logic encapsulation
- Contains: DashboardService, ExamSchedulingAssistantService, AiCompanionService, etc.
- Key files: `app/Services/DashboardService.php`, `app/Services/AiCompanionService.php`

**app/Http/Middleware/:**
- Purpose: HTTP middleware
- Contains: HandleInertiaRequests, EnsureUserHasRole, RedirectIfApplicantAuthenticated

**app/Policies/:**
- Purpose: Authorization policies
- Contains: ApplicationPolicy, ExamSessionPolicy, etc.

**app/Http/Requests/:**
- Purpose: Form request validation
- Contains: StoreApplicationRequest, LoginRequest, etc.

**resources/js/Pages/:**
- Purpose: Inertia page components
- Contains: Admin pages, Application pages, Grading pages, Portal pages

**resources/js/Components/:**
- Purpose: Reusable Svelte components
- Contains: UI components (ui/), shared components

**resources/js/Layouts/:**
- Purpose: Inertia layout wrappers
- Contains: AuthenticatedLayout, PortalLayout, GuestLayout

## Key File Locations

**Entry Points:**
- `routes/web.php`: Web routing definition
- `bootstrap/app.php`: Application middleware and routing config
- `resources/js/app.svelte`: Root Svelte component

**Configuration:**
- `config/app.php`: Application config
- `config/database.php`: Database config
- `config/auth.php`: Authentication config

**Core Logic:**
- `app/Http/Controllers/DashboardController.php`: Dashboard page
- `app/Services/DashboardService.php`: Dashboard KPIs
- `app/Http/Middleware/HandleInertiaRequests.php`: Inertia props

**Testing:**
- `tests/Feature/`: Feature tests
- `tests/Unit/`: Unit tests

## Naming Conventions

**Files:**
- Controllers: `PascalCaseController.php`
- Models: `PascalCase.php`
- Services: `PascalCaseService.php`
- Middleware: `PascalCase.php`
- Requests: `PascalCaseRequest.php`
- Svelte pages: `PascalCase.svelte`
- Svelte components: `PascalCase.svelte`

**Directories:**
- Controllers: `PascalCase/` (Admin, Grading, Portal, Proctor)
- Models: `PascalCase/`
- Services: `PascalCase/`
- Pages: `PascalCase/`
- Components: `PascalCase/`

**Routes:**
- Resource routes: snake_case with dashes (`exam-scheduling`, `academic-years`)
- Route names: dot notation (`admin.exam-scheduling.index`)

## Where to Add New Code

**New Admin Feature:**
- Controller: `app/Http/Controllers/Admin/{Feature}Controller.php`
- Service: `app/Services/{Feature}Service.php`
- Model: `app/Models/{Feature}.php`
- Pages: `resources/js/Pages/Admin/{Feature}/`
- Routes: `routes/web.php` under `admin.` prefix

**New Portal Feature:**
- Controller: `app/Http/Controllers/Portal/{Feature}Controller.php`
- Pages: `resources/js/Pages/Portal/{Feature}.svelte`
- Routes: `routes/web.php` under `portal.` prefix

**New Service:**
- Implementation: `app/Services/{Feature}Service.php`
- Usage: Inject in controller via constructor

**New Component:**
- UI components: `resources/js/Components/ui/{component}/`
- Feature components: `resources/js/Components/`

**New Model:**
- Implementation: `app/Models/{Feature}.php`
- Migration: `database/migrations/YYYY_MM_DD_HHMMSS_create_{feature}_table.php`
- Factory: `database/factories/{Feature}Factory.php`

---

*Structure analysis: 2026-04-13*