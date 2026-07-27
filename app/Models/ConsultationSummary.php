<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsultationSummary extends Model
{
    use HasFactory;
    public const STATUS_PENDING = 'pending';
    public const STATUS_DRAFT = 'draft';
    public const STATUS_RELEASED = 'released';

    protected $fillable = [
        'applicant_id',
        'status',
        'recommended_course_id',
        'counselor_comments',
        'system_notes',
        'counselor_id',
        'released_at',
        'released_by',
    ];

    protected function casts(): array
    {
        return [
            'system_notes' => 'array',
            'released_at' => 'datetime',
        ];
    }

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Applicant::class);
    }

    public function recommendedCourse(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'recommended_course_id');
    }

    public function counselor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'counselor_id');
    }

    public function releasedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by');
    }
}
