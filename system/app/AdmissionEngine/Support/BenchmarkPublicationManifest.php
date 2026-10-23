<?php

namespace App\AdmissionEngine\Support;

use App\Foundation\Support\TagudinUndergraduateCatalog;
use App\Models\Admission\AdmissionBenchmarkRecovery;
use App\Models\Admission\AdmissionBenchmarkSet;

final class BenchmarkPublicationManifest
{
    /** @return list<int> */
    public static function retainedProgramIds(AdmissionBenchmarkSet $base, TagudinUndergraduateCatalog $catalog, ?AdmissionBenchmarkRecovery $recovery = null): array
    {
        $base->loadMissing('exemplars.program');
        $retiredCodes = $catalog->retiredCodes();
        $ids = [];

        foreach ($base->exemplars as $exemplar) {
            if ($exemplar->program !== null && ! in_array((string) $exemplar->program->code, $retiredCodes, true)) {
                $ids[(int) $exemplar->program_id] = (int) $exemplar->program_id;
            }
        }
        if ($recovery !== null) {
            foreach ($recovery->programReviews()->pluck('program_id') as $programId) {
                $ids[(int) $programId] = (int) $programId;
            }
        }

        sort($ids);

        return array_values($ids);
    }
}
