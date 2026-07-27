# Scaling Readiness — Phase 2: ApplicationController God Class Split

> **For Antigravity:** REQUIRED WORKFLOW: Use `.agent/workflows/execute-plan.md` to execute this plan in single-flow mode.

**Goal:** Break `ApplicationController` (1,047 lines, 23 methods, 6 responsibilities) into focused single-responsibility controllers + Action classes.

**Architecture:** Extract without changing behavior. Each new controller is a thin HTTP layer delegating to the existing service/model layer. No business logic moves. Tests are updated to target new controller locations. Routing is updated atomically — old named routes are preserved via aliases where needed.

**Tech Stack:** Laravel 12, Inertia v2, PHP 8.4, PHPUnit 11

**Key Principle:** TDD — write the test pointing at the new controller FIRST, then move the method, then delete from the old controller.

---

## Background: The 6 Responsibilities in `ApplicationController`

| Group | Methods | Lines (approx) | New Home |
|---|---|---|---|
| Public apply flow | `create`, `store`, `success` | ~120 | `PublicApplicationController` |
| Portal (applicant self-service) | `portalShow`, `portalEdit`, `portalUpdate` | ~100 | `PortalApplicationController` |
| Admin CRUD | `index`, `show`, `edit`, `create` (admin), `storeAdmin`, `updateAdmin`, `destroy`, `reopen` | ~200 | `AdminApplicationController` |
| Bulk operations | `bulkAccept`, `bulkDismiss`, `bulkReopen`, `accept`, `dismiss` | ~120 | `BulkApplicationController` |
| Admission slips | `admissionSlip`, `admissionSlipPrint`, `admissionSlipBulkPrint`, `admissionSlipBulkPdf` | ~150 | `AdmissionSlipController` |
| Portal auth admission slip | `admissionSlipPdf` (in PortalAuthController) | already separate | leave in PortalAuthController |

---

## Pre-flight: Read the source first

```bash
wc -l app/Http/Controllers/ApplicationController.php
# Expected: ~1050
php artisan route:list --path=applications --compact
php artisan route:list --path=apply --compact
php artisan test --compact --filter=Application
```

Note ALL currently passing application tests before starting.

---

## Task 1: Create the directory structure

**Files:**
- Create dir: `app/Http/Controllers/Applications/`

**Step 1: Create directory**

```bash
mkdir -p app/Http/Controllers/Applications
```

**Step 2: Commit**

```bash
git commit --allow-empty -m "chore: scaffold Applications/ controller namespace directory"
```

---

## Task 2: Extract `PublicApplicationController`

**Files:**
- Create: `app/Http/Controllers/Applications/PublicApplicationController.php`
- Modify: `routes/web.php` (update 3 public apply routes)
- Test: `tests/Feature/Applications/PublicApplicationControllerTest.php`

**Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Applications;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PublicApplicationControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_apply_page_is_accessible_to_guests(): void
    {
        $response = $this->get(route('applications.create'));
        $response->assertStatus(200);
    }

    public function test_success_page_is_accessible(): void
    {
        $response = $this->get(route('applications.success'));
        $response->assertStatus(200);
    }
}
```

Run: `php artisan test --compact tests/Feature/Applications/PublicApplicationControllerTest.php`
Expected: **FAIL** (file doesn't exist yet, but route exists — it hits the old controller).

Actually the test will PASS since routes still point to old controller. That is expected at this stage. The test is your regression guard.

**Step 2: Create the new controller**

```php
<?php

namespace App\Http\Controllers\Applications;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreApplicationRequest;
use App\Models\AcademicYear;
use App\Models\PrivacyPolicy;
use Inertia\Inertia;

class PublicApplicationController extends Controller
{
    public function create()
    {
        // Copy the exact body of ApplicationController::create() here.
        // Do not rewrite — exact copy first, refactor later.
    }

    public function store(StoreApplicationRequest $request)
    {
        // Copy the exact body of ApplicationController::store() here.
    }

    public function success()
    {
        // Copy the exact body of ApplicationController::success() here.
    }
}
```

**Step 3: Update the 3 public routes in `web.php`**

Change:
```php
use App\Http\Controllers\ApplicationController;
// ...
Route::get('/apply', [ApplicationController::class, 'create'])->name('applications.create');
Route::post('/applications', [ApplicationController::class, 'store'])->name('applications.store');
Route::get('/apply/success', [ApplicationController::class, 'success'])->name('applications.success');
```

To:
```php
use App\Http\Controllers\Applications\PublicApplicationController;
// ...
Route::get('/apply', [PublicApplicationController::class, 'create'])->name('applications.create');
Route::post('/applications', [PublicApplicationController::class, 'store'])->name('applications.store');
Route::get('/apply/success', [PublicApplicationController::class, 'success'])->name('applications.success');
```

**Step 4: Run the test**

```bash
php artisan test --compact tests/Feature/Applications/PublicApplicationControllerTest.php
```
Expected: PASS.

**Step 5: Remove the 3 methods from `ApplicationController`**

Delete `create`, `store`, `success` from `ApplicationController.php`.

**Step 6: Run full suite**

```bash
php artisan test --compact --filter=Application
```
Expected: all green.

**Step 7: Commit**

```bash
git add app/Http/Controllers/Applications/PublicApplicationController.php routes/web.php app/Http/Controllers/ApplicationController.php tests/Feature/Applications/PublicApplicationControllerTest.php
git commit -m "refactor: extract public apply flow into PublicApplicationController"
```

---

## Task 3: Extract `PortalApplicationController`

**Files:**
- Create: `app/Http/Controllers/Applications/PortalApplicationController.php`
- Modify: `routes/web.php` (update 3 portal application routes)
- Test: `tests/Feature/Applications/PortalApplicationControllerTest.php`

**Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Applications;

use App\Models\Applicant;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PortalApplicationControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_portal_application_show_requires_auth(): void
    {
        $response = $this->get(route('portal.application.show'));
        $response->assertRedirect(route('portal.login'));
    }

    public function test_authenticated_applicant_can_view_application(): void
    {
        $applicant = Applicant::factory()->withApplication()->create();
        $response = $this->actingAs($applicant, 'applicant')
            ->get(route('portal.application.show'));
        $response->assertStatus(200);
    }
}
```

