<?php

use App\DocumentTemplate\DocumentFieldPolicy;
use App\DocumentTemplate\MountSlotRegistry;
use App\Http\Controllers\Reports\DocumentPrintController;
use App\Jobs\Printing\RenderDocumentPrintItem;
use App\Models\Reports\ReportTemplate;
use App\Models\User;
use App\Reports\Surfaces\AdmissionSlipSurface;
use App\Reports\Surfaces\ClearanceSlipSurface;
use App\Reports\Surfaces\InstitutionalSummarySurface;
use Database\Seeders\ReportTemplateSeeder;
use ReflectionMethod;

beforeEach(function (): void {
    seedE2Foundation();
});

test('has dynamic_qr in InstitutionalSummarySurface component catalog and default blueprint', function (): void {
    $surface = app(InstitutionalSummarySurface::class);
    $catalog = $surface->componentCatalog();
    $blueprint = $surface->defaultBlueprint();
    $sampleContext = $surface->sampleContext();
    $resolvedData = $surface->resolveData([]);

    expect($catalog)->toHaveKey('dynamic_qr')
        ->and(collect($blueprint['components'])->contains(fn (array $component): bool => $component['type'] === 'dynamic_qr'))->toBeTrue()
        ->and($sampleContext)->toHaveKey('verification_qr')
        ->and($sampleContext['verification_qr'])->not->toBeEmpty()
        ->and($resolvedData)->toHaveKey('verification_qr')
        ->and($resolvedData['verification_qr'])->not->toBeEmpty();
});

test('seeds institutional executive summary template with dynamic_qr in ReportTemplateSeeder', function (): void {
    (new ReportTemplateSeeder)->run();

    $template = ReportTemplate::query()
        ->where('surface_key', 'institutional_executive_summary')
        ->firstOrFail();
    $components = $template->builder_json['components'] ?? [];

    expect(collect($components)->contains(fn (array $component): bool => $component['type'] === 'dynamic_qr'))->toBeTrue()
        ->and($template->compiled_html)->toContain('component-dynamic-qr');
});

test('includes applicant locator qr and verification qr in DocumentPrintController student context', function (): void {
    $attempt = submitSyntheticApplicant('admin-office-template-qr-context@example.test')['attempt'];
    $method = new ReflectionMethod(DocumentPrintController::class, 'buildStudentContext');
    $context = $method->invoke(app(DocumentPrintController::class), $attempt, 1);

    expect($context)->toHaveKeys(['applicant_locator_qr', 'verification_qr', 'qr_code_svg'])
        ->and($context['applicant_locator_qr'])->not->toBeEmpty()
        ->and($context['verification_qr'])->not->toBeEmpty()
        ->and($context['qr_code_svg'])->not->toBeEmpty();
});
test('office student contexts expose native and short school and history without changing source values', function (): void {
    $attempt = submitSyntheticApplicant('admin-office-template-short-context@example.test')['attempt'];
    $attempt->forceFill([
        'last_school_enrolled' => 'Ilocos Sur College',
        'strand' => 'Humanities and Social Sciences',
    ])->save();
    $attempt->refresh();

    $syncMethod = new ReflectionMethod(DocumentPrintController::class, 'buildStudentContext');
    $syncContext = $syncMethod->invoke(app(DocumentPrintController::class), $attempt, 1);
    $queuedMethod = new ReflectionMethod(RenderDocumentPrintItem::class, 'studentContext');
    $queuedContext = $queuedMethod->invoke(
        new RenderDocumentPrintItem(1, 1, 1, []),
        $attempt,
        1,
        new ReportTemplate(['name' => 'Student sample', 'office' => 'guidance']),
        null,
    );

    foreach ([$syncContext, $queuedContext] as $context) {
        expect($context)
            ->toHaveKeys(['last_school_enrolled', 'last_school_short', 'strand_or_prev_course', 'strand_or_prev_course_short', 'school_short', 'history_short'])
            ->and($context['last_school_enrolled'])->toBe('Ilocos Sur College')
            ->and($context['last_school_short'])->toBe('ISC')
            ->and($context['school_short'])->toBe('ISC')
            ->and($context['strand_or_prev_course'])->toBe('Humanities and Social Sciences')
            ->and($context['strand_or_prev_course_short'])->toBe('HUMSS')
            ->and($context['history_short'])->toBe('HUMSS');
    }
    expect($attempt->fresh()->last_school_enrolled)->toBe('Ilocos Sur College')
        ->and($attempt->fresh()->strand)->toBe('Humanities and Social Sciences');
});

test('office policy advertises short student fields only on student-grain contexts', function (): void {
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $policy = app(DocumentFieldPolicy::class);
    $studentLeaves = $policy->allowedFor($guidance, 'guidance', MountSlotRegistry::GRAIN_STUDENT);
    $sessionLeaves = $policy->allowedFor($guidance, 'guidance', MountSlotRegistry::GRAIN_SESSION);

    expect($studentLeaves)
        ->toContain('last_school_enrolled', 'last_school_short', 'strand_or_prev_course', 'strand_or_prev_course_short')
        ->and($policy->validateReferences($guidance, 'guidance', MountSlotRegistry::GRAIN_STUDENT, ['school_short', 'history_short']))->toBeTrue()
        ->and($sessionLeaves)
        ->not->toContain('last_school_enrolled', 'last_school_short', 'strand_or_prev_course', 'strand_or_prev_course_short')
        ->and($policy->validateReferences($guidance, 'guidance', MountSlotRegistry::GRAIN_SESSION, ['school_short']))->toBeFalse();
});
test('supported office student surface samples carry native and abbreviated academic history', function (): void {
    foreach ([
        app(AdmissionSlipSurface::class),
        app(ClearanceSlipSurface::class),
    ] as $surface) {
        $sample = $surface->sampleContext();
        $catalog = $surface->placeholderCatalog();

        expect($catalog['all'])
            ->toContain('last_school_enrolled', 'last_school_short', 'school_short', 'strand_or_prev_course', 'strand_or_prev_course_short', 'history_short', 'strand')
            ->and($catalog['required'])
            ->not->toContain('last_school_enrolled', 'last_school_short', 'school_short', 'strand_or_prev_course', 'strand_or_prev_course_short', 'history_short', 'strand')
            ->and($sample['last_school_enrolled'])->toBe('Ilocos Sur College')
            ->and($sample['last_school_short'])->toBe('ISC')
            ->and($sample['school_short'])->toBe('ISC')
            ->and($sample['strand'])->toBe('Humanities and Social Sciences')
            ->and($sample['strand_or_prev_course'])->toBe('Humanities and Social Sciences')
            ->and($sample['strand_or_prev_course_short'])->toBe('HUMSS')
            ->and($sample['history_short'])->toBe('HUMSS');
    }
});
