<?php

use App\AdmissionEngine\Actions\PublishProgramBenchmarkSet;
use App\AdmissionEngine\Actions\RestoreProgramBenchmarkDraftRevision;
use App\AdmissionEngine\Actions\SaveProgramBenchmarkDraft;
use App\AdmissionEngine\DTOs\AdmissionInputPayload;
use App\AdmissionEngine\ProgramBenchmarkPreviewQuery;
use App\AdmissionEngine\Support\BenchmarkRangeAuthoring;
use App\Models\Admission\AdmissionBenchmarkActivation;
use App\Models\Admission\AdmissionBenchmarkRevision;
use App\Models\Admission\AdmissionBenchmarkSet;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Program;
use App\Models\User;
use Database\Seeders\AdmissionBenchmarkSeeder;
use InvalidArgumentException;
use RuntimeException;

function seedBenchmarkRangeAuthoringFixture(): array
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

function benchmarkRangeAuthoringTestContent(AdmissionBenchmarkSet $set): array
{
    $tiers = [];
    foreach (range(1, 5) as $tier) {
        $values = [];
        foreach ($set->features as $feature) {
            $values[$feature->feature_key] = [1 => 92, 2 => 86, 3 => 82, 4 => 88, 5 => 60][$tier];
        }
        ksort($values);
        $tiers[(string) $tier] = ['values' => $values];
    }

    return ['tiers' => $tiers];
}

function benchmarkRangeAuthoringTestPayload(array $content): array
{
    $content['authoring'] = [
        'version' => 1,
        'ranges' => [
            'honors' => ['gwa' => [90, 96]],
            'regular' => ['gwa' => [80, 88]],
            'cutoff' => ['gwa' => [70, 82]],
        ],
        'preferred_strands' => null,
        'interests' => null,
    ];

    return $content;
}
test('legacy point-only saves and preference-only or one-band authoring preserve exact profile values', function (): void {
    [, $cycle, $set, $guidance] = seedBenchmarkRangeAuthoringFixture();
    $program = Program::query()->where('institution_id', $set->institution_id)->where('code', 'BSIT')->firstOrFail();
    $set->load('features');
    $url = route('setup.admission-support.benchmarks.draft.save', [$program, $cycle]);
    $content = benchmarkRangeAuthoringTestContent($set);

    $legacy = $this->actingAs($guidance)->postJson($url, [
        'content' => $content,
        'expected_version' => 0,
        'reason' => 'Save legacy point profile',
        'idempotency_key' => 'range-authoring-legacy-profile',
    ])->assertOk()->json('revision');
    $preferences = $content;
    $preferences['authoring'] = [
        'version' => 1,
        'ranges' => ['honors' => [], 'regular' => [], 'cutoff' => []],
        'preferred_strands' => [],
        'interests' => null,
    ];
    $preferenceRevision = $this->postJson($url, [
        'content' => $preferences,
        'expected_version' => 1,
        'reason' => 'Save review preferences without ranges',
        'idempotency_key' => 'range-authoring-preferences-only',
    ])->assertOk()->json('revision');
    $oneBand = $preferences;
    $oneBand['authoring']['ranges']['honors']['gwa'] = [90, 96];
    $oneBandRevision = $this->postJson($url, [
        'content' => $oneBand,
        'expected_version' => 2,
        'reason' => 'Save a single authored band',
        'idempotency_key' => 'range-authoring-one-band',
    ])->assertOk()->json('revision');

    expect(array_key_exists('authoring', $legacy['content']))->toBeFalse()
        ->and($preferenceRevision['content']['authoring']['preferred_strands'])->toBe([])
        ->and($preferenceRevision['content']['authoring']['interests'])->toBeNull()
        ->and($preferenceRevision['content']['tiers'])->toBe($content['tiers'])
        ->and($oneBandRevision['content']['tiers']['1']['values']['gwa'])->toBe(96)
        ->and($oneBandRevision['content']['tiers']['2']['values']['gwa'])->toBe(86)
        ->and($oneBandRevision['content']['tiers']['3']['values']['gwa'])->toBe(82);
});

