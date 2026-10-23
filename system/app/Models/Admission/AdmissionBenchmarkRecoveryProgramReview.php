<?php

namespace App\Models\Admission;

use App\Models\Foundation\Program;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

class AdmissionBenchmarkRecoveryProgramReview extends Model
{
    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new RuntimeException('Historical recovery program reviews are immutable.');
        });
        static::deleting(function (): never {
            throw new RuntimeException('Historical recovery program reviews cannot be deleted.');
        });
    }

    /** @return BelongsTo<AdmissionBenchmarkRecovery, $this> */
    public function recovery(): BelongsTo
    {
        return $this->belongsTo(AdmissionBenchmarkRecovery::class);
    }

    /** @return BelongsTo<Program, $this> */
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    /** @return BelongsTo<User, $this> */
    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by_user_id');
    }
}
