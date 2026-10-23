<?php

namespace App\Models\Admission;

use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use RuntimeException;

class AdmissionBenchmarkRecovery extends Model
{
    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new RuntimeException('Historical benchmark recovery snapshots are immutable.');
        });
        static::deleting(function (): never {
            throw new RuntimeException('Historical benchmark recovery snapshots cannot be deleted.');
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'source_version' => 'integer',
            'source_activation_sequence' => 'integer',
            'source_snapshot_json' => 'array',
            'source_activation_snapshot_json' => 'array',
        ];
    }

    /** @return BelongsTo<AdmissionBenchmarkSet, $this> */
    public function sourceSet(): BelongsTo
    {
        return $this->belongsTo(AdmissionBenchmarkSet::class, 'source_benchmark_set_id');
    }

    /** @return BelongsTo<AdmissionBenchmarkActivation, $this> */
    public function sourceActivation(): BelongsTo
    {
        return $this->belongsTo(AdmissionBenchmarkActivation::class, 'source_activation_id');
    }

    /** @return BelongsTo<AcademicCycle, $this> */
    public function academicCycle(): BelongsTo
    {
        return $this->belongsTo(AcademicCycle::class);
    }

    /** @return BelongsTo<Institution, $this> */
    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** @return HasMany<AdmissionBenchmarkDraft, $this> */
    public function drafts(): HasMany
    {
        return $this->hasMany(AdmissionBenchmarkDraft::class, 'recovery_id');
    }

    /** @return HasMany<AdmissionBenchmarkRecoveryProgramReview, $this> */
    public function programReviews(): HasMany
    {
        return $this->hasMany(AdmissionBenchmarkRecoveryProgramReview::class, 'recovery_id');
    }
}
