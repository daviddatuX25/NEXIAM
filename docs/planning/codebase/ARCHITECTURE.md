# Architecture

**Analysis Date:** 2026-04-13

## Pattern Overview

**Overall:** Laravel MVC with Inertia.js v2 SSR

**Key Characteristics:**
- Server-side rendering with Inertia.js v2 + Svelte v2
- Role-based access control (RBAC) via middleware and policies
- Service layer for business logic encapsulation
- RESTful resource controllers with explicit route definitions

## Layers

**Controllers (HTTP Layer):**
- Purpose: Handle HTTP requests, authentication, validation, Inertia rendering
- Location: `app/Http/Controllers/`
- Contains: Resource controllers, auth controllers, portal controllers
- Depends on: Form Requests, Services, Models
- Used by: Routes

**Services (Business Logic):**
- Purpose: Encapsulate business rules, data transformation, external integrations
- Location: `app/Services/`
- Contains: DashboardService, ExamSchedulingAssistantService, AiCompanionService, etc.
- Depends on: Models, External SDKs
- Used by: Controllers

**Models (Data):**
- Purpose: Eloquent ORM, data relationships, query scopes
- Location: `app/Models/`
- Contains: User, Applicant, Application, ExamSession, GradingSession, etc.
- Depends on: Database migrations
- Used by: Services, Controllers, Policies

**Middleware:**
- Purpose: Authentication, authorization, Inertia props sharing
- Location: `app/Http/Middleware/`
- Contains: HandleInertiaRequests, EnsureUserHasRole, RedirectIfApplicantAuthenticated
- Depends on: Request, Auth
- Used by: Bootstrap configuration

**Policies (Authorization):**
- Purpose: Authorization logic for specific models
- Location: `app/Policies/`
- Contains: ApplicationPolicy, ExamSessionPolicy, etc.
- Depends on: Models
- Used by: Controllers (implicitly via Gate)

**Form Requests (Validation):**
- Purpose: Request validation and authorization
- Location: `app/Http/Requests/`
- Contains: StoreApplicationRequest, LoginRequest, etc.
- Depends on: Validation rules
- Used by: Controllers

## Data Flow

**User Request Flow:**

1. Route matches (`routes/web.php`)
2. Middleware stack executes (auth, role, inertia)
3. Controller receives Request
4. Form Request validates input
5. Controller calls Service
6. Service performs business logic using Models
7. Service returns data to Controller
8. Controller renders Inertia page with props

**Inertia Request Flow:**

1. Browser makes XHR with Inertia header
2. HandleInertiaRequests middleware shares auth, settings
3. Controller returns Inertia::render()
4. Server renders Svelte component with props
5. Browser hydrates SPA

**Roles & Permissions:**
- Roles: super_admin, registrar_administrator, test_administrator, staff, proctor
- Auth guards: web (users), applicant (applicant portal)
- Middleware: EnsureUserHasRole for route-level protection

## Key Abstractions

**Services:**
- `DashboardService` - KPI aggregation for admin dashboard
- `ExamSchedulingAssistantService` - AI-powered exam scheduling
- `AiCompanionService` - RAG-based applicant AI companion
- `KnowledgeRetrievalService` - Mixedbread + MySQL fallback retrieval
- `GradingSessionService` - Grading workflow management
- `AdmissionSlipService` - PDF generation for admission slips

**Models with Scopes:**
- `AcademicYear::active()` - Filter active academic year
- `Application::forAcademicYear()` - Filter by academic year
- `ExamSession::withAssignedApplicants()` - Eager load relationships

**Inertia Layouts:**
- `AuthenticatedLayout.svelte` - Admin dashboard wrapper
- `PortalLayout.svelte` - Applicant portal wrapper
- `GuestLayout.svelte` - Public pages

## Entry Points

**Web Routes:**
- Location: `routes/web.php`
- Triggers: Browser requests
- Responsibilities: Route to controllers, middleware groups, resource routes

**Inertia Middleware:**
- Location: `app/Http/Middleware/HandleInertiaRequests.php`
- Triggers: Every Inertia request
- Responsibilities: Share auth user, CSRF token, system settings, page titles

**Bootstrap Configuration:**
- Location: `bootstrap/app.php`
- Triggers: Application bootstrap
- Responsibilities: Register middleware, routing, exceptions

## Error Handling

**Strategy:** Exception-based with Inertia validation errors

**Patterns:**
- Form Request validation returns 422 with Inertia errors
- Policy denies return 403 page
- Not found routes show custom 404 via Inertia

## Cross-Cutting Concerns

**Logging:** Laravel logging via config/logging.php

**Validation:** FormRequest classes in `app/Http/Requests/`

**Authentication:** 
- User: Laravel's auth:web guard
- Applicant: Custom auth:applicant guard
- Google OAuth: GoogleOAuthUserResolver service

---

*Architecture analysis: 2026-04-13*