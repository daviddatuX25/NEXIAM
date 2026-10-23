<?php

declare(strict_types=1);

namespace Tests\Feature\Assessment;

use App\Assessment\Omr\Diagnostics\OmrDiagnosticReportService;
use App\Assessment\Omr\Diagnostics\OmrDiagnosticScenarioManifest;
use App\Assessment\Omr\Verification\OmrPhysicalQualificationDeriver;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use App\Models\Assessment\OmrTemplateVerificationCapture;
use App\Models\Assessment\OmrTemplateVerificationItemEvidence;
use App\Models\Assessment\OmrTemplateVerificationRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);
function reportServiceReviewGeometry(int $itemCount = 18): array
{
    $fields = [];
    for ($item = 1; $item <= $itemCount; $item++) {
        foreach (['A', 'B', 'C', 'D'] as $optionIndex => $option) {
            $fields[] = [
                'page' => 1,
                'block' => 'DIAGNOSTIC',
                'label' => 'DIAGNOSTIC_Q_'.$item,
                'option' => $option,
                'pixel' => [
                    'x' => 100 + ($optionIndex * 60),
                    'y' => 100 + (($item - 1) * 70),
                    'w' => 40,
                    'h' => 40,
                ],
                'region' => [
                    'x' => (100 + ($optionIndex * 60)) / 2480,
                    'y' => (100 + (($item - 1) * 70)) / 3508,
                    'w' => 40 / 2480,
                    'h' => 40 / 3508,
                ],
            ];
        }
    }

    return [
        'manifest_version' => 'nexiam.omr.review-manifest.v1',
        'template_code' => 'DIAGNOSTIC',
        'template_version' => 1,
        'page_count' => 1,
        'fields' => $fields,
    ];
}

function createMockReportRun(): OmrTemplateVerificationRun
{
    $user = User::factory()->create();
    $draft = OmrTemplateAuthoringDraft::query()->create([
        'owner_user_id' => $user->id,
        'code' => 'REPORT-TEST-'.uniqid(),
        'name' => 'Report Test Draft',
        'stage' => 'mapping_complete',
        'lock_version' => 0,
        'source_kind' => 'html',
        'authoring_json' => [],
    ]);

    return OmrTemplateVerificationRun::query()->create([
        'draft_id' => $draft->id,
        'status' => OmrTemplateVerificationRun::STATUS_AWAITING_CAPTURE,
        'mode' => OmrTemplateVerificationRun::MODE_QUICK_REHEARSAL,
        'attempt' => 1,
        'plan_version' => 'v1',
        'plan_hash' => str_repeat('a', 64),
        'expected_vector_hash' => str_repeat('b', 64),
        'artifact_hash' => str_repeat('c', 64),
        'native_bundle_hash' => str_repeat('d', 64),
        'manifest_hash' => str_repeat('e', 64),
        'config_hash' => str_repeat('f', 64),
        'mapping_hash' => str_repeat('1', 64),
        'challenge_code' => 'CHAL-TESTREPORT',
        'progress_done' => 0,
        'progress_total' => 2,
        'started_by_user_id' => $user->id,
        'started_at' => now(),
    ]);
}

function createMockReportManifest(): OmrDiagnosticScenarioManifest
{
    return OmrDiagnosticScenarioManifest::quickRehearsalV1([
        'review_manifest' => reportServiceReviewGeometry(18),
        'target_id' => 'report-fixture',
        'target_kind' => 'candidate',
        'geometry_hash' => str_repeat('e', 64),
        'artifact_hash' => str_repeat('c', 64),
        'profile_hash' => str_repeat('2', 64),
        'native_bundle_hash' => str_repeat('d', 64),
        'config_hash' => str_repeat('f', 64),
        'mapping_hash' => str_repeat('1', 64),
        'renderer_version' => 'v4',
    ]);
}

