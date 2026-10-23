<?php

namespace App\Models\Assessment;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TestInstrumentSubtest extends Model
{
    protected $fillable = [
        'test_instrument_version_id',
        'code',
        'name',
        'display_order',
        'response_mode',
        'item_order_policy',
    ];

    /**
     * @return BelongsTo<TestInstrumentVersion, $this>
     */
    public function version(): BelongsTo
    {
        return $this->belongsTo(TestInstrumentVersion::class, 'test_instrument_version_id');
    }

    /**
     * @return HasMany<TestInstrumentItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(TestInstrumentItem::class, 'test_instrument_subtest_id')->orderBy('display_order');
    }

    /**
     * @return HasMany<TestInstrumentMetric, $this>
     */
    public function metrics(): HasMany
    {
        return $this->hasMany(TestInstrumentMetric::class, 'test_instrument_subtest_id');
    }
}
