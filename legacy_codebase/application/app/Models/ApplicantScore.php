<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicantScore extends Model
{
    use HasFactory;

    protected $fillable = [
        'grading_session_id',
        'applicant_id',
        'aptitude_area_id',
        'raw_score',
        'max_score',
        'normalized_score',
        'percentile_string',
        'scored_by',
        'scored_at',
    ];

    protected function casts(): array
    {
        return [
            'scored_at' => 'datetime',
        ];
    }

    public function gradingSession(): BelongsTo
    {
        return $this->belongsTo(GradingSession::class);
    }

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Applicant::class);
    }

    public function aptitudeArea(): BelongsTo
    {
        return $this->belongsTo(AptitudeArea::class, 'aptitude_area_id');
    }

    public function scoredByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scored_by');
    }
}
