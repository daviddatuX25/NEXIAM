<?php

use App\Models\Assessment\AssessmentNormPackage;
use App\Models\Assessment\AssessmentNormPackageVersion;
use App\Models\Assessment\TestInstrument;
use App\Models\Assessment\TestInstrumentMetric;
use App\Models\Assessment\TestInstrumentVersion;
use App\Models\Foundation\Institution;
use App\Models\User;
use Database\Seeders\FormulaScoringDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('formula scoring demo is an idempotent synthetic draft with subset, weighted, and decimal examples', function (): void {
    $this->withoutVite();
    seedE2Foundation();
    $institution = Institution::current();
    $actor = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();
    $seeder = new FormulaScoringDemoSeeder;

    $seeder->seed($institution, $actor);

    $instrument = TestInstrument::query()
        ->where('institution_id', $institution->id)
        ->where('code', 'FORMULA-SCORING-DEMO')
        ->firstOrFail();
    $draft = $instrument->draftVersion()->with('metrics', 'subtests.items')->firstOrFail();
    $metrics = $draft->metrics->keyBy('code');
    $normPackage = AssessmentNormPackage::query()->where('code', 'INSTRUMENT_'.$instrument->id)->firstOrFail();
    $normDraft = $normPackage->draftVersion()->with('tables')->firstOrFail();
    expect($instrument->is_synthetic)->toBeTrue()
        ->and($instrument->current_approved_version_id)->toBeNull()
        ->and($draft->status)->toBe(TestInstrumentVersion::STATUS_DRAFT)
        ->and($draft->content_sha256)->toBeNull()
        ->and($draft->subtests)->toHaveCount(2)
        ->and($draft->subtests->sum(fn ($subtest): int => $subtest->items->count()))->toBe(20)
        ->and($metrics['A_RAW']->input_contract_json['allowed_sources'])->toBe(['import', 'manual'])
        ->and($metrics['A_RAW']->input_contract_json['required_resources'])->toBe([])
        ->and($metrics['A_CORE_RAW']->item_codes_json)->toBe(['A_ITEM_1', 'A_ITEM_2', 'A_ITEM_3', 'A_ITEM_4', 'A_ITEM_5'])
        ->and($metrics['WEIGHTED_RAW']->rule_kind)->toBe(TestInstrumentMetric::RULE_FORMULA_RAW_METRICS)
        ->and($metrics['WEIGHTED_RAW']->formula_ast_json)->not->toBeNull()
        ->and($metrics['SCALED_DECIMAL_RAW']->formula_expression)->toBe('0.20*A_CORE_RAW')
        ->and($metrics['SCALED_DECIMAL_RAW']->precision_scale)->toBe(2)
        ->and($draft->material_requirement)->toBe(TestInstrumentVersion::MATERIAL_REQUIREMENT_NO_PAPER)
        ->and($normPackage->is_synthetic)->toBeTrue()
        ->and($normPackage->current_approved_version_id)->toBeNull()
        ->and($normDraft->status)->toBe(AssessmentNormPackageVersion::STATUS_DRAFT)
        ->and($normDraft->tables)->toHaveCount(4);

    $this->actingAs($actor)
        ->get(route('setup.test-instruments.show', ['instrument' => $instrument->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Setup/TestInstruments/Show')
            ->where('instrument.code', 'FORMULA-SCORING-DEMO')
            ->where('instrument.draft_version.status', TestInstrumentVersion::STATUS_DRAFT)
            ->has('instrument.draft_version.metrics', 5)
        );

    $versionId = $draft->id;
    $lockVersion = $draft->lock_version;
    $metricIds = $draft->metrics->pluck('id')->sort()->values()->all();
    $normDraftId = $normDraft->id;
    $normDraftLockVersion = $normDraft->lock_version;
    $normTableIds = $normDraft->tables->pluck('id')->sort()->values()->all();

    $seeder->seed($institution, $actor);

    expect(TestInstrument::query()->where('code', 'FORMULA-SCORING-DEMO')->count())->toBe(1)
        ->and($instrument->fresh()->versions()->count())->toBe(1)
        ->and($instrument->fresh()->draftVersion()->firstOrFail()->id)->toBe($versionId)
        ->and($instrument->fresh()->draftVersion()->firstOrFail()->lock_version)->toBe($lockVersion)
        ->and(TestInstrumentMetric::query()->where('test_instrument_version_id', $versionId)->orderBy('id')->pluck('id')->all())
        ->toBe($metricIds)
        ->and($normPackage->fresh()->versions()->count())->toBe(1)
        ->and($normPackage->fresh()->draftVersion()->firstOrFail()->id)->toBe($normDraftId)
        ->and($normPackage->fresh()->draftVersion()->firstOrFail()->lock_version)->toBe($normDraftLockVersion)
        ->and($normDraft->fresh()->tables()->reorder('id')->pluck('id')->all())->toBe($normTableIds);
});
