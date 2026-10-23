<?php

namespace App\Admission\Queries;

use App\Assessment\Support\GradingRoster;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Consultation\Services\JitsiMediaAdapter;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ProgramPool;
use App\Models\Admission\ProgramSelection;
use App\Models\Consultation\ConsultationEncounter;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\Program;
use App\Models\Release\ResultDocument;
use App\Models\User;
use App\ProgramHead\Support\ProtectedViewGuard;
use App\Scheduling\Queries\SchedulingWaitlistQuery;
use Illuminate\Database\Eloquent\Builder;

final class ApplicantActionMenuQuery
{
    public function __construct(private readonly CapabilityAuthorizer $authorizer) {}

    /** @return Builder<AdmissionAttempt> */
    public function visibleAttempts(User $actor): Builder
    {
        $scope = $actor->actorScope();
        $profile = $actor->staffProfile()->first();
        $query = AdmissionAttempt::query()->forWorkspace()
            ->where('institution_id', $scope->institutionId)
            ->whereHas('academicCycle', fn (Builder $cycle) => $cycle->where('institution_id', $scope->institutionId));

        if ($profile === null || ! $profile->isActive() || $profile->deactivated_at !== null
            || ! $this->authorizer->allows($actor, CapabilityCatalog::APPLICANT_QR_RESOLVE)) {
            return $query->whereRaw('1 = 0');
        }

        if ($scope->office === 'registrar') {
            return $this->authorizer->allowsAny($actor, [
                CapabilityCatalog::REGISTRAR_APPLICATIONS_VIEW,
                CapabilityCatalog::REGISTRAR_ENROLLMENT_ELIGIBILITY,
            ]) ? $query : $query->whereRaw('1 = 0');
        }

        $query->whereHas('academicCycle', fn (Builder $cycle) => $cycle
            ->where('is_active', true)->where('code', $scope->activeCycleCode));

        if ($scope->office === 'guidance') {
            if ($this->authorizer->allowsAny($actor, [
                CapabilityCatalog::GUIDANCE_CONSULTATION_DECIDE,
                CapabilityCatalog::GUIDANCE_SELECTION_MANAGE,
            ])) {
                return $query;
            }
            if ($this->authorizer->allows($actor, CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE)) {
                return $query->where('status', AdmissionAttempt::STATUS_ACCEPTED);
            }
            if ($this->authorizer->allows($actor, CapabilityCatalog::GUIDANCE_CONSULTATION_PLAN)) {
                return $query->where('status', AdmissionAttempt::STATUS_ACCEPTED)->where(function (Builder $planning): void {
                    $planning->whereHas('consultationAssignments', fn (Builder $assignment) => $assignment->whereIn('status', ['assigned', 'consulted']))
                        ->orWhereDoesntHave('consultationDecisions', fn (Builder $decision) => $decision->whereIn('decision_type', ['approve', 'modify', 'override']));
                });
            }
            if ($this->authorizer->allowsAny($actor, [
                CapabilityCatalog::GUIDANCE_SCORING_MANUAL,
                CapabilityCatalog::GUIDANCE_SCORING_OMR,
                CapabilityCatalog::GUIDANCE_RESULTS_PREPARE,
                CapabilityCatalog::GUIDANCE_RESULTS_RELEASE,
            ])) {
                if (! $this->authorizer->allowsAny($actor, [CapabilityCatalog::GUIDANCE_SCORING_MANUAL, CapabilityCatalog::GUIDANCE_SCORING_OMR])) {
                    $query->whereHas('resultDocuments', fn (Builder $document) => $document
                        ->whereColumn('result_documents.id', 'admission_attempts.current_result_document_id')->where('status', ResultDocument::STATUS_READY));
                }

                return $query->whereHas('examAssignments', fn (Builder $assignment) => $assignment
                    ->active()->whereHas('attendanceRecord', fn (Builder $attendance) => $attendance->where('outcome', 'present'))
                    ->whereHas('submissionRecord')->whereHas('examSession.gradingSession'));
            }
        }

        if ($scope->office === 'program' && $scope->programCodes !== []
            && $this->authorizer->allows($actor, CapabilityCatalog::PROGRAM_HEAD_VIEW)) {
            $programIds = Program::query()->where('institution_id', $scope->institutionId)
                ->where('is_active', true)->whereIn('code', $scope->programCodes)->pluck('id')->all();

            return $this->relatedToPrograms($query, $scope->programCodes, $programIds);
        }

        return $query->whereRaw('1 = 0');
    }