test('positive and negative half-cent midpoints retain canonical values after revision hydration', function (): void {
    [, $cycle, $set, $guidance] = seedBenchmarkRangeAuthoringFixture();
    $program = Program::query()->where('institution_id', $set->institution_id)->where('code', 'BSIT')->firstOrFail();
    $set->load('features');
    $content = benchmarkRangeAuthoringTestContent($set);
    $content['authoring'] = [
        'version' => 1,
        'ranges' => [
            'honors' => ['gwa' => [0, 0.01]],
            'regular' => ['gwa' => [0, 0.01]],
            'cutoff' => ['gwa' => [0, 0.01]],
        ],
        'preferred_strands' => null,
        'interests' => null,
    ];
    $revision = app(SaveProgramBenchmarkDraft::class)->handle($guidance, $program, $cycle, $content, 0, 'Review half-cent midpoint rounding', 'range-authoring-half-cent');
    $revision->refresh();
    $types = $set->features->pluck('logical_type', 'feature_key')->all();

    BenchmarkRangeAuthoring::assertConsumable($revision->content, $types);
    expect($revision->content['tiers']['2']['values']['gwa'])->toEqual(0.01);
});

test('HTTP range save derives canonical numeric cells, persists authored metadata, binds replay, and restores losslessly', function (): void {
    [, $cycle, $set, $guidance] = seedBenchmarkRangeAuthoringFixture();
    $program = Program::query()->where('institution_id', $set->institution_id)->where('code', 'BSIT')->firstOrFail();
    $set->load('features');
    $content = benchmarkRangeAuthoringTestPayload(benchmarkRangeAuthoringTestContent($set));
    $url = route('setup.admission-support.benchmarks.draft.save', [$program, $cycle]);
    $payload = [
        'content' => $content,
        'expected_version' => 0,
        'reason' => 'Review synthetic authored ranges',
        'idempotency_key' => 'range-authoring-http-save',
    ];

    $response = $this->actingAs($guidance)->postJson($url, $payload)->assertOk()->assertJsonPath('status', 'success');
    $first = $response->json('revision');
    expect($first['content']['authoring'])->toBe($content['authoring'])
        ->and($first['content']['tiers']['1']['values']['gwa'])->toBe(96)
        ->and($first['content']['tiers']['2']['values']['gwa'])->toBe(84)
        ->and($first['content']['tiers']['3']['values']['gwa'])->toBe(70)
        ->and($first['content']['tiers']['4']['values']['gwa'])->toBe(88)
        ->and($first['content']['tiers']['5']['values']['gwa'])->toBe(60);

    $this->postJson($url, $payload)->assertOk()->assertJsonPath('revision.id', $first['id']);
    $changed = $payload;
    $changed['content']['authoring']['ranges']['honors']['gwa'][1] = 97;
    $this->postJson($url, $changed)->assertConflict();

    $next = $content;
    $next['authoring']['ranges']['honors']['gwa'] = [91, 97];
    app(SaveProgramBenchmarkDraft::class)->handle($guidance, $program, $cycle, $next, 1, 'Revise synthetic top range', 'range-authoring-second');
    $source = AdmissionBenchmarkRevision::query()->findOrFail($first['id']);
    $restored = app(RestoreProgramBenchmarkDraftRevision::class)->handle($guidance, $program, $cycle, $source, 2, 'Restore authored range', 'range-authoring-restore');

    expect($restored->content)->toBe($source->content)
        ->and($restored->old_content['authoring'])->toBe($next['authoring'])
        ->and($restored->content['authoring'])->toBe($content['authoring'])
        ->and($restored->content['tiers']['1']['values']['gwa'])->toBe(96);
});

