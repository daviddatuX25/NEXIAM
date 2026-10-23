<?php

use App\AdmissionEngine\Actions\ActivateAdmissionBenchmarkSet;
use App\AdmissionEngine\Actions\BenchmarkExecutionContext;
use App\AdmissionEngine\Actions\ImportAdmissionBenchmarkSet;
use App\AdmissionEngine\Actions\PublishProgramBenchmarkSet;
use App\AdmissionEngine\Actions\RestoreProgramBenchmarkDraftRevision;
use App\AdmissionEngine\Actions\SaveProgramBenchmarkDraft;
use App\AdmissionEngine\Actions\StartProgramBenchmarkDraft;
use App\Authorization\CapabilityCatalog;
use App\Models\Admission\AdmissionBenchmarkActivation;
use App\Models\Admission\AdmissionBenchmarkDraft;
use App\Models\Admission\AdmissionBenchmarkSet;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Program;
use App\Models\StaffProfile;
use App\Models\User;
use App\Staffing\Actions\GrantTestAdministratorCapabilities;
use App\Staffing\Actions\RevokeTestAdministratorCapabilities;
use Database\Seeders\AdmissionBenchmarkSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

function benchmarkDraftContent(AdmissionBenchmarkSet $set, bool $negativeAnchor = true): array
{
    $tiers = [];
    foreach (range(1, 5) as $tier) {
        $values = [];
        foreach ($set->features as $feature) {
            $values[$feature->feature_key] = [1 => 92.0, 2 => 86.0, 3 => 82.0, 4 => 88.0, 5 => 60.0][$tier];
        }
        ksort($values);
        $tiers[(string) $tier] = ['values' => $tier === 5 && ! $negativeAnchor ? array_fill_keys(array_keys($values), null) : $values];
    }

    return ['tiers' => $tiers];
}
function seedPublishedBenchmarkGovernance(): array
{
    seedE2Foundation();
    (new AdmissionBenchmarkSeeder)->run();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $set = completeSyntheticBenchmarkTestBase();

    return [
        Institution::query()->where('code', 'SYN-INST-001')->firstOrFail(),
        $cycle,
        $set,
        $guidance,
    ];
}

test('benchmark draft saves append immutable canonical revisions and replays stable receipts', function (): void {
    [, $cycle, $set, $guidance] = seedPublishedBenchmarkGovernance();
    $program = Program::query()->where('institution_id', $set->institution_id)->where('code', 'BSIT')->firstOrFail();
    $set->load('features');
    $content = benchmarkDraftContent($set);
    $action = app(SaveProgramBenchmarkDraft::class);

    $first = $action->handle($guidance, $program, $cycle, $content, 0, 'Initial reviewed cells', 'benchmark-save-1');
    $replay = $action->handle($guidance, $program, $cycle, $content, 0, 'Initial reviewed cells', 'benchmark-save-1');

    expect($first->id)->toBe($replay->id)
        ->and($first->version)->toBe(1)
        ->and($first->actor_id)->toBe($guidance->id)
        ->and($replay->content)->toBe($first->content)
        ->and($first->content['tiers']['1']['values']['gwa'])->toBe(92)
        ->and(AdmissionBenchmarkDraft::query()->where('program_id', $program->id)->value('current_version'))->toBe(1);

    $changed = $content;
    $changed['tiers']['3']['values']['gwa'] = 83;
    expect(fn () => $action->handle($guidance, $program, $cycle, $changed, 0, 'Changed payload', 'benchmark-save-1'))
        ->toThrow(RuntimeException::class);
    expect(fn () => $action->handle($guidance, $program, $cycle, $changed, 0, 'Stale editor', 'benchmark-save-2'))
        ->toThrow(RuntimeException::class);
    $guidance->syncRoles([]);
    expect(fn () => $action->handle($guidance, $program, $cycle, $content, 0, 'Initial reviewed cells', 'benchmark-save-1'))
        ->toThrow(AuthorizationException::class);
});

