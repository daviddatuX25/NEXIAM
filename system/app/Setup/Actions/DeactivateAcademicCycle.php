<?php

namespace App\Setup\Actions;

use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\ApplicationWindow;
use App\Models\Foundation\Institution;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class DeactivateAcademicCycle
{
    public function __construct(
        private readonly RecordAuditEvent $audit,
    ) {}

    public function handle(AcademicCycle $cycle, User|string $actor): AcademicCycle
    {
        return DB::transaction(function () use ($cycle, $actor): AcademicCycle {
            $institutionId = Institution::current()->id;
            $lockedCycle = AcademicCycle::query()
                ->where('institution_id', $institutionId)
                ->lockForUpdate()
                ->findOrFail($cycle->id);

            if (! $lockedCycle->is_active) {
                throw ValidationException::withMessages([
                    'cycle' => 'Academic cycle is already inactive.',
                ]);
            }

            $lockedCycle->update(['is_active' => false]);

            ApplicationWindow::query()
                ->where('academic_cycle_id', $lockedCycle->id)
                ->where('status', 'published')
                ->update(['status' => 'closed']);

            $actorId = is_string($actor) ? $actor : (string) $actor->id;

            $this->audit->handle('foundation.cycle_deactivated', [
                'cycle_id' => $lockedCycle->id,
                'code' => $lockedCycle->code,
                'institution_id' => $lockedCycle->institution_id,
            ], $actorId);

            return $lockedCycle->fresh();
        });
    }
}
