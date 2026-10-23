<?php

use App\Assessment\Actions\ActivateTestKitVersion;
use App\Assessment\Actions\RetireTestKitVersion;
use App\Assessment\Actions\RevokeTestKitVersion;
use App\Assessment\Support\AssessmentRawEvaluator;
use App\Assessment\Support\TestKitAcceptancePlan;
use App\Assessment\Support\TestKitEligibility;
use App\Models\Assessment\OmrAnswerSheetTemplate;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\TestKit;
use App\Models\Assessment\TestKitAcceptancePlanRecord;
use App\Models\Assessment\TestKitAcceptanceReceipt;
use App\Models\Assessment\TestKitVersion;
use App\Models\Foundation\Institution;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    seedE2Foundation();
    $this->actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $this->institution = Institution::current();
});

/** @return array{kit:TestKit,version:TestKitVersion,materials:array<string,int|string>} */
function lifecycleKitFixture(User $actor, Institution $institution, string $status = TestKitVersion::STATUS_CANDIDATE): array
{
    $now = now();
    $suffix = Str::upper(Str::random(8));
    $instrumentId = DB::table('test_instruments')->insertGetId([
        'institution_id' => $institution->id,
        'code' => 'LIFECYCLE-'.$suffix,
        'is_synthetic' => false,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $profileId = DB::table('test_instrument_versions')->insertGetId([
        'test_instrument_id' => $instrumentId,
        'version_number' => 1,
        'status' => 'approved',
        'display_name' => 'Lifecycle profile '.$suffix,
        'publisher_name' => 'Test fixture',
        'form_code' => 'LIF-'.$suffix,
        'edition' => 'Fixture',
        'language' => 'en',
        'source_reference' => 'fixture://source',
        'permitted_use_reference' => 'Fixture only',
        'source_reviewed_by_user_id' => $actor->id,
        'source_reviewed_at' => $now,
        'approved_by_user_id' => $actor->id,
        'approved_at' => $now,
        'lock_version' => 1,
        'content_sha256' => hash('sha256', 'profile-'.$suffix),
        'created_by_user_id' => $actor->id,
        'material_requirement' => 'paper_backed',
        'administration_procedure_reference' => 'Lifecycle fixture procedure.',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    DB::table('test_instruments')->where('id', $instrumentId)->update(['current_approved_version_id' => $profileId]);
    $sheetFamily = OmrAnswerSheetTemplate::factory()->create([
        'institution_id' => $institution->id,
        'is_synthetic' => false,
    ]);
    $sheet = OmrAnswerSheetTemplateVersion::factory()->create([
        'template_id' => $sheetFamily->id,
        'test_instrument_version_id' => $profileId,
    ]);
    $bookletId = DB::table('omr_question_booklets')->insertGetId([
        'institution_id' => $institution->id,
        'code' => 'LIF-BOOKLET-'.$suffix,
        'name' => 'Lifecycle fixture booklet',
        'is_synthetic' => false,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $bookletVersionId = DB::table('omr_question_booklet_versions')->insertGetId([
        'booklet_id' => $bookletId,
        'template_version_id' => $sheet->id,
        'test_instrument_version_id' => $profileId,
        'version' => 1,
        'status' => 'draft',
        'authoring_mode' => 'answer_keys_only',
        'allow_multiple_sets' => false,
        'lock_version' => 1,
        'answer_key_hash' => hash('sha256', 'booklet-'.$suffix),
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $setId = DB::table('omr_booklet_sets')->insertGetId([
        'booklet_version_id' => $bookletVersionId,
        'set_code' => 'A',
        'status' => 'finalized',
        'generation_version' => 'fixture-v1',
        'order_hash' => hash('sha256', 'order-'.$suffix),
        'answer_key_hash' => hash('sha256', 'key-'.$suffix),
        'finalized_at' => $now,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    DB::table('omr_question_booklet_versions')->where('id', $bookletVersionId)->update([
        'status' => 'finalized',
        'content_hash' => hash('sha256', 'content-'.$suffix),
        'finalized_at' => $now,
        'finalized_by_user_id' => $actor->id,
    ]);
    $kit = TestKit::query()->create([
        'public_id' => (string) Str::uuid(),
        'institution_id' => $institution->id,
        'code' => 'LIF-KIT-'.$suffix,
        'name' => 'Lifecycle fixture kit',
        'is_synthetic' => false,
        'created_by_user_id' => $actor->id,
    ]);
    $manifest = ['fixture' => 'lifecycle', 'version' => $suffix];
    $manifestJson = json_encode($manifest, JSON_THROW_ON_ERROR);
    $version = TestKitVersion::query()->create([
        'test_kit_id' => $kit->id,
        'institution_id' => $institution->id,
        'is_synthetic' => false,
        'template_id' => $sheetFamily->id,
        'booklet_id' => $bookletId,
        'test_instrument_id' => $instrumentId,
        'version_number' => 1,
        'template_version_id' => $sheet->id,
        'booklet_version_id' => $bookletVersionId,
        'booklet_set_id' => $setId,
        'test_instrument_version_id' => $profileId,
        'booklet_set_code' => 'A',
        'booklet_set_order_hash' => hash('sha256', 'order-'.$suffix),
        'booklet_set_answer_key_hash' => hash('sha256', 'key-'.$suffix),
        'manifest_schema_version' => 1,
        'canonicalizer_version' => 'fixture-v1',
        'manifest_json' => $manifest,
        'manifest_sha256' => hash('sha256', $manifestJson),
        'status' => $status,
        'revision' => 1,
        'created_by_user_id' => $actor->id,
        'activated_at' => $status === TestKitVersion::STATUS_ACTIVE ? now() : null,
        'activated_by_user_id' => $status === TestKitVersion::STATUS_ACTIVE ? $actor->id : null,
    ]);

    return [
        'kit' => $kit,
        'version' => $version,
        'materials' => ['sheet_id' => $sheet->id, 'booklet_id' => $bookletVersionId, 'set_id' => $setId, 'profile_id' => $profileId, 'profile_content_sha256' => hash('sha256', 'profile-'.$suffix)],
    ];
}

/** Historical-only row for lifecycle wiring; never evidence of current acceptance. */
function lifecycleHistoricalReceipt(TestKitVersion $version, User $actor, array $materials): TestKitAcceptanceReceipt
{
    $now = now();
    $kit = TestKit::query()->findOrFail($version->test_kit_id);
    $institutionId = (int) $kit->institution_id;
    $cycleId = DB::table('academic_cycles')->where('institution_id', $institutionId)->value('id');
    $planService = app(TestKitAcceptancePlan::class);
    $protocol = $planService->protocolIdentity();
    $planRecord = TestKitAcceptancePlanRecord::query()->create([
        'public_id' => (string) Str::uuid(),
        'test_kit_version_id' => $version->id,
        'manifest_sha256' => $version->manifest_sha256,
        'source_key' => 'lifecycle-plan-'.$version->id,
        'authored_source_sha256' => hash('sha256', 'lifecycle-source-'.$version->id),
        'protocol_schema_version' => $protocol['schema_version'],
        'protocol_sha256' => $protocol['sha256'],
        'plan_schema_version' => 'test-kit-acceptance-plan-v1',
        'plan_sha256' => hash('sha256', 'lifecycle-plan-hash-'.$version->id),
        'plan_payload' => ['cases' => []],
        'author_reference' => 'Lifecycle author',
        'provenance_kind' => 'synthetic_software_fixture',
        'truth_source_references_json' => [['reference' => 'tests/fixtures', 'sha256' => hash('sha256', 'truth')]],
        'review_evidence_reference' => 'tests/fixtures/review.json',
        'review_evidence_sha256' => hash('sha256', 'review'),
        'created_by_user_id' => $actor->id,
    ]);

    $runId = DB::table('omr_diagnostic_runs')->insertGetId([
        'public_id' => (string) Str::uuid(),
        'execution_kind' => 'diagnostic',
        'institution_id' => $institutionId,
        'academic_cycle_id' => $cycleId,
        'test_kit_id' => $version->test_kit_id,
        'test_kit_version_id' => $version->id,
        'acceptance_plan_id' => $planRecord->id,
        'booklet_set_id' => $version->booklet_set_id,
        'manifest_sha256' => $version->manifest_sha256,
        'test_instrument_version_id' => $materials['profile_id'],
        'profile_content_sha256' => $materials['profile_content_sha256'],
        'expected_worker_identity_json' => json_encode(['engine_name' => 'worker-test', 'engine_commit' => str_repeat('a', 40)], JSON_THROW_ON_ERROR),
        'acceptance_plan_json' => Crypt::encryptString(json_encode(['cases' => []], JSON_THROW_ON_ERROR)),
        'template_version_id' => $materials['sheet_id'],
        'booklet_version_id' => $materials['booklet_id'],
        'declared_set_code' => 'A',
        'status' => 'completed',
        'phase' => 'complete',
        'revision' => 1,
        'sealed_at' => $now,
        'sealed_evidence_revision' => 1,
        'sealed_evidence_vector_sha256' => hash('sha256', 'fixture-vector'),
        'acceptance_protocol_schema_version' => $protocol['schema_version'],
        'acceptance_protocol_sha256' => $protocol['sha256'],
        'acceptance_plan_schema_version' => $planRecord->plan_schema_version,
        'acceptance_plan_sha256' => $planRecord->plan_sha256,
        'scoring_kernel_identity' => AssessmentRawEvaluator::KERNEL_VERSION,
        'created_by_user_id' => $actor->id,
        'completed_at' => $now,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    return TestKitAcceptanceReceipt::query()->create([
        'public_id' => (string) Str::uuid(),
        'test_kit_version_id' => $version->id,
        'acceptance_plan_id' => $planRecord->id,
        'diagnostic_run_id' => $runId,
        'template_version_id' => $materials['sheet_id'],
        'booklet_version_id' => $materials['booklet_id'],
        'booklet_set_code' => 'A',
        'run_revision' => 1,
        'evidence_revision_vector_json' => ['run_revision' => 1, 'captures' => []],
        'evidence_revision_vector_sha256' => hash('sha256', 'fixture-vector'),
        'acceptance_protocol_schema_version' => $protocol['schema_version'],
        'acceptance_protocol_sha256' => $protocol['sha256'],
        'acceptance_plan_schema_version' => $planRecord->plan_schema_version,
        'acceptance_plan_sha256' => $planRecord->plan_sha256,
        'scoring_kernel_identity' => AssessmentRawEvaluator::KERNEL_VERSION,
        'worker_commit' => str_repeat('a', 40),
        'manifest_sha256' => $version->manifest_sha256,
        'result' => TestKitAcceptanceReceipt::RESULT_FAILED,
        'check_report_json' => ['checks' => [['code' => 'historical_fixture_only', 'passed' => false]]],
        'confidential_evidence_references' => ['case' => 'private://fixture'],
        'created_by_user_id' => $actor->id,
        'created_at' => $now,
    ]);
}

test('activation delegates acceptance to eligibility and retires an active predecessor atomically', function (): void {
    $fixture = lifecycleKitFixture($this->actor, $this->institution);
    $receipt = lifecycleHistoricalReceipt($fixture['version'], $this->actor, $fixture['materials']);
    $fixture['version']->forceFill(['selected_acceptance_receipt_id' => $receipt->id])->save();
    $predecessor = TestKitVersion::query()->create([
        'test_kit_id' => $fixture['kit']->id,
        'institution_id' => $fixture['version']->institution_id,
        'is_synthetic' => false,
        'template_id' => $fixture['version']->template_id,
        'booklet_id' => $fixture['version']->booklet_id,
        'test_instrument_id' => $fixture['version']->test_instrument_id,
        'version_number' => 2,
        'template_version_id' => $fixture['version']->template_version_id,
        'booklet_version_id' => $fixture['version']->booklet_version_id,
        'booklet_set_id' => $fixture['version']->booklet_set_id,
        'test_instrument_version_id' => $fixture['version']->test_instrument_version_id,
        'booklet_set_code' => $fixture['version']->booklet_set_code,
        'booklet_set_order_hash' => $fixture['version']->booklet_set_order_hash,
        'booklet_set_answer_key_hash' => $fixture['version']->booklet_set_answer_key_hash,
        'manifest_schema_version' => $fixture['version']->manifest_schema_version,
        'canonicalizer_version' => $fixture['version']->canonicalizer_version,
        'manifest_json' => $fixture['version']->manifest_json,
        'manifest_sha256' => $fixture['version']->manifest_sha256,
        'status' => TestKitVersion::STATUS_ACTIVE,
        'revision' => 1,
        'created_by_user_id' => $this->actor->id,
        'activated_at' => now(),
        'activated_by_user_id' => $this->actor->id,
    ]);
    $eligibility = Mockery::mock(TestKitEligibility::class);
    $eligibility->shouldReceive('evaluate')->once()->withArgs(
        fn (TestKitVersion $version, mixed $cycle, string $purpose): bool => $version->selectedAcceptanceReceipt->is($receipt)
            && $cycle === null
            && $purpose === TestKitEligibility::PURPOSE_ACTIVATION,
    )->andReturn(['eligible' => true, 'blockers' => [], 'pins' => []]);
    app()->instance(TestKitEligibility::class, $eligibility);

    $activated = app(ActivateTestKitVersion::class)->handle(
        $this->actor,
        $fixture['kit']->id,
        $fixture['version']->id,
        ['expected_revision' => 1, 'idempotency_key' => 'activate-selected-receipt'],
    );

    expect($activated->status)->toBe(TestKitVersion::STATUS_ACTIVE)
        ->and($activated->activated_by_user_id)->toBe($this->actor->id)
        ->and($activated->activated_at)->not->toBeNull()
        ->and($predecessor->fresh()->status)->toBe(TestKitVersion::STATUS_RETIRED)
        ->and($predecessor->fresh()->retired_by_user_id)->toBe($this->actor->id)
        ->and(TestKitVersion::query()->where('test_kit_id', $fixture['kit']->id)->where('status', TestKitVersion::STATUS_ACTIVE)->count())->toBe(1);
});

test('activation reports physical or source holds independently from historical receipt rows', function (): void {
    $fixture = lifecycleKitFixture($this->actor, $this->institution);
    $action = app(ActivateTestKitVersion::class);
    expect(fn () => $action->handle($this->actor, $fixture['kit']->id, $fixture['version']->id, [
        'expected_revision' => 1,
        'idempotency_key' => 'activation-no-receipt',
    ]))->toThrow(ValidationException::class);

    $receipt = lifecycleHistoricalReceipt($fixture['version'], $this->actor, $fixture['materials']);
    $fixture['version']->forceFill(['selected_acceptance_receipt_id' => $receipt->id])->save();
    try {
        $action->handle($this->actor, $fixture['kit']->id, $fixture['version']->id, [
            'expected_revision' => 1,
            'idempotency_key' => 'activation-held',
        ]);
        test()->fail('Activation must remain blocked by the source and physical qualification holds.');
    } catch (ValidationException $exception) {
        $messages = $exception->errors()['eligibility'];
        expect($messages)->toContain('Lawful institutional source, rights, and independent expected-truth evidence are not currently verifiable.')
            ->and(collect($messages)->contains(fn (string $message): bool => stripos($message, 'physical') !== false))->toBeTrue();
    }
});

test('retiring an active kit preserves its pinned version and exact replay is idempotent', function (): void {
    $fixture = lifecycleKitFixture($this->actor, $this->institution, TestKitVersion::STATUS_ACTIVE);
    $action = app(RetireTestKitVersion::class);
    $first = $action->handle($this->actor, $fixture['kit']->id, $fixture['version']->id, 1, 'retire-active');
    $replay = $action->handle($this->actor, $fixture['kit']->id, $fixture['version']->id, 1, 'retire-active');

    expect($first->status)->toBe(TestKitVersion::STATUS_RETIRED)
        ->and($first->retired_by_user_id)->toBe($this->actor->id)
        ->and($first->retired_at)->not->toBeNull()
        ->and($replay->id)->toBe($first->id)
        ->and($replay->revision)->toBe(2);
});

test('revocation requires a reason, records attribution, and replays the original result', function (): void {
    $fixture = lifecycleKitFixture($this->actor, $this->institution, TestKitVersion::STATUS_ACTIVE);
    $action = app(RevokeTestKitVersion::class);
    expect(fn () => $action->handle($this->actor, $fixture['kit']->id, $fixture['version']->id, 1, '  ', 'revoke-empty'))
        ->toThrow(ValidationException::class);

    $first = $action->handle($this->actor, $fixture['kit']->id, $fixture['version']->id, 1, 'Source rights withdrawn', 'revoke-valid');
    $replay = $action->handle($this->actor, $fixture['kit']->id, $fixture['version']->id, 1, 'Source rights withdrawn', 'revoke-valid');

    expect($first->revoked_at)->not->toBeNull()
        ->and($first->revoked_by_user_id)->toBe($this->actor->id)
        ->and($first->revocation_reason)->toBe('Source rights withdrawn')
        ->and($first->status)->toBe(TestKitVersion::STATUS_ACTIVE)
        ->and($replay->id)->toBe($first->id)
        ->and($replay->revision)->toBe(2);
});
