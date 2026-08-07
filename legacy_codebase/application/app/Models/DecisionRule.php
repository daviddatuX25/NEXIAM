<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DecisionRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'course_id',
        'aptitude_area_id',
        'min_score',
        'max_score',
        'note',
        'created_by',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'min_score' => 'float',
            'max_score' => 'float',
            'is_active' => 'boolean',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function aptitudeArea(): BelongsTo
    {
        return $this->belongsTo(AptitudeArea::class);
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
