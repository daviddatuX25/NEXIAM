<?php

namespace App\Setup\Actions;

use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\ApplicationWindow;
use App\Models\Foundation\Institution;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CreateAcademicCycle
{
    public function __construct(
        private readonly RecordAuditEvent $audit,
    ) {}

    /**
     * @param  array{school_year: string, semester: string|int, application_start_date?: ?string, application_end_date?: ?string}  $attributes
     */
    public function handle(array $attributes, User|string $actor, ?Institution $institution = null): AcademicCycle
    {
        return DB::transaction(function () use ($attributes, $actor, $institution): AcademicCycle {
            $currentInstitution = $institution ?? Institution::current();
            $lockedInstitution = Institution::query()->lockForUpdate()->findOrFail($currentInstitution->id);

            $schoolYear = trim((string) $attributes['school_year']);
            $semester = (string) $attributes['semester'];

            $exists = AcademicCycle::query()
                ->where('institution_id', $lockedInstitution->id)
                ->where('school_year', $schoolYear)
                ->where('semester', $semester)
                ->exists();

            if ($exists) {
                throw ValidationException::withMessages([
                    'school_year' => 'That school year and semester already exist.',
                ]);
            }

            $semesterLabel = $semester === '1' ? '1st Semester' : '2nd Semester';
            $staffName = "{$schoolYear} {$semesterLabel}";
            $code = 'AY-'.strtoupper(str_replace(' ', '', $schoolYear)).'-S'.$semester;

            $start = $attributes['application_start_date'] ?? null;
            $end = $attributes['application_end_date'] ?? null;

            $cycle = AcademicCycle::query()->create([
                'institution_id' => $lockedInstitution->id,
                'code' => $code,
                'name' => $staffName,
                'school_year' => $schoolYear,
                'semester' => $semester,
                'opens_on' => $start ?? now()->toDateString(),
                'closes_on' => $end ?? now()->addMonths(6)->toDateString(),
                'is_active' => false,
            ]);

            if ($start || $end) {
                ApplicationWindow::query()->create([
                    'academic_cycle_id' => $cycle->id,
                    'code' => $code.'-WIN',
                    'name' => $staffName.' application window',
                    'opens_at' => ($start ?? now()->toDateString()).' 00:00:00',
                    'closes_at' => ($end ?? now()->addMonths(6)->toDateString()).' 23:59:59',
                    'status' => 'draft',
                ]);
            }

            $actorId = is_string($actor) ? $actor : (string) $actor->id;

            $this->audit->handle('setup.cycle_upserted', [
                'cycle_id' => $cycle->id,
                'code' => $cycle->code,
                'school_year' => $cycle->school_year,
                'semester' => $cycle->semester,
            ], $actorId);

            return $cycle;
        });
    }
}
