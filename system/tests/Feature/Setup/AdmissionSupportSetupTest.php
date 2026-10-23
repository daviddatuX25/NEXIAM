<?php

use App\Admission\Actions\SaveProgramAdvisingNote;
use App\AdmissionEngine\Support\BenchmarkInputSchema;
use App\Authorization\CapabilityCatalog;
use App\Models\Admission\AdmissionBenchmarkActivation;
use App\Models\Admission\AdmissionBenchmarkDraft;
use App\Models\Admission\AdmissionBenchmarkSet;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\AptitudeArea;
use App\Models\Foundation\Program;
use App\Models\User;
use Database\Seeders\AdmissionBenchmarkSeeder;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->withoutVite();
    config([
        'inertia.ssr.enabled' => false,
        'admission_engine.tabfm.institutional_use_approved' => true,
        'admission_engine.tabfm.hosted_use_approved' => true,
        'admission_engine.tabfm.package_version' => '9.1.0',
        'admission_engine.tabfm.checkpoint_id' => 'Prior-Labs/tabpfn_3/tabpfn-v3-classifier-v3_20260417_binary.ckpt',
        'admission_engine.tabfm.checkpoint_sha256' => '1f9949fd52e20b6ed8ece20b3b37242d2abbbf337d14b461a4244f9318031a88',
    ]);
    Http::preventStrayRequests();
});

/** Synthetic HTTP response for consumer-contract coverage, not real-model evidence. */
function setupAdmissionSupportTabFmResponse(ClientRequest $request): array
{
    $programs = [];
    foreach ($request['context_exemplars'] as $exemplar) {
        $programs[$exemplar['program_code']] = $exemplar['program_name'];
    }
    ksort($programs);

    $recommendations = [];
    foreach ($programs as $programCode => $programName) {
        $recommendations[] = [
            'rank' => 0,
            'program_code' => $programCode,
            'program_name' => $programName,
            'compatibility_index' => 80,
            'band' => 'high_match',
            'drivers' => ['Synthetic adapter attribution'],
        ];
    }
    usort($recommendations, static fn (array $left, array $right): int => ($right['compatibility_index'] <=> $left['compatibility_index']) ?: ($left['program_code'] <=> $right['program_code']));
    foreach ($recommendations as $position => &$recommendation) {
        $recommendation['rank'] = $position + 1;
    }
    unset($recommendation);

    $featureDefinitions = $request['feature_definitions'];
    $queryApplicant = $request['query_applicant'];
    $featureCoverage = [];
    $missingFeatures = [];
    $missingOptionalFeatures = [];
    foreach ($featureDefinitions as $definition) {
        $featureKey = $definition['feature_key'];
        $available = array_key_exists($featureKey, $queryApplicant) && $queryApplicant[$featureKey] !== null;
        $required = (bool) $definition['is_required'];
        $featureCoverage[$featureKey] = ['available' => $available, 'required' => $required];
        if (! $available) {
            $missingFeatures[] = $featureKey;
            if (! $required) {
                $missingOptionalFeatures[] = $featureKey;
            }
        }
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
        'execution_time_ms' => 1.25,
        'modality_coverage' => [
            'available_features' => count($featureDefinitions) - count($missingFeatures),
            'total_features' => count($featureDefinitions),
            'missing_optional_features' => $missingOptionalFeatures,
            'missing_features' => $missingFeatures,
            'feature_coverage' => $featureCoverage,
        ],
        'feature_provenance' => $featureDefinitions,
        'recommendations' => $recommendations,
    ];
}
test('Decision Support entry excludes ungranted staff and does not expose the institution catalog overview', function (): void {
    seedE2Foundation();

    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $unauthorized = User::factory()->create(['email' => 'unauth.staff@nexiam.test']);

    $this->actingAs($unauthorized)
        ->get(route('setup.decision-support.index'))
        ->assertForbidden();

    actingAsAdmissionSupportStaff($this, $guidance)
        ->get(route('setup.decision-support.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Setup/AdmissionSupport/TabFmStudio')
            ->where('governance.selected_program', null)
            ->where('governance.selected_cycle', null)
            ->where('governance.sets', [])
            ->where('governance.draft', null)
            ->missing('providers')
            ->missing('history')
        );
});