test('diagnostic report separates machine outcome, optional review, gate outcome, and coverage gaps', function (): void {
    $run = createMockReportRun();
    $manifest = createMockReportManifest();
    $service = new OmrDiagnosticReportService;

    // Simulate pre-shaded capture evidence
    OmrTemplateVerificationCapture::query()->create([
        'run_id' => $run->id,
        'attempt' => 1,
        'attempt_no' => 1,
        'page_no' => 1,
        'method' => OmrTemplateVerificationCapture::METHOD_PRE_SHADED,
        'mode' => 'quick_rehearsal',
        'variant' => 'clean',
        'attempt_state' => OmrTemplateVerificationCapture::ATTEMPT_PASSED,
        'status' => OmrTemplateVerificationCapture::STATUS_PASS,
        'actual' => 'pass',
        'input_hash' => str_repeat('9', 64),
        'evidence_hash' => str_repeat('8', 64),
        'expected_vector_hash' => str_repeat('b', 64),
        'detected_vector_hash' => str_repeat('b', 64),
        'vector_match' => true,
        'summary' => [
            'evidence' => [
                'items' => [
                    [
                        'native_label' => 'DIAGNOSTIC_Q_1',
                        'detected' => [
                            'state' => 'marked',
                            'selected_option' => 'A',
                            'observed_options' => ['A'],
                        ],
                    ],
                ],
            ],
            'issues' => [],
        ],
        'captured_by_user_id' => $run->started_by_user_id,
        'captured_at' => now(),
    ]);

    $report = $service->buildReport($run, $manifest);

    expect($report['schema_version'])->toBe(OmrDiagnosticReportService::REPORT_SCHEMA)
        ->and($report['run_id'])->toBe($run->id)
        ->and($report['manifest_hash'])->toBe($manifest->hash())
        ->and($report['lane_summaries'])->toHaveKeys(['generated', 'hand_shaded'])
        ->and($report['qualification_status'])->toHaveKeys(['generated_diagnostic_passed', 'physical_qualification_status', 'physical_qualified', 'physical_ready'])
        ->and($report['coverage'])->toHaveKeys(['is_complete', 'gaps', 'required', 'covered', 'missing'])
        ->and($report['coverage']['is_complete'])->toBeTrue()
        ->and($report['scenarios'])->toHaveCount(18);

    $firstScenario = $report['scenarios'][0];
    expect($firstScenario)->toHaveKeys([
        'scenario_id', 'lane', 'seed', 'item', 'mark_style',
        'answer_condition', 'capture_condition', 'expected_class',
        'expected_option', 'candidates', 'machine_outcome',
        'human_review', 'effective_production',
    ])
        ->and($firstScenario['machine_outcome']['status'])->toBe('pass')
        ->and($firstScenario['machine_outcome']['state'])->toBe('marked')
        ->and($firstScenario['machine_outcome']['selected_option'])->toBe('A')
        ->and($firstScenario['human_review']['reviewed'])->toBeFalse()
        ->and($firstScenario['effective_production']['effective_answer'])->toBe('A')
        ->and($firstScenario['effective_production']['effective_state'])->toBe('resolved');
});