Run: `php artisan test --compact tests/Feature/Applications/PortalApplicationControllerTest.php`
Expected: PASS (routes still point to old controller at this stage — test is a regression guard).

**Step 2: Create the controller**

```php
<?php

namespace App\Http\Controllers\Applications;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateApplicationRequest;
use Inertia\Inertia;

class PortalApplicationController extends Controller
{
    public function show()
    {
        // Copy exact body of ApplicationController::portalShow()
    }

    public function edit()
    {
        // Copy exact body of ApplicationController::portalEdit()
    }

    public function update(UpdateApplicationRequest $request)
    {
        // Copy exact body of ApplicationController::portalUpdate()
    }
}
```

**Step 3: Update routes in `web.php` (portal.application.* group)**

Change:
```php
Route::get('application', [ApplicationController::class, 'portalShow'])->name('application.show');
Route::get('application/edit', [ApplicationController::class, 'portalEdit'])->name('application.edit');
Route::put('application', [ApplicationController::class, 'portalUpdate'])->name('application.update');
```
To:
```php
use App\Http\Controllers\Applications\PortalApplicationController;
// ...
Route::get('application', [PortalApplicationController::class, 'show'])->name('application.show');
Route::get('application/edit', [PortalApplicationController::class, 'edit'])->name('application.edit');
Route::put('application', [PortalApplicationController::class, 'update'])->name('application.update');
```

**Step 4: Run test + delete methods from old controller**

```bash
php artisan test --compact tests/Feature/Applications/PortalApplicationControllerTest.php
```

Remove `portalShow`, `portalEdit`, `portalUpdate` from `ApplicationController`.

**Step 5: Run full suite**

```bash
php artisan test --compact --filter=Application
```

**Step 6: Commit**

```bash
git add .
git commit -m "refactor: extract portal application self-service into PortalApplicationController"
```

---

## Task 4: Extract `BulkApplicationController`

**Files:**
- Create: `app/Http/Controllers/Applications/BulkApplicationController.php`
- Modify: `routes/web.php` (bulk-* and accept/dismiss single routes)
- Test: `tests/Feature/Applications/BulkApplicationControllerTest.php`

**Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Applications;

use App\Models\Application;
use App\Models\User;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class BulkApplicationControllerTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        return User::factory()->withRole('registrar_administrator')->create();
    }

    public function test_bulk_accept_requires_auth(): void
    {
        $response = $this->post(route('admin.applications.bulk-accept'));
        $response->assertRedirect(route('login'));
    }

    public function test_bulk_accept_updates_application_statuses(): void
    {
        $admin = $this->adminUser();
        $applications = Application::factory()->count(3)->pending()->create();

        $response = $this->actingAs($admin)
            ->post(route('admin.applications.bulk-accept'), [
                'ids' => $applications->pluck('id')->toArray(),
            ]);

        $response->assertRedirect();
        $this->assertDatabaseMissing('applications', ['status' => 'pending']);
    }
}
```

Run: `php artisan test --compact tests/Feature/Applications/BulkApplicationControllerTest.php`

**Step 2: Create the controller**

```php
<?php

namespace App\Http\Controllers\Applications;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class BulkApplicationController extends Controller
{
    public function accept(Request $request, $application)
    {
        // Copy exact body of ApplicationController::accept()
    }

    public function dismiss(Request $request, $application)
    {
        // Copy exact body of ApplicationController::dismiss()
    }

    public function bulkAccept(Request $request)
    {
        // Copy exact body of ApplicationController::bulkAccept()
    }

    public function bulkDismiss(Request $request)
    {
        // Copy exact body of ApplicationController::bulkDismiss()
    }