test('benchmark draft saves an incomplete template without a fabricated negative anchor and restore appends a new revision', function (): void {
    [, $cycle, $set, $guidance] = seedPublishedBenchmarkGovernance();
    $program = Program::query()->where('institution_id', $set->institution_id)->where('code', 'BSIT')->firstOrFail();
    $set->load('features');
    $action = app(SaveProgramBenchmarkDraft::class);
    $incomplete = benchmarkDraftContent($set, false);
    $first = $action->handle($guidance, $program, $cycle, $incomplete, 0, 'Start incomplete review', 'benchmark-negative-1');
    $content = benchmarkDraftContent($set);
    $expectedMissingTier = array_fill_keys($set->features->pluck('feature_key')->all(), null);
    ksort($expectedMissingTier);
    expect($first->content['tiers']['5']['values'])->toBe($expectedMissingTier);

    $second = $action->handle($guidance, $program, $cycle, $content, 1, 'Complete reviewed cells', 'benchmark-restore-current');
    $restored = app(RestoreProgramBenchmarkDraftRevision::class)->handle($guidance, $program, $cycle, $first, 2, 'Restore incomplete review', 'benchmark-restore-1');

    expect($restored->version)->toBe(3)
        ->and($restored->content)->toBe($first->content)
        ->and($restored->predecessor_revision_id)->toBe($second->id)
        ->and($restored->restored_from_revision_id)->toBe($first->id)
        ->and($restored->old_content)->toBe($second->content);
});

test('starting an unconfigured program creates only explicit missing cells from the active schema', function (): void {
    [$institution, $cycle, $base, $guidance] = seedPublishedBenchmarkGovernance();
    $program = Program::query()->create([
        'institution_id' => $institution->id,
        'code' => 'SYN-NO-BASE-PROGRAM',
        'name' => 'Synthetic programme without prior exemplars',
        'is_active' => true,
        'is_admission_offered' => true,
    ]);

    $revision = app(StartProgramBenchmarkDraft::class)->handle(
        $guidance,
        $program,
        $cycle,
        0,
        'Start a blank review',
        'benchmark-start-no-program-base',
    );

    expect($revision->base_benchmark_set_id)->toBe($base->id)
        ->and($revision->version)->toBe(1)
        ->and($revision->content['tiers'])->toHaveCount(5)
        ->and(collect($revision->content['tiers'])->flatMap(fn (array $tier): array => $tier['values'])->every(fn ($value): bool => $value === null))->toBeTrue();
});
test('benchmark start fails clearly without an approved current feature schema and leaves no draft', function (): void {
    seedE2Foundation();
    $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $program = Program::query()->create([
        'institution_id' => $institution->id,
        'code' => 'SYN-NO-SCHEMA',
        'name' => 'Synthetic offered program without a source schema',
        'is_active' => true,
        'is_admission_offered' => true,
    ]);

    expect(fn () => app(StartProgramBenchmarkDraft::class)->handle(
        $guidance,
        $program,
        $cycle,
        0,
        'Attempt blank template without source schema',
        'benchmark-start-no-schema',
    ))->toThrow(RuntimeException::class, 'approved benchmark feature schema')
        ->and(AdmissionBenchmarkDraft::query()->where('program_id', $program->id)->exists())->toBeFalse();
});

test('benchmark actions deny actors without fresh governance capability and published content cannot be edited', function (): void {
    [, $cycle, $set] = seedPublishedBenchmarkGovernance();
    $program = Program::query()->where('institution_id', $set->institution_id)->where('code', 'BSIT')->firstOrFail();
    $unauthorized = User::factory()->create();
    $set->load('features');

    expect(fn () => app(SaveProgramBenchmarkDraft::class)->handle($unauthorized, $program, $cycle, benchmarkDraftContent($set), 0, 'Attempt direct edit', 'benchmark-denied-1'))
        ->toThrow(AuthorizationException::class);

    $feature = $set->features->firstOrFail();
    $feature->label = 'Tampered label';
    expect(fn () => $feature->save())->toThrow(RuntimeException::class);
});

