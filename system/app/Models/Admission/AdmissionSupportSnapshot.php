<?php

namespace App\Models\Admission;

use App\Models\Foundation\AcademicCycle;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdmissionSupportSnapshot extends Model
{
    protected $guarded = ['id'];

    public $timestamps = false;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'version' => 'integer',
        ];
    }

    /** @return BelongsTo<AdmissionAttempt, $this> */
    public function admissionAttempt(): BelongsTo
    {
        return $this->belongsTo(AdmissionAttempt::class);
    }

    /** @return BelongsTo<AcademicCycle, $this> */
    public function academicCycle(): BelongsTo
    {
        return $this->belongsTo(AcademicCycle::class);
    }

    /** @return BelongsTo<AdmissionBenchmarkActivation, $this> */
    public function benchmarkActivation(): BelongsTo
    {
        return $this->belongsTo(AdmissionBenchmarkActivation::class);
    }

    /** @return BelongsTo<User, $this> */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }

    public static function latestForAttempt(int $attemptId): ?self
    {
        return self::query()
            ->where('admission_attempt_id', $attemptId)
            ->orderByDesc('version')
            ->first();
    }
}
