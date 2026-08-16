<?php

namespace App\ProgramHead\Queries;

use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ProgramPool;

final class ProgramPoolHas
{
    public static function optionFor(AdmissionAttempt $attempt, int $programId): bool
    {
        return ProgramPool::query()
            ->where('admission_attempt_id', $attempt->id)
            ->whereHas('options', fn ($o) => $o->where('program_id', $programId))
            ->exists();
    }
}