test('incomplete authored ranges save as editable missing cells but cannot preview or publish', function (): void {
    [, $cycle, $set, $guidance] = seedBenchmarkRangeAuthoringFixture();
    $program = Program::query()->where('institution_id', $set->institution_id)->where('code', 'BSIT')->firstOrFail();
    $set->load('features');
    $content = benchmarkRangeAuthoringTestPayload(benchmarkRangeAuthoringTestContent($set));
    $content['authoring']['ranges']['cutoff']['gwa'] = [null, 82];
    $activationId = AdmissionBenchmarkActivation::currentForCycle((int) $cycle->id)?->id;
    $setCount = AdmissionBenchmarkSet::query()->count();
    $sourceDigest = $set->content_sha256;
    $response = $this->actingAs($guidance)->postJson(route('setup.admission-support.benchmarks.draft.save', [$program, $cycle]), [
        'content' => $content,
        'expected_version' => 0,
        'reason' => 'Save incomplete range for review',
        'idempotency_key' => 'range-authoring-partial',
    ])->assertOk();
    $revision = AdmissionBenchmarkRevision::query()->findOrFail($response->json('revision.id'));

    expect($revision->content['tiers']['3']['values']['gwa'])->toBeNull()
        ->and($revision->content['authoring']['ranges']['cutoff']['gwa'])->toBe([null, 82])
        ->and(AdmissionBenchmarkActivation::currentForCycle((int) $cycle->id)?->id)->toBe($activationId);

    $input = new AdmissionInputPayload(0, (int) $program->institution_id, (int) $cycle->id, 91.5, ['LOGIC' => 82.0]);
    expect(fn () => app(ProgramBenchmarkPreviewQuery::class)->handle($guidance, $program, $cycle, $input, 'draft', $revision->id, 'range-partial-preview'))
        ->toThrow(InvalidArgumentException::class, 'only one endpoint');

    expect(fn () => app(PublishProgramBenchmarkSet::class)->handle(
        $guidance,
        $cycle,
        [$program->id => $revision->id],
        (string) $set->content_sha256,
        'Reject incomplete range publication',
        'range-partial-publish',
    ))->toThrow(InvalidArgumentException::class, 'only one endpoint')
        ->and(AdmissionBenchmarkSet::query()->count())->toBe($setCount)
        ->and(AdmissionBenchmarkSet::query()->findOrFail($set->id)->content_sha256)->toBe($sourceDigest)
        ->and(AdmissionBenchmarkActivation::currentForCycle((int) $cycle->id)?->id)->toBe($activationId);
});

test('fully missing required cutoff ranges remain editable but cannot preview or publish', function (): void {
    [, $cycle, $set, $guidance] = seedBenchmarkRangeAuthoringFixture();
    $program = Program::query()->where('institution_id', $set->institution_id)->where('code', 'BSIT')->firstOrFail();
    $set->load('features');
    $content = benchmarkRangeAuthoringTestPayload(benchmarkRangeAuthoringTestContent($set));
    $content['authoring']['ranges']['cutoff']['gwa'] = [null, null];
    $content['tiers']['3']['values']['gwa'] = null;
    $url = route('setup.admission-support.benchmarks.draft.save', [$program, $cycle]);
    $response = $this->actingAs($guidance)->postJson($url, [
        'content' => $content,
        'expected_version' => 0,
        'reason' => 'Save fully missing required cutoff range',
        'idempotency_key' => 'range-required-null-save',
    ])->assertOk();
    $revision = AdmissionBenchmarkRevision::query()->findOrFail($response->json('revision.id'));
    $input = new AdmissionInputPayload(0, (int) $program->institution_id, (int) $cycle->id, 91.5, ['LOGIC' => 82.0]);
    $activationId = AdmissionBenchmarkActivation::currentForCycle((int) $cycle->id)?->id;
    $setCount = AdmissionBenchmarkSet::query()->count();

    expect($revision->content['authoring']['ranges']['cutoff']['gwa'])->toBe([null, null])
        ->and(fn () => app(ProgramBenchmarkPreviewQuery::class)->handle($guidance, $program, $cycle, $input, 'draft', $revision->id, 'range-required-null-preview'))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => app(PublishProgramBenchmarkSet::class)->handle($guidance, $cycle, [$program->id => $revision->id], (string) $set->content_sha256, 'Reject missing required cutoff', 'range-required-null-publish'))
        ->toThrow(RuntimeException::class, 'incomplete')
        ->and(AdmissionBenchmarkSet::query()->count())->toBe($setCount)
        ->and(AdmissionBenchmarkActivation::currentForCycle((int) $cycle->id)?->id)->toBe($activationId);
});

