<?php

namespace App\Models\Foundation;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScoreConversionRow extends Model
{
    protected $fillable = [
        'aptitude_area_id',
        'min_score',
        'max_score',
        'output_value',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'min_score' => 'integer',
            'max_score' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<AptitudeArea, $this>
     */
    public function aptitudeArea(): BelongsTo
    {
        return $this->belongsTo(AptitudeArea::class);
    }
}
