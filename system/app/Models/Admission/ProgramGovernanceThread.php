<?php

namespace App\Models\Admission;

use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Program;
use App\Models\Foundation\ProgramQuotaChange;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProgramGovernanceThread extends Model
{
    public const TOPIC_BENCHMARK = 'benchmark';

    public const TOPIC_QUOTA = 'quota';

    public const TOPIC_ADVISING = 'advising';

    public const STATUS_OPEN = 'open';

    public const STATUS_READ_ONLY = 'read_only';

    public const STATUS_CLOSED = 'closed';

    public const TOPICS = [self::TOPIC_BENCHMARK, self::TOPIC_QUOTA, self::TOPIC_ADVISING];

    public const STATUSES = [self::STATUS_OPEN, self::STATUS_READ_ONLY, self::STATUS_CLOSED];

    protected $fillable = [
        'institution_id',
        'program_id',
        'academic_cycle_id',
        'topic',
        'subject',
        'status',
        'created_by_user_id',
        'admission_benchmark_revision_id',
        'quota_change_id',
        'created_at',
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Program, $this> */
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    /** @return BelongsTo<AcademicCycle, $this> */
    public function academicCycle(): BelongsTo
    {
        return $this->belongsTo(AcademicCycle::class);
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** @return BelongsTo<AdmissionBenchmarkRevision, $this> */
    public function benchmarkRevision(): BelongsTo
    {
        return $this->belongsTo(AdmissionBenchmarkRevision::class, 'admission_benchmark_revision_id');
    }

    /** @return BelongsTo<ProgramQuotaChange, $this> */
    public function quotaChange(): BelongsTo
    {
        return $this->belongsTo(ProgramQuotaChange::class, 'quota_change_id');
    }

    /** @return HasMany<ProgramGovernanceMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(ProgramGovernanceMessage::class, 'program_governance_thread_id');
    }

    /** @return HasMany<ProgramGovernanceRead, $this> */
    public function readMarkers(): HasMany
    {
        return $this->hasMany(ProgramGovernanceRead::class, 'program_governance_thread_id');
    }
}
