<?php

namespace App\Setup\Actions;

use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\ApplicationWindow;
use App\Models\Foundation\Institution;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UpdateAcademicCycle
{
    public function __construct(
        private readonly RecordAuditEvent $audit,
    ) {}

    /**
     * @param  array{school_year: string, semester: string|int, application_start_date?: ?string, application_end_date?: ?string}  $attributes
     */
    public function handle(AcademicCycle $cycle, array $attributes, User|string $actor): AcademicCycle
    {
        return DB::transaction(function () use ($cycle, $attributes, $actor): AcademicCycle {
            $institutionId = Institution::current()->id;
            $lockedCycle = AcademicCycle::query()
                ->where('institution_id', $institutionId)
                ->lockForUpdate()
                ->findOrFail($cycle->id);

            $schoolYear = trim((string) $attributes['school_year']);
            $semester = (string) $attributes['semester'];

            $duplicate = AcademicCycle::query()
                ->where('institution_id', $lockedCycle->institution_id)
                ->where('school_year', $schoolYear)
                ->where('semester', $semester)
                ->where('id', '!=', $lockedCycle->id)
                ->exists();

            if ($duplicate) {
                throw ValidationException::withMessages([
                    'school_year' => 'That school year and semester already exist.',
                ]);
            }

            $semesterLabel = $semester === '1' ? '1st Semester' : '2nd Semester';
            $staffName = "{$schoolYear} {$semesterLabel}";
            $start = $attributes['application_start_date'] ?? null;
            $end = $attributes['application_end_date'] ?? null;

            $lockedCycle->update([
                'school_year' => $schoolYear,
                'semester' => $semester,
                'name' => $staffName,
                'opens_on' => $start ?? $lockedCycle->opens_on,
                'closes_on' => $end ?? $lockedCycle->closes_on,
            ]);

            if ($start || $end) {
                $window = $lockedCycle->primaryWindow();
                $payload = [
                    'name' => $staffName.' application window',
                    'opens_at' => ($start ?? $lockedCycle->opens_on?->toDateString()).' 00:00:00',
                    'closes_at' => ($end ?? $lockedCycle->closes_on?->toDateString()).' 23:59:59',
                ];

                if ($window !== null) {
                    $window->update($payload);
                } else {
                    ApplicationWindow::query()->create([
                        'academic_cycle_id' => $lockedCycle->id,
                        'code' => $lockedCycle->code.'-WIN',
                        ...$payload,
                        'status' => $lockedCycle->is_active ? 'published' : 'draft',
                    ]);
                }
            }

            $actorId = is_string($actor) ? $actor : (string) $actor->id;

            $this->audit->handle('setup.cycle_upserted', [
                'cycle_id' => $lockedCycle->id,
                'code' => $lockedCycle->code,
                'school_year' => $lockedCycle->school_year,
                'semester' => $lockedCycle->semester,
            ], $actorId);

            return $lockedCycle->fresh();
        });
    }
}
