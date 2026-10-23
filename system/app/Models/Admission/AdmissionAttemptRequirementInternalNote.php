<?php

namespace App\Models\Admission;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdmissionAttemptRequirementInternalNote extends Model
{
    protected $fillable = [
        'admission_attempt_id',
        'requirement_item_id',
        'recorded_by_user_id',
        'recorded_at',
        'internal_remarks',
        'predecessor_id',
        'idempotency_key',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'recorded_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }

    /** @return BelongsTo<AdmissionAttemptRequirementItem, $this> */
    public function requirementItem(): BelongsTo
    {
        return $this->belongsTo(AdmissionAttemptRequirementItem::class, 'requirement_item_id');
    }

    /** @return BelongsTo<AdmissionAttempt, $this> */
    public function attempt(): BelongsTo
    {
        return $this->belongsTo(AdmissionAttempt::class, 'admission_attempt_id');
    }

    /** @return BelongsTo<self, $this> */
    public function predecessor(): BelongsTo
    {
        return $this->belongsTo(self::class, 'predecessor_id');
    }
}
