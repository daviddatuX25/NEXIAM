<?php

namespace App\Models\Assessment;

use App\Assessment\Support\RawMetricFormulaEngine;
use App\Models\Foundation\AptitudeArea;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TestInstrumentMetric extends Model
{
    public const RULE_CORRECT_ITEM_COUNT = 'correct_item_count';

    public const RULE_SUM_RAW_METRICS = 'sum_raw_metrics';

    public const RULE_FORMULA_RAW_METRICS = 'formula_raw_metrics';

    public const FORMULA_ALGORITHM_IDENTITY = RawMetricFormulaEngine::IDENTITY;

    public const RULE_RECORDED_MEASURE = 'recorded_measure';

    public const RULE_EXTERNAL_FINAL = 'external_final';

    /** @var list<string> */
    public const OUTPUT_SCALES = ['percentile_rank', 'stanine', 'category'];

    protected $fillable = [
        'test_instrument_version_id',
        'code',
        'name',
        'rule_kind',
        'test_instrument_subtest_id',
        'unit',
        'min_raw_value',
        'max_raw_value',
        'precision_scale',
        'is_required',
        'required_output_scales',
        'input_contract_json',
        'formula_expression',
        'formula_ast_json',
        'item_codes_json',
        'procedure_reference',
        'construct_aptitude_area_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'min_raw_value' => 'decimal:6',
            'max_raw_value' => 'decimal:6',
            'precision_scale' => 'integer',
            'is_required' => 'boolean',
            'required_output_scales' => 'array',
            'input_contract_json' => 'array',
            'formula_ast_json' => 'array',
            'item_codes_json' => 'array',
        ];
    }

    /**
     * @return BelongsTo<TestInstrumentVersion, $this>
     */
    public function version(): BelongsTo
    {
        return $this->belongsTo(TestInstrumentVersion::class, 'test_instrument_version_id');
    }

    /**
     * @return BelongsTo<TestInstrumentSubtest, $this>
     */
    public function subtest(): BelongsTo
    {
        return $this->belongsTo(TestInstrumentSubtest::class, 'test_instrument_subtest_id');
    }

    /**
     * @return BelongsTo<AptitudeArea, $this>
     */
    public function construct(): BelongsTo
    {
        return $this->belongsTo(AptitudeArea::class, 'construct_aptitude_area_id');
    }

    /**
     * @return HasMany<TestInstrumentMetricInput, $this>
     */
    public function inputs(): HasMany
    {
        return $this->hasMany(TestInstrumentMetricInput::class, 'metric_id')->orderBy('position');
    }

    /**
     * @return HasMany<TestInstrumentMetricInput, $this>
     */
    public function dependents(): HasMany
    {
        return $this->hasMany(TestInstrumentMetricInput::class, 'input_metric_id');
    }
}
