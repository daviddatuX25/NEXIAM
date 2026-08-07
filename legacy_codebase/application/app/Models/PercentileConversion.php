<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PercentileConversion extends Model
{
    use HasFactory;

    protected $fillable = [
        'aptitude_area_id',
        'raw_score',
        'percentile_output',
    ];

    protected function casts(): array
    {
        return [
            'raw_score' => 'integer',
        ];
    }

    public function aptitudeArea(): BelongsTo
    {
        return $this->belongsTo(AptitudeArea::class);
    }
}
