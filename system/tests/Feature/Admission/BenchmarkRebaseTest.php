<?php

use App\AdmissionEngine\Actions\ActivateAdmissionBenchmarkSet;
use App\AdmissionEngine\Actions\BenchmarkExecutionContext;
use App\AdmissionEngine\Actions\PublishProgramBenchmarkSet;
use App\AdmissionEngine\Actions\RebaseProgramBenchmarkDraft;
use App\AdmissionEngine\Actions\SaveProgramBenchmarkDraft;
use App\AdmissionEngine\DTOs\AdmissionInputPayload;
use App\AdmissionEngine\ProgramBenchmarkPreviewQuery;
use App\Models\Admission\AdmissionBenchmarkActivation;
use App\Models\Admission\AdmissionBenchmarkDraft;
use App\Models\Admission\AdmissionBenchmarkRevision;
use App\Models\Admission\AdmissionBenchmarkSet;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Program;
use App\Models\User;
use Database\Seeders\AdmissionBenchmarkSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\Response;

beforeEach(function (): void {
    config([
        'admission_engine.tabfm.institutional_use_approved' => true,
        'admission_engine.tabfm.hosted_use_approved' => true,
        'admission_engine.tabfm.package_version' => '9.1.0',
        'admission_engine.tabfm.checkpoint_id' => 'Prior-Labs/tabpfn_3/tabpfn-v3-classifier-v3_20260417_binary.ckpt',
        'admission_engine.tabfm.checkpoint_sha256' => '1f9949fd52e20b6ed8ece20b3b37242d2abbbf337d14b461a4244f9318031a88',
    ]);
});
function benchmarkRebaseFixture(): array
{
    seedE2Foundation();
    (new AdmissionBenchmarkSeeder)->run();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $base = completeSyntheticBenchmarkTestBase();
    $program = Program::query()->where('institution_id', $base->institution_id)->where('code', 'BSIT')->firstOrFail();
    $content = ['tiers' => []];
    foreach ($base->exemplars()->where('program_id', $program->id)->with('values.feature')->orderBy('tier_level')->get() as $exemplar) {
        $values = [];
        foreach ($exemplar->values as $cell) {
            $values[$cell->feature->feature_key] = $cell->is_missing ? null : ($cell->feature->logical_type === 'categorical' ? $cell->categorical_value : (float) $cell->numeric_value);
        }
        ksort($values);
        foreach ($base->features as $feature) {
            if ($feature->is_required && ($values[$feature->feature_key] ?? null) === null) {
                $values[$feature->feature_key] = $feature->logical_type === 'categorical' ? 'STEM' : 75;
            }
        }
        $content['tiers'][(string) $exemplar->tier_level] = ['values' => $values];
    }
    $content['tiers']['3']['values']['gwa'] = 81.5;
    $content['authoring'] = [
        'version' => 1,
        'ranges' => [
            'honors' => ['gwa' => [80, 92]],
            'regular' => ['gwa' => [80, 92]],
            'cutoff' => ['gwa' => [81.5, 92]],
        ],
        'preferred_strands' => null,
        'interests' => null,
    ];
    $revision = app(SaveProgramBenchmarkDraft::class)->handle($actor, $program, $cycle, $content, 0, 'Reviewed synthetic programme cells', 'rebase-fixture-save');
    $sibling = Program::query()->where('institution_id', $base->institution_id)->where('code', 'BSEd')->firstOrFail();
    app(SaveProgramBenchmarkDraft::class)->handle($actor, $sibling, $cycle, $content, 0, 'Pending sibling programme review', 'rebase-fixture-sibling-save');
    $published = app(PublishProgramBenchmarkSet::class)->handle($actor, $cycle, [$program->id => $revision->id], (string) $base->content_sha256, 'Publish exact reviewed programme', 'rebase-fixture-publish');
    $current = AdmissionBenchmarkActivation::currentForCycle((int) $cycle->id);
    app(ActivateAdmissionBenchmarkSet::class)->handle($cycle, $published, BenchmarkExecutionContext::human($actor), $current->id, (string) $published->content_sha256, 'rebase-fixture-activate');

    return [$actor, $program, $cycle, $base, $published, $revision];
}

