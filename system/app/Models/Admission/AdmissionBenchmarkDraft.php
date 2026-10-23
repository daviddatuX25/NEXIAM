<?php

namespace App\Models\Admission;

use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Program;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdmissionBenchmarkDraft extends Model
{
    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['current_version' => 'integer', 'feature_schema_json' => 'array'];
    }

    /** @return BelongsTo<Institution, $this> */
    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
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

    /** @return BelongsTo<AdmissionBenchmarkRecovery, $this> */
    public function recovery(): BelongsTo
    {
        return $this->belongsTo(AdmissionBenchmarkRecovery::class, 'recovery_id');
    }

    /** @return HasMany<AdmissionBenchmarkRevision, $this> */
    public function revisions(): HasMany
    {
        return $this->hasMany(AdmissionBenchmarkRevision::class, 'draft_id')->orderBy('version');
    }

    public function currentRevision(): ?AdmissionBenchmarkRevision
    {
        return $this->revisions()->where('version', $this->current_version)->first();
    }
}
