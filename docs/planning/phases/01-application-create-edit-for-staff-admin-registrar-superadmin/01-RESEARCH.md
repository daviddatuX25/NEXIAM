# Phase 1: Application Create/Edit for Staff Admin Registrar Superadmin - Research

**Researched:** 2026-04-13
**Domain:** Laravel + Inertia.js + Svelte Application Management Module
**Confidence:** HIGH

## Summary

The Applications module already has significant infrastructure in place but is missing staff-facing create and edit capabilities. The existing Apply.svelte page (public-facing) handles new application submission during application windows. The admin routes provide accept/dismiss/bulk operations and viewing, but no dedicated create (for staff to enter applications on behalf of applicants) or edit (for staff to modify existing applications) forms exist.

**Primary recommendation:** Add dedicated admin create/edit routes and Svelte pages reusing the existing form patterns from Apply.svelte. The ApplicationController already has store() but it's designed for public applicants and enforces application window restrictions. Staff create/edit needs separate controller methods that bypass window restrictions, plus an UpdateApplicationRequest form request for editing.

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|-------------|
| laravel/framework | v12 | Backend framework | Current project Laravel 12 |
| inertiajs/inertia-laravel | v2 | Server-side rendering | Inertia v2 per project config |
| @inertiajs/svelte | v2 | Frontend adapter | Inertia Svelte per project config |
| tailwindcss | v4 | Styling | Current project |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| lucide-svelte | latest | Icons | All Svelte UI components |
| @inertiajs/svelte (Form) | — | Form handling | All Inertia form submissions |
| Laravel Form Request | — | Validation | All controller input validation |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Custom validation in controller | Laravel Form Request | Form request provides reuse + clear rules separation |
| Inline validation | useForm() from Inertia | useForm() handles errors, loading states automatically |
| Separate Svelte pages for each role | Single create/edit page with role checks | Single page with different access levels |

## Architecture Patterns

### Recommended Project Structure
```
app/Http/Controllers/
  ApplicationController.php  (existing - add create, storeAdmin, edit, update methods)

app/Http/Requests/
  UpdateApplicationRequest.php  (new - for edit operations)
  StoreApplicationRequest.php  (existing - for create)

resources/js/Pages/Admin/Applications/
  Create.svelte   (new - staff create application)
  Edit.svelte    (new - staff edit application)
```

### Pattern 1: Admin Create/Edit Controller Methods
Existing ApplicationController uses single responsibility per action. Follow same pattern for create/edit:

- `create()` — Return empty form with courses and appointments
- `storeAdmin(StoreApplicationRequest)` — Create without application window restriction, set processed_by
- `edit(Application $application)` — Return populated form
- `update(Application $application, UpdateApplicationRequest)` — Update with all fields

### Pattern 2: Form Reuse from Public Apply.svelte
The existing Apply.svelte has full form logic including:
- All 18 form fields (first_name through appointment_id)
- Course preference dropdowns with unique filtering
- Real-time preference conflict prevention
- Birthday validation (15-50 years)

Extract form into shared component or copy-paste with admin-specific modifications (no appointment requirement, different auth).

### Anti-Patterns to Avoid
- **Reusing public store() for staff:** store() enforces application window check and sets submitted_at. Staff create should bypass these.
- **Single form for both create and edit:** Different validation rules (all fields required on create, partial on edit). Use separate pages or conditional logic.
- **Missing audit trail:** When staff edit applications, record processed_by and processed_at timestamp.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|------------|-------------|-----|
| Form validation | Inline validation arrays | Laravel Form Request | Reuse across controller + API, clear rules |
| Form state management | Custom stores | Inertia useForm() | Built-in error handling, loading, progress |
| Date formatting | Custom moment.js | JavaScript Intl | Native, no extra dependency |

## Runtime State Inventory

> Not applicable - Phase is about adding functionality, not renaming/migration.

