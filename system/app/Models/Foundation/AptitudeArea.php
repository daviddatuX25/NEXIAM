<?php

namespace App\Models\Foundation;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AptitudeArea extends Model
{
    public const SCORING_METHOD_CONVERSION_TABLE = 'conversion_table';

    protected $fillable = [
        'code',
        'name',
        'version',
        'scoring_method',
        'max_items',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'max_items' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<ScoreConversionRow, $this>
     */
    public function conversionRows(): HasMany
    {
        return $this->hasMany(ScoreConversionRow::class)->orderBy('min_score');
    }

    /**
     * Resolve a raw score through this area's conversion table.
     *
     * Returns the first range row that covers the raw score, or null when the
     * table has no covering row (caller decides to reject).
     */
    public function convertScore(int $rawScore): ?ScoreConversionRow
    {
        return $this->conversionRows()
            ->where('min_score', '<=', $rawScore)
            ->where('max_score', '>=', $rawScore)
            ->first();
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