test('canonical model inputs publish without removed optional subject ranges and publication does not activate', function (): void {
    [, $cycle, $set, $guidance] = seedBenchmarkRangeAuthoringFixture();
    $program = Program::query()->where('institution_id', $set->institution_id)->where('code', 'BSIT')->firstOrFail();
    $set->load('features');
    $featureKeys = $set->features->pluck('feature_key')->all();
    expect($featureKeys)->toBe(['LOGIC', 'PROFILE_VERBAL', 'PROFILE_QUANT', 'SPATIAL', 'MEMORY', 'LANGUAGE', 'gwa'])
        ->and($set->features->contains(fn ($feature): bool => ! $feature->is_required && $feature->logical_type === 'numeric'))->toBeFalse();

    $content = benchmarkRangeAuthoringTestContent($set);
    $content['authoring'] = [
        'version' => 1,
        'ranges' => [
            'honors' => ['gwa' => [90, 96]],
            'regular' => ['gwa' => [80, 88]],
            'cutoff' => ['gwa' => [70, 82]],
        ],
        'preferred_strands' => null,
        'interests' => null,
    ];
    $revision = app(SaveProgramBenchmarkDraft::class)->handle($guidance, $program, $cycle, $content, 0, 'Save canonical GWA ranges without removed optional inputs', 'range-canonical-optional-save');
    $activationId = AdmissionBenchmarkActivation::currentForCycle((int) $cycle->id)?->id;
    $published = app(PublishProgramBenchmarkSet::class)->handle($guidance, $cycle, [$program->id => $revision->id], (string) $set->content_sha256, 'Publish complete canonical ranges', 'range-canonical-optional-publish');

    $manifest = $published->publication_manifest_json;
    expect($published->status)->toBe('published')
        ->and($published->features->pluck('feature_key')->all())->toBe($featureKeys)
        ->and($manifest[(string) $program->id]['revision_id'] ?? $manifest[$program->id]['revision_id'] ?? null)->toBe($revision->id)
        ->and(AdmissionBenchmarkActivation::currentForCycle((int) $cycle->id)?->id)->toBe($activationId);
});

test('range authoring rejects unknown numeric keys, unsupported versions, endpoint bounds, and stale revisions', function (): void {
    [, $cycle, $set, $guidance] = seedBenchmarkRangeAuthoringFixture();
    $program = Program::query()->where('institution_id', $set->institution_id)->where('code', 'BSIT')->firstOrFail();
    $set->load('features');
    $base = benchmarkRangeAuthoringTestContent($set);
    $action = app(SaveProgramBenchmarkDraft::class);

    foreach ([
        ['version' => 3, 'ranges' => ['honors' => [], 'regular' => [], 'cutoff' => []], 'preferred_strands' => null, 'interests' => null],
        ['version' => 1, 'ranges' => ['honors' => ['riasec_r' => [1, 2]], 'regular' => [], 'cutoff' => []], 'preferred_strands' => null, 'interests' => null],
        ['version' => 1, 'ranges' => ['honors' => ['gwa' => [9, 1]], 'regular' => [], 'cutoff' => []], 'preferred_strands' => null, 'interests' => null],
        ['version' => 1, 'ranges' => ['honors' => ['gwa' => [1, 1.111]], 'regular' => [], 'cutoff' => []], 'preferred_strands' => null, 'interests' => null],
        ['version' => 1, 'ranges' => ['honors' => [], 'regular' => [], 'cutoff' => []], 'preferred_strands' => null, 'interests' => null, 'server_model' => 'forged'],
        ['version' => 1, 'ranges' => ['honors' => ['gwa' => [1, 10000]], 'regular' => [], 'cutoff' => []], 'preferred_strands' => null, 'interests' => null],
    ] as $index => $authoring) {
        $invalid = $base;
        $invalid['authoring'] = $authoring;
        expect(fn () => $action->handle($guidance, $program, $cycle, $invalid, 0, 'Reject invalid authored ranges', 'range-authoring-invalid-'.$index))
            ->toThrow(InvalidArgumentException::class);
        $this->actingAs($guidance)->postJson(route('setup.admission-support.benchmarks.draft.save', [$program, $cycle]), [
            'content' => $invalid,
            'expected_version' => 0,
            'reason' => 'Reject invalid HTTP authored ranges',
            'idempotency_key' => 'range-authoring-http-invalid-'.$index,
        ])->assertUnprocessable();
    }

    $content = benchmarkRangeAuthoringTestPayload($base);
    $saved = $action->handle($guidance, $program, $cycle, $content, 0, 'Save exact range revision', 'range-authoring-stale-first');
    expect(fn () => $action->handle($guidance, $program, $cycle, $content, 0, 'Stale range editor', 'range-authoring-stale-second'))
        ->toThrow(RuntimeException::class, 'version conflict')
        ->and($saved->content['authoring'])->toBe($content['authoring']);
});
