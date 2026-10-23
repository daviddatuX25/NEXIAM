<?php

namespace App\Models\Admission;

use App\Models\Foundation\Program;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdmissionClearanceReceipt extends Model
{
    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_REVOKED = 'revoked';

    protected $fillable = [
        'admission_attempt_id',
        'program_id',
        'receipt_number',
        'status',
        'evidence_vector',
        'confirmed_by_user_id',
        'confirmed_at',
        'revoked_by_user_id',
        'revoked_at',
        'revocation_reason',
        'idempotency_key',
        'predecessor_receipt_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'evidence_vector' => 'array',
            'confirmed_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function predecessor(): BelongsTo
    {
        return $this->belongsTo(self::class, 'predecessor_receipt_id');
    }

    public function successor(): HasMany
    {
        return $this->hasMany(self::class, 'predecessor_receipt_id');
    }

    public static function latestForAttempt(int $attemptId): ?self
    {
        return self::query()->where('admission_attempt_id', $attemptId)->latest('id')->first();
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_CONFIRMED);
    }

    /**
     * @return BelongsTo<AdmissionAttempt, $this>
     */
    public function admissionAttempt(): BelongsTo
    {
        return $this->belongsTo(AdmissionAttempt::class, 'admission_attempt_id');
    }

    /**
     * @return BelongsTo<Program, $this>
     */
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class, 'program_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by_user_id');
    }

    /**
     * @return HasMany<EnrollmentSheetHandoffReceipt, $this>
     */
    public function sheetHandoffReceipts(): HasMany
    {
        return $this->hasMany(EnrollmentSheetHandoffReceipt::class, 'admission_clearance_receipt_id');
    }
}