/** Synthetic HTTP response for consumer-contract coverage, not real-model evidence. */
function benchmarkRebaseNeuralSuccessFixture(array $request): array
{
    $programs = collect($request['context_exemplars'])
        ->unique('program_code')
        ->sortBy('program_code')
        ->values();
    $featureDefinitions = $request['feature_definitions'];
    $applicant = $request['query_applicant'];
    $featureCoverage = [];
    $missingFeatures = [];
    $missingOptionalFeatures = [];
    foreach ($featureDefinitions as $definition) {
        $featureKey = $definition['feature_key'];
        $available = array_key_exists($featureKey, $applicant) && $applicant[$featureKey] !== null;
        $required = (bool) $definition['is_required'];
        $featureCoverage[$featureKey] = ['available' => $available, 'required' => $required];
        if (! $available) {
            $missingFeatures[] = $featureKey;
            if (! $required) {
                $missingOptionalFeatures[] = $featureKey;
            }
        }
    }
    $recommendations = [];
    foreach ($programs as $program) {
        $recommendations[] = [
            'rank' => count($recommendations) + 1,
            'program_code' => $program['program_code'],
            'program_name' => $program['program_name'],
            'compatibility_index' => 91,
            'band' => 'high_match',
            'drivers' => ['Synthetic neural success fixture'],
        ];
    }
    $packageVersion = '9.1.0';

    return [
        'status' => 'evaluated',
        'engine_version' => $packageVersion,
        'inference_backend' => 'tabpfn_pytorch',
        'fallback_reason' => null,
        'provenance' => [
            'package' => 'tabpfn',
            'package_version' => $packageVersion,
            'checkpoint_identity' => 'Prior-Labs/tabpfn_3/tabpfn-v3-classifier-v3_20260417_binary.ckpt',
            'checkpoint_sha256' => '1f9949fd52e20b6ed8ece20b3b37242d2abbbf337d14b461a4244f9318031a88',
        ],
        'execution_time_ms' => 1.0,
        'modality_coverage' => [
            'available_features' => count($featureDefinitions) - count($missingFeatures),
            'total_features' => count($featureDefinitions),
            'missing_optional_features' => $missingOptionalFeatures,
            'missing_features' => $missingFeatures,
            'feature_coverage' => $featureCoverage,
        ],
        'feature_provenance' => array_map(static fn (array $definition): array => array_diff_key($definition, ['scale' => true]), $featureDefinitions),
        'recommendations' => $recommendations,
    ];
}

