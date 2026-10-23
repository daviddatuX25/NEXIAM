<?php

namespace App\Setup\Actions;

use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\ApplicationWindow;
use App\Models\Foundation\Institution;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class ActivateAcademicCycle
{
    public function __construct(
        private readonly RecordAuditEvent $audit,
    ) {}

    public function handle(AcademicCycle $cycle, User|string $actor): AcademicCycle
    {
        return DB::transaction(function () use ($cycle, $actor): AcademicCycle {
            $institutionId = Institution::current()->id;
            $institution = Institution::query()->lockForUpdate()->findOrFail($institutionId);
            $lockedCycle = AcademicCycle::query()
                ->where('institution_id', $institutionId)
                ->lockForUpdate()
                ->findOrFail($cycle->id);

            AcademicCycle::query()
                ->where('institution_id', $institution->id)
                ->where('id', '!=', $lockedCycle->id)
                ->where('is_active', true)
                ->update(['is_active' => false]);

            $lockedCycle->update(['is_active' => true]);

            $window = $lockedCycle->primaryWindow();
            if ($window !== null) {
                ApplicationWindow::query()
                    ->where('academic_cycle_id', $lockedCycle->id)
                    ->where('id', '!=', $window->id)
                    ->where('status', 'published')
                    ->update(['status' => 'closed']);

                $window->update(['status' => 'published']);
            }

            $actorId = is_string($actor) ? $actor : (string) $actor->id;

            $this->audit->handle('foundation.cycle_activated', [
                'cycle_id' => $lockedCycle->id,
                'code' => $lockedCycle->code,
                'institution_id' => $institution->id,
            ], $actorId);

            return $lockedCycle->fresh();
        });
    }
}