    /** @param list<string> $codes
     * @param  list<int>  $ids
     * @return Builder<AdmissionAttempt>
     */
    private function relatedToPrograms(Builder $query, array $codes, array $ids): Builder
    {
        $poolTable = (new ProgramPool)->getTable();

        return $query->where('status', AdmissionAttempt::STATUS_ACCEPTED)
            ->where(function (Builder $related) use ($codes, $ids, $poolTable): void {
                $related->whereIn('program_preference_1_code', $codes)
                    ->orWhereIn('program_preference_2_code', $codes)
                    ->orWhereIn('program_preference_3_code', $codes)
                    ->orWhereHas('programSelections', fn (Builder $selection) => $selection
                        ->whereIn('status', ProgramSelection::NON_TERMINAL_STATUSES)->whereIn('program_id', $ids))
                    ->orWhereHas('programFollowUps', fn (Builder $followUp) => $followUp->whereIn('program_id', $ids))
                    ->orWhereHas('programPools', fn (Builder $pool) => $pool
                        ->whereRaw("{$poolTable}.version = (select max(current_pool.version) from {$poolTable} current_pool where current_pool.admission_attempt_id = {$poolTable}.admission_attempt_id)")
                        ->whereHas('options', fn (Builder $option) => $option->whereIn('program_id', $ids)));
            });
    }

    /** @param array<string, mixed> $context
     * @return list<array{key: string, label: string, url: string, group: string, priority: int}>
     */
    public function for(AdmissionAttempt $attempt, User $actor, array $context = []): array
    {
        $actor = $actor->fresh();
        if ($actor === null || ! $this->visibleAttempts($actor)->whereKey($attempt->id)->exists()) {
            return [];
        }
        $attempt->loadMissing('academicCycle', 'identity');
        $office = $actor->actorScope()->office;
        $destinations = [];
        $add = static function (string $key, string $label, string $route, array $parameters, string $group, int $priority, string $kind = 'page') use (&$destinations): void {
            $destinations[] = compact('key', 'label', 'group', 'priority', 'kind') + ['url' => route($route, $parameters)];
        };

        if ($office === 'registrar') {
            if ($this->authorizer->allows($actor, CapabilityCatalog::REGISTRAR_APPLICATIONS_VIEW)) {
                $add('application_facts', 'View application', 'admissions.show', ['attempt' => $attempt->id], 'Application', 10);
            }
            if ($this->authorizer->allows($actor, CapabilityCatalog::REGISTRAR_ENROLLMENT_ELIGIBILITY)) {
                $add('enrollment_eligibility', 'Open admission clearance', 'registrar.enrollment-eligibility.show', ['attempt' => $attempt->id], 'Admission Clearance', 70);
            }
        } elseif ($office === 'guidance') {
            $this->guidanceDestinations($attempt, $actor, $context, $add);
        } elseif ($office === 'program') {
            $programs = Program::query()->where('institution_id', $attempt->institution_id)
                ->where('is_active', true)->whereIn('code', $actor->actorScope()->programCodes)->orderBy('code')->get();
            foreach ($programs as $program) {
                if (! $this->relatedToPrograms(AdmissionAttempt::query()->whereKey($attempt->id), [$program->code], [$program->id])->exists()) {
                    continue;
                }
                $parameters = ['attempt' => $attempt->id, 'program_code' => $program->code, 'cycle_code' => $attempt->academicCycle->code];
                $priority = ! isset($context['program_code']) || $context['program_code'] === $program->code ? 70 : 0;
                $add('program_record_'.$program->code, 'View '.$program->code.' applicant record', 'admission-support.show', $parameters + ['tab' => 'record'], 'Academic review', $priority);
                if (app(ProtectedViewGuard::class)->tierFor($attempt, $program)['tier'] === 'B') {
                    $add('program_result_'.$program->code, 'View '.$program->code.' academic review copy', 'program-head.applicants.result-sheet', ['program' => $program->code, 'attempt' => $attempt->id], 'Academic review', 0, 'document');
                    if ($this->authorizer->allows($actor, CapabilityCatalog::RECORD_THREAD_PARTICIPATE)) {
                        $add('program_discussion_'.$program->code, 'Open '.$program->code.' discussions', 'admission-support.show', $parameters + ['tab' => 'threads'], 'Discussions', 0);
                    }
                }
            }
        }

        return $destinations;
    }

