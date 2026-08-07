<?php

namespace App\Models\Assessment;

use App\Models\Examination\ExamSession;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GradingSession extends Model
{
    public const STATUS_OPEN = 'open';

    public const STATUS_REVIEW = 'review';

    public const STATUS_FINALIZED = 'finalized';

    protected $fillable = [
        'exam_session_id',
        'status',
        'version',
        'opened_at',
        'finalized_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'finalized_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ExamSession, $this>
     */
    public function examSession(): BelongsTo
    {
        return $this->belongsTo(ExamSession::class);
    }

    /**
     * @return HasMany<ScoreRecord, $this>
     */
    public function scoreRecords(): HasMany
    {
        return $this->hasMany(ScoreRecord::class);
    }
}
