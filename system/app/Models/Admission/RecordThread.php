<?php

namespace App\Models\Admission;

use App\Models\Foundation\Program;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Server-owned thread (D-092): internal PH<->Guidance case thread, or bounded
 * applicant-support thread gated by current selection/targeted follow-up.
 */
class RecordThread extends Model
{
    protected $table = 'record_threads';

    public const TYPE_INTERNAL_PH_GUIDANCE = 'internal_ph_guidance';

    public const TYPE_APPLICANT_SUPPORT = 'applicant_support';

    public const STATUS_OPEN = 'open';

    public const STATUS_READ_ONLY = 'read_only';

    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'admission_attempt_id',
        'program_id',
        'thread_type',
        'subject',
        'status',
        'created_by_user_id',
        'latest_message_sequence',
        'created_at',
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'latest_message_sequence' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    /**
     * @return BelongsTo<AdmissionAttempt, $this>
     */
    public function attempt(): BelongsTo
    {
        return $this->belongsTo(AdmissionAttempt::class, 'admission_attempt_id');
    }

    /**
     * @return BelongsTo<Program, $this>
     */
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return HasMany<RecordThreadMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(RecordThreadMessage::class, 'record_thread_id')->orderBy('created_at');
    }
}
