<?php

use App\AdmissionEngine\Support\BenchmarkInputSchema;
use App\AdmissionEngine\Support\BenchmarkRangeAuthoring;
use App\AdmissionEngine\Support\RiasecActivityCatalog;
use App\Models\Admission\AdmissionBenchmarkDraft;
use App\Models\Admission\AdmissionBenchmarkFeature;
use App\Models\Admission\AdmissionBenchmarkRevision;
use App\Models\Admission\AdmissionBenchmarkSet;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;

function schemaFeature(string $key, array $source, bool $required = false): AdmissionBenchmarkFeature
{
    $feature = new AdmissionBenchmarkFeature;
    $feature->setRawAttributes([
        'feature_key' => $key,
        'label' => 'Biology',
        'logical_type' => 'numeric',
        'position' => 15,
        'is_required' => $required,
        'source_definition_json' => json_encode($source, JSON_THROW_ON_ERROR),
    ], true);

    return $feature;
}

function schemaSetWith(AdmissionBenchmarkFeature ...$features): AdmissionBenchmarkSet
{
    $set = new AdmissionBenchmarkSet;
    $set->setRelation('features', new Collection($features));

    return $set;
}

test('subject validation uses only the pinned source bounds and precision while retaining explicit missingness', function (): void {
    $set = schemaSetWith(schemaFeature('bio_mark', [
        'source_id' => 9,
        'source_code' => 'biology_grade',
        'source_version' => 1,
        'unit' => 'institution-approved grade scale',
        'min_value' => 0,
        'max_value' => 100,
        'precision' => 2,
        'missing_value' => null,
    ]));

    expect(BenchmarkInputSchema::validateValues($set, ['bio_mark' => 88.25]))
        ->toBe(['bio_mark' => 88.25])
        ->and(BenchmarkInputSchema::validateValues($set, ['bio_mark' => null]))
        ->toBe(['bio_mark' => null])
        ->and(BenchmarkInputSchema::validateValues($set, []))->toBe([]);

    expect(fn () => BenchmarkInputSchema::validateValues($set, ['biology' => 88]))
        ->toThrow(InvalidArgumentException::class, 'not declared')
        ->and(fn () => BenchmarkInputSchema::validateValues($set, ['bio_mark' => 100.01]))
        ->toThrow(InvalidArgumentException::class, 'bounds')
        ->and(fn () => BenchmarkInputSchema::validateValues($set, ['bio_mark' => 88.256]))
        ->toThrow(InvalidArgumentException::class, 'precision')
        ->and(fn () => BenchmarkInputSchema::validateValues($set, ['bio_mark' => INF]))
        ->toThrow(InvalidArgumentException::class, 'finite');
});

test('activity catalog preserves all stable identities and reviewed OR projection missingness', function (): void {
    $definitions = RiasecActivityCatalog::definitions();

    expect($definitions)->toHaveCount(18)
        ->and(RiasecActivityCatalog::project(['hardware_tech', 'construction_drafting', 'athletics_sports', 'software_algorithms'], true))
        ->toBe([
            'riasec_r' => 1,
            'riasec_i' => 1,
            'riasec_a' => 0,
            'riasec_s' => 0,
            'riasec_e' => 0,
            'riasec_c' => 0,
        ])
        ->and(RiasecActivityCatalog::project([], true))
        ->toBe(array_fill_keys(['riasec_r', 'riasec_i', 'riasec_a', 'riasec_s', 'riasec_e', 'riasec_c'], 0))
        ->and(RiasecActivityCatalog::project([], false))
        ->toBe(array_fill_keys(['riasec_r', 'riasec_i', 'riasec_a', 'riasec_s', 'riasec_e', 'riasec_c'], null));

    expect(fn () => RiasecActivityCatalog::project(['unknown_activity'], true))
        ->toThrow(InvalidArgumentException::class, 'not in the approved')
        ->and(fn () => RiasecActivityCatalog::project(['hardware_tech', 'hardware_tech'], true))
        ->toThrow(InvalidArgumentException::class, 'unique');
});

