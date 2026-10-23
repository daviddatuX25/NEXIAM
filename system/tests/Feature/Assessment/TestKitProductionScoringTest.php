<?php

use App\Assessment\Actions\AcceptAssessmentRawResults;
use App\Assessment\Actions\Omr\ConfirmOmrCaptureBatch as ConfirmOmrCaptureBatchAction;
use App\Assessment\Omr\Review\GradingOmrEffectiveAnswerProjector;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\TestInstrumentMetric;
use App\Models\Assessment\TestInstrumentVersion;
use App\Models\Assessment\TestKitVersion;
use App\Models\Examination\AssessmentAdministration;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSessionMaterialSnapshot;
use App\Models\Examination\ExamSessionSeat;
use Illuminate\Validation\ValidationException;

/** @return array<string, mixed> */
function testKitProductionScoringContract(): array
{
    return [
        'profile_version_id' => 22,
        'content_sha256' => hash('sha256', 'profile'),
        'items' => [
            ['item_id' => 1, 'subtest_id' => 2, 'is_scored' => true, 'option_tokens' => ['A', 'B']],
        ],
        'bindings' => [
            [
                'page_no' => 1,
                'native_label' => 'Q1',
                'global_position' => 1,
                'sheet_item_id' => 10,
                'item_id' => 1,
                'subtest_id' => 2,
                'option_tokens' => ['A', 'B'],
                'correct_token' => 'B',
            ],
        ],
        'composition_rows' => [
            [
                'disposition' => 'physical',
                'global_position' => 1,
                'page_no' => 1,
                'native_label' => 'Q1',
                'sheet_item_id' => 10,
                'physical_item_uid' => 'physical-q1',
                'formal_item_id' => 1,
                'formal_subtest_id' => 2,
            ],
        ],
        'metrics' => [
            [
                'metric_id' => 3,
                'code' => 'RAW',
                'rule_kind' => TestInstrumentMetric::RULE_CORRECT_ITEM_COUNT,
                'subtest_id' => 2,
                'unit' => 'count',
                'min_raw_value' => '0',
                'max_raw_value' => '1',
                'precision_scale' => 0,
                'is_required' => true,
                'input_metric_ids' => [],
                'procedure_reference' => null,
            ],
        ],
    ];
}

function invokeMaterialApplicability(
    AssessmentAdministration $administration,
    TestInstrumentVersion $profile,
    string $source = 'manual',
): void {
    $action = app(AcceptAssessmentRawResults::class);
    $method = new ReflectionMethod($action, 'assertMaterialApplicability');
    $method->invoke($action, $administration, $profile, $source);
}

test('paper-backed pinned observations are evaluated into raw metrics with the production evaluator', function (): void {
    $profile = new TestInstrumentVersion;
    $profile->setRelation('metrics', collect([
        tap(new TestInstrumentMetric, static fn (TestInstrumentMetric $metric) => $metric->forceFill(['id' => 3, 'code' => 'RAW'])),
    ]));
    $kitVersion = new TestKitVersion;
    $kitVersion->setRelation('testInstrumentVersion', $profile);
    $action = new ConfirmOmrCaptureBatchAction(
        app(RecordAuditEvent::class),
        app(AcceptAssessmentRawResults::class),
    );
    $method = new ReflectionMethod($action, 'evaluateKitMetricValues');
    $values = $method->invoke($action, testKitProductionScoringContract(), [[
        'position' => 1,
        'page_no' => 1,
        'native_label' => 'Q1',
        'physical_item_uid' => 'physical-q1',
        'test_instrument_item_id' => 1,
        'test_instrument_subtest_id' => 2,
        'effective_state' => 'selected',
        'effective_option' => 'B',
    ]], $kitVersion);

    expect($values)->toBe(['RAW' => '1']);
});

