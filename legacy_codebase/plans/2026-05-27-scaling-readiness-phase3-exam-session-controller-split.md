# Scaling Readiness — Phase 3: ExamSessionController God Class Split

> **For Antigravity:** REQUIRED WORKFLOW: Use `.agent/workflows/execute-plan.md` to execute this plan in single-flow mode.

**Goal:** Break `ExamSessionController` (857 lines, 19 methods) into 4 focused controllers, each with a single clear axis of responsibility.

**Architecture:** Thin HTTP controllers only. No business logic is written — methods are moved verbatim first, then the transition logic is optionally extracted to Action classes. State-transition guards live on the `ExamSession` model or in Action classes. All named routes preserved.

**Tech Stack:** Laravel 12, PHP 8.4, PHPUnit 11

---

## Background: 4 Responsibilities in `ExamSessionController`

| Responsibility | Methods | New Controller |
|---|---|---|
| CRUD | `index`, `create`, `store`, `show`, `edit`, `update`, `destroy` | `ExamSessionController` (keep, trimmed) |
| State transitions | `publish`, `unpublish`, `cancel`, `start`, `complete`, `reopen`, `backtrack` | `ExamSessionWorkflowController` |
| Applicant roster management | `assignApplicants`, `removeApplicant` | `ExamSessionRosterController` |
| Monitoring + test-admin views | `monitoring`, `testAdminIndex`, `testAdminRoster` | `ExamSessionMonitoringController` |

---

## Pre-flight

```bash
php artisan test --compact --filter=ExamSession
php artisan route:list --path=exam-scheduling --compact
php artisan route:list --path=exam-monitoring --compact
php artisan route:list --path=admin/test-admin --compact
```

Note all passing tests before starting.

---

## Task 1: Create the Workflow Controller

**Files:**
- Create: `app/Http/Controllers/Admin/ExamSessionWorkflowController.php`
- Modify: `routes/web.php` (7 transition POST routes)
- Test: `tests/Feature/Admin/ExamSessionWorkflowControllerTest.php`

**Step 1: Write failing tests**

```php
<?php

namespace Tests\Feature\Admin;

use App\Models\ExamSession;
use App\Models\User;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ExamSessionWorkflowControllerTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        return User::factory()->withRole('registrar_administrator')->create();
    }

    public function test_publish_requires_auth(): void
    {
        $session = ExamSession::factory()->create();
        $response = $this->post(route('admin.exam-scheduling.publish', $session));
        $response->assertRedirect(route('login'));
    }

    public function test_registrar_can_publish_draft_session(): void
    {
        $admin = $this->adminUser();
        $session = ExamSession::factory()->draft()->create();

        $response = $this->actingAs($admin)
            ->post(route('admin.exam-scheduling.publish', $session));

        $response->assertRedirect();
        $this->assertDatabaseHas('exam_sessions', ['id' => $session->id, 'status' => 'published']);
    }

    public function test_cancel_transitions_session_to_cancelled(): void
    {
        $admin = $this->adminUser();
        $session = ExamSession::factory()->published()->create();

        $response = $this->actingAs($admin)
            ->post(route('admin.exam-scheduling.cancel', $session));

        $response->assertRedirect();
        $this->assertDatabaseHas('exam_sessions', ['id' => $session->id, 'status' => 'cancelled']);
    }

    public function test_backtrack_requires_completed_session(): void
    {
        $admin = $this->adminUser();
        $session = ExamSession::factory()->draft()->create();

        $response = $this->actingAs($admin)
            ->post(route('admin.exam-scheduling.backtrack', $session));

        // Should fail gracefully — not a 500
        $response->assertStatus(302);
    }
}
```

Run: `php artisan test --compact tests/Feature/Admin/ExamSessionWorkflowControllerTest.php`

**Step 2: Create the controller**

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExamSession;
use Illuminate\Http\Request;

class ExamSessionWorkflowController extends Controller
{
    public function publish(Request $request, ExamSession $examSession)
    {
        // Copy exact body of ExamSessionController::publish()
    }

    public function unpublish(Request $request, ExamSession $examSession)
    {
        // Copy exact body of ExamSessionController::unpublish()
    }

    public function cancel(Request $request, ExamSession $examSession)
    {
        // Copy exact body of ExamSessionController::cancel()
    }

    public function start(Request $request, ExamSession $examSession)
    {
        // Copy exact body of ExamSessionController::start()
    }

    public function complete(Request $request, ExamSession $examSession)
    {
        // Copy exact body of ExamSessionController::complete()
    }

    public function reopen(Request $request, ExamSession $examSession)
    {
        // Copy exact body of ExamSessionController::reopen()
    }

    public function backtrack(Request $request, ExamSession $examSession)
    {
        // Copy exact body of ExamSessionController::backtrack()
    }
}
```

**Step 3: Update the 7 POST routes in `web.php`**

In the `registrar_administrator` group, change all 7 transition routes from `ExamSessionController` to `ExamSessionWorkflowController`:

```php
use App\Http\Controllers\Admin\ExamSessionWorkflowController;