| Category | Items Found | Action Required |
|----------|-------------|------------------|
| Stored data | Application records in MySQL - existing | None - adds new records |
| Live service config | None | None |
| OS-registered state | None | None |
| Secrets/env vars | None | None |
| Build artifacts | None | None |

## Common Pitfalls

### Pitfall 1: Application Window Restriction
**What goes wrong:** Staff trying to create applications during closed window get blocked.
**Why it happens:** Existing store() method checks `$activeAcademicYear->isApplicationWindowOpen()`.
**How to avoid:** Create separate `storeAdmin()` method that doesn't check window.
**Warning signs:** 422 errors when window is closed.

### Pitfall 2: Duplicate Course Preferences
**What goes wrong:** User selects same course for multiple preferences.
**Why it happens:** No server-side enforcement in original Apply.svelte.
**How to avoid:** Laravel validation `different:course_preference_1` handles this server-side; client-side `$effect` in Apply.svelte provides UX.
**Warning forms:** Duplicate course IDs in database.

### Pitfall 3: Missing processed_by Tracking
**What goes wrong:** No record of who edited application.
**Why it happens:** Both store() and update don't always set processed_by.
**How to avoid:** Explicitly set processed_by on staff create/edit operations.
**Warning signs:** NULL processed_by on edited applications.

## Code Examples

### 1. ApplicationPolicy Update (existing pattern)

```php
// Source: app/Policies/ApplicationPolicy.php
public function create(User $user): bool
{
    return $user->hasAnyRole(['super_admin', 'staff', 'registrar_administrator']);
}

public function update(User $user, Application $application): bool
{
    return $user->hasAnyRole(['super_admin', 'staff', 'registrar_administrator']);
}
```

### 2. Routes Pattern for Admin Create/Edit

```php
// Source: routes/web.php pattern
// Add to admin scope (around line 150)
Route::get('/admin/applications/create', [ApplicationController::class, 'create'])->name('admin.applications.create')->middleware('role:super_admin,staff,registrar_administrator');
Route::post('/admin/applications', [ApplicationController::class, 'storeAdmin'])->name('admin.applications.store-admin')->middleware('role:super_admin,staff,registrar_administrator');
Route::get('/admin/applications/{application}/edit', [ApplicationController::class, 'edit'])->name('admin.applications.edit')->middleware('role:super_admin,staff,registrar_administrator');
Route::put('/admin/applications/{application}', [ApplicationController::class, 'update'])->name('admin.applications.update')->middleware('role:super_admin,staff,registrar_administrator');
```

### 3. UpdateApplicationRequest (validation)