    /** @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function projection(AdmissionAttempt $attempt, User $actor, array $context = []): array
    {
        $destinations = $this->for($attempt, $actor, $context);
        if ($destinations === []) {
            return [
                'scan_result' => ['state' => 'no_destination', 'message' => 'No applicant destination is available in your current scope.'],
                'destinations' => [], 'default_destination' => null, 'requires_choice' => false,
            ];
        }
        $priority = max(array_column($destinations, 'priority'));
        $defaults = array_values(array_filter($destinations, static fn (array $destination): bool => $destination['priority'] === $priority));
        $default = $priority > 0 && count($defaults) === 1 ? $defaults[0] : null;

        return [
            'scan_result' => ['state' => 'resolved', 'message' => 'Applicant resolved.'],
            'scanned_attempt' => ['id' => $attempt->id, 'reference_code' => $attempt->reference_code, 'name' => $attempt->identity?->fullName() ?? $attempt->reference_code],
            'destinations' => $destinations, 'default_destination' => $default, 'requires_choice' => $default === null,
        ];
    }

    /** @param array<string, mixed> $context */
    private function guidanceDestinations(AdmissionAttempt $attempt, User $actor, array $context, callable $add): void
    {
        $canSchedule = $this->authorizer->allows($actor, CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE);
        $canScore = $this->authorizer->allows($actor, CapabilityCatalog::GUIDANCE_SCORING_MANUAL);
        $canOmr = $this->authorizer->allows($actor, CapabilityCatalog::GUIDANCE_SCORING_OMR);
        $canResults = $this->authorizer->allowsAny($actor, [CapabilityCatalog::GUIDANCE_RESULTS_PREPARE, CapabilityCatalog::GUIDANCE_RESULTS_RELEASE]);
        $canAdvise = $this->authorizer->allowsAny($actor, [CapabilityCatalog::GUIDANCE_CONSULTATION_DECIDE, CapabilityCatalog::GUIDANCE_SELECTION_MANAGE]);
        $canPlanRelease = $this->authorizer->allows($actor, CapabilityCatalog::GUIDANCE_CONSULTATION_PLAN);

        if ($canAdvise) {
            $add('admission_support', 'Open applicant case', 'admission-support.show', ['attempt' => $attempt->id], 'Release Day', 70);
            foreach (['profile' => 'Applicant profile', 'analysis' => 'Decision support', 'logs' => 'Case history'] as $tab => $label) {
                $add('case_'.$tab, $label, 'admission-support.show', ['attempt' => $attempt->id, 'tab' => $tab], 'Applicant case', 0);
            }
            if ($this->authorizer->allows($actor, CapabilityCatalog::RECORD_THREAD_PARTICIPATE)) {
                $add('case_discussions', 'Applicant discussions', 'admission-support.show', ['attempt' => $attempt->id, 'tab' => 'threads'], 'Discussions', 0);
            }
        }

        $assignments = $attempt->examAssignments()->forConductRoster()
            ->whereHas('examSession', fn (Builder $session) => $session->forWorkspace()
                ->where('academic_cycle_id', $attempt->academic_cycle_id)->where('status', '!=', ExamSession::STATUS_CANCELLED))
            ->with(['examSession.gradingSession', 'attendanceRecord', 'submissionRecord'])->get();
        if (isset($context['exam_session_id'])) {
            $assignments = $assignments->filter(fn (ExamAssignment $assignment): bool => (int) $assignment->exam_session_id === (int) $context['exam_session_id']);
        }
        $eligible = $assignments->filter(fn (ExamAssignment $assignment): bool => $assignment->examSession?->gradingSession !== null && GradingRoster::isEligible($assignment));
        $document = $attempt->current_result_document_id === null ? null : ResultDocument::query()
            ->whereKey($attempt->current_result_document_id)->where('admission_attempt_id', $attempt->id)->where('status', ResultDocument::STATUS_READY)->first();

        foreach ($assignments as $assignment) {
            $session = $assignment->examSession;
            $label = (string) ($session->code ?? $session->id);
            if ($canSchedule && $assignment->isActive()) {
                $parameters = ['session' => $session->id, 'focus_assignment_id' => $assignment->id];
                $add('exam_assignment_'.$assignment->id, 'Exam scheduling · '.$label, 'exams.sessions.show', $parameters, 'Examination', 20);
                $add('exam_monitoring_'.$assignment->id, 'Exam monitoring · '.$label, 'exams.sessions.monitoring', $parameters, 'Examination', 30);
            }
        }
        foreach ($eligible as $assignment) {
            $session = $assignment->examSession;
            $label = (string) ($session->code ?? $session->id);
            if ($canScore) {
                $add('score_roster_'.$session->id, 'Scoring status · '.$label, 'scoring.sessions.show', ['examSession' => $session->id, 'focus_attempt_id' => $attempt->id], 'Scoring', 0);
                $add('scores_'.$session->id, 'View scores · '.$label, 'scoring.sessions.attempts.scores', ['examSession' => $session->id, 'attempt' => $attempt->id], 'Scoring', 40);
            }
            if ($canResults && $document !== null && count($eligible) === 1) {
                $add('result_document_'.$session->id, 'View / print result sheet · '.$label, 'release.print.sessions.applicant', ['examSession' => $session->id, 'attempt' => $attempt->id], 'Results', 50);
            }
        }
        if ($canOmr && $eligible->isNotEmpty()) {
            $add('omr', 'Open OMR session discovery', 'scoring.omr.scanner', ['applicant_number' => $attempt->reference_code], 'Scoring', 0);
        }
        $hasBlockingAssignment = $attempt->examAssignments()->blockingScheduling()->exists();
        if ($canSchedule && $attempt->status === AdmissionAttempt::STATUS_ACCEPTED && ! $hasBlockingAssignment) {
            $add('exam_waitlist', 'Schedule examination', 'exams.sessions.waitlist', ['academic_cycle_id' => $attempt->academic_cycle_id, 'focus_attempt_id' => $attempt->id, 'applicant_navigation' => 1], 'Examination', 20);
            if (! $attempt->examAssignments()->active()->exists()) {
                $add('direct_assessment', 'Prepare Direct Assessment', 'exams.direct-assessment.create', ['focus_attempt_id' => $attempt->id], 'Examination', 0);
            }
        }

        if ($canPlanRelease || $this->authorizer->allows($actor, CapabilityCatalog::GUIDANCE_CONSULTATION_DECIDE)) {
            $appointments = $attempt->consultationAssignments()->whereIn('status', ['assigned', 'consulted'])
                ->whereHas('consultationSession', fn (Builder $session) => $session->where('academic_cycle_id', $attempt->academic_cycle_id)
                    ->whereHas('academicCycle', fn (Builder $cycle) => $cycle->where('institution_id', $attempt->institution_id))->where('status', '!=', 'cancelled'))
                ->with('consultationSession')->get();
            if (isset($context['consultation_session_id'])) {
                $appointments = $appointments->filter(fn ($assignment): bool => (int) $assignment->consultation_session_id === (int) $context['consultation_session_id']);
            }
            foreach ($appointments as $appointment) {
                $add('release_appointment_'.$appointment->id, 'Release Day appointment · '.$appointment->consultationSession->code, 'consultation.sessions.show', ['session' => $appointment->consultation_session_id, 'focus_assignment_id' => $appointment->id], 'Release Scheduling', 60);
            }
            if ($canPlanRelease && $appointments->isEmpty() && $attempt->status === AdmissionAttempt::STATUS_ACCEPTED) {
                foreach (['ready', 'not_ready'] as $readiness) {
                    $pool = app(SchedulingWaitlistQuery::class)->forDomain('consultation', (int) $attempt->institution_id, (int) $attempt->academic_cycle_id, [
                        'readiness' => $readiness, 'pool_search' => $attempt->reference_code, 'focus_attempt_id' => $attempt->id,
                    ], null, true);
                    if (collect($pool['applicants']['data'] ?? [])->contains('attempt_id', $attempt->id)) {
                        $add('release_waitlist', 'Schedule Release Day', 'consultation.sessions.waitlist', ['academic_cycle_id' => $attempt->academic_cycle_id, 'focus_attempt_id' => $attempt->id, 'readiness' => $readiness], 'Release Scheduling', 60);
                        break;
                    }
                }
            }
        }
        if ($this->authorizer->allows($actor, CapabilityCatalog::GUIDANCE_CONSULTATION_DECIDE)) {
            $encounters = ConsultationEncounter::query()->where('admission_attempt_id', $attempt->id)
                ->where('counselor_user_id', $actor->id)->where('medium', 'online')
                ->whereIn('status', ['invited', 'waiting', 'in_progress'])->get();
            foreach ($encounters as $encounter) {
                $media = app(JitsiMediaAdapter::class);
                if ($media->accessWindowEnd($encounter) !== null && $media->isParticipantAuthorized($encounter, $actor)) {
                    $add('online_encounter_'.$encounter->id, 'Resume online consultation', 'consultation.encounter.show', ['encounter' => $encounter->id], 'Online consultation', 0);
                }
            }
        }
    }
}