test('real rebase endpoint unblocks editing after activation and preserves historical preview context', function (): void {
    [$actor, $program, $cycle, $base, $published, $first] = benchmarkRebaseFixture();
    $draft = AdmissionBenchmarkDraft::query()->findOrFail($first->draft_id);
    expect($draft->base_benchmark_set_id)->toBe($base->id);
    $changed = $first->content;
    $changed['tiers']['3']['values']['gwa'] = 82;
    $changed['authoring']['ranges']['cutoff']['gwa'][0] = 82;
    actingAsAdmissionSupportStaff($this, $actor)->postJson(route('setup.admission-support.benchmarks.draft.save', [$program, $cycle]), [
        'content' => $changed, 'expected_version' => 1, 'reason' => 'Post activation edit', 'idempotency_key' => 'rebase-save-conflict',
    ])->assertConflict()->assertJsonPath('status', 'conflict');

    $rebasePayload = [
        'expected_version' => 1, 'benchmark_set_id' => $published->id, 'expected_content_sha256' => $published->content_sha256,
        'reason' => 'Review unchanged saved cells against the newly activated set', 'idempotency_key' => 'rebase-http-reviewed',
    ];
    $response = $this->postJson(route('setup.admission-support.benchmarks.draft.rebase', [$program, $cycle]), $rebasePayload)
        ->assertOk()->assertJsonPath('status', 'success')->assertJsonPath('revision.version', 2)
        ->assertJsonPath('revision.base_benchmark_set_id', $published->id)->assertJsonPath('revision.feature_schema_digest', $published->schema_sha256);
    $rebased = AdmissionBenchmarkRevision::query()->findOrFail($response->json('revision.id'));
    expect($rebased->content)->toBe($first->content)
        ->and($rebased->predecessor_revision_id)->toBe($first->id)
        ->and($rebased->reason)->toBe($rebasePayload['reason'])
        ->and($draft->fresh()->current_version)->toBe(2)
        ->and($draft->fresh()->base_benchmark_set_id)->toBe($published->id)
        ->and($first->fresh()->base_benchmark_set_id)->toBe($base->id)
        ->and($first->fresh()->feature_schema_digest)->toBe($base->schema_sha256);
    $this->postJson(route('setup.admission-support.benchmarks.draft.save', [$program, $cycle]), [
        'content' => $changed, 'expected_version' => 2, 'reason' => 'Edit after explicit review', 'idempotency_key' => 'rebase-save-success',
    ])->assertOk()->assertJsonPath('revision.version', 3)->assertJsonPath('revision.content.tiers.3.values.gwa', 82);
    $this->postJson(route('setup.admission-support.benchmarks.draft.rebase', [$program, $cycle]), $rebasePayload)
        ->assertOk()->assertJsonPath('revision.id', $rebased->id)->assertJsonPath('revision.version', 2);
    expect($draft->fresh()->current_version)->toBe(3)
        ->and(AdmissionBenchmarkActivation::currentForCycle((int) $cycle->id)->benchmark_set_id)->toBe($published->id)
        ->and(AdmissionBenchmarkDraft::query()->where('academic_cycle_id', $cycle->id)->where('program_id', '!=', $program->id)->value('base_benchmark_set_id'))->toBe($base->id)
        ->and(AdmissionBenchmarkSet::query()->count())->toBe(2);
    $submittedProgramCodes = [];
    config(['admission_engine.tabfm.driver' => 'http', 'admission_engine.tabfm.endpoint' => 'http://tabfm-inference:8101']);
    Http::preventStrayRequests();
    Http::fake([
        'http://tabfm-inference:8101/inference/tabpfn' => function (Request $request) use (&$submittedProgramCodes): Response {
            $payload = $request->data();
            $submittedProgramCodes[] = collect($payload['context_exemplars'])
                ->pluck('program_code')
                ->unique()
                ->sort()
                ->values()
                ->all();

            return Http::response(benchmarkRebaseNeuralSuccessFixture($payload), 200);
        },
    ]);
    $input = new AdmissionInputPayload(
        attemptId: 0,
        institutionId: (int) $program->institution_id,
        academicCycleId: (int) $cycle->id,
        gwa: 90.0,
        aptitudePercentiles: ['LOGIC' => 82.0, 'PROFILE_VERBAL' => 80.0, 'PROFILE_QUANT' => 81.0, 'SPATIAL' => 77.0, 'MEMORY' => 76.0, 'LANGUAGE' => 75.0],
    );
    $historical = app(ProgramBenchmarkPreviewQuery::class)->handle($actor, $program, $cycle, $input, 'draft', $first->id, 'historical-rebase-preview');
    $reviewed = app(ProgramBenchmarkPreviewQuery::class)->handle($actor, $program, $cycle, $input, 'draft', $rebased->id, 'reviewed-rebase-preview');
    expect($historical->status)->toBe('evaluated')
        ->and($historical->engineVersion)->toBe('9.1.0')
        ->and($reviewed->status)->toBe('evaluated')
        ->and($reviewed->engineVersion)->toBe('9.1.0')
        ->and($reviewed->inferenceBackend)->toBe('tabpfn_pytorch')
        ->and(collect($historical->recommendations)->pluck('programCode')->all())->toBe([$program->code])
        ->and(collect($reviewed->recommendations)->pluck('programCode')->all())->toBe([$program->code])
        ->and($submittedProgramCodes)->toBe([[$program->code], [$program->code]])
        ->and($historical->evaluationContext?->benchmarkSetId)->toBe($base->id)
        ->and($historical->evaluationContext?->benchmarkRevisionId)->toBe($first->id)
        ->and($reviewed->evaluationContext?->benchmarkSetId)->toBe($published->id)
        ->and($reviewed->evaluationContext?->schemaDigest)->toBe($published->schema_sha256);
    Http::assertSentCount(2);
});