test('human review decisions never change raw scenario success, machine outcome, or physical qualification', function (): void {
    $run = createMockReportRun();
    $manifest = createMockReportManifest();
    $service = new OmrDiagnosticReportService;

    // Simulate capture where detector read B, but expected was A (machine failure)
    $capture = OmrTemplateVerificationCapture::query()->create([
        'run_id' => $run->id,
        'attempt' => 1,
        'attempt_no' => 1,
        'page_no' => 1,
        'method' => OmrTemplateVerificationCapture::METHOD_PRE_SHADED,
        'mode' => 'quick_rehearsal',
        'variant' => 'clean',
        'attempt_state' => OmrTemplateVerificationCapture::ATTEMPT_FAILED,
        'status' => OmrTemplateVerificationCapture::STATUS_FAIL,
        'actual' => 'answer_vector_mismatch',
        'input_hash' => str_repeat('9', 64),
        'evidence_hash' => str_repeat('8', 64),
        'expected_vector_hash' => str_repeat('b', 64),
        'detected_vector_hash' => str_repeat('7', 64),
        'vector_match' => false,
        'summary' => [
            'evidence' => [
                'items' => [
                    [
                        'native_label' => 'DIAGNOSTIC_Q_1',
                        'detected' => [
                            'state' => 'marked',
                            'selected_option' => 'B',
                            'observed_options' => ['B'],
                        ],
                    ],
                ],
            ],
            'issues' => [],
        ],
        'captured_by_user_id' => $run->started_by_user_id,
        'captured_at' => now(),
    ]);

    // Initial report before human review
    $beforeReview = $service->buildReport($run, $manifest);
    expect($beforeReview['scenarios'][0]['machine_outcome']['status'])->toBe('fail')
        ->and($beforeReview['scenarios'][0]['machine_outcome']['selected_option'])->toBe('B')
        ->and($beforeReview['scenarios'][0]['human_review']['reviewed'])->toBeFalse()
        ->and($beforeReview['qualification_status']['physical_qualified'])->toBeFalse();

    // Now simulate human review setting the answer to 'A' (correcting the detector)
    $itemEvidence = OmrTemplateVerificationItemEvidence::query()->create([
        'capture_id' => $capture->id,
        'item_id' => 'DIAGNOSTIC_Q_1',
        'group_type' => 'aptitude_area',
        'area_key' => 'DIAGNOSTIC',
        'question_no' => 1,
        'expected' => 'A',
        'detected' => 'B',
        'state' => 'marked',
        'evidence_hash' => str_repeat('8', 64),
    ]);

    DB::table('omr_item_review_revisions')->insert([
        'verification_item_evidence_id' => $itemEvidence->id,
        'item_key' => 'DIAGNOSTIC_Q_1',
        'revision' => 1,
        'decision' => 'set_option',
        'selected_option' => 'A',
        'reason_code' => 'light_mark_corrected',
        'reviewed_machine_evidence_hash' => str_repeat('8', 64),
        'parent_version' => 1,
        'reviewed_by_user_id' => $run->started_by_user_id,
        'command_name' => 'ReviewVerificationItemEvidence',
        'idempotency_key' => 'test-review-1',
        'correlation_id' => (string) Str::uuid(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $afterReview = $service->buildReport($run, $manifest);

    // CRITICAL (Rule 11.3): The raw machine outcome remains 'fail' and selected_option remains 'B'!
    expect($afterReview['scenarios'][0]['machine_outcome']['status'])->toBe('fail')
        ->and($afterReview['scenarios'][0]['machine_outcome']['selected_option'])->toBe('B')
        // Human review column records the review
        ->and($afterReview['scenarios'][0]['human_review']['reviewed'])->toBeTrue()
        ->and($afterReview['scenarios'][0]['human_review']['decision'])->toBe('set_option')
        ->and($afterReview['scenarios'][0]['human_review']['reviewed_option'])->toBe('A')
        ->and($afterReview['scenarios'][0]['human_review']['reason_code'])->toBe('light_mark_corrected')
        // Effective production answer reflects the review
        ->and($afterReview['scenarios'][0]['effective_production']['effective_answer'])->toBe('A')
        ->and($afterReview['scenarios'][0]['effective_production']['effective_state'])->toBe('resolved')
        // Physical qualification remains false (rehearsal only, human review cannot qualify)
        ->and($afterReview['qualification_status']['physical_qualified'])->toBeFalse()
        ->and($afterReview['lane_summaries']['generated']['passed_scenarios'])->toBe($beforeReview['lane_summaries']['generated']['passed_scenarios']);
});

test('generated diagnostic pass is labeled separately from pending physical qualification', function (): void {
    $run = createMockReportRun();
    $manifest = createMockReportManifest();
    $deriver = new OmrPhysicalQualificationDeriver;
    $service = new OmrDiagnosticReportService(null, $deriver);

    $report = $service->buildReport($run, $manifest);

    // Rule 11.4: Distinct labeling for generated vs physical
    expect($report['qualification_status'])->toHaveKey('generated_diagnostic_passed')
        ->and($report['qualification_status'])->toHaveKey('physical_qualification_status')
        ->and($report['qualification_status']['physical_qualification_status'])->toBe('rehearsal_only')
        ->and($report['qualification_status']['physical_qualified'])->toBeFalse()
        ->and($report['lane_summaries']['hand_shaded']['physical_qualification_status'])->toBe('rehearsal_only');
});

test('exportRedactedReport strips file storage paths, candidate PII, and notes with canonical hash', function (): void {
    $run = createMockReportRun();
    $manifest = createMockReportManifest();
    $service = new OmrDiagnosticReportService;

    $capture = OmrTemplateVerificationCapture::query()->create([
        'run_id' => $run->id,
        'attempt' => 1,
        'attempt_no' => 1,
        'page_no' => 1,
        'method' => OmrTemplateVerificationCapture::METHOD_PRE_SHADED,
        'mode' => 'quick_rehearsal',
        'variant' => 'clean',
        'attempt_state' => OmrTemplateVerificationCapture::ATTEMPT_PASSED,
        'status' => OmrTemplateVerificationCapture::STATUS_PASS,
        'actual' => 'pass',
        'input_hash' => str_repeat('9', 64),
        'evidence_hash' => str_repeat('8', 64),
        'expected_vector_hash' => str_repeat('b', 64),
        'detected_vector_hash' => str_repeat('b', 64),
        'vector_match' => true,
        'captured_by_user_id' => $run->started_by_user_id,
        'captured_at' => now(),
    ]);

    $itemEvidence = OmrTemplateVerificationItemEvidence::query()->create([
        'capture_id' => $capture->id,
        'item_id' => 'DIAGNOSTIC_Q_1',
        'group_type' => 'aptitude_area',
        'area_key' => 'DIAGNOSTIC',
        'question_no' => 1,
        'expected' => 'A',
        'detected' => 'A',
        'state' => 'marked',
        'evidence_hash' => str_repeat('8', 64),
    ]);

    DB::table('omr_item_review_revisions')->insert([
        'verification_item_evidence_id' => $itemEvidence->id,
        'item_key' => 'DIAGNOSTIC_Q_1',
        'revision' => 1,
        'decision' => 'confirm_machine',
        'selected_option' => 'A',
        'reason_code' => 'confirmed_clear_fill',
        'note' => 'Operator private notes about /tmp/scratch/source.png scan bytes',
        'reviewed_machine_evidence_hash' => str_repeat('8', 64),
        'parent_version' => 1,
        'reviewed_by_user_id' => $run->started_by_user_id,
        'command_name' => 'ReviewVerificationItemEvidence',
        'idempotency_key' => 'test-redact-1',
        'correlation_id' => (string) Str::uuid(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $redacted = $service->exportRedactedReport($run, $manifest);

    expect($redacted['schema_version'])->toBe(OmrDiagnosticReportService::REDACTED_SCHEMA)
        ->and($redacted['report_hash'])->toHaveLength(64);

    $json = json_encode($redacted, JSON_THROW_ON_ERROR);

    // Verify redactions
    expect($json)->not->toContain('/tmp/')
        ->and($json)->not->toContain('/var/')
        ->and($json)->not->toContain('/storage/')
        ->and($json)->not->toContain('applicant_number')
        ->and($json)->not->toContain('birthdate')
        ->and($redacted['scenarios'][0]['human_review']['reason_code'])->toBe('confirmed_clear_fill')
        ->and($redacted['scenarios'][0]['human_review'])->not->toHaveKey('note')
        ->and($redacted['scenarios'][0]['human_review'])->not->toHaveKey('notes');
});
