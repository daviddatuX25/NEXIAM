<?php

namespace App\Models\Assessment;

use App\Models\Examination\AssessmentAdministration;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $administration_id
 * @property int $exam_session_id
 * @property int $grading_session_id
 * @property array $raw_revision_vector_json
 * @property array $conversion_revision_vector_json
 * @property string $status
 * @property int|null $finalized_by_user_id
 * @property Carbon $finalized_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class AssessmentResultSet extends Model
{
    public const STATUS_FINALIZED = 'finalized';

    protected $table = 'assessment_result_sets';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'administration_id',
        'exam_session_id',
        'grading_session_id',
        'raw_revision_vector_json',
        'conversion_revision_vector_json',
        'status',
        'finalized_by_user_id',
        'finalized_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'raw_revision_vector_json' => 'array',
            'conversion_revision_vector_json' => 'array',
            'finalized_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<AssessmentAdministration, $this>
     */
    public function administration(): BelongsTo
    {
        return $this->belongsTo(AssessmentAdministration::class, 'administration_id');
    }

    /**
     * @return BelongsTo<GradingSession, $this>
     */
    public function gradingSession(): BelongsTo
    {
        return $this->belongsTo(GradingSession::class, 'grading_session_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function finalizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function finalizedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by_user_id');
    }
}
