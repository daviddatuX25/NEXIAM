# Scaling Readiness — Phase 4: God Service Class Splits

> **For Antigravity:** REQUIRED WORKFLOW: Use `.agent/workflows/execute-plan.md` to execute this plan in single-flow mode.

**Goal:** Decompose `ResultSheetTemplateService` (1,122 lines), `ReportExportService` (746 lines), and `DashboardAnalyticsService` (545 lines) into focused classes using Strategy and Pipeline patterns.

**Architecture:**
- `ResultSheetTemplateService` → Strategy pattern with a shared `ResultSheetRenderer` interface, delegating to `DocxRenderer`, `OdtRenderer`, `PdfRenderer`
- `ReportExportService` → Laravel Pipeline with focused pipe classes per report type
- `DashboardAnalyticsService` → Query Object pattern — one class per metric group

**Tech Stack:** Laravel 12, PHP 8.4, PHPUnit 11. No new packages required — uses built-in `Illuminate\Pipeline\Pipeline`.

---

## Pre-flight

```bash
wc -l app/Services/ResultSheetTemplateService.php
wc -l app/Services/ReportExportService.php
wc -l app/Services/DashboardAnalyticsService.php
php artisan test --compact --filter="ResultSheet|Report|Dashboard"
```

Note all currently passing tests before starting.

---

# Part A: `ResultSheetTemplateService` — Strategy Pattern

## Background: What the service does

Reading the source, the 1,122 lines break into these concerns:

| Concern | Methods (approx) | Renderer needed |
|---|---|---|
| Template validation + placeholder parsing | `validate()`, `extractPlaceholders()`, `validateDocument()` | None — stays in service |
| DOCX rendering | `renderDocx()`, `buildDocxFromTemplate()`, `substituteDocxPlaceholders()` | `DocxRenderer` |
| ODT rendering | `renderOdt()`, `buildOdtFromTemplate()`, `substituteOdtPlaceholders()` | `OdtRenderer` |
| PDF conversion | `convertToPdf()`, `renderPdf()` | `PdfRenderer` |
| Preview generation | `preview()` | Orchestrator method, stays in service |

---

## Task A1: Define the `ResultSheetRenderer` interface

**Files:**
- Create: `app/Services/ResultSheet/ResultSheetRenderer.php`

**Step 1: Write the interface**

```php
<?php

namespace App\Services\ResultSheet;

use App\Models\ResultSheetTemplate;

interface ResultSheetRenderer
{
    /**
     * Render the template with the given applicant data.
     *
     * @param  ResultSheetTemplate  $template
     * @param  array<string, mixed>  $data  Placeholder key → value map
     * @return RenderResult
     */
    public function render(ResultSheetTemplate $template, array $data): RenderResult;

    /**
     * Returns true if this renderer can handle the given template type.
     */
    public function supports(ResultSheetTemplate $template): bool;
}
```

**Step 2: Create the `RenderResult` value object**

```php
<?php

namespace App\Services\ResultSheet;

class RenderResult
{
    public function __construct(
        public readonly string $content,
        public readonly string $mimeType,
        public readonly string $extension,
    ) {}
}
```

**Step 3: Commit**

```bash
git add app/Services/ResultSheet/
git commit -m "feat: add ResultSheetRenderer interface and RenderResult value object"
```

---

## Task A2: Extract `DocxRenderer`

**Files:**
- Create: `app/Services/ResultSheet/DocxRenderer.php`
- Test: `tests/Feature/Services/ResultSheet/DocxRendererTest.php`

**Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Services\ResultSheet;

use App\Models\ResultSheetTemplate;
use App\Services\ResultSheet\DocxRenderer;
use App\Services\ResultSheet\RenderResult;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class DocxRendererTest extends TestCase
{
    use RefreshDatabase;

    public function test_supports_docx_template(): void
    {
        $renderer = new DocxRenderer();
        $template = ResultSheetTemplate::factory()->docx()->create();
        $this->assertTrue($renderer->supports($template));
    }

    public function test_does_not_support_odt_template(): void
    {
        $renderer = new DocxRenderer();
        $template = ResultSheetTemplate::factory()->odt()->create();
        $this->assertFalse($renderer->supports($template));
    }

    public function test_render_returns_render_result(): void
    {
        $renderer = new DocxRenderer();
        $template = ResultSheetTemplate::factory()->docx()->withFile()->create();
        $data = ['applicant_name' => 'Juan dela Cruz', 'score' => '85'];

        $result = $renderer->render($template, $data);

        $this->assertInstanceOf(RenderResult::class, $result);
        $this->assertEquals('docx', $result->extension);
        $this->assertNotEmpty($result->content);
    }
}
```

Run: `php artisan test --compact tests/Feature/Services/ResultSheet/DocxRendererTest.php`
Expected: **FAIL** with class not found.

**Step 2: Create `DocxRenderer`**

```php
<?php

