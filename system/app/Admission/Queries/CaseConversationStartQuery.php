<?php

namespace App\Admission\Queries;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\EnrollmentStamp;
use App\Models\Admission\ProgramFollowUp;
use App\Models\Admission\ProgramSelection;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Program;
use App\Models\User;
use App\Support\Search\TokenizedInnerMatchScope;
use Illuminate\Database\Eloquent\Builder;

final class CaseConversationStartQuery
{
    public function __construct(private readonly CapabilityAuthorizer $authorizer) {}

    /** @return array{programs:list<array{code:string,name:string}>,cycle_code:string,lookup_url:string}|null */
    public function context(User $actor): ?array
    {
        $freshActor = User::query()->find($actor->id);
        $profile = $freshActor?->staffProfile()->first();
        $scope = $freshActor?->actorScope();
        $cycleCode = $scope?->activeCycleCode;
        $cycle = $profile?->institution_id !== null && is_string($cycleCode) && $cycleCode !== ''
            ? AcademicCycle::query()->where('institution_id', $profile->institution_id)->where('code', $cycleCode)->where('is_active', true)->first()
            : null;

        if ($freshActor === null || $freshActor->applicantAccount()->exists() || $profile === null
            || ! $profile->isActive() || $profile->deactivated_at !== null || $profile->institution_id === null
            || $scope?->office !== 'program' || $scope->programCodes === [] || $cycle === null
            || (int) $cycle->institution_id !== (int) $profile->institution_id
            || ! $this->authorizer->decideFresh($freshActor, CapabilityCatalog::RECORD_THREAD_PARTICIPATE, ['institution_id' => $profile->institution_id])->allowed) {
            return null;
        }

        $programs = Program::query()->where('institution_id', $profile->institution_id)->where('is_active', true)
            ->whereIn('code', $scope->programCodes)->orderBy('name')->get(['code', 'name'])
            ->filter(fn (Program $program): bool => $this->canStartForProgram($freshActor, (int) $profile->institution_id, (string) $cycle->code, (string) $program->code))
            ->map(fn (Program $program): array => ['code' => (string) $program->code, 'name' => (string) $program->name])
            ->values()->all();

        return $programs === [] ? null : [
            'programs' => $programs,
            'cycle_code' => (string) $cycle->code,
            'lookup_url' => route('staff.inquiries.case-applicants', [], false),
        ];
    }

