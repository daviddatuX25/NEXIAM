<?php

namespace App\Models\Assessment;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TestInstrumentItem extends Model
{
    protected $fillable = [
        'test_instrument_version_id',
        'test_instrument_subtest_id',
        'item_code',
        'local_number',
        'display_order',
        'is_scored',
        'option_tokens',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_scored' => 'boolean',
            'option_tokens' => 'array',
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
}
