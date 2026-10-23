<?php

namespace App\ProgramHead\Queries;

use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ProgramFollowUp;
use App\Models\Foundation\Program;
use Illuminate\Database\Eloquent\Builder;

final class PendingProgramReviewQuery
{
    public static function countFor(Program $program, ?int $cycleId): int
    {
        if ($cycleId === null || ! $program->is_active) {
            return 0;
        }

        return ProgramFollowUp::query()
            ->where('program_id', $program->id)
            ->whereIn('status', ProgramFollowUp::OPEN_STATUSES)
            ->whereHas('program', fn (Builder $query) => $query
                ->where('institution_id', $program->institution_id)
                ->where('is_active', true))
            ->whereHas('attempt', fn (Builder $query) => $query
                ->forWorkspace()
                ->where('institution_id', $program->institution_id)
                ->where('academic_cycle_id', $cycleId)
                ->where('status', AdmissionAttempt::STATUS_ACCEPTED)
                ->whereHas('academicCycle', fn (Builder $cycle) => $cycle
                    ->where('institution_id', $program->institution_id)
                    ->where('is_active', true)))
            ->count();
    }
}