test('explicit successor projection drops retired model inputs while retaining advisory metadata and missingness', function (): void {
    $content = [
        'tiers' => array_fill_keys(['1', '2', '3', '4', '5'], ['values' => [
            'riasec_r' => 1,
            'shs_math' => 95,
            'shs_strand' => 'STEM',
        ]]),
        'authoring' => [
            'version' => 2,
            'ranges' => ['honors' => [], 'regular' => [], 'cutoff' => []],
            'preferred_strands' => ['STEM'],
            'interests' => null,
            'interest_activities' => ['sales_marketing'],
            'activities_reviewed' => true,
            'profile_activity_mapping' => [
                '1' => ['reviewed' => true, 'activity_ids' => ['hardware_tech']],
            ],
        ],
    ];
    $content['tiers']['1']['values']['LOGIC'] = 0;
    $content['tiers']['1']['values']['gwa'] = 89;
    $historical = $content;

    $successor = BenchmarkInputSchema::canonicalDraftContent($content);

    expect($successor['tiers']['1']['values'])->toBe([
        'LOGIC' => 0,
        'PROFILE_VERBAL' => null,
        'PROFILE_QUANT' => null,
        'SPATIAL' => null,
        'MEMORY' => null,
        'LANGUAGE' => null,
        'gwa' => 89,
    ])->and($successor['tiers']['5']['values'])
        ->toBe(array_fill_keys(['LOGIC', 'PROFILE_VERBAL', 'PROFILE_QUANT', 'SPATIAL', 'MEMORY', 'LANGUAGE', 'gwa'], null))
        ->and($successor['authoring']['preferred_strands'])->toBe(['STEM'])
        ->and($successor['authoring']['interest_activities'])->toBe(['sales_marketing'])
        ->and($successor['authoring'])->not->toHaveKey('profile_activity_mapping')
        ->and($content)->toBe($historical);
});

test('a historical revision keeps its pinned schema when the current draft root has a schema successor', function (): void {
    $oldSchema = [
        [
            'feature_key' => 'shs_math',
            'label' => 'Senior High School Mathematics',
            'logical_type' => 'numeric',
            'aptitude_area_id' => null,
            'is_required' => false,
            'position' => 1,
            'source_definition' => null,
        ],
    ];
    $successorSchema = [...$oldSchema, [
        'feature_key' => 'bio_mark',
        'label' => 'Biology',
        'logical_type' => 'numeric',
        'aptitude_area_id' => null,
        'is_required' => false,
        'position' => 2,
        'source_definition' => ['source_id' => 9, 'source_code' => 'biology_grade', 'source_version' => 1, 'unit' => 'approved', 'min_value' => 0, 'max_value' => 100, 'precision' => 2],
    ]];
    $draft = new AdmissionBenchmarkDraft;
    $draft->setRawAttributes([
        'base_benchmark_set_id' => 1,
        'feature_schema_json' => json_encode($successorSchema, JSON_THROW_ON_ERROR),
    ], true);
    $revision = new AdmissionBenchmarkRevision;
    $revision->setRawAttributes([
        'base_benchmark_set_id' => 1,
        'feature_schema_digest' => 'historical-schema',
        'feature_schema_json' => json_encode($oldSchema, JSON_THROW_ON_ERROR),
    ], true);

    expect(array_column(BenchmarkInputSchema::schemaForDraft($draft, $revision), 'feature_key'))->toBe(['shs_math'])
        ->and(array_column(BenchmarkInputSchema::projectionForDraft($draft, $revision)['subjects'], 'key'))->toBe(['shs_math']);
});

test('prepared common-range provenance survives later manual overrides and stays bound to prior saved ranges', function (): void {
    $content = [
        'tiers' => array_fill_keys(['1', '2', '3', '4', '5'], ['values' => ['bio_mark' => null]]),
        'authoring' => [
            'version' => 2,
            'ranges' => [
                'honors' => ['bio_mark' => [80, 90]],
                'regular' => ['bio_mark' => [80, 90]],
                'cutoff' => ['bio_mark' => [80, 90]],
            ],
            'preferred_strands' => null,
            'interests' => null,
            'preparation' => [
                'bio_mark' => [
                    'version' => 1,
                    'common_range' => [80, 90],
                    'before' => ['honors' => null, 'regular' => null, 'cutoff' => null],
                    'applied_values' => ['honors' => 90, 'regular' => 85, 'cutoff' => 80],
                    'reviewed' => true,
                ],
            ],
        ],
    ];
    $types = ['bio_mark' => 'numeric'];

    $prepared = BenchmarkRangeAuthoring::apply($content, $types);
    expect($prepared['tiers']['1']['values']['bio_mark'])->toBe(90)
        ->and($prepared['tiers']['2']['values']['bio_mark'])->toBe(85.0)
        ->and($prepared['tiers']['3']['values']['bio_mark'])->toBe(80);

    $prepared['authoring']['ranges']['honors']['bio_mark'] = [88, 92];
    $prepared['tiers']['1']['values']['bio_mark'] = 92;
    $overridden = BenchmarkRangeAuthoring::apply($prepared, $types);
    BenchmarkRangeAuthoring::assertCanonical($overridden, $types);
    BenchmarkRangeAuthoring::assertPreparationBeforeState($overridden, null);

    expect(fn () => BenchmarkRangeAuthoring::assertPreparationBeforeState($overridden, [
        'authoring' => ['ranges' => ['honors' => ['bio_mark' => [75, 80]]]],
    ]))->toThrow(InvalidArgumentException::class, 'previously saved');
});
