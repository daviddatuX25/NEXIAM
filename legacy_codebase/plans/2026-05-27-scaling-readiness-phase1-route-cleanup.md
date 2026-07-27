# Scaling Readiness — Phase 1: Route Cleanup & Dead Code Removal

> **For Antigravity:** REQUIRED WORKFLOW: Use `.agent/workflows/execute-plan.md` to execute this plan in single-flow mode.

**Goal:** Remove dead routes, clarify intent of existing redirects, and delete orphaned files — zero behavior change to users.

**Architecture:** Surgical removal only. Each task is independently safe. No new files. No model/service changes. Routes stay backward-compatible where they still serve any purpose.

**Tech Stack:** Laravel 12 route file (`routes/web.php`), PHP 8.4

---

## Pre-flight: Understand the Route Map

Before touching anything, run:

```bash
php artisan route:list --path=admin --except-vendor
```

Cross-check that every route you are about to remove truly has no traffic in the JS frontend. The grep evidence already confirms:

| Route (GET) | Status | Evidence |
|---|---|---|
| `admin/knowledge-documents` redirect to ai-companion | **KEEP** - still used in 4 Svelte pages as breadcrumb/nav href | `KnowledgeDocuments/Index.svelte:26`, `Import.svelte:11`, `Edit.svelte:13`, `Create.svelte:10` |
| `admin/exam-scheduling/schedule-assistant` redirect to index | **REMOVE** - panel only calls POST sub-routes, never navigates to the GET | No frontend href found |
| `/proctor` redirect to dashboard | **REMOVE** - no frontend links, no Svelte page, was always a stub | No frontend href found |
| `admin/grading/import/preview` redirect to import | **KEEP** - POST-refresh guard, intentional UX protection | — |
| `admin/grading/import/confirm` redirect to import | **KEEP** - same reason | — |

---

## Task 1: Remove the dead `/proctor` stub redirect

**Files:**
- Modify: `routes/web.php:255`

**Step 1: Locate the line**

Open `routes/web.php`. Find line 255:

```php
Route::get('/proctor', fn () => redirect()->route('dashboard'))->middleware('role:super_admin,proctor');
```

**Step 2: Verify no frontend reference exists**

Run:
```bash
grep -r "/proctor" resources/js --include="*.svelte" --include="*.js" --include="*.ts" -l
```
Expected: **no files listed.**

**Step 3: Delete the line**

Remove the entire line 255.

**Step 4: Verify routes list is clean**

```bash
php artisan route:list --path=proctor
```
Expected: only `proctor/my-sessions`, `proctor/sessions/{exam_session}`, etc. No bare `/proctor` GET.

**Step 5: Commit**

```bash
git add routes/web.php
git commit -m "chore: remove dead /proctor stub redirect route"
```

---

## Task 2: Remove the dead `exam-scheduling/schedule-assistant` GET redirect

**Files:**
- Modify: `routes/web.php:158`

**Step 1: Locate the line**

```php
Route::get('exam-scheduling/schedule-assistant', fn () => redirect()->route('admin.exam-scheduling.index'))->name('exam-scheduling.schedule-assistant.index');
```

**Step 2: Verify the named route is never referenced**

```bash
grep -r "schedule-assistant.index\|exam-scheduling/schedule-assistant'" resources/js --include="*.svelte" --include="*.js" --include="*.ts"
grep -r "schedule-assistant.index" app --include="*.php"
```
Expected: **no matches** for the named route. The panel (`ScheduleAssistantPanel.svelte`) uses only POST endpoints `.chat`, `.apply-schedule`, `.conversation`.

**Step 3: Delete the line**

Remove line 158 entirely. The POST sub-routes for the assistant remain in the `registrar_administrator` group below — do not touch those.

**Step 4: Verify**

```bash
php artisan route:list --path=exam-scheduling
```
Expected: The `schedule-assistant` POST routes (`chat`, `apply-schedule`, `conversation`) are still listed. The GET `schedule-assistant` is gone.

**Step 5: Run the feature test suite for exam scheduling**

```bash
php artisan test --compact --filter=ExamSession
```
Expected: all green.

**Step 6: Commit**

```bash
git add routes/web.php
git commit -m "chore: remove dead GET schedule-assistant redirect route (panel uses POST sub-routes only)"
```

---

## Task 3: Clarify the grading import POST-guard redirects

**Files:**
- Modify: `routes/web.php:290-292`

**Step 1: Locate the block**

```php
// GET fallbacks — redirect to import form on page refresh
Route::get('grading/import/preview', fn () => redirect()->route('admin.grading.import'));
Route::get('grading/import/confirm', fn () => redirect()->route('admin.grading.import'));
```

**Step 2: Replace the comment only — do not touch the routes**

```php
// POST-only flow guards: these GET routes exist solely to handle browser page-refresh
// on POST-only steps (preview/confirm). Without them, refreshing would produce a 405.
// Do NOT remove — they are intentional UX safety nets, not legacy redirects.
Route::get('grading/import/preview', fn () => redirect()->route('admin.grading.import'));
Route::get('grading/import/confirm', fn () => redirect()->route('admin.grading.import'));
```

**Step 3: Commit**

```bash
git add routes/web.php
git commit -m "docs: clarify grading import GET redirects are POST-flow guards, not legacy code"
```

---

## Task 4: Clarify the `knowledge-documents` index redirect comment