test('TabFM studio governance is authorized for and bound to the selected program and cycle', function (): void {
    seedE2Foundation();
    (new AdmissionBenchmarkSeeder)->run();

    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $unauthorized = User::factory()->create(['email' => 'unauth.studio@nexiam.test']);
    $program = Program::query()->where('code', 'BSIT')->firstOrFail();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();

    $studioUrl = route('setup.decision-support.index', [
        'program' => $program->code,
        'cycle' => $cycle->code,
    ]);

    $this->actingAs($unauthorized)->get($studioUrl)->assertForbidden();

    actingAsAdmissionSupportStaff($this, $guidance)
        ->get($studioUrl)
        ->assertOk()
        ->assertInertia(function (Assert $page) use ($program, $cycle): void {
            $page->component('Setup/AdmissionSupport/TabFmStudio')
                ->where('governance.selected_program.id', $program->id)
                ->where('governance.selected_program.code', 'BSIT')
                ->where('governance.selected_cycle.id', $cycle->id)
                ->where('governance.selected_cycle.code', 'SYN-CYCLE-2026')
                ->where('governance.capabilities', function ($capabilities): bool {
                    return $capabilities[CapabilityCatalog::ADMISSION_BENCHMARK_GOVERNANCE_VIEW] === true;
                })
                ->where('governance.active_content.program_id', $program->id)
                ->has('governance.active_content.tiers', 5);
        });
});

test('Decision Support permits the assigned academic editor without catalog access and retains validated return context', function (): void {
    seedE2Foundation();
    $head = User::query()->where('email', 'program.head.bsit@nexiam.test')->firstOrFail();
    $program = Program::query()->where('code', 'BSIT')->firstOrFail();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $parameters = ['program_id' => $program->id, 'cycle_id' => $cycle->id, 'tool' => 'profile', 'return_to' => route('consultation.index')];

    actingAsAdmissionSupportStaff($this, $head)
        ->get(route('setup.decision-support.index', $parameters))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('governance.selected_program.id', $program->id)
            ->where('governance.initial_tool', 'profile')
            ->where('governance.return_to', route('consultation.index'))
            ->missing('providers')
            ->missing('history'));

    $parameters['return_to'] = 'https://untrusted.invalid/elsewhere';
    $this->get(route('setup.decision-support.index', $parameters))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('governance.return_to', null));
});