    public function bulkReopen(Request $request)
    {
        // Copy exact body of ApplicationController::bulkReopen()
    }
}
```

**Step 3: Update routes + run tests + remove from old controller + commit**

Follow the same pattern as Tasks 2 and 3. Update the 5 routes in `web.php` pointing to `bulkAccept`, `bulkDismiss`, `bulkReopen`, `accept`, `dismiss`.

```bash
php artisan test --compact --filter=Application
git add .
git commit -m "refactor: extract bulk accept/dismiss/reopen into BulkApplicationController"
```

---

## Task 5: Extract `AdmissionSlipController`

**Files:**
- Create: `app/Http/Controllers/Applications/AdmissionSlipController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Applications/AdmissionSlipControllerTest.php`

**Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Applications;

use App\Models\Application;
use App\Models\User;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AdmissionSlipControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admission_slip_requires_auth(): void
    {
        $application = Application::factory()->accepted()->create();
        $response = $this->get(route('admin.applications.admission-slip', $application));
        $response->assertRedirect(route('login'));
    }

    public function test_admission_slip_is_accessible_to_registrar(): void
    {
        $admin = User::factory()->withRole('registrar_administrator')->create();
        $application = Application::factory()->accepted()->create();
        $response = $this->actingAs($admin)
            ->get(route('admin.applications.admission-slip', $application));
        $response->assertStatus(200);
    }
}
```

**Step 2: Create the controller**

```php
<?php

namespace App\Http\Controllers\Applications;

use App\Http\Controllers\Controller;
use App\Models\Application;

class AdmissionSlipController extends Controller
{
    public function show(Application $application)
    {
        // Copy exact body of ApplicationController::admissionSlip()
    }

    public function print(Application $application)
    {
        // Copy exact body of ApplicationController::admissionSlipPrint()
    }

    public function bulkPrint()
    {
        // Copy exact body of ApplicationController::admissionSlipBulkPrint()
    }

    public function bulkPdf()
    {
        // Copy exact body of ApplicationController::admissionSlipBulkPdf()
    }
}
```

**Step 3: Update routes, run tests, remove methods, commit**

```bash
php artisan test --compact --filter=AdmissionSlip
git add .
git commit -m "refactor: extract admission slip actions into AdmissionSlipController"
```

---

## Task 6: Rename remaining methods in `AdminApplicationController`

**Files:**
- Create: `app/Http/Controllers/Applications/AdminApplicationController.php`
- Modify: `routes/web.php`

The remaining methods in `ApplicationController` after extractions above should be:
`index`, `show`, `create` (admin version), `storeAdmin`, `updateAdmin`, `destroy`, `reopen`, `resendSetupEmail`

Move these to `AdminApplicationController`, update all 8 routes, run tests, commit.

```bash
git commit -m "refactor: extract admin CRUD into AdminApplicationController - ApplicationController is now empty"
```

---

## Task 7: Delete the now-empty `ApplicationController`

**Step 1: Verify the file has no remaining public methods**

```bash
grep "public function" app/Http/Controllers/ApplicationController.php
```
Expected: **no output.**

**Step 2: Delete the file**

```bash
git rm app/Http/Controllers/ApplicationController.php
```

**Step 3: Remove the `use` import from `web.php`**

Remove line `use App\Http\Controllers\ApplicationController;` from `web.php`.

**Step 4: Run full test suite**

```bash
php artisan test --compact
```
Expected: all green.

**Step 5: Final commit**

```bash
git add .
git commit -m "chore: delete now-empty ApplicationController — fully split into 4 focused controllers"
```

---

## Route Map After Completion

```
Public apply:        GET  /apply                      → PublicApplicationController@create
                     POST /applications               → PublicApplicationController@store
                     GET  /apply/success              → PublicApplicationController@success

Portal self-service: GET  portal/application          → PortalApplicationController@show
                     GET  portal/application/edit     → PortalApplicationController@edit
                     PUT  portal/application          → PortalApplicationController@update

Admin CRUD:          GET  admin/applications          → AdminApplicationController@index
                     GET  admin/applications/create   → AdminApplicationController@create
                     POST admin/applications          → AdminApplicationController@storeAdmin
                     GET  admin/applications/{app}    → AdminApplicationController@show
                     GET  admin/applications/{app}/edit → AdminApplicationController@edit
                     PUT  admin/applications/{app}    → AdminApplicationController@updateAdmin
                     PUT  admin/applications/{app}/reopen → AdminApplicationController@reopen
                     DELETE admin/applications/{app} → AdminApplicationController@destroy

Bulk actions:        PUT  admin/applications/{app}/accept  → BulkApplicationController@accept
                     PUT  admin/applications/{app}/dismiss → BulkApplicationController@dismiss
                     POST admin/applications/bulk-accept   → BulkApplicationController@bulkAccept
                     POST admin/applications/bulk-dismiss  → BulkApplicationController@bulkDismiss
                     POST admin/applications/bulk-reopen   → BulkApplicationController@bulkReopen

Admission slips:     GET  admin/applications/{app}/admission-slip       → AdmissionSlipController@show
                     GET  admin/applications/{app}/admission-slip/print  → AdmissionSlipController@print
                     POST admin/applications/admission-slips/bulk-print → AdmissionSlipController@bulkPrint
                     POST admin/applications/admission-slips/bulk-pdf   → AdmissionSlipController@bulkPdf
```

All named routes remain **identical** — no Svelte/frontend changes needed.
