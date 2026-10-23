<?php

namespace App\Admission\Support;

use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\AdmissionReferenceCounter;
use App\Models\Foundation\AcademicCycle;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Collision-safe reference generator (INV-003). Retries on unique violation
 * and advances past occupied AY<YY>- sequences across multi-semester cycles.
 */
final class ReferenceCodeGenerator
{
    public function next(AcademicCycle $cycle, int $maxAttempts = 5): string
    {
        $yy = ApplicantReference::cycleYearTwoDigits($cycle);
        $prefix = "AY{$yy}-";

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $candidate = DB::transaction(function () use ($cycle, $prefix): string {
                $counter = AdmissionReferenceCounter::query()
                    ->where('academic_cycle_id', $cycle->id)
                    ->lockForUpdate()
                    ->first();

                if ($counter === null) {
                    $maxOccupied = $this->maxOccupiedSequence($prefix);
                    $seq = $maxOccupied + 1;
                    AdmissionReferenceCounter::query()->create([
                        'academic_cycle_id' => $cycle->id,
                        'next_value' => $seq + 1,
                    ]);

                    return ApplicantReference::format($cycle, $seq);
                }

                $seq = (int) $counter->next_value;
                $candidate = ApplicantReference::format($cycle, $seq);

                if (AdmissionAttempt::query()->where('reference_code', $candidate)->exists()) {
                    $seq = max($seq, $this->maxOccupiedSequence($prefix)) + 1;
                    $candidate = ApplicantReference::format($cycle, $seq);
                }

                $counter->update(['next_value' => $seq + 1]);

                return $candidate;
            });

            if (! AdmissionAttempt::query()->where('reference_code', $candidate)->exists()) {
                return $candidate;
            }
        }

        throw new RuntimeException('Unable to allocate admission reference code.');
    }

    private function maxOccupiedSequence(string $prefix): int
    {
        $existingCodes = AdmissionAttempt::query()
            ->where('reference_code', 'like', $prefix.'%')
            ->pluck('reference_code');

        $max = 0;
        foreach ($existingCodes as $code) {
            $seq = ApplicantReference::extractSequence((string) $code);
            if ($seq !== null && $seq > $max) {
                $max = $seq;
            }
        }

        return $max;
    }
}