namespace App\Services\ResultSheet;

use App\Models\ResultSheetTemplate;

class DocxRenderer implements ResultSheetRenderer
{
    public function supports(ResultSheetTemplate $template): bool
    {
        return $template->file_type === 'docx';
    }

    public function render(ResultSheetTemplate $template, array $data): RenderResult
    {
        // Move all DOCX-related private methods from ResultSheetTemplateService here:
        // buildDocxFromTemplate(), substituteDocxPlaceholders(), etc.
        // Return new RenderResult($content, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'docx')
    }
}
```

**Step 3: Run tests, commit**

```bash
php artisan test --compact tests/Feature/Services/ResultSheet/DocxRendererTest.php
git add .
git commit -m "feat: extract DocxRenderer from ResultSheetTemplateService"
```

---

## Task A3: Extract `OdtRenderer`

Follow the same structure as Task A2 for ODT files.

```php
class OdtRenderer implements ResultSheetRenderer
{
    public function supports(ResultSheetTemplate $template): bool
    {
        return $template->file_type === 'odt';
    }
    // ...
}
```

Test: `tests/Feature/Services/ResultSheet/OdtRendererTest.php`

```bash
git commit -m "feat: extract OdtRenderer from ResultSheetTemplateService"
```

---

## Task A4: Extract `PdfRenderer`

```php
class PdfRenderer implements ResultSheetRenderer
{
    public function supports(ResultSheetTemplate $template): bool
    {
        return $template->file_type === 'pdf';
    }
    // ...
}
```

Test: `tests/Feature/Services/ResultSheet/PdfRendererTest.php`

```bash
git commit -m "feat: extract PdfRenderer from ResultSheetTemplateService"
```

---

## Task A5: Update `ResultSheetTemplateService` to delegate to renderers

**Files:**
- Modify: `app/Services/ResultSheetTemplateService.php`

**Step 1: Inject renderers via constructor**

```php
<?php

namespace App\Services;

use App\Services\ResultSheet\ResultSheetRenderer;
use App\Models\ResultSheetTemplate;
use Illuminate\Contracts\Foundation\Application;

class ResultSheetTemplateService
{
    /** @var ResultSheetRenderer[] */
    private array $renderers;

    public function __construct(
        private readonly Application $app,
    ) {
        // Renderers registered in AppServiceProvider
        $this->renderers = $this->app->tagged('result_sheet_renderers');
    }

    public function render(ResultSheetTemplate $template, array $data)
    {
        foreach ($this->renderers as $renderer) {
            if ($renderer->supports($template)) {
                return $renderer->render($template, $data);
            }
        }

        throw new \RuntimeException("No renderer supports template type: {$template->file_type}");
    }

    // Keep: validate(), extractPlaceholders(), validateDocument(), preview() — template-agnostic methods
    // Remove: all DOCX/ODT/PDF private methods (now in individual renderers)
}
```

**Step 2: Register renderers in `AppServiceProvider`**

In `app/Providers/AppServiceProvider.php`:

```php
use App\Services\ResultSheet\DocxRenderer;
use App\Services\ResultSheet\OdtRenderer;
use App\Services\ResultSheet\PdfRenderer;

public function register(): void
{
    $this->app->tag([DocxRenderer::class, OdtRenderer::class, PdfRenderer::class], 'result_sheet_renderers');
    $this->app->singleton(ResultSheetTemplateService::class);
}
```

**Step 3: Run full test suite**

```bash
php artisan test --compact --filter="ResultSheet"
```
Expected: all green.

**Step 4: Check line count**

```bash
wc -l app/Services/ResultSheetTemplateService.php
```
Expected: **under 300 lines** (down from 1,122).

**Step 5: Commit**

```bash
git add .
git commit -m "refactor: ResultSheetTemplateService delegates rendering to Strategy pattern renderers"
```

---

# Part B: `ReportExportService` — Laravel Pipeline Pattern

## Background

The service has 4 export methods, each ~185 lines of inline: data gathering + stat computation + column building + CSV/Excel formatting. This is replaced by a Pipeline where each pipe has one job.

---

## Task B1: Create the pipe classes for report export

**Files:**
- Create: `app/Services/Reports/Pipes/GatherApplicantData.php`
- Create: `app/Services/Reports/Pipes/ComputeStatistics.php`
- Create: `app/Services/Reports/Pipes/FormatForExport.php`
- Create: `app/Services/Reports/ReportContext.php`

**Step 1: Create the `ReportContext` value object**

```php
<?php

namespace App\Services\Reports;

class ReportContext
{
    public function __construct(
        public readonly string $type,
        public readonly array $filters = [],
        public array $applicants = [],
        public array $statistics = [],
        public array $rows = [],
        public array $headers = [],
    ) {}
}
```

**Step 2: Create a sample pipe**

```php
<?php

namespace App\Services\Reports\Pipes;

