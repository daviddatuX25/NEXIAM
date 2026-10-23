<?php

use App\Models\Assessment\TestKitAcceptanceReceipt;
use App\Models\Assessment\TestKitVersion;
use App\Models\Examination\AssessmentAdministration;
use App\Models\Examination\ExamSessionMaterialSnapshot;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

it('stores nullable test kit production pins on snapshots and administrations', function (): void {
    foreach (['exam_session_material_snapshots', 'assessment_administrations'] as $table) {
        expect(Schema::hasColumns($table, [
            'test_kit_version_id',
            'manifest_sha256',
            'acceptance_receipt_id',
        ]))->toBeTrue();
    }

    expect(Schema::hasColumn('assessment_administrations', 'procedure_snapshot_json'))->toBeTrue();
});

it('exposes production pin relations and casts', function (): void {
    $snapshot = new ExamSessionMaterialSnapshot;
    $administration = new AssessmentAdministration;

    expect($snapshot->testKitVersion())->toBeInstanceOf(BelongsTo::class)
        ->and($snapshot->testKitVersion()->getRelated())->toBeInstanceOf(TestKitVersion::class)
        ->and($snapshot->acceptanceReceipt())->toBeInstanceOf(BelongsTo::class)
        ->and($snapshot->acceptanceReceipt()->getRelated())->toBeInstanceOf(TestKitAcceptanceReceipt::class)
        ->and($snapshot->getCasts())->toMatchArray([
            'test_kit_version_id' => 'integer',
            'manifest_sha256' => 'string',
            'acceptance_receipt_id' => 'integer',
        ])
        ->and($administration->testKitVersion())->toBeInstanceOf(BelongsTo::class)
        ->and($administration->testKitVersion()->getRelated())->toBeInstanceOf(TestKitVersion::class)
        ->and($administration->acceptanceReceipt())->toBeInstanceOf(BelongsTo::class)
        ->and($administration->acceptanceReceipt()->getRelated())->toBeInstanceOf(TestKitAcceptanceReceipt::class)
        ->and($administration->getCasts())->toMatchArray([
            'test_kit_version_id' => 'integer',
            'manifest_sha256' => 'string',
            'acceptance_receipt_id' => 'integer',
            'procedure_snapshot_json' => 'array',
        ]);
});

it('keeps session material snapshots immutable including production pins', function (): void {
    $snapshot = new ExamSessionMaterialSnapshot;
    $snapshot->exists = true;
    $snapshot->setRawAttributes([
        'id' => 1,
        'test_kit_version_id' => 10,
        'manifest_sha256' => str_repeat('a', 64),
        'acceptance_receipt_id' => 20,
    ]);
    $snapshot->syncOriginal();

    expect(fn () => $snapshot->update(['manifest_sha256' => str_repeat('b', 64)]))
        ->toThrow(RuntimeException::class, 'Session material snapshots are immutable.')
        ->and(fn () => $snapshot->delete())
        ->toThrow(RuntimeException::class, 'Session material snapshots are immutable.');
});

it('classifies paper-backed administrations and requires their test kit', function (): void {
    $directManual = new AssessmentAdministration([
        'binding_kind' => AssessmentAdministration::KIND_DIRECT_MANUAL,
        'procedure_snapshot_json' => ['material_requirement' => 'no_paper'],
    ]);
    $scheduled = new AssessmentAdministration(['binding_kind' => AssessmentAdministration::KIND_SCHEDULED_MATERIAL]);
    $procedureBacked = new AssessmentAdministration([
        'binding_kind' => AssessmentAdministration::KIND_DIRECT_MANUAL,
        'procedure_snapshot_json' => ['material_requirement' => 'paper_backed'],
    ]);

    expect($directManual->isPaperBacked())->toBeFalse()
        ->and($directManual->requiresTestKit())->toBeFalse()
        ->and($scheduled->isPaperBacked())->toBeTrue()
        ->and($scheduled->requiresTestKit())->toBeTrue()
        ->and($procedureBacked->isPaperBacked())->toBeTrue()
        ->and($procedureBacked->requiresTestKit())->toBeTrue();
});