**Files:**
- Modify: `routes/web.php:121-125`

**Step 1: Locate the block**

```php
// AI Companion hub (replaces knowledge-documents index)
Route::get('ai-companion', [AiCompanionAdminController::class, 'index'])->name('ai-companion.index');
Route::put('ai-companion/persona', [AiCompanionAdminController::class, 'updatePersona'])->name('ai-companion.persona.update');
// Redirect old knowledge-documents index → new hub
Route::get('knowledge-documents', fn () => redirect()->route('admin.ai-companion.index'))->name('knowledge-documents.index');
```

**Step 2: Update the comment**

```php
// AI Companion hub (replaced the old standalone knowledge-documents index page)
Route::get('ai-companion', [AiCompanionAdminController::class, 'index'])->name('ai-companion.index');
Route::put('ai-companion/persona', [AiCompanionAdminController::class, 'updatePersona'])->name('ai-companion.persona.update');
// Backward-compat redirect: 4 Svelte pages (KnowledgeDocuments/Index, Edit, Create, Import)
// all use href="/admin/knowledge-documents" as their breadcrumb root and self-links.
// Do NOT remove until all four pages are updated to use the ai-companion route directly.
Route::get('knowledge-documents', fn () => redirect()->route('admin.ai-companion.index'))->name('knowledge-documents.index');
```

**Step 3: Commit**

```bash
git add routes/web.php
git commit -m "docs: document why knowledge-documents redirect must stay (4 Svelte pages link to it)"
```

---

## Task 5: Delete the orphaned `AdmissionSlipTemplateController`

**Files:**
- Delete: `app/Http/Controllers/Admin/AdmissionSlipTemplateController.php`
- Modify: `routes/web.php:118-120`

**Step 1: Confirm zero active references**

```bash
grep -r "AdmissionSlipTemplateController" app routes --include="*.php"
```
Expected only:
- `routes/web.php:119-120` — already commented out
- `app/Http/Controllers/Admin/AdmissionSlipTemplateController.php` — the class itself

No `use` import for this class exists at the top of `web.php` (check lines 1-41). Safe to delete.

**Step 2: Delete the controller file**

```bash
rm app/Http/Controllers/Admin/AdmissionSlipTemplateController.php
```

**Step 3: Remove the three commented-out route lines in `web.php` (lines 118-120)**

Remove this block entirely:

```php
        // Admission slip templates — deprecated; replaced by system_settings.admission_slip_html_template
        // Route::post('admission-slip-templates/preview', [AdmissionSlipTemplateController::class, 'preview'])->name('admission-slip-templates.preview');
        // Route::resource('admission-slip-templates', AdmissionSlipTemplateController::class)->except('show')->parameters(['admission_slip_templates' => 'admission_slip_template']);
```

**Step 4: Run full test suite**

```bash
php artisan test --compact
```
Expected: all green (no test references the deleted controller).

**Step 5: Commit**

```bash
git add routes/web.php
git rm app/Http/Controllers/Admin/AdmissionSlipTemplateController.php
git commit -m "chore: delete orphaned AdmissionSlipTemplateController and its commented-out routes"
```

---

## Task 6: Merge the duplicate `setup` + `reports` route groups

**Files:**
- Modify: `routes/web.php:136-151`

**Background:** Lines 137-145 (Setup Hub) and lines 148-151 (Reports) are two separate `->group()` blocks with **identical middleware + prefix + name**:
`middleware('role:super_admin,registrar_administrator,test_administrator')->prefix('admin')->name('admin.')`

These should be one group.

**Step 1: Verify the two groups share identical signatures**

```bash
grep -n "role:super_admin,registrar_administrator,test_administrator" routes/web.php
```
Expected: appears at exactly lines 137 and 148 (and line 155 which has a different role set — do not touch that one).

**Step 2: Merge into a single group**

Replace the two separate groups with:

```php
    // Setup Hub + Reports — accessible to super_admin, registrar_administrator, test_administrator
    Route::middleware('role:super_admin,registrar_administrator,test_administrator')->prefix('admin')->name('admin.')->group(function () {
        Route::get('setup', [SetupController::class, 'index'])->name('setup.index');
        Route::get('setup/institution', [InstitutionController::class, 'index'])->name('setup.institution.index');
        Route::put('setup/institution', [InstitutionController::class, 'update'])->name('setup.institution.update');
        Route::post('setup/institution/reset', [InstitutionController::class, 'resetDefaults'])->name('setup.institution.reset');
        Route::resource('setup/rating-scales', RatingScaleController::class)
            ->except('show')
            ->parameters(['rating-scales' => 'rating_scale']);

        Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('reports/export/{type}', [ReportController::class, 'export'])->name('reports.export');
    });
```

**Step 3: Verify named routes still resolve**

```bash
php artisan route:list --name=admin.setup --compact
php artisan route:list --name=admin.reports --compact
```
Expected: all routes still appear with correct methods, URIs, and names.

**Step 4: Run full test suite**

```bash
php artisan test --compact
```

**Step 5: Commit**

```bash
git add routes/web.php
git commit -m "refactor: merge duplicate setup+reports route groups (same middleware, split was unnecessary)"
```

---

## Final Verification

```bash
php artisan route:list --except-vendor | wc -l
php artisan test --compact
git log --oneline -6
```

Expected: tests pass, 6 clean commits, route count is slightly lower (2 routes removed), no 404s for any previously working route.