test('production scoring evaluates opaque observations through the exact nonordinal Kit item map', function (): void {
    $contract = testKitProductionScoringContract();
    $contract['items'][0]['item_id'] = 9901;
    $contract['items'][0]['subtest_id'] = 7301;
    $contract['items'][0]['option_tokens'] = ['opaque::token-Ω', 'opaque::other-token'];
    $contract['metrics'][0]['subtest_id'] = 7301;
    $contract['bindings'][0] = [
        'page_no' => 3,
        'native_label' => 'response-field[opaque]',
        'global_position' => 19,
        'sheet_item_id' => 8841,
        'subtest_id' => 7301,
        'item_id' => 9901,
        'option_tokens' => ['opaque::token-Ω', 'opaque::other-token'],
        'correct_token' => 'opaque::token-Ω',
    ];
    $contract['composition_rows'] = [[
        'disposition' => 'physical',
        'global_position' => 19,
        'page_no' => 3,
        'native_label' => 'response-field[opaque]',
        'sheet_item_id' => 8841,
        'physical_item_uid' => 'physical-opaque-uid',
        'formal_item_id' => 9901,
        'formal_subtest_id' => 7301,
    ]];

    $profile = new TestInstrumentVersion;
    $profile->setRelation('metrics', collect([
        tap(new TestInstrumentMetric, static fn (TestInstrumentMetric $metric) => $metric->forceFill(['id' => 3, 'code' => 'RAW'])),
    ]));
    $kitVersion = new TestKitVersion;
    $kitVersion->setRelation('testInstrumentVersion', $profile);
    $action = new ConfirmOmrCaptureBatchAction(
        app(RecordAuditEvent::class),
        app(AcceptAssessmentRawResults::class),
    );
    $method = new ReflectionMethod($action, 'evaluateKitMetricValues');
    $item = [
        'position' => 19,
        'page_no' => 3,
        'native_label' => 'response-field[opaque]',
        'physical_item_uid' => 'physical-opaque-uid',
        'test_instrument_item_id' => 9901,
        'test_instrument_subtest_id' => 7301,
        'effective_state' => 'selected',
        'effective_option' => 'opaque::token-Ω',
    ];

    $projector = app(GradingOmrEffectiveAnswerProjector::class);
    $projectionMap = (new ReflectionMethod($projector, 'kitBindingsByPosition'))
        ->invoke($projector, ['item_map' => $contract]);
    expect($projectionMap)->toBe([19 => [
        'page_no' => 3,
        'native_label' => 'response-field[opaque]',
        'global_position' => 19,
        'item_id' => 9901,
        'subtest_id' => 7301,
        'physical_item_uid' => 'physical-opaque-uid',
    ]])
        ->and($method->invoke($action, $contract, [$item], $kitVersion))->toBe(['RAW' => '1'])
        ->and(fn () => $method->invoke($action, $contract, [[...$item, 'test_instrument_item_id' => 1]], $kitVersion))
        ->toThrow(RuntimeException::class, 'exact Test Kit item map')
        ->and(fn () => $method->invoke($action, $contract, [[...$item, 'physical_item_uid' => 'wrong-physical-uid']], $kitVersion))
        ->toThrow(RuntimeException::class, 'exact Test Kit item map');
});

test('paper-backed raw acceptance rejects an administration without a Test Kit pin', function (): void {
    $profile = new TestInstrumentVersion([
        'material_requirement' => TestInstrumentVersion::MATERIAL_REQUIREMENT_PAPER_BACKED,
        'administration_procedure_reference' => 'paper-v1',
        'content_sha256' => hash('sha256', 'profile'),
    ]);
    $administration = new AssessmentAdministration;
    $administration->forceFill([
        'binding_kind' => AssessmentAdministration::KIND_SCHEDULED_MATERIAL,
        'procedure_snapshot_json' => [
            'material_requirement' => TestInstrumentVersion::MATERIAL_REQUIREMENT_PAPER_BACKED,
            'administration_procedure_reference' => 'paper-v1',
            'content_sha256' => hash('sha256', 'profile'),
        ],
    ]);

    try {
        invokeMaterialApplicability($administration, $profile);
        test()->fail('Paper-backed scoring without a Test Kit pin must be rejected.');
    } catch (ValidationException $exception) {
        expect($exception->errors()['test_kit_version_id'][0])
            ->toBe('Paper-backed examination scoring requires an accepted Test Kit.');
    }
});

