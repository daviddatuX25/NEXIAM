<?php

declare(strict_types=1);

namespace Tests\Feature\Setup;

use App\Assessment\Actions\Omr\Verification\CreateOmrPhysicalChallenge;
use App\Assessment\Omr\Diagnostics\OmrDiagnosticReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function reportFlowSetup(): void
{
    config([
        'omr.qr-keyring.current' => 'current-v1',
        'omr.qr-keyring.keys' => ['current-v1' => 'test-secret'],
        'omr.omrchecker.signing_secret' => str_repeat('s', 40),
    ]);
}

test('diagnostic report endpoints return report and redacted export for authorized user', function (): void {
    reportFlowSetup();
    $actor = physicalChallengeActor();
    $draft = physicalChallengeDraft($actor);

    $createResult = app(CreateOmrPhysicalChallenge::class)->handle($draft, $actor, 'quick');
    $run = $createResult['run'];

    // 1. GET diagnostic report
    $reportResponse = $this->actingAs($actor)
        ->getJson(route('setup.omr.templates.canonical.verification_runs.diagnostic_report', $run))
        ->assertStatus(200)
        ->assertJsonStructure([
            'schema_version',
            'run_id',
            'manifest_hash',
            'lane_summaries' => ['generated', 'hand_shaded'],
            'qualification_status' => ['generated_diagnostic_passed', 'physical_qualification_status', 'physical_qualified'],
            'coverage' => ['is_complete', 'gaps'],
            'scenarios',
        ]);

    expect($reportResponse->json('schema_version'))->toBe(OmrDiagnosticReportService::REPORT_SCHEMA)
        ->and($reportResponse->json('run_id'))->toBe($run->id);

    // 2. GET redacted export
    $exportResponse = $this->actingAs($actor)
        ->get(route('setup.omr.templates.canonical.verification_runs.diagnostic_report.export', $run))
        ->assertStatus(200)
        ->assertHeader('Content-Disposition', 'attachment; filename="omr-diagnostic-report-'.$run->id.'.json"');

    $exportData = $exportResponse->json();
    expect($exportData['schema_version'])->toBe(OmrDiagnosticReportService::REDACTED_SCHEMA)
        ->and($exportData['report_hash'])->toHaveLength(64);

    $rawJson = $exportResponse->getContent();
    expect($rawJson)->not->toContain('/tmp/')
        ->and($rawJson)->not->toContain('/var/')
        ->and($rawJson)->not->toContain('applicant_number');
});

test('diagnostic report endpoints deny unauthorized or cross-scope users', function (): void {
    reportFlowSetup();
    $actor = physicalChallengeActor();
    $draft = physicalChallengeDraft($actor);

    $createResult = app(CreateOmrPhysicalChallenge::class)->handle($draft, $actor, 'quick');
    $run = $createResult['run'];

    $stranger = User::factory()->create();
    $this->actingAs($stranger)
        ->getJson(route('setup.omr.templates.canonical.verification_runs.diagnostic_report', $run))
        ->assertStatus(403);

    $this->actingAs($stranger)
        ->getJson(route('setup.omr.templates.canonical.verification_runs.diagnostic_report.export', $run))
        ->assertStatus(403);
});