test('benchmark activation checks exact current identity and returns the original activation receipt', function (): void {
    [, $cycle, $set, $guidance] = seedPublishedBenchmarkGovernance();
    $current = AdmissionBenchmarkActivation::currentForCycle((int) $cycle->id);
    expect($current)->not->toBeNull();

    $action = app(ActivateAdmissionBenchmarkSet::class);
    $next = $action->handle($cycle, $set, BenchmarkExecutionContext::human($guidance), $current->id, (string) $set->content_sha256, 'benchmark-activation-1');
    $later = $action->handle($cycle, $set, BenchmarkExecutionContext::human($guidance), $next->id, (string) $set->content_sha256, 'benchmark-activation-2');
    $replay = $action->handle($cycle, $set, BenchmarkExecutionContext::human($guidance), $current->id, (string) $set->content_sha256, 'benchmark-activation-1');

    expect($next->id)->toBe($replay->id)
        ->and($next->sequence)->toBe($current->sequence + 1)
        ->and($current->fresh()->superseded_at)->not->toBeNull()
        ->and($later->id)->not->toBe($replay->id);

    $guidance->syncRoles([]);
    expect(fn () => $action->handle($cycle, $set, BenchmarkExecutionContext::human($guidance), $current->id, (string) $set->content_sha256, 'benchmark-activation-1'))
        ->toThrow(AuthorizationException::class);
});

test('benchmark activation denies direct action calls without authority', function (): void {
    [, $cycle, $set] = seedPublishedBenchmarkGovernance();
    $unauthorized = User::factory()->create();
    $current = AdmissionBenchmarkActivation::currentForCycle((int) $cycle->id);

    expect(fn () => app(ActivateAdmissionBenchmarkSet::class)->handle(
        $cycle,
        $set,
        BenchmarkExecutionContext::human($unauthorized),
        $current?->id,
        (string) $set->content_sha256,
        'benchmark-activation-denied',
    ))->toThrow(AuthorizationException::class);
});

