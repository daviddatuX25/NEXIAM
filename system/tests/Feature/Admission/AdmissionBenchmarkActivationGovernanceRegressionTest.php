<?php

use App\AdmissionEngine\Actions\ActivateAdmissionBenchmarkSet;
use App\AdmissionEngine\Actions\BenchmarkExecutionContext;
use App\AdmissionEngine\Actions\ImportAdmissionBenchmarkSet;
use App\Models\Admission\AdmissionBenchmarkActivation;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\AuditEvent;
use App\Models\Foundation\Institution;
use App\Models\User;

test('trusted seed may bootstrap a benchmark but cannot activate an unreviewed successor', function (): void {
    seedE2Foundation();

    $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $importer = app(ImportAdmissionBenchmarkSet::class);
    $activator = app(ActivateAdmissionBenchmarkSet::class);
    $humanContext = BenchmarkExecutionContext::human($guidance);
    $trustedContext = BenchmarkExecutionContext::trustedSeed($institution, 'seed');

    $baseRanges = [
        'BSBA' => [
            'strands' => ['STEM'],
            'riasec' => ['I', 'S'],
            'honors' => ['gwa' => [90, 95], 'gen' => [80, 90], 'verb' => [80, 90], 'num' => [80, 90], 'spat' => [75, 85], 'perc' => [80, 90], 'man' => [80, 90]],
            'regular' => ['gwa' => [85, 89], 'gen' => [74, 79], 'verb' => [74, 79], 'num' => [74, 79], 'spat' => [70, 74], 'perc' => [74, 79], 'man' => [74, 79]],
            'cutoff' => ['gwa' => [82, 84], 'gen' => [70, 73], 'verb' => [70, 73], 'num' => [70, 73], 'spat' => [66, 69], 'perc' => [70, 73], 'man' => [70, 73]],
        ],
    ];

    $base = $importer->handle($institution, $cycle, $humanContext, [
        'name' => 'Trusted bootstrap baseline',
        'change_reason' => 'Trusted bootstrap governance regression fixture',
        'raw_ranges' => $baseRanges,
    ], 'trusted-governance-base-import');

    expect($base->publication_manifest_json)->toBeNull()
        ->and(AdmissionBenchmarkActivation::currentForCycle((int) $cycle->id))->toBeNull();

    $activation = $activator->handle(
        $cycle,
        $base,
        $trustedContext,
        null,
        (string) $base->content_sha256,
        'trusted-governance-bootstrap',
    );

    expect($activation->superseded_at)->toBeNull()
        ->and(AdmissionBenchmarkActivation::currentForCycle((int) $cycle->id)?->id)->toBe($activation->id);

    $successorRanges = $baseRanges;
    $successorRanges['BSBA']['honors']['gwa'] = [91, 96];
    $successor = $importer->handle($institution, $cycle, $humanContext, [
        'name' => 'Trusted unreviewed successor',
        'change_reason' => 'Unreviewed successor must not activate',
        'raw_ranges' => $successorRanges,
    ], 'trusted-governance-successor-import');
    $activationCount = AdmissionBenchmarkActivation::query()->count();
    $auditEventCount = AuditEvent::query()->count();

    expect($successor->publication_manifest_json)->toBeNull();

    expect(fn () => $activator->handle(
        $cycle,
        $successor,
        $trustedContext,
        $activation->id,
        (string) $successor->content_sha256,
        'trusted-governance-unreviewed-successor',
    ))->toThrow(RuntimeException::class, 'reviewed publication manifest');

    expect(AdmissionBenchmarkActivation::query()->count())->toBe($activationCount)
        ->and(AdmissionBenchmarkActivation::currentForCycle((int) $cycle->id)?->id)->toBe($activation->id)
        ->and($activation->fresh()->superseded_at)->toBeNull()
        ->and(AdmissionBenchmarkActivation::query()->where('benchmark_set_id', $successor->id)->exists())->toBeFalse()
        ->and(AuditEvent::query()->count())->toBe($auditEventCount);
});