test('a genuine no-paper direct-manual binding is allowed without a Test Kit pin', function (): void {
    $profile = new TestInstrumentVersion([
        'material_requirement' => TestInstrumentVersion::MATERIAL_REQUIREMENT_NO_PAPER,
        'administration_procedure_reference' => 'manual-v1',
        'content_sha256' => hash('sha256', 'profile'),
    ]);
    $administration = new AssessmentAdministration;
    $administration->forceFill([
        'binding_kind' => AssessmentAdministration::KIND_DIRECT_MANUAL,
        'procedure_snapshot_json' => [
            'material_requirement' => TestInstrumentVersion::MATERIAL_REQUIREMENT_NO_PAPER,
            'administration_procedure_reference' => 'manual-v1',
            'content_sha256' => hash('sha256', 'profile'),
        ],
    ]);

    invokeMaterialApplicability($administration, $profile);
    expect(true)->toBeTrue();
});
test('a no-paper direct-manual procedure cannot bypass applicability for imported data', function (): void {
    $profile = new TestInstrumentVersion([
        'material_requirement' => TestInstrumentVersion::MATERIAL_REQUIREMENT_NO_PAPER,
        'administration_procedure_reference' => 'manual-v1',
        'content_sha256' => hash('sha256', 'profile'),
    ]);
    $administration = new AssessmentAdministration;
    $administration->forceFill([
        'binding_kind' => AssessmentAdministration::KIND_DIRECT_MANUAL,
        'procedure_snapshot_json' => [
            'material_requirement' => TestInstrumentVersion::MATERIAL_REQUIREMENT_NO_PAPER,
            'administration_procedure_reference' => 'manual-v1',
            'content_sha256' => hash('sha256', 'profile'),
        ],
    ]);

    expect(fn () => invokeMaterialApplicability($administration, $profile, 'import'))
        ->toThrow(RuntimeException::class, 'assessment_material_applicability_unresolved');
});

test('paper-backed raw acceptance rejects a partially frozen kit pin set', function (): void {
    $profile = new TestInstrumentVersion([
        'material_requirement' => TestInstrumentVersion::MATERIAL_REQUIREMENT_PAPER_BACKED,
        'administration_procedure_reference' => 'paper-v1',
        'content_sha256' => hash('sha256', 'profile'),
    ]);
    $administration = new AssessmentAdministration;
    $administration->forceFill([
        'binding_kind' => AssessmentAdministration::KIND_SCHEDULED_MATERIAL,
        'test_kit_version_id' => 31,
        'manifest_sha256' => hash('sha256', 'manifest'),
        'procedure_snapshot_json' => [
            'material_requirement' => TestInstrumentVersion::MATERIAL_REQUIREMENT_PAPER_BACKED,
            'administration_procedure_reference' => 'paper-v1',
            'content_sha256' => hash('sha256', 'profile'),
        ],
    ]);

    expect(fn () => invokeMaterialApplicability($administration, $profile))
        ->toThrow(RuntimeException::class, 'paper_backed_assessment_requires_frozen_test_kit_pins');
});
test('paper-backed raw acceptance rejects a snapshot from another sitting before scoring', function (): void {
    $profile = new TestInstrumentVersion([
        'id' => 22,
        'material_requirement' => TestInstrumentVersion::MATERIAL_REQUIREMENT_PAPER_BACKED,
        'administration_procedure_reference' => 'paper-v1',
        'content_sha256' => hash('sha256', 'profile'),
    ]);
    $assignment = new ExamAssignment;
    $assignment->forceFill(['id' => 5, 'admission_attempt_id' => 9, 'exam_session_id' => 40]);
    $snapshot = new ExamSessionMaterialSnapshot;
    $snapshot->forceFill([
        'id' => 12,
        'exam_session_id' => 41,
        'test_instrument_version_id' => 22,
        'test_kit_version_id' => 31,
        'manifest_sha256' => hash('sha256', 'manifest'),
        'acceptance_receipt_id' => 50,
    ]);
    $seat = new ExamSessionSeat;
    $seat->forceFill(['id' => 16, 'material_snapshot_id' => 12]);
    $administration = new AssessmentAdministration;
    $administration->forceFill([
        'id' => 14,
        'exam_assignment_id' => 5,
        'admission_attempt_id' => 9,
        'binding_kind' => AssessmentAdministration::KIND_SCHEDULED_MATERIAL,
        'test_instrument_version_id' => 22,
        'test_kit_version_id' => 31,
        'manifest_sha256' => hash('sha256', 'manifest'),
        'acceptance_receipt_id' => 50,
        'material_snapshot_id' => 12,
        'exam_session_seat_id' => 16,
        'procedure_snapshot_json' => [
            'material_requirement' => TestInstrumentVersion::MATERIAL_REQUIREMENT_PAPER_BACKED,
            'administration_procedure_reference' => 'paper-v1',
            'content_sha256' => hash('sha256', 'profile'),
        ],
    ]);
    $administration->setRelation('examAssignment', $assignment);
    $administration->setRelation('materialSnapshot', $snapshot);
    $administration->setRelation('examSessionSeat', $seat);

    expect(fn () => invokeMaterialApplicability($administration, $profile))
        ->toThrow(RuntimeException::class, 'paper_backed_assessment_test_kit_snapshot_pins_mismatch');
});