test('note-only staff enter Decision Support without receiving benchmark or catalog projections', function (): void {
    seedE2Foundation();
    $actor = User::query()->where('email', 'program.head.bsit@nexiam.test')->firstOrFail();
    $actor->syncRoles([]);
    $actor->syncPermissions([CapabilityCatalog::ADMISSION_ADVISING_NOTE_VIEW]);
    $program = Program::query()->where('code', 'BSIT')->firstOrFail();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();

    actingAsAdmissionSupportStaff($this, $actor)
        ->get(route('setup.index'))
        ->assertRedirect(route('setup.decision-support.index'));
    $this->get(route('setup.decision-support.index', ['program_id' => $program->id, 'cycle_id' => $cycle->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('governance.initial_tab', 'notes')
            ->where('governance.active_content', null)
            ->where('governance.draft', null)
            ->where('governance.sets', [])
            ->where('governance.schema_features', [])
            ->missing('providers')
            ->missing('history'));
});

test('a note details deep link opens the exact authorized note rather than the benchmark view', function (): void {
    seedE2Foundation();
    $actor = User::query()->where('email', 'program.head.bsit@nexiam.test')->firstOrFail();
    $program = Program::query()->where('code', 'BSIT')->firstOrFail();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $revision = app(SaveProgramAdvisingNote::class)->handle(
        $actor, $program, $cycle, 'Exact note reached from the consultation card.', 0, '', 'deep-link-note',
    );
    actingAsAdmissionSupportStaff($this, $actor)
        ->get(route('setup.decision-support.index', ['program_id' => $program->id, 'cycle_id' => $cycle->id, 'note_id' => $revision->program_advising_note_id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('governance.initial_tab', 'notes')
            ->where('governance.note_detail.draft.current_revision_id', $revision->id)
            ->where('governance.note_detail.draft.content', 'Exact note reached from the consultation card.'));
});

test('Program Head cannot read or edit another program benchmark history', function (): void {
    seedE2Foundation();
    (new AdmissionBenchmarkSeeder)->run();

    $owner = User::query()->where('email', 'program.head.bsit@nexiam.test')->firstOrFail();
    $unrelatedHead = User::query()->where('email', 'dean.cte@nexiam.test')->firstOrFail();
    $program = Program::query()->where('code', 'BSIT')->firstOrFail();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $routeParameters = ['program' => $program->id, 'cycle' => $cycle->id];

    actingAsAdmissionSupportStaff($this, $owner)
        ->postJson(route('setup.admission-support.benchmarks.draft.start', $routeParameters), [
            'expected_version' => 0,
            'reason' => 'Create owned benchmark revision for the scope regression',
            'idempotency_key' => 'owned-benchmark-scope-start',
        ])
        ->assertCreated();

    $historyUrl = route('setup.admission-support.benchmarks.history', $routeParameters);
    $ownedHistory = actingAsAdmissionSupportStaff($this, $owner)
        ->getJson($historyUrl)
        ->assertOk()
        ->assertJsonPath('pagination.total', 1)
        ->json('items.0.content');
    expect($ownedHistory)->toBeArray();

    actingAsAdmissionSupportStaff($this, $unrelatedHead)
        ->getJson($historyUrl)
        ->assertForbidden()
        ->assertJsonMissingPath('items')
        ->assertJsonMissingPath('items.0.content');

    $draft = AdmissionBenchmarkDraft::query()
        ->where('program_id', $program->id)
        ->where('academic_cycle_id', $cycle->id)
        ->firstOrFail();
    $revisionCount = $draft->revisions()->count();
    $currentVersion = $draft->current_version;
    $currentContent = $draft->currentRevision()?->content;

    actingAsAdmissionSupportStaff($this, $unrelatedHead)
        ->postJson(route('setup.admission-support.benchmarks.draft.save', $routeParameters), [
            'content' => $ownedHistory,
            'expected_version' => $currentVersion,
            'reason' => 'Attempt out-of-scope benchmark edit',
            'idempotency_key' => 'foreign-benchmark-scope-save',
        ])
        ->assertForbidden();

    expect($draft->fresh()->current_version)->toBe($currentVersion)
        ->and($draft->revisions()->count())->toBe($revisionCount)
        ->and($draft->currentRevision()?->content)->toBe($currentContent);
});

test('setup admission-support Yukee studio renders its standby workspace', function (): void {
    seedE2Foundation();

    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();

    actingAsAdmissionSupportStaff($this, $guidance)
        ->get(route('setup.admission-support.yukee'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Setup/AdmissionSupport/YukeeStudio')
        );
});
test('benchmark import endpoint persists the cycle-scoped set and redirects with flash success', function (): void {
    seedE2Foundation();

    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();

    $rawRanges = [
        'BSIT' => [
            'honors' => ['gwa' => [90, 95], 'gen' => [80, 90], 'verb' => [75, 85], 'num' => [80, 90], 'spat' => [75, 85], 'perc' => [75, 85], 'man' => [75, 85]],
            'regular' => ['gwa' => [85, 89], 'gen' => [74, 79], 'verb' => [70, 74], 'num' => [74, 79], 'spat' => [70, 74], 'perc' => [70, 74], 'man' => [70, 74]],
            'cutoff' => ['gwa' => [82, 84], 'gen' => [70, 73], 'verb' => [65, 69], 'num' => [70, 73], 'spat' => [66, 69], 'perc' => [65, 69], 'man' => [65, 69]],
        ],
    ];

    $response = actingAsAdmissionSupportStaff($this, $guidance)
        ->post(route('setup.admission-support.benchmarks.import', ['cycle' => $cycle]), [
            'name' => 'Imported Setup Set',
            'change_reason' => 'Test import from setup hub',
            'payload' => [
                'raw_ranges' => $rawRanges,
            ],
            'idempotency_key' => 'setup-import-key-1',
        ]);

    $response->assertRedirect()
        ->assertSessionHas('success');

    $set = AdmissionBenchmarkSet::query()->where('name', 'Imported Setup Set')->firstOrFail();
    expect($set->status)->toBe('published')
        ->and($set->content_sha256)->toMatch('/^[a-f0-9]{64}$/')
        ->and($set->exemplars()->whereHas('program', fn ($query) => $query->where('code', 'BSIT'))->orderBy('tier_level')->pluck('tier_level')->all())
        ->toBe([1, 2, 3, 4, 5]);
});

test('benchmarks activate endpoint activates set for cycle and supersedes prior activation', function (): void {
    seedE2Foundation();

    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    (new AdmissionBenchmarkSeeder)->run();

    $set1 = AdmissionBenchmarkActivation::currentForCycle((int) $cycle->id)->benchmarkSet()->firstOrFail();
    $set2 = publishSyntheticCompleteBenchmarkSuccessor($guidance, $cycle, false, 'setup-activation');
    $priorActive = AdmissionBenchmarkActivation::currentForCycle((int) $cycle->id);
    expect($priorActive?->benchmark_set_id)->toBe($set1->id);

    $response = actingAsAdmissionSupportStaff($this, $guidance)
        ->postJson(route('setup.admission-support.benchmarks.activate', ['cycle' => $cycle]), [
            'benchmark_set_id' => $set2->id,
            'expected_current_activation_id' => $priorActive->id,
            'expected_content_sha256' => $set2->content_sha256,
            'idempotency_key' => 'setup-activate-key-1',
        ]);

    $response->assertOk()->assertJsonPath('status', 'success');

    $currentActive = AdmissionBenchmarkActivation::currentForCycle($cycle->id);
    expect($currentActive?->benchmark_set_id)->toBe($set2->id)
        ->and($priorActive->fresh()->superseded_at)->not->toBeNull();
});

test('program-scoped benchmark preview evaluates the active source without database mutations', function (): void {
    seedE2Foundation();

    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $program = Program::query()->where('code', 'BSIT')->firstOrFail();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    (new AdmissionBenchmarkSeeder)->run();

    $setsBefore = AdmissionBenchmarkSet::query()->count();
    $activationsBefore = AdmissionBenchmarkActivation::query()->count();
    $aptitudes = AptitudeArea::query()
        ->where('is_active', true)
        ->whereIn('code', BenchmarkInputSchema::canonicalAptitudeCodes())
        ->orderBy('id')
        ->pluck('code')
        ->mapWithKeys(static fn (string $code): array => [$code => 80])
        ->all();

    config([
        'admission_engine.tabfm.driver' => 'http',
        'admission_engine.tabfm.endpoint' => 'http://inference:8101',
    ]);
    Http::fake([
        'http://inference:8101/inference/tabpfn' => fn (ClientRequest $request) => Http::response(
            setupAdmissionSupportTabFmResponse($request),
        ),
    ]);
    $response = actingAsAdmissionSupportStaff($this, $guidance)
        ->postJson(route('setup.admission-support.sandbox.governance-preview', [
            'program' => $program,
            'cycle' => $cycle,
        ]), [
            'source' => 'active',
            'request_id' => 'setup-preview-bsit-cycle-2026',
            'input' => [
                'gwa' => 91.5,
                'aptitudes' => $aptitudes,
            ],
        ]);

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('evaluation.slot', 'tabfm_pairwise')
        ->assertJsonPath('evaluation.status', 'evaluated')
        ->assertJsonStructure([
            'evaluation' => [
                'recommendations' => [
                    '*' => ['rank', 'program_code', 'program_name', 'compatibility_index', 'band', 'passed_cutoff', 'drivers'],
                ],
            ],
        ]);

    expect($response->json('evaluation.recommendations'))->toHaveCount(1)
        ->and($response->json('evaluation.recommendations.0.program_code'))->toBe('BSIT')
        ->and($response->json('evaluation.engine_version'))->toBe('9.1.0')
        ->and($response->json('evaluation.inference_backend'))->toBe('tabpfn_pytorch');

    actingAsAdmissionSupportStaff($this, $guidance)
        ->postJson(route('setup.admission-support.sandbox.governance-preview', [
            'program' => $program,
            'cycle' => $cycle,
        ]), [
            'source' => 'active',
            'request_id' => 'setup-preview-unknown-aptitude',
            'input' => [
                'gwa' => 91.5,
                'aptitudes' => [...$aptitudes, 'UNKNOWN' => 80],
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('input.aptitudes.UNKNOWN');

    expect(AdmissionBenchmarkSet::query()->count())->toBe($setsBefore)
        ->and(AdmissionBenchmarkActivation::query()->count())->toBe($activationsBefore);
    Http::assertSentCount(1);

});

test('draft preview uses the selected program revision and current input contract', function (): void {
    seedE2Foundation();
    (new AdmissionBenchmarkSeeder)->run();
    $set = completeSyntheticBenchmarkTestBase();

    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $program = Program::query()->where('code', 'BSIT')->firstOrFail();
    $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
    $aptitudes = AptitudeArea::query()
        ->where('is_active', true)
        ->whereIn('code', BenchmarkInputSchema::canonicalAptitudeCodes())
        ->orderBy('id')
        ->pluck('code')
        ->mapWithKeys(static fn (string $code): array => [$code => 80])
        ->all();

    $tiers = [];
    foreach ($set->exemplars()
        ->where('program_id', $program->id)
        ->with('values.feature')
        ->get() as $exemplar) {
        $values = [];
        foreach ($exemplar->values as $value) {
            $feature = $value->feature;
            $featureValue = $value->numeric_value ?? $value->categorical_value;
            $values[$feature->feature_key] = $feature->logical_type === 'binary' && $featureValue !== null
                ? (int) $featureValue
                : $featureValue;
        }
        $tiers[(string) $exemplar->tier_level] = ['values' => $values];
    }

    $draftResponse = actingAsAdmissionSupportStaff($this, $guidance)
        ->postJson(route('setup.admission-support.benchmarks.draft.save', [
            'program' => $program,
            'cycle' => $cycle,
        ]), [
            'content' => ['tiers' => $tiers],
            'expected_version' => 0,
            'reason' => 'Test scoped TabFM revision preview',
            'idempotency_key' => 'setup-draft-preview-save-1',
        ]);

    $draftResponse->assertOk()->assertJsonPath('status', 'success');
    $revisionId = $draftResponse->json('revision.id');

    config([
        'admission_engine.tabfm.driver' => 'http',
        'admission_engine.tabfm.endpoint' => 'http://inference:8101',
    ]);
    Http::fake([
        'http://inference:8101/inference/tabpfn' => fn (ClientRequest $request) => Http::response(
            setupAdmissionSupportTabFmResponse($request),
        ),
    ]);

    $preview = $this->postJson(route('setup.admission-support.sandbox.governance-preview', [
        'program' => $program,
        'cycle' => $cycle,
    ]), [
        'source' => 'draft',
        'revision_id' => $revisionId,
        'request_id' => 'setup-draft-preview-bsit-1',
        'input' => [
            'gwa' => 91.5,
            'aptitudes' => $aptitudes,
        ],
    ]);

    $preview->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('evaluation.slot', 'tabfm_pairwise')
        ->assertJsonPath('evaluation.status', 'evaluated')
        ->assertJsonPath('evaluation.engine_version', '9.1.0')
        ->assertJsonPath('evaluation.inference_backend', 'tabpfn_pytorch')
        ->assertJsonPath('evaluation.recommendations.0.program_code', 'BSIT');

    expect($revisionId)->toBeInt()
        ->and($preview->json('evaluation.recommendations'))->toHaveCount(1);
    Http::assertSentCount(1);
});