test('publication pins the exact edited revision and preserves unchanged program exemplars', function (): void {
    [$institution, $cycle, $base, $guidance] = seedPublishedBenchmarkGovernance();
    $program = Program::query()->where('institution_id', $base->institution_id)->where('code', 'BSIT')->firstOrFail();
    $blankSibling = Program::query()->create([
        'institution_id' => $institution->id,
        'code' => 'SYN-BLANK-SIBLING',
        'name' => 'Synthetic blank sibling',
        'is_active' => true,
        'is_admission_offered' => true,
    ]);
    app(StartProgramBenchmarkDraft::class)->handle(
        $guidance,
        $blankSibling,
        $cycle,
        0,
        'Start an unrelated blank sibling draft',
        'publish-blank-sibling-start',
    );
    $base->load('features');
    $content = benchmarkDraftContent($base);
    $content['tiers']['3']['values']['gwa'] = 81.5;
    $revision = app(SaveProgramBenchmarkDraft::class)->handle($guidance, $program, $cycle, $content, 0, 'Review BSIT cells', 'publish-draft-1');
    $manifest = [$program->id => $revision->id];
    $publisher = app(PublishProgramBenchmarkSet::class);

    expect(fn () => $publisher->handle(User::factory()->create(), $cycle, $manifest, (string) $base->content_sha256, 'Unauthorized publication', 'publish-denied'))
        ->toThrow(AuthorizationException::class);

    $published = $publisher->handle($guidance, $cycle, $manifest, (string) $base->content_sha256, 'Reviewed exact revision', 'publish-exact-1');
    $unchangedProgramId = Program::query()->where('code', 'BSEd')->value('id');
    $baseUnchanged = $base->exemplars()->where('program_id', $unchangedProgramId)->count();
    $publishedUnchanged = $published->exemplars()->where('program_id', $unchangedProgramId)->count();
    $baseTierOne = $base->exemplars()->where('program_id', $unchangedProgramId)->where('tier_level', 1)->with('values.feature')->firstOrFail();
    $publishedTierOne = $published->exemplars()->where('program_id', $unchangedProgramId)->where('tier_level', 1)->with('values.feature')->firstOrFail();
    $publishedTierThree = $published->exemplars()->where('program_id', $program->id)->where('tier_level', 3)->with('values.feature')->firstOrFail();
    $publishedTierThreeValues = $publishedTierThree->values->mapWithKeys(fn ($value): array => [$value->feature->feature_key => $value->is_missing ? null : ($value->feature->logical_type === 'categorical' ? $value->categorical_value : (float) $value->numeric_value)])->all();
    $publishedTierThreeMissing = false;
    $publishedTierFive = $published->exemplars()->where('program_id', $program->id)->where('tier_level', 5)->with('values.feature')->firstOrFail();
    $publishedTierFiveValues = $publishedTierFive->values->mapWithKeys(fn ($value): array => [$value->feature->feature_key => $value->is_missing ? null : ($value->feature->logical_type === 'categorical' ? $value->categorical_value : (float) $value->numeric_value)])->all();

    $baseValues = $baseTierOne->values->mapWithKeys(fn ($value): array => [$value->feature->feature_key => $value->is_missing ? null : ($value->feature->logical_type === 'categorical' ? $value->categorical_value : (float) $value->numeric_value)])->all();
    $publishedValues = $publishedTierOne->values->mapWithKeys(fn ($value): array => [$value->feature->feature_key => $value->is_missing ? null : ($value->feature->logical_type === 'categorical' ? $value->categorical_value : (float) $value->numeric_value)])->all();
    ksort($baseValues);
    ksort($publishedValues);

    expect($published->predecessor_id)->toBe($base->id)
        ->and($published->publication_manifest_json[(string) $program->id]['revision_id'])->toBe($revision->id)
        ->and($published->publication_manifest_json[(string) $unchangedProgramId]['source_set_id'])->toBe($base->id)
        ->and($publishedUnchanged)->toBe($baseUnchanged)
        ->and($publishedTierThreeMissing)->toBeFalse()
        ->and($publishedTierThreeValues['gwa'])->toBe(81.5)
        ->and($published->exemplars()->count())->toBe($base->exemplars()->count())
        ->and($published->exemplars()->where('program_id', $blankSibling->id)->exists())->toBeFalse()
        ->and($published->status)->toBe('published');
});

test('benchmark draft rejects boolean numeric cells and storage precision loss', function (): void {
    [, $cycle, $set, $guidance] = seedPublishedBenchmarkGovernance();
    $program = Program::query()->where('institution_id', $set->institution_id)->where('code', 'BSIT')->firstOrFail();
    $set->load('features');
    $action = app(SaveProgramBenchmarkDraft::class);

    foreach ([true, 81.555] as $index => $invalidValue) {
        $content = benchmarkDraftContent($set);
        $content['tiers']['3']['values']['gwa'] = $invalidValue;

        expect(fn () => $action->handle($guidance, $program, $cycle, $content, 0, 'Reject unrepresentable review cell', 'benchmark-storage-invalid-'.$index))
            ->toThrow(InvalidArgumentException::class);
    }

    $content = benchmarkDraftContent($set);
    $content['tiers']['3']['values']['gwa'] = '81.50';
    $revision = $action->handle($guidance, $program, $cycle, $content, 0, 'Preserve canonical reviewed cells', 'benchmark-storage-valid');

    expect($revision->content['tiers']['3']['values']['gwa'])->toBe('81.50');
});