test('paper-backed raw acceptance rejects a snapshot with mismatched frozen pins', function (): void {
    $profile = new TestInstrumentVersion([
        'id' => 22,
        'material_requirement' => TestInstrumentVersion::MATERIAL_REQUIREMENT_PAPER_BACKED,
        'administration_procedure_reference' => 'paper-v1',
        'content_sha256' => hash('sha256', 'profile'),
    ]);
    $assignment = new ExamAssignment;
    $assignment->forceFill(['id' => 5, 'admission_attempt_id' => 9, 'exam_session_id' => 40]);
    $snapshot = new ExamSessionMaterialSnapshot;
    $snapshot->forceFill([
        'id' => 12,
        'exam_session_id' => 40,
        'test_instrument_version_id' => 22,
        'test_kit_version_id' => 31,
        'manifest_sha256' => hash('sha256', 'other-manifest'),
        'acceptance_receipt_id' => 50,
    ]);
    $seat = new ExamSessionSeat;
    $seat->forceFill(['id' => 16, 'material_snapshot_id' => 12]);
    $administration = new AssessmentAdministration;
    $administration->forceFill([
        'exam_assignment_id' => 5,
        'admission_attempt_id' => 9,
        'binding_kind' => AssessmentAdministration::KIND_SCHEDULED_MATERIAL,
        'test_instrument_version_id' => 22,
        'test_kit_version_id' => 31,
        'manifest_sha256' => hash('sha256', 'manifest'),
        'acceptance_receipt_id' => 50,
        'material_snapshot_id' => 12,
        'exam_session_seat_id' => 16,
        'procedure_snapshot_json' => [
            'material_requirement' => TestInstrumentVersion::MATERIAL_REQUIREMENT_PAPER_BACKED,
            'administration_procedure_reference' => 'paper-v1',
            'content_sha256' => hash('sha256', 'profile'),
        ],
    ]);
    $administration->setRelation('examAssignment', $assignment);
    $administration->setRelation('materialSnapshot', $snapshot);
    $administration->setRelation('examSessionSeat', $seat);

    expect(fn () => invokeMaterialApplicability($administration, $profile))
        ->toThrow(RuntimeException::class, 'paper_backed_assessment_test_kit_snapshot_pins_mismatch');
});

test('an applicant-only administration is never selected without an exact session assignment', function (): void {
    $action = new ConfirmOmrCaptureBatchAction(
        app(RecordAuditEvent::class),
        app(AcceptAssessmentRawResults::class),
    );
    $method = new ReflectionMethod($action, 'activeAdministrationForAssignment');

    expect($method->invoke($action, null))->toBeNull();
});