```php
// app/Http/Requests/UpdateApplicationRequest.php
class UpdateApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'suffix' => ['nullable', 'string', 'max:20'],
            'birthdate' => ['required', 'date', 'before:-15 years', 'after:-50 years'],
            'sex' => ['required', 'string', 'in:male,female'],
            'email' => ['required', 'email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'address_line' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'zip_code' => ['nullable', 'string', 'max:10'],
            'course_preference_1' => ['required', 'integer', 'exists:courses,id'],
            'course_preference_2' => ['nullable', 'integer', 'exists:courses,id', 'different:course_preference_1'],
            'course_preference_3' => ['nullable', 'integer', 'exists:courses,id', 'different:course_preference_1', 'different:course_preference_2'],
            'appointment_id' => ['nullable', 'integer', 'exists:appointments,id'],
            'status' => ['nullable', 'string', 'in:pending,accepted,dismissed'],
        ];
    }
}
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Public Apply.svelte only | Staff create/edit via admin routes | This phase | Full admin CRUD capability |
| Accept/dismiss only | Full record editing | This phase | Complete application management |
| No edit audit trail | Track processed_by on edit | This phase | Who modified records |

**Deprecated/outdated:**
- None identified.

## Assumptions Log

| # | Claim | Section | Risk if Wrong |
|---|-------|---------|------------|
| A1 | Application window check is only in store() method | Architecture Patterns | LOW - verified in ApplicationController |
| A2 | Course preferences use Eloquent relationships | Standard Stack | LOW - verified in Application model |
| A3 | Roles from hasAnyRole() pattern are current | Code Examples | LOW - verified in ApplicationPolicy |

**If this table is empty:** All claims in this research were verified or cited — no user confirmation needed.

## Open Questions — RESOLVED

1. **Should staff create allow setting status directly?**
   - RESOLVED: Allow setting status to support bulk data entry scenarios. Staff can set accepted/dismissed on create.

2. **Should appointments be required for staff create?**
   - RESOLVED: Make appointment optional for staff (nullable in UpdateApplicationRequest).

3. **Should rejection_reason be editable?**
   - RESOLVED: Add field to admin edit form. Staff can add/edit rejection reason on edit.

## Environment Availability

| Dependency | Required By | Available | Version | Fallback |
|------------|------------|-----------|---------|----------|
| Laravel 12 | Backend | ✓ | v12 | — |
| Inertia.js v2 | Frontend | ✓ | v2 | — |
| Svelte 5 | Frontend | ✓ | v5 | — |
| MySQL | Database | ✓ | — | — |

**No missing dependencies identified.**

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | PHPUnit v11 |
| Config file | phpunit.xml |
| Quick run command | `php artisan test --compact` |
| Full suite command | `php artisan test --compact` |

### Phase Requirements -> Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| REQ-APP-01 | Staff can create application | Feature | `php artisan test --filter ApplicationCreateTest` | ❌ Create new |
| REQ-APP-02 | Staff can edit application | Feature | `php artisan test --filter ApplicationEditTest` | ❌ Create new |
| REQ-APP-03 | staff create bypasses window | Feature | `php artisan test --filter ApplicationCreateTest` | ❌ Create new |
| REQ-APP-04 | Role authorization | Feature | `php artisan test --filter ApplicationPolicyTest` | ❌ Create new |

### Sampling Rate
- **Per task commit:** `php artisan test --compact tests/Feature/Application*Test.php`
- **Per wave merge:** Full test suite
- **Phase gate:** Full suite green before /gsd-verify-work

### Wave 0 Gaps
- `tests/Feature/ApplicationCreateEditTest.php` — covers REQ-APP-01 through REQ-APP-04
- Test factory: ApplicationFactory already exists
- `tests/Feature/ApplicationTest.php` — existing but needs update for new endpoints

## Security Domain

### Applicable ASVS Categories
| ASVS Category | Applies | Standard Control |
|---------------|---------|----------------|
| V2 Authentication | yes | Laravel auth (existing) |
| V3 Session Management | yes | Laravel session (existing) |
| V4 Access Control | yes | ApplicationPolicy + role middleware |
| V5 Input Validation | yes | Form Request validation |
| V6 Cryptography | no | Not needed |

### Known Threat Patterns for Laravel/Inertia
| Pattern | STRIDE | Standard Mitigation |
|---------|--------|---------------------|
| Mass assignment | Tampering | $fillable whitelist on Application model |
| Role escalation | Elevation | Policy checks before actions |
| Input injection | Injection | Laravel validation |
| XSS from user data | XSS | Blade escaping (server renders) |

## Sources

### Primary (HIGH confidence)
- app/Models/Application.php - Model structure and fillable fields
- app/Http/Controllers/ApplicationController.php - Existing controller methods
- app/Policies/ApplicationPolicy.php - Authorization rules
- routes/web.php - Existing route definitions

### Secondary (MEDIUM confidence)
- resources/js/Pages/Applications/Apply.svelte - Form pattern to reuse
- resources/js/Pages/Applications/Index.svelte - Admin list pattern

### Tertiary (LOW confidence)
- None used.

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - Laravel 12 + Inertia v2 confirmed in project
- Architecture: HIGH - Existing patterns verified in codebase
- Pitfalls: MEDIUM - Based on code review, not documented elsewhere
- Validation: HIGH - PHPUnit v11 confirmed in project config

**Research date:** 2026-04-13
**Valid until:** 2026-05-13 (30 days for stable stack)