test('direct benchmark imports reject forged publication metadata and replay key-reordered payloads', function (): void {
    [$institution, $cycle, $base, $guidance] = seedPublishedBenchmarkGovernance();
    $program = Program::query()->where('institution_id', $institution->id)->where('code', 'BSIT')->firstOrFail();
    $base->load(['features', 'exemplars.program', 'exemplars.values.feature']);
    $action = app(ImportAdmissionBenchmarkSet::class);

    expect(fn () => $action->handle($institution, $cycle, BenchmarkExecutionContext::human($guidance), [
        'change_reason' => 'Forged predecessor',
        'predecessor_id' => $base->id,
        'publication_manifest' => [],
    ], 'benchmark-forged-metadata'))->toThrow(InvalidArgumentException::class);
    expect(fn () => $action->handle($institution, $cycle, BenchmarkExecutionContext::human($guidance), [
        'name' => 'Missing reason import',
        'synthesized_exemplars' => [],
    ], 'benchmark-no-reason'))->toThrow(InvalidArgumentException::class);

    $exemplars = $base->exemplars->where('program_id', $program->id)->map(function ($exemplar): array {
        $values = [];
        foreach ($exemplar->values as $value) {
            $values[$value->feature->feature_key] = $value->is_missing
                ? null
                : ($value->feature->logical_type === 'categorical' ? $value->categorical_value : (float) $value->numeric_value);
        }

        return [
            'program_code' => $exemplar->program->code,
            'program_name' => $exemplar->program->name,
            'tier_level' => $exemplar->tier_level,
            'tier_label' => $exemplar->tier_label,
            'target_class' => $exemplar->target_class,
            'values' => $values,
        ];
    })->values()->all();
    $firstPayload = ['name' => 'Key order replay', 'change_reason' => 'Authorized direct import', 'synthesized_exemplars' => $exemplars];
    $reorderedExemplars = array_map(static function (array $exemplar): array {
        $exemplar['values'] = array_reverse($exemplar['values'], true);

        return $exemplar;
    }, $exemplars);
    $reorderedPayload = ['synthesized_exemplars' => $reorderedExemplars, 'change_reason' => 'Authorized direct import', 'name' => 'Key order replay'];

    $created = $action->handle($institution, $cycle, BenchmarkExecutionContext::human($guidance), $firstPayload, 'benchmark-order-replay');
    $replayed = $action->handle($institution, $cycle, BenchmarkExecutionContext::human($guidance), $reorderedPayload, 'benchmark-order-replay');

    expect($replayed->id)->toBe($created->id)
        ->and($created->change_reason)->toBe('Authorized direct import');
});
test('assigned Program Head can edit and restore all program cells but only Guidance publishes', function (): void {
    [$institution, $cycle, $base, $guidance] = seedPublishedBenchmarkGovernance();
    $program = Program::query()->where('institution_id', $institution->id)->where('code', 'BSIT')->firstOrFail();
    $programHead = User::factory()->create();
    $programHead->assignRole('program_head');
    StaffProfile::query()->create([
        'user_id' => $programHead->id,
        'institution_id' => $institution->id,
        'office' => 'program',
        'program_codes' => [$program->code],
        'active_cycle_code' => $cycle->code,
        'status' => StaffProfile::STATUS_ACTIVE,
    ]);
    $programHead->givePermissionTo(CapabilityCatalog::ADMISSION_BENCHMARK_PUBLISH);
    $base->load('features');
    $content = benchmarkDraftContent($base);
    $draftAction = app(SaveProgramBenchmarkDraft::class);
    $first = $draftAction->handle($programHead, $program, $cycle, $content, 0, 'Initial assigned-program review', 'ph-governance-first');
    $changed = $content;
    $changed['tiers']['3']['values']['gwa'] = 81.0;
    $draftAction->handle($programHead, $program, $cycle, $changed, 1, 'Revise assigned-program review', 'ph-governance-second');
    $restored = app(RestoreProgramBenchmarkDraftRevision::class)->handle(
        $programHead,
        $program,
        $cycle,
        $first,
        2,
        'Restore assigned-program cells',
        'ph-governance-restore',
    );
    $publisher = app(PublishProgramBenchmarkSet::class);
    $current = AdmissionBenchmarkActivation::currentForCycle((int) $cycle->id);
    expect($current)->not->toBeNull();

    expect(fn () => $publisher->handle(
        $programHead,
        $cycle,
        [$program->id => $restored->id],
        (string) $base->content_sha256,
        'Obsolete academic publication grant',
        'ph-governance-publish',
    ))->toThrow(AuthorizationException::class);

    $published = $publisher->handle(
        $guidance,
        $cycle,
        [$program->id => $restored->id],
        (string) $base->content_sha256,
        'Guidance publishes the reviewed program revision',
        'guidance-publish-ph-draft',
    );
    $currentAfterPublish = AdmissionBenchmarkActivation::currentForCycle((int) $cycle->id);
    expect($currentAfterPublish?->id)->toBe($current->id)
        ->and($currentAfterPublish?->benchmark_set_id)->toBe($current->benchmark_set_id)
        ->and($currentAfterPublish?->sequence)->toBe($current->sequence)
        ->and($currentAfterPublish?->superseded_at)->toBeNull()
        ->and(AdmissionBenchmarkActivation::query()->where('benchmark_set_id', $published->id)->exists())->toBeFalse();
    $activation = app(ActivateAdmissionBenchmarkSet::class)->handle(
        $cycle,
        $published,
        BenchmarkExecutionContext::human($programHead),
        $current->id,
        (string) $published->content_sha256,
        'ph-governance-activate',
    );

    expect($restored->content)->toBe($first->content)
        ->and($published->publication_manifest_json[(string) $program->id]['revision_id'])->toBe($restored->id)
        ->and($published->exemplars()->count())->toBe($base->exemplars()->count())
        ->and($activation->benchmark_set_id)->toBe($published->id)
        ->and($activation->expected_current_activation_id)->toBe($current->id);
});
test('revoked designated TA cannot disclose a prior publication receipt through action replay', function (): void {
    [$institution, $cycle, $base, $guidance] = seedPublishedBenchmarkGovernance();
    $program = Program::query()->where('institution_id', $institution->id)->where('code', 'BSIT')->firstOrFail();
    $ta = User::factory()->create();
    $ta->assignRole('guidance_clerk');
    StaffProfile::query()->create([
        'user_id' => $ta->id,
        'institution_id' => $institution->id,
        'office' => 'guidance',
        'status' => StaffProfile::STATUS_ACTIVE,
    ]);
    app(GrantTestAdministratorCapabilities::class)->handle($guidance, $ta);

    $base->load('features');
    $revision = app(SaveProgramBenchmarkDraft::class)->handle(
        $guidance,
        $program,
        $cycle,
        benchmarkDraftContent($base),
        0,
        'Save reviewed revision for TA publication',
        'ta-replay-draft',
    );
    $publisher = app(PublishProgramBenchmarkSet::class);
    $published = $publisher->handle(
        $ta,
        $cycle,
        [$program->id => $revision->id],
        (string) $base->content_sha256,
        'Designated TA publication',
        'revoked-ta-publication-replay',
    );
    app(RevokeTestAdministratorCapabilities::class)->handle($guidance, $ta);

    expect(fn () => $publisher->handle(
        $ta,
        $cycle,
        [$program->id => $revision->id],
        (string) $base->content_sha256,
        'Designated TA publication',
        'revoked-ta-publication-replay',
    ))->toThrow(AuthorizationException::class)
        ->and(AdmissionBenchmarkSet::query()->whereKey($published->id)->exists())->toBeTrue();
});