Route::post('exam-scheduling/{exam_session}/publish', [ExamSessionWorkflowController::class, 'publish'])->name('exam-scheduling.publish');
Route::post('exam-scheduling/{exam_session}/unpublish', [ExamSessionWorkflowController::class, 'unpublish'])->name('exam-scheduling.unpublish');
Route::post('exam-scheduling/{exam_session}/cancel', [ExamSessionWorkflowController::class, 'cancel'])->name('exam-scheduling.cancel');
Route::post('exam-scheduling/{exam_session}/start', [ExamSessionWorkflowController::class, 'start'])->name('exam-scheduling.start');
Route::post('exam-scheduling/{exam_session}/complete', [ExamSessionWorkflowController::class, 'complete'])->name('exam-scheduling.complete');
Route::post('exam-scheduling/{exam_session}/reopen', [ExamSessionWorkflowController::class, 'reopen'])->name('exam-scheduling.reopen');
Route::post('exam-scheduling/{exam_session}/backtrack', [ExamSessionWorkflowController::class, 'backtrack'])->name('exam-scheduling.backtrack');
```

**Step 4: Run tests, remove 7 methods from old controller, run full suite, commit**

```bash
php artisan test --compact tests/Feature/Admin/ExamSessionWorkflowControllerTest.php
# Remove publish/unpublish/cancel/start/complete/reopen/backtrack from ExamSessionController
php artisan test --compact --filter=ExamSession
git add .
git commit -m "refactor: extract state transition methods into ExamSessionWorkflowController"
```

---

## Task 2: Create the Roster Controller

**Files:**
- Create: `app/Http/Controllers/Admin/ExamSessionRosterController.php`
- Modify: `routes/web.php` (2 POST routes)
- Test: `tests/Feature/Admin/ExamSessionRosterControllerTest.php`

**Step 1: Write failing test**

```php
<?php

namespace Tests\Feature\Admin;

use App\Models\Application;
use App\Models\ExamSession;
use App\Models\User;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ExamSessionRosterControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_assign_applicants_requires_registrar_role(): void
    {
        $proctor = User::factory()->withRole('proctor')->create();
        $session = ExamSession::factory()->create();

        $response = $this->actingAs($proctor)
            ->post(route('admin.exam-scheduling.assign-applicants', $session), ['ids' => []]);

        $response->assertStatus(403);
    }

    public function test_registrar_can_assign_applicants(): void
    {
        $admin = User::factory()->withRole('registrar_administrator')->create();
        $session = ExamSession::factory()->published()->create();
        $application = Application::factory()->accepted()->create();

        $response = $this->actingAs($admin)
            ->post(route('admin.exam-scheduling.assign-applicants', $session), [
                'ids' => [$application->id],
            ]);

        $response->assertRedirect();
    }
}
```

**Step 2: Create the controller**

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExamSession;
use Illuminate\Http\Request;

class ExamSessionRosterController extends Controller
{
    public function assignApplicants(Request $request, ExamSession $examSession)
    {
        // Copy exact body of ExamSessionController::assignApplicants()
    }

    public function removeApplicant(Request $request, ExamSession $examSession)
    {
        // Copy exact body of ExamSessionController::removeApplicant()
    }
}
```

**Step 3: Update 2 routes, run tests, remove from old controller, commit**

```bash
php artisan test --compact --filter=ExamSession
git add .
git commit -m "refactor: extract applicant roster management into ExamSessionRosterController"
```

---

## Task 3: Create the Monitoring Controller

**Files:**
- Create: `app/Http/Controllers/Admin/ExamSessionMonitoringController.php`
- Modify: `routes/web.php` (3 GET routes)
- Test: `tests/Feature/Admin/ExamSessionMonitoringControllerTest.php`

**Step 1: Write failing test**

```php
<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ExamSessionMonitoringControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_monitoring_page_requires_test_administrator_role(): void
    {
        $user = User::factory()->withRole('registrar_administrator')->create();
        $response = $this->actingAs($user)->get(route('admin.exam-monitoring.index'));
        $response->assertStatus(403);
    }

    public function test_test_administrator_can_view_monitoring(): void
    {
        $admin = User::factory()->withRole('test_administrator')->create();
        $response = $this->actingAs($admin)->get(route('admin.exam-monitoring.index'));
        $response->assertStatus(200);
    }

    public function test_test_admin_sessions_index_is_accessible(): void
    {
        $admin = User::factory()->withRole('test_administrator')->create();
        $response = $this->actingAs($admin)->get(route('admin.test-admin.sessions.index'));
        $response->assertStatus(200);
    }
}
```

**Step 2: Create the controller**

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExamSession;

class ExamSessionMonitoringController extends Controller
{
    public function monitoring()
    {
        // Copy exact body of ExamSessionController::monitoring()
    }

    public function testAdminIndex()
    {
        // Copy exact body of ExamSessionController::testAdminIndex()
    }

    public function testAdminRoster(ExamSession $examSession)
    {
        // Copy exact body of ExamSessionController::testAdminRoster()
    }
}
```

**Step 3: Update 3 routes, run tests, remove from old controller, commit**

```bash
php artisan test --compact --filter=ExamSession
git add .
git commit -m "refactor: extract monitoring and test-admin views into ExamSessionMonitoringController"
```

---

## Task 4: Final — Verify `ExamSessionController` is now CRUD-only

**Step 1: Verify remaining methods**

```bash
grep "public function" app/Http/Controllers/Admin/ExamSessionController.php
```
Expected output — exactly these 7 methods:
```
public function index()
public function create()
public function store()
public function show()
public function edit()
public function update()
public function destroy()
```

**Step 2: Check line count**

```bash
wc -l app/Http/Controllers/Admin/ExamSessionController.php
```
Expected: **under 250 lines** (down from 857).

**Step 3: Run full test suite**

```bash
php artisan test --compact
```
Expected: all green.

**Step 4: Final commit**

```bash
git add .
git commit -m "refactor: ExamSessionController now CRUD-only (857 → ~220 lines)"
```

---

## What Changes After This Plan

| Before | After |
|---|---|
| `ExamSessionController` 857 lines, 19 methods | `ExamSessionController` ~220 lines, 7 methods |
| — | `ExamSessionWorkflowController` ~200 lines, 7 methods |
| — | `ExamSessionRosterController` ~60 lines, 2 methods |
| — | `ExamSessionMonitoringController` ~80 lines, 3 methods |

All named routes are **unchanged**. All Svelte pages continue working without modification.