use App\Services\Reports\ReportContext;
use Closure;

class GatherApplicantData
{
    public function handle(ReportContext $context, Closure $next): ReportContext
    {
        // Move the data-gathering portion of the relevant ReportExportService method here
        $context->applicants = /* query logic */;
        return $next($context);
    }
}
```

**Step 3: Test a pipe**

```php
<?php

namespace Tests\Unit\Services\Reports;

use App\Services\Reports\Pipes\GatherApplicantData;
use App\Services\Reports\ReportContext;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class GatherApplicantDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_populates_applicants_in_context(): void
    {
        $pipe = new GatherApplicantData();
        $context = new ReportContext(type: 'applicants');

        $result = $pipe->handle($context, fn ($ctx) => $ctx);

        $this->assertIsArray($result->applicants);
    }
}
```

**Step 4: Update `ReportExportService` to use Pipeline**

```php
<?php

namespace App\Services;

use App\Services\Reports\ReportContext;
use App\Services\Reports\Pipes\GatherApplicantData;
use App\Services\Reports\Pipes\ComputeStatistics;
use App\Services\Reports\Pipes\FormatForExport;
use Illuminate\Pipeline\Pipeline;

class ReportExportService
{
    public function __construct(private readonly Pipeline $pipeline) {}

    public function export(string $type, array $filters = []): array
    {
        $context = new ReportContext(type: $type, filters: $filters);

        return $this->pipeline
            ->send($context)
            ->through([
                GatherApplicantData::class,
                ComputeStatistics::class,
                FormatForExport::class,
            ])
            ->thenReturn()
            ->rows;
    }
}
```

**Step 5: Run full test suite + check line count + commit**

```bash
php artisan test --compact --filter="Report"
wc -l app/Services/ReportExportService.php
# Expected: under 80 lines
git add .
git commit -m "refactor: ReportExportService uses Pipeline pattern — 746 lines → 3 focused pipe classes"
```

---

# Part C: `DashboardAnalyticsService` — Query Objects

## Task C1: Extract query objects per metric group

**Files:**
- Create: `app/Queries/ApplicantStatusQuery.php`
- Create: `app/Queries/ExamSessionStatsQuery.php`
- Create: `app/Queries/GradingProgressQuery.php`

**Step 1: Create a query object**

```php
<?php

namespace App\Queries;

use App\Models\AcademicYear;
use App\Models\Application;
use Illuminate\Support\Facades\Cache;

class ApplicantStatusQuery
{
    public function __construct(private readonly AcademicYear $academicYear) {}

    public function get(): array
    {
        return Cache::remember(
            "applicant_status_{$this->academicYear->id}",
            now()->addMinutes(5),
            fn () => Application::query()
                ->where('academic_year_id', $this->academicYear->id)
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status')
                ->toArray()
        );
    }
}
```

**Step 2: Test the query object**

```php
<?php

namespace Tests\Feature\Queries;

use App\Models\AcademicYear;
use App\Models\Application;
use App\Queries\ApplicantStatusQuery;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ApplicantStatusQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_counts_grouped_by_status(): void
    {
        $year = AcademicYear::factory()->active()->create();
        Application::factory()->pending()->count(3)->create(['academic_year_id' => $year->id]);
        Application::factory()->accepted()->count(2)->create(['academic_year_id' => $year->id]);

        $result = (new ApplicantStatusQuery($year))->get();

        $this->assertEquals(3, $result['pending']);
        $this->assertEquals(2, $result['accepted']);
    }
}
```

**Step 3: Update `DashboardAnalyticsService` to use query objects**

```php
public function getApplicantMetrics(AcademicYear $year): array
{
    return (new ApplicantStatusQuery($year))->get();
}

public function getExamSessionStats(AcademicYear $year): array
{
    return (new ExamSessionStatsQuery($year))->get();
}
```

**Step 4: Run tests + check line count + commit**

```bash
php artisan test --compact --filter="Dashboard"
wc -l app/Services/DashboardAnalyticsService.php
# Expected: under 120 lines (down from 545)
git add .
git commit -m "refactor: DashboardAnalyticsService delegates to Query Objects per metric group"
```

---

## Final Verification

```bash
php artisan test --compact
php artisan route:list --except-vendor
```

Expected: all tests pass, no route errors.

## Size Summary After Phase 4

| Class | Before | After |
|---|---|---|
| `ResultSheetTemplateService` | 1,122 lines | ~280 lines |
| `DocxRenderer` (new) | — | ~250 lines |
| `OdtRenderer` (new) | — | ~200 lines |
| `PdfRenderer` (new) | — | ~150 lines |
| `ReportExportService` | 746 lines | ~60 lines |
| 3 Pipe classes (new) | — | ~60 lines each |
| `DashboardAnalyticsService` | 545 lines | ~110 lines |
| 3 Query classes (new) | — | ~40 lines each |