test('rebase rejects stale review metadata versions payload replays and empty reasons without changing the draft', function (): void {
    [$actor, $program, $cycle, $base, $published, $first] = benchmarkRebaseFixture();
    $url = route('setup.admission-support.benchmarks.draft.rebase', [$program, $cycle]);
    $payload = ['expected_version' => 1, 'benchmark_set_id' => $published->id, 'expected_content_sha256' => $published->content_sha256, 'reason' => 'Exact active set review', 'idempotency_key' => 'rebase-metadata-review'];
    actingAsAdmissionSupportStaff($this, $actor)->postJson($url, [...$payload, 'benchmark_set_id' => $base->id, 'expected_content_sha256' => $base->content_sha256])->assertConflict();
    $this->postJson($url, [...$payload, 'expected_content_sha256' => str_repeat('0', 64)])->assertConflict();
    $this->postJson($url, [...$payload, 'expected_version' => 0])->assertConflict();
    $this->postJson($url, [...$payload, 'reason' => ' '])->assertUnprocessable();
    expect(AdmissionBenchmarkDraft::query()->findOrFail($first->draft_id)->current_version)->toBe(1);
    $receipt = $this->postJson($url, $payload)->assertOk()->json('revision.id');
    $this->postJson($url, [...$payload, 'reason' => 'Different payload with the same key'])->assertConflict();
    $this->postJson($url, [...$payload, 'idempotency_key' => 'rebase-stale-version'])->assertConflict();
    expect(AdmissionBenchmarkDraft::query()->findOrFail($first->draft_id)->current_version)->toBe(2)
        ->and(AdmissionBenchmarkRevision::query()->where('draft_id', $first->draft_id)->count())->toBe(2)
        ->and(AdmissionBenchmarkRevision::query()->findOrFail($receipt)->base_benchmark_set_id)->toBe($published->id);
});

test('direct rebase and replay require fresh role authority and reject actor payload injection', function (): void {
    [$actor, $program, $cycle, , $published, $first] = benchmarkRebaseFixture();
    $unauthorized = User::factory()->create();
    $action = app(RebaseProgramBenchmarkDraft::class);
    expect(fn () => $action->handle($unauthorized, $program, $cycle, 1, $published->id, (string) $published->content_sha256, 'Unauthorized direct rebase', 'rebase-direct-denied'))->toThrow(AuthorizationException::class);
    $url = route('setup.admission-support.benchmarks.draft.rebase', [$program, $cycle]);
    $payload = ['expected_version' => 1, 'benchmark_set_id' => $published->id, 'expected_content_sha256' => $published->content_sha256, 'reason' => 'Authorized active set review', 'idempotency_key' => 'rebase-authorized-replay'];
    actingAsAdmissionSupportStaff($this, $actor)->postJson($url, [...$payload, 'actor_id' => $unauthorized->id])->assertUnprocessable();
    $revision = $action->handle($actor, $program, $cycle, 1, $published->id, (string) $published->content_sha256, $payload['reason'], $payload['idempotency_key']);
    $actor->syncRoles([]);
    expect(fn () => $action->handle($actor, $program, $cycle, 1, $published->id, (string) $published->content_sha256, $payload['reason'], $payload['idempotency_key']))->toThrow(AuthorizationException::class);
    actingAsAdmissionSupportStaff($this, $actor)->postJson($url, $payload)->assertForbidden();
    expect(AdmissionBenchmarkDraft::query()->findOrFail($first->draft_id)->current_version)->toBe($revision->version);
});
