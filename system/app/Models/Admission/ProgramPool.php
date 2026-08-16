<?php

namespace App\Models\Admission;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Immutable Guidance program pool version header (D-090). The highest committed
 * version per attempt is current; later versions reference a predecessor.
 */
class ProgramPool extends Model
{
    protected $table = 'admission_attempt_program_pools';

    public $timestamps = false;

    protected $fillable = [
        'admission_attempt_id',
        'version',
        'predecessor_id',
        'actor_user_id',
        'reason',
        'source',
        'idempotency_key',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<AdmissionAttempt, $this>
     */
    public function attempt(): BelongsTo
    {
        return $this->belongsTo(AdmissionAttempt::class, 'admission_attempt_id');
    }

    /**
     * @return BelongsTo<ProgramPool, $this>
     */
    public function predecessor(): BelongsTo
    {
        return $this->belongsTo(self::class, 'predecessor_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    /**
     * @return HasMany<ProgramPoolOption, $this>
     */
    public function options(): HasMany
    {
        return $this->hasMany(ProgramPoolOption::class, 'program_pool_id')->orderBy('display_order');
    }

    public static function currentFor(int $attemptId): ?self
    {
        return self::query()
            ->where('admission_attempt_id', $attemptId)
            ->orderByDesc('version')
            ->first();
    }
}
