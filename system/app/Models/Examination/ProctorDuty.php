<?php

namespace App\Models\Examination;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Retained duty history only. Even a stored "active" status grants no current exam access.
 */
class ProctorDuty extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_REVOKED = 'revoked';

    protected $fillable = [
        'exam_session_id',
        'user_id',
        'status',
        'idempotency_key',
        'assigned_at',
        'revoked_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'revoked_at' => 'datetime',
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
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
