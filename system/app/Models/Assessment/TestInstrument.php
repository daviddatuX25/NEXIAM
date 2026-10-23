<?php

namespace App\Models\Assessment;

use App\Models\Foundation\Institution;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TestInstrument extends Model
{
    protected $fillable = [
        'institution_id',
        'code',
        'current_approved_version_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_synthetic' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Institution, $this>
     */
    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    /**
     * @return HasMany<TestInstrumentVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(TestInstrumentVersion::class)->orderByDesc('version_number');
    }

    /**
     * @return HasOne<TestInstrumentVersion, $this>
     */
    public function draftVersion(): HasOne
    {
        return $this->hasOne(TestInstrumentVersion::class, 'test_instrument_id')
            ->where('status', TestInstrumentVersion::STATUS_DRAFT);
    }

    /**
     * @return BelongsTo<TestInstrumentVersion, $this>
     */
    public function currentApprovedVersion(): BelongsTo
    {
        return $this->belongsTo(TestInstrumentVersion::class, 'current_approved_version_id');
    }
}
