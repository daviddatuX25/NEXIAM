<?php

namespace App\Models\Assessment;

use App\Assessment\Support\GradingRoster;
use App\Models\Examination\ExamSession;
use App\Models\User;
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
        'opened_by_user_id',
        'finalized_by_user_id',
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
     * @return BelongsTo<User, $this>
     */
    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function finalizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by_user_id');
    }

    /**
     * @return HasMany<ScoreRecord, $this>
     */
    public function scoreRecords(): HasMany
    {
        return $this->hasMany(ScoreRecord::class);
    }

    /**
     * @return HasMany<OmrCaptureBatch, $this>
     */
    public function omrCaptureBatches(): HasMany
    {
        return $this->hasMany(OmrCaptureBatch::class);
    }

    /**
     * @return HasMany<AssessmentResultSet, $this>
     */
    public function assessmentResultSets(): HasMany
    {
        return $this->hasMany(AssessmentResultSet::class, 'grading_session_id');
    }

    /**
     * Resolve the distinct admission attempt IDs belonging to this grading session
     * across finalized result sets, eligible exam roster sittings, and legacy score records.
     *
     * @return list<int>
     */
    public function scoredAttemptIds(): array
    {
        $resultSetAttemptIds = $this->relationLoaded('assessmentResultSets')
            ? $this->assessmentResultSets
                ->map(fn (AssessmentResultSet $rs): ?int => $rs->administration?->admission_attempt_id)
                ->filter()
                ->all()
            : AssessmentResultSet::query()
                ->where('grading_session_id', $this->id)
                ->with('administration:id,admission_attempt_id')
                ->get()
                ->map(fn (AssessmentResultSet $rs): ?int => $rs->administration?->admission_attempt_id)
                ->filter()
                ->all();

        $rosterAttemptIds = $this->exam_session_id === null
            ? []
            : GradingRoster::queryFor($this)
                ->pluck('admission_attempt_id')
                ->all();

        $legacyScoreAttemptIds = $this->relationLoaded('scoreRecords')
            ? $this->scoreRecords->pluck('admission_attempt_id')->all()
            : $this->scoreRecords()->pluck('admission_attempt_id')->all();

        return array_values(array_unique(array_map('intval', array_filter([
            ...$resultSetAttemptIds,
            ...$rosterAttemptIds,
            ...$legacyScoreAttemptIds,
        ]))));
    }
}