    /** @return array{data:list<array{id:string,name:string,reference_code:string,cycle_code:string}>,has_more:bool,program_code:string,cycle_code:string} */
    public function applicants(User $actor, string $programCode, string $cycleCode, ?string $search): array
    {
        $freshActor = User::query()->find($actor->id);
        $profile = $freshActor?->staffProfile()->first();
        $scope = $freshActor?->actorScope();
        $cycle = AcademicCycle::query()->where('institution_id', $profile?->institution_id)
            ->where('code', $cycleCode)->where('is_active', true)->first();
        $program = Program::query()->where('institution_id', $profile?->institution_id)
            ->where('code', $programCode)->where('is_active', true)->first();

        abort_unless($freshActor !== null && ! $freshActor->applicantAccount()->exists() && $profile !== null
            && $profile->isActive() && $profile->deactivated_at === null && $profile->institution_id !== null
            && $scope?->office === 'program' && $scope->activeCycleCode === $cycleCode
            && in_array($programCode, $scope->programCodes, true) && $cycle !== null && $program !== null
            && (int) $cycle->institution_id === (int) $profile->institution_id
            && (int) $program->institution_id === (int) $profile->institution_id
            && $this->canStartForProgram($freshActor, (int) $profile->institution_id, $cycleCode, $programCode), 404);

        $query = AdmissionAttempt::query()->forWorkspace()
            ->join('applicant_identities', 'applicant_identities.id', '=', 'admission_attempts.applicant_identity_id')
            ->where('admission_attempts.institution_id', $profile->institution_id)
            ->where('admission_attempts.academic_cycle_id', $cycle->id)
            ->where('admission_attempts.status', AdmissionAttempt::STATUS_ACCEPTED)
            ->where(function (Builder $eligibility) use ($program): void {
                $eligibility
                    ->whereExists(fn ($release) => $release->selectRaw('1')->from('result_releases')
                        ->whereColumn('result_releases.admission_attempt_id', 'admission_attempts.id')
                        ->where('result_releases.is_current', true))
                    ->orWhereExists(fn ($followUp) => $followUp->selectRaw('1')->from('admission_attempt_program_follow_ups')
                        ->whereColumn('admission_attempt_program_follow_ups.admission_attempt_id', 'admission_attempts.id')
                        ->where('admission_attempt_program_follow_ups.program_id', $program->id)
                        ->whereIn('admission_attempt_program_follow_ups.status', ProgramFollowUp::OPEN_STATUSES));
            })
            ->whereNotExists(function ($enrolled): void {
                $enrolled->selectRaw('1')->from('admission_attempt_enrollment_stamps as enrolled_stamp')
                    ->whereColumn('enrolled_stamp.admission_attempt_id', 'admission_attempts.id')
                    ->where('enrolled_stamp.stamp_type', EnrollmentStamp::STAMP_ENROLLED)
                    ->whereNotExists(fn ($newer) => $newer->selectRaw('1')->from('admission_attempt_enrollment_stamps as newer_stamp')
                        ->whereColumn('newer_stamp.admission_attempt_id', 'enrolled_stamp.admission_attempt_id')
                        ->whereColumn('newer_stamp.id', '>', 'enrolled_stamp.id'));
            })
            ->where(function (Builder $relationship) use ($program): void {
                $relationship->whereExists(function ($selection) use ($program): void {
                    $statuses = ProgramSelection::NON_TERMINAL_STATUSES;
                    $selection->selectRaw('1')->from('admission_attempt_program_selections')
                        ->whereColumn('admission_attempt_program_selections.admission_attempt_id', 'admission_attempts.id')
                        ->where('admission_attempt_program_selections.program_id', $program->id)
                        ->whereIn('admission_attempt_program_selections.status', $statuses)
                        ->whereRaw('admission_attempt_program_selections.id = (SELECT MAX(current_selection.id) FROM admission_attempt_program_selections AS current_selection WHERE current_selection.admission_attempt_id = admission_attempts.id AND current_selection.status IN ('.implode(',', array_fill(0, count($statuses), '?')).'))', $statuses);
                })->orWhereExists(fn ($followUp) => $followUp->selectRaw('1')->from('admission_attempt_program_follow_ups')
                    ->whereColumn('admission_attempt_program_follow_ups.admission_attempt_id', 'admission_attempts.id')
                    ->where('admission_attempt_program_follow_ups.program_id', $program->id)
                    ->whereIn('admission_attempt_program_follow_ups.status', ProgramFollowUp::OPEN_STATUSES));
            })
            ->where(function (Builder $practice) use ($freshActor): void {
                $practice->whereNull('admission_attempts.practice_fixture_set_id')->orWhereExists(function ($set) use ($freshActor): void {
                    $set->selectRaw('1')->from('practice_fixture_sets')
                        ->whereColumn('practice_fixture_sets.id', 'admission_attempts.practice_fixture_set_id')
                        ->whereColumn('practice_fixture_sets.institution_id', 'admission_attempts.institution_id')
                        ->whereColumn('practice_fixture_sets.academic_cycle_id', 'admission_attempts.academic_cycle_id')
                        ->where('practice_fixture_sets.status', 'active')
                        ->where('practice_fixture_sets.actor_bindings->academic-leader', (int) $freshActor->id)
                        ->whereExists(fn ($identity) => $identity->selectRaw('1')->from('applicant_identities')
                            ->whereColumn('applicant_identities.id', 'admission_attempts.applicant_identity_id')
                            ->whereColumn('applicant_identities.practice_fixture_set_id', 'practice_fixture_sets.id'));
                });
            });

        TokenizedInnerMatchScope::apply($query, $search, [
            'applicant_identities.first_name',
            'applicant_identities.middle_name',
            'applicant_identities.last_name',
            'applicant_identities.suffix',
            'admission_attempts.reference_code',
        ]);

        $rows = $query->orderBy('applicant_identities.last_name')->orderBy('applicant_identities.first_name')->orderBy('admission_attempts.id')
            ->limit(21)->get([
                'admission_attempts.id', 'admission_attempts.reference_code',
                'applicant_identities.first_name', 'applicant_identities.middle_name',
                'applicant_identities.last_name', 'applicant_identities.suffix',
            ]);

        return [
            'data' => $rows->take(20)->map(fn ($row): array => [
                'id' => (string) $row->id,
                'name' => trim(implode(' ', array_filter([$row->first_name, $row->middle_name, $row->last_name, $row->suffix]))),
                'reference_code' => (string) $row->reference_code,
                'cycle_code' => $cycleCode,
            ])->values()->all(),
            'has_more' => $rows->count() > 20,
            'program_code' => $programCode,
            'cycle_code' => $cycleCode,
        ];
    }

    private function canStartForProgram(User $actor, int $institutionId, string $cycleCode, string $programCode): bool
    {
        foreach ([CapabilityCatalog::PROGRAM_HEAD_VIEW, CapabilityCatalog::PROGRAM_HEAD_MESSAGE, CapabilityCatalog::RECORD_THREAD_PARTICIPATE] as $capability) {
            if (! $this->authorizer->decideFresh($actor, $capability, [
                'institution_id' => $institutionId,
                'program' => $programCode,
                'cycle' => $cycleCode,
            ])->allowed) {
                return false;
            }
        }

        return true;
    }
}
