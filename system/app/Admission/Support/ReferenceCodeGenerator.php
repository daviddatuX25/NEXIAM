<?php

namespace App\Admission\Support;

use App\Models\Admission\AdmissionReferenceCounter;
use App\Models\Foundation\AcademicCycle;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Collision-safe reference generator (INV-003). Retries on unique violation.
 * Replaces legacy count()+1 race.
 */
final class ReferenceCodeGenerator
{
    public function next(AcademicCycle $cycle, int $maxAttempts = 5): string
    {
        $year = substr((string) $cycle->opens_on, 0, 4);
        if ($year === '' || $year === '0') {
            $year = (string) now()->year;
        }

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $seq = DB::transaction(function () use ($cycle): int {
                $counter = AdmissionReferenceCounter::query()
                    ->where('academic_cycle_id', $cycle->id)
                    ->lockForUpdate()
                    ->first();

                if ($counter === null) {
                    $counter = AdmissionReferenceCounter::query()->create([
                        'academic_cycle_id' => $cycle->id,
                        'next_value' => 2,
                    ]);

                    return 1;
                }

                $value = $counter->next_value;
                $counter->update(['next_value' => $value + 1]);

                return $value;
            });

            return sprintf('APP-%s-%05d', $year, $seq);
        }

        throw new RuntimeException('Unable to allocate admission reference code.');
    }
}