test('activation recomputes content delta and rejects a tampered foreign-program manifest claim', function (): void {
    [$institution, $cycle, $base, $guidance] = seedPublishedBenchmarkGovernance();
    $program = Program::query()->where('institution_id', $institution->id)->where('code', 'BSIT')->firstOrFail();
    $foreignProgram = Program::query()->where('institution_id', $institution->id)->where('code', 'BSEd')->firstOrFail();
    $base->load('features');
    $content = benchmarkDraftContent($base);
    $content['tiers']['3']['values']['gwa'] = 81.0;
    $revision = app(SaveProgramBenchmarkDraft::class)->handle($guidance, $program, $cycle, $content, 0, 'Review one program only', 'delta-manifest-save');
    $published = app(PublishProgramBenchmarkSet::class)->handle(
        $guidance,
        $cycle,
        [$program->id => $revision->id],
        (string) $base->content_sha256,
        'Publish one reviewed program',
        'delta-manifest-publish',
    );
    $foreignRevision = app(SaveProgramBenchmarkDraft::class)->handle(
        $guidance,
        $foreignProgram,
        $cycle,
        benchmarkDraftContent($base),
        0,
        'Synthetic revision for manifest integrity coverage',
        'delta-manifest-foreign-revision',
    );
    $tamperedManifest = $published->publication_manifest_json;
    $tamperedManifest[(string) $foreignProgram->id]['revision_id'] = $foreignRevision->id;
    $tamperedManifest[(string) $foreignProgram->id]['revision_version'] = $foreignRevision->version;
    DB::table('admission_benchmark_sets')
        ->where('id', $published->id)
        ->update(['publication_manifest_json' => json_encode($tamperedManifest, JSON_THROW_ON_ERROR)]);
    $current = AdmissionBenchmarkActivation::currentForCycle((int) $cycle->id);

    expect(fn () => app(ActivateAdmissionBenchmarkSet::class)->handle(
        $cycle,
        $published,
        BenchmarkExecutionContext::human($guidance),
        $current?->id,
        (string) $published->content_sha256,
        'delta-manifest-activate-tampered',
    ))->toThrow(RuntimeException::class, 'delta')
        ->and(AdmissionBenchmarkActivation::currentForCycle((int) $cycle->id)?->benchmark_set_id)->toBe($base->id)
        ->and(AdmissionBenchmarkActivation::query()->where('benchmark_set_id', $published->id)->exists())->toBeFalse();
});
test('a human activation cannot use a non-manifest sibling rollback as a successor', function (): void {
    [$institution, $cycle, $base, $guidance] = seedPublishedBenchmarkGovernance();
    $program = Program::query()->where('institution_id', $institution->id)->where('code', 'BSIT')->firstOrFail();
    $base->load('features');
    $content = benchmarkDraftContent($base);
    $content['tiers']['3']['values']['gwa'] = 81.0;
    $revision = app(SaveProgramBenchmarkDraft::class)->handle($guidance, $program, $cycle, $content, 0, 'Review a one-program successor', 'nonmanifest-successor-save');
    $candidate = app(PublishProgramBenchmarkSet::class)->handle(
        $guidance,
        $cycle,
        [$program->id => $revision->id],
        (string) $base->content_sha256,
        'Publish a one-program successor',
        'nonmanifest-successor-publish',
    );
    DB::table('admission_benchmark_sets')
        ->where('id', $candidate->id)
        ->update(['publication_manifest_json' => null]);
    $current = AdmissionBenchmarkActivation::currentForCycle((int) $cycle->id);

    expect(fn () => app(ActivateAdmissionBenchmarkSet::class)->handle(
        $cycle,
        $candidate,
        BenchmarkExecutionContext::human($guidance),
        $current?->id,
        (string) $candidate->content_sha256,
        'nonmanifest-successor-activate',
    ))->toThrow(RuntimeException::class, 'reviewed publication manifest')
        ->and(AdmissionBenchmarkActivation::currentForCycle((int) $cycle->id)?->benchmark_set_id)->toBe($base->id)
        ->and(AdmissionBenchmarkActivation::query()->where('benchmark_set_id', $candidate->id)->exists())->toBeFalse();
});
