<?php

namespace App\Setup\Queries;

use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use Illuminate\Database\Eloquent\Builder;

final class SetupSeatingApplicabilityQuery
{
    public static function applicable(Institution $institution): bool
    {
        return app(self::class)->isApplicable($institution);
    }

    public function isApplicable(Institution $institution): bool
    {
        if (! $institution->usesSeatingArrangements()) {
            return false;
        }

        $activeCycle = AcademicCycle::query()
            ->where('institution_id', $institution->id)
            ->where('is_active', true)
            ->first() ?? AcademicCycle::active();

        if ($activeCycle === null || (int) $activeCycle->institution_id !== (int) $institution->id) {
            return false;
        }

        $hasSessions = ExamSession::query()
            ->where('academic_cycle_id', $activeCycle->id)
            ->exists();

        if (! $hasSessions) {
            return false;
        }

        return ExamSession::query()
            ->where('academic_cycle_id', $activeCycle->id)
            ->where(function (Builder $query): void {
                $query->where(function (Builder $draftQuery): void {
                    $draftQuery->where('status', ExamSession::STATUS_DRAFT)
                        ->where('seating_mode', ExamSession::SEATING_MODE_ASSIGNED_SEATING);
                })->orWhere(function (Builder $operationalQuery): void {
                    $operationalQuery->whereIn('status', [
                        ExamSession::STATUS_PUBLISHED,
                        ExamSession::STATUS_IN_PROGRESS,
                    ])->whereHas('currentMaterialSnapshot', function (Builder $snapshotQuery): void {
                        $snapshotQuery->where('seating_mode', ExamSession::SEATING_MODE_ASSIGNED_SEATING);
                    });
                });
            })
            ->exists();
    }
}
