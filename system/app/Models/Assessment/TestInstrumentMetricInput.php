<?php

namespace App\Models\Assessment;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TestInstrumentMetricInput extends Model
{
    protected $fillable = [
        'test_instrument_version_id',
        'metric_id',
        'input_metric_id',
        'position',
    ];

    /**
     * @return BelongsTo<TestInstrumentVersion, $this>
     */
    public function version(): BelongsTo
    {
        return $this->belongsTo(TestInstrumentVersion::class, 'test_instrument_version_id');
    }

    /**
     * @return BelongsTo<TestInstrumentMetric, $this>
     */
    public function metric(): BelongsTo
    {
        return $this->belongsTo(TestInstrumentMetric::class, 'metric_id');
    }

    /**
     * @return BelongsTo<TestInstrumentMetric, $this>
     */
    public function inputMetric(): BelongsTo
    {
        return $this->belongsTo(TestInstrumentMetric::class, 'input_metric_id');
    }
}
