<?php

namespace App\Models\Admission;

use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdmissionBenchmarkActivation extends Model
{
    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['sequence' => 'integer', 'activated_at' => 'datetime', 'superseded_at' => 'datetime', 'expected_current_activation_id' => 'integer'];
    }

    /** @return BelongsTo<AdmissionBenchmarkSet, $this> */
    public function benchmarkSet(): BelongsTo
    {
        return $this->belongsTo(AdmissionBenchmarkSet::class, 'benchmark_set_id');
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

    public static function currentForCycle(int $academicCycleId): ?self
    {
        return self::query()
            ->where('academic_cycle_id', $academicCycleId)
            ->whereNull('superseded_at')
            ->orderByDesc('sequence')
            ->first();
    }
}
