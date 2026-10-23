<?php

namespace App\Testing\Queries;

use App\Admission\Support\ApplicantOfficeInquiryAccess;
use App\Admission\Support\ApplicantScope;
use App\Admission\Support\AttemptProgramFacts;
use App\Assessment\Support\GradingRoster;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Consultation\Support\AttemptConsultationStatus;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ApplicantAccount;
use App\Models\Admission\ProgramFollowUp;
use App\Models\Admission\ProgramPool;
use App\Models\Assessment\GradingSession;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Program;
use App\Models\Release\ResultDocument;
use App\Models\ResultSheet\ResultDocumentPrintReceipt;
use App\Models\ResultSheet\ResultSheetTemplate;
use App\Models\User;
use App\ProgramHead\Queries\ProgramHeadCandidateListsQuery;
use App\ProgramHead\Support\ProtectedViewGuard;
use App\Testing\PracticeFixtureSet;
use App\Testing\PracticeWorkspace;
use RuntimeException;

/**
 * Read-only route descriptors. Access and action prerequisites are deliberately
 * separate; preparing a descriptor never opens an audited record or a provider.
 */
final class PracticeCaseHandoffs
{
    public function __construct(
        private readonly CapabilityAuthorizer $authorizer,
        private readonly ApplicantOfficeInquiryAccess $inquiries,
        private readonly ProgramHeadCandidateListsQuery $candidateLists,
        private readonly ProtectedViewGuard $protectedView,
        private readonly PracticeWorkspace $workspace,
    ) {}

    /**
     * @return list<array{key: string, actor: string, label: string, url: ?string, available: bool, reason: string, program_id?: int, session_id?: int, grading_session_id?: int, tier?: string}>
     */
    public function for(PracticeFixtureSet $set, AdmissionAttempt $attempt): array
    {
        return $this->workspace->run($set, fn (): array => $this->handoffsFor($set, $attempt));
    }

    /** @return list<array<string, mixed>> */
    private function handoffsFor(PracticeFixtureSet $set, AdmissionAttempt $attempt): array
    {
        $rows = $this->unavailableRows('The case is not in this active practice set and current institution/cycle.');
        $set = PracticeFixtureSet::query()->find($set->getKey());
        $attempt = AdmissionAttempt::query()->with('identity')->find($attempt->getKey());
        if ($set === null || $attempt === null || $set->status !== 'active'
            || (string) $attempt->practice_fixture_set_id !== (string) $set->id
            || (int) $attempt->institution_id !== (int) $set->institution_id
            || (int) $attempt->academic_cycle_id !== (int) $set->academic_cycle_id
            || $attempt->identity === null
            || (string) $attempt->identity->practice_fixture_set_id !== (string) $set->id
            || (int) $attempt->identity->institution_id !== (int) $set->institution_id) {
            return array_values($rows);
        }

        $cycle = AcademicCycle::query()->whereKey($set->academic_cycle_id)
            ->where('institution_id', $set->institution_id)->where('is_active', true)->first();
        if ($cycle === null) {
            return array_values($rows);
        }

        $actors = User::query()->with('staffProfile')
            ->whereIn('id', array_values($set->actor_bindings ?? []))->get()->keyBy('id');
        $registrar = $actors->get($set->actor_bindings['registrar'] ?? null);
        $guidance = $actors->get($set->actor_bindings['guidance'] ?? null);
        $academic = $actors->get($set->actor_bindings['academic-leader'] ?? null);
        $account = ApplicantAccount::query()->where('applicant_identity_id', $attempt->applicant_identity_id)->first();
        $applicant = $account === null ? null : User::query()->find($account->user_id);
        $ownsAttempt = $applicant !== null && ApplicantScope::attemptBelongsToUser($attempt, $applicant);
        $portalAttemptId = $ownsAttempt ? AdmissionAttempt::query()
            ->where('applicant_identity_id', $attempt->applicant_identity_id)->latest('id')->value('id') : null;
        $ownsPortal = $ownsAttempt && (int) $portalAttemptId === (int) $attempt->id;
        $rows['applicant'] = $this->row($set, 'applicant', 'applicant', 'Own applicant portal', $ownsPortal,
            $ownsPortal ? 'Sign in normally with this identity’s assigned access key and birthdate. The portal opens this current own case; no staff login or impersonation is supplied.'
                : 'No own applicant account opens this case as its current portal record. Preparation does not provision or repair access here.',
            'applicant.portal');

        $reviewAllowed = $this->staffCapability($registrar, 'registrar', $cycle, CapabilityCatalog::REGISTRAR_APPLICATIONS_VIEW);
        $reviewReason = 'The assigned Registrar lacks current application-view authority or institution/office/cycle scope.';
        if ($reviewAllowed) {
            $reviewReason = 'Permitted intake dossier only; no assessment results or private counselling thread.';
            if ($attempt->status !== AdmissionAttempt::STATUS_PENDING) {
                $reviewReason .= ' Request Correction requires a separate pending case; this case is '.$attempt->status.'.';
            } elseif (! $this->staffCapability($registrar, 'registrar', $cycle, CapabilityCatalog::REGISTRAR_APPLICATIONS_CORRECT)) {
                $reviewReason .= ' Reading is allowed, but Request Correction is unavailable without the current correction capability.';
            } else {
                $reviewReason .= ' Request Correction requires a supplied reason and current version; it has not been performed.';
            }
        }
        $rows['review'] = $this->row($set, 'review', 'registrar', 'Application review', $reviewAllowed,
            $reviewReason, 'admissions.show', ['attempt' => $attempt->id]);

        $assignment = ExamAssignment::query()->active()->where('admission_attempt_id', $attempt->id)
            ->whereHas('examSession', fn ($query) => $query->where('practice_fixture_set_id', $set->id)
                ->where('academic_cycle_id', $cycle->id))
            ->with('examSession')->latest('id')->first();
        $session = $assignment?->examSession;
        $grading = $session === null ? null : GradingSession::query()->where('exam_session_id', $session->id)->first();
        $sessionMetadata = $session === null ? [] : ['session_id' => (int) $session->id];
        $gradingMetadata = $grading === null ? $sessionMetadata : [...$sessionMetadata, 'grading_session_id' => (int) $grading->id];
        $inRoster = $grading !== null && GradingRoster::includesAttempt($grading, (int) $attempt->id);
        foreach (['grading' => CapabilityCatalog::GUIDANCE_SCORING_MANUAL, 'omr' => CapabilityCatalog::GUIDANCE_SCORING_OMR] as $key => $capability) {
            $canScore = $this->staffCapability($guidance, 'guidance', $cycle, $capability);
            $allowed = $canScore && $inRoster;
            $reason = ! $canScore ? 'The assigned Guidance account lacks this scoring capability or current institution/office/cycle scope.'
                : ($grading === null ? 'No grading session exists for this case’s current assigned owned exam session.'
                    : (! $inRoster ? 'This case is not on the exact grading roster: active assignment, present attendance and recorded submission are required.'
                        : ($grading->status === GradingSession::STATUS_FINALIZED
                            ? 'The exact roster is readable, but grading is finalized; this is not an editable scoring starting state.'
                            : ($key === 'omr' ? 'The exact OMR workspace is permitted. Actual bound answer-sheet material and ordinary physical capture are still required; no manual-score substitute or capture is performed.'
                                : 'The exact per-case scoring workspace is permitted; ordinary scoring and finalization remain staff actions.'))));
            $rows[$key] = $this->row($set, $key, 'guidance', $key === 'omr' ? 'Ordinary OMR scoring' : 'Case scoring',
                $allowed, $reason, $key === 'omr' ? 'scoring.sessions.omr' : 'grading.sessions.attempts.scores',
                $key === 'omr'
                    ? ['examSession' => $grading?->exam_session_id]
                    : ['gradingSession' => $grading?->id, ...($key === 'grading' ? ['attempt' => $attempt->id] : [])], $gradingMetadata);
        }

        $consultationAllowed = $this->staffCapability($guidance, 'guidance', $cycle, CapabilityCatalog::GUIDANCE_CONSULTATION_DECIDE)
            || $this->staffCapability($guidance, 'guidance', $cycle, CapabilityCatalog::GUIDANCE_SELECTION_MANAGE);
        $consultationReason = 'The assigned Guidance account lacks consultation-decision/selection authority or active Guidance institution/cycle scope.';
        if ($consultationAllowed) {
            $consultationReason = 'The native Guidance case workspace is permitted.';
            if (! AttemptProgramFacts::isAccepted($attempt)) {
                $consultationReason .= ' Consultation actions require Registrar acceptance.';
            }
            if (! AttemptProgramFacts::isResultReady($attempt)) {
                $consultationReason .= ' Result-ready actions require finalized grading and a prepared result document; they are not yet available.';
            }
            $consultationReason .= ' Face-to-face decisions and handover require actual current physical evidence; no media readiness or model/provider is probed.';
        }
        $rows['consultation'] = $this->row($set, 'consultation', 'guidance', 'Guidance consultation', $consultationAllowed,
            $consultationReason, 'admission-support.show', ['attempt' => $attempt->id]);

        $eligibilityAllowed = $this->staffCapability($registrar, 'registrar', $cycle, CapabilityCatalog::REGISTRAR_ENROLLMENT_ELIGIBILITY);
        $eligibilityReason = 'The assigned Registrar lacks current enrollment-eligibility authority or institution/office/cycle scope.';
        if ($eligibilityAllowed) {
            $facts = [
                'Registrar acceptance' => AttemptProgramFacts::isAccepted($attempt),
                'finalized grading' => ExamAssignment::query()->where('admission_attempt_id', $attempt->id)
                    ->whereHas('examSession.gradingSession', fn ($query) => $query->where('status', GradingSession::STATUS_FINALIZED))->exists(),
                'current result release' => AttemptProgramFacts::isResultReleased($attempt),
                'confirmed Guidance decision' => AttemptConsultationStatus::isAdvised($attempt),
            ];
            $missing = array_keys(array_filter($facts, static fn (bool $present): bool => ! $present));
            $eligibilityReason = 'Permitted status, selection, follow-up and enrollment facts only; no private thread or raw assessment/counselling data.';
            $eligibilityReason .= $missing === [] ? ' The four eligibility milestones are present; enrollment is not inferred.'
                : ' Missing milestones: '.implode(', ', $missing).'. The status workspace remains readable.';
        }
        $rows['enrollment'] = $this->row($set, 'enrollment', 'registrar', 'Enrollment eligibility and handoff facts',
            $eligibilityAllowed, $eligibilityReason, 'registrar.enrollment-eligibility.show', ['attempt' => $attempt->id]);

        $rows['academic'] = $this->academicRow($set, $attempt, $academic, $cycle);

        $schedulingAllowed = $this->staffCapability($guidance, 'guidance', $cycle, CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE);
        $schedulingSession = $session ?? $this->preparedSchedulingSession($set, $attempt, $cycle);
        $rows['scheduling'] = $this->row($set, 'scheduling', 'guidance', 'Staff-managed exam scheduling', $schedulingAllowed,
            ! $schedulingAllowed ? 'The assigned Guidance account lacks current exam-scheduling authority or institution/office/cycle scope.'
                : ($schedulingSession === null ? 'The staff-managed create workspace is permitted; this case has no current assigned or valid prepared-stage exam session. Creating, publishing and assigning remain ordinary staff actions, not applicant booking.'
                    : ($session === null ? 'The exact prepared-stage session is readable; its current state is '.$schedulingSession->status.'. This case is not assigned to it. Capacity inspection, staff assignment and publication remain ordinary actions; no applicant booking or assigned schedule is inferred.'
                        : 'The exact assigned session is readable; its current state is '.$session->status.'. Draft creation, publication, capacity and assignment remain ordinary staff-managed actions.')),
            $schedulingSession === null ? 'exams.sessions.create' : 'exams.sessions.show',
            $schedulingSession === null ? [] : ['session' => $schedulingSession->id],
            $schedulingSession === null ? [] : ['session_id' => (int) $schedulingSession->id]);

        $monitoringActor = $guidance;
        $monitoringRole = 'guidance';
        if ($session !== null && ! $this->canMonitor($monitoringActor, $cycle, $session)) {
            foreach ($set->actor_bindings ?? [] as $role => $userId) {
                $candidate = $actors->get($userId);
                if ($this->canMonitor($candidate, $cycle, $session)) {
                    $monitoringActor = $candidate;
                    $monitoringRole = $role;
                    break;
                }
            }
        }
        $monitoringAllowed = $session !== null && $this->canMonitor($monitoringActor, $cycle, $session);
        $rows['monitoring'] = $this->row($set, 'monitoring', $monitoringRole, 'Assigned exam monitoring', $monitoringAllowed,
            $session === null ? 'This case has no current active assignment to an owned exam session.'
                : (! $monitoringAllowed ? 'No assigned active current-scope staff account has exam-management authority or active duty for this exact session.'
                    : $this->attendanceReason($session)),
            'exams.sessions.monitoring', ['session' => $session?->id], $sessionMetadata);

        $rows['inquiry'] = $this->inquiryRow($set, $attempt, $ownsPortal ? $applicant : null);
        $rows['print'] = $this->printRow($set, $attempt, $guidance, $cycle, $grading, $gradingMetadata);

        return array_values($rows);
    }

    private function preparedSchedulingSession(PracticeFixtureSet $set, AdmissionAttempt $attempt, AcademicCycle $cycle): ?ExamSession
    {
        foreach ($set->material_snapshot['stages'] ?? [] as $stage) {
            if (! in_array((int) $attempt->id, array_map('intval', $stage['attempt_ids'] ?? []), true)
                || ! isset($stage['session_id'])) {
                continue;
            }
            $session = ExamSession::query()->whereKey($stage['session_id'])
                ->where('practice_fixture_set_id', $set->id)
                ->where('academic_cycle_id', $cycle->id)
                ->whereHas('room', fn ($query) => $query->where('institution_id', $cycle->institution_id)
                    ->where('is_active', true))
                ->first();
            if ($session !== null) {
                return $session;
            }
        }

        return null;
    }

    private function staffScope(?User $actor, AcademicCycle $cycle, ?string $office = null): bool
    {
        if ($actor === null) {
            return false;
        }
        $profile = $actor->staffProfile;
        $scope = $actor->actorScope();

        return $profile !== null && $profile->isActive()
            && ($office === null || $profile->office === $office)
            && (int) $profile->institution_id === (int) $cycle->institution_id
            && $scope->institutionId === (int) $cycle->institution_id
            && $scope->activeCycleCode === $cycle->code;
    }

    private function staffCapability(?User $actor, string $office, AcademicCycle $cycle, string $capability): bool
    {
        return $this->staffScope($actor, $cycle, $office)
            && $this->authorizer->decideFresh($actor, $capability, [
                'institution_id' => (int) $cycle->institution_id, 'office' => $office, 'cycle' => $cycle->code,
            ])->allowed;
    }

    /** @return array<string, mixed> */
    private function academicRow(PracticeFixtureSet $set, AdmissionAttempt $attempt, ?User $actor, AcademicCycle $cycle): array
    {
        $denied = $this->row($set, 'academic', 'academic-leader', 'Own-program candidate workspace', false,
            'The assigned academic leader lacks current own-active-program authority or the case is absent from the native candidate lists.');
        if (! $this->staffCapability($actor, 'program', $cycle, CapabilityCatalog::PROGRAM_HEAD_VIEW)) {
            return $denied;
        }

        $selection = AttemptProgramFacts::currentSelection($attempt);
        $selectionCode = $selection === null ? null : Program::query()->whereKey($selection->program_id)->value('code');
        $followUpCodes = ProgramFollowUp::query()->where('admission_attempt_id', $attempt->id)
            ->with('program:id,code')->latest('id')->get()->pluck('program.code')->all();
        $poolCodes = ProgramPool::currentFor((int) $attempt->id)?->options()
            ->with('program:id,code')->get()->pluck('program.code')->all() ?? [];
        $scope = $actor->actorScope();
        $candidateCodes = array_filter([$selectionCode, ...$followUpCodes,
            $attempt->program_preference_1_code, $attempt->program_preference_2_code,
            $attempt->program_preference_3_code, ...$poolCodes]);
        $code = collect($candidateCodes)->first(fn (string $candidate): bool => in_array($candidate, $scope->programCodes, true));
        $program = $code === null ? null : Program::query()->where('institution_id', $cycle->institution_id)
            ->where('code', $code)->where('is_active', true)->first();
        if ($program === null || ! $this->authorizer->decideFresh($actor, CapabilityCatalog::PROGRAM_HEAD_VIEW, [
            'institution_id' => (int) $program->institution_id, 'program' => $program->code, 'cycle' => $cycle->code,
        ])->allowed) {
            return $denied;
        }

        $lists = $this->candidateLists->for($program, $cycle->code);
        $included = false;
        foreach (['potential', 'upcoming', 'follow_ups', 'enrolling', 'enrolled'] as $list) {
            foreach ($lists[$list] ?? [] as $candidate) {
                if ((int) ($candidate['attempt_id'] ?? 0) === (int) $attempt->id) {
                    $included = true;
                    break 2;
                }
            }
        }
        if (! $included) {
            return $denied;
        }

        $tier = $this->protectedView->tierFor($attempt, $program)['tier'];

        return $this->row($set, 'academic', 'academic-leader', $tier === 'B' ? 'Own-program protected record' : 'Own-program candidate summary', true,
            $tier === 'B' ? 'The case belongs to the native own-program candidate lists and meets protected tier B. This descriptor does not open or audit the record; ordinary navigation does. Thread and decision capabilities remain separate.'
                : 'The case belongs to the native own-program candidate lists at summary tier A only. Released-result plus acknowledged own selection or targeted follow-up is required for protected tier B; no confidential results or thread access is inferred.',
            'admission-support.show', ['attempt' => $attempt->id], ['program_id' => (int) $program->id, 'tier' => $tier]);
    }

    private function canMonitor(?User $actor, AcademicCycle $cycle, ExamSession $session): bool
    {
        if (! $this->staffScope($actor, $cycle)) {
            return false;
        }
        foreach (CapabilityCatalog::examSessionManagementCapabilities() as $capability) {
            if ($this->authorizer->decideFresh($actor, $capability, [
                'institution_id' => (int) $cycle->institution_id, 'cycle' => $cycle->code,
            ])->allowed) {
                return true;
            }
        }

        return false;
    }

    private function attendanceReason(ExamSession $session): string
    {
        $reason = 'The exact assigned session is readable under current exam-management authority or active session duty.';
        if (! $session->attendanceIsOpen()) {
            return $reason.' Attendance requires an actually started session that has not closed.';
        }

        return $reason.' Attendance is open until actual session closure; recording it remains an ordinary staff action, not a completed outcome.';
    }

    /** @return array<string, mixed> */
    private function inquiryRow(PracticeFixtureSet $set, AdmissionAttempt $attempt, ?User $applicant): array
    {
        $allowed = false;
        $reason = 'The own applicant account or native inquiry registration/capability is unavailable.';
        if ($applicant !== null && $this->inquiries->availability($applicant)['available']) {
            try {
                $inquiryAttempt = $this->inquiries->applicantAttempt($applicant);
                if ((int) $inquiryAttempt->id !== (int) $attempt->id) {
                    $reason = 'Native inquiry access resolves another current attempt, not this assigned case.';
                } else {
                    $offices = [];
                    foreach (['registrar', 'guidance'] as $office) {
                        if ($this->inquiries->officeStaffFor($attempt, $office)->isNotEmpty()) {
                            $offices[] = ucfirst($office);
                        }
                    }
                    $allowed = true;
                    $reason = $offices !== [] ? 'Native own-case inquiry access and assigned permitted '.implode('/', $offices).' counterpart are present. Sending a question and obtaining a reply remain ordinary actions.'
                        : 'The native own inquiry workspace is readable, but sending requires a permitted destination. Neither assigned office counterpart currently meets the registered handling boundary; no real or unrelated recipient is substituted.';
                }
            } catch (RuntimeException) {
                $reason = 'Native inquiry authority changed or is unavailable for this assigned case.';
            }
        }

        return $this->row($set, 'inquiry', 'applicant', 'Own office inquiry', $allowed, $reason, 'applicant.inquiries.index');
    }

    /**
     * @param  array<string, int>  $metadata
     * @return array<string, mixed>
     */
    private function printRow(PracticeFixtureSet $set, AdmissionAttempt $attempt, ?User $guidance, AcademicCycle $cycle, ?GradingSession $grading, array $metadata): array
    {
        $canRelease = $this->staffCapability($guidance, 'guidance', $cycle, CapabilityCatalog::GUIDANCE_RESULTS_RELEASE);
        $document = ResultDocument::query()->whereKey($attempt->current_result_document_id)
            ->where('admission_attempt_id', $attempt->id)->where('status', ResultDocument::STATUS_READY)
            ->first(['id', 'admission_attempt_id']);
        $inBatch = $grading !== null && in_array((int) $attempt->id, $grading->scoredAttemptIds(), true);
        $allowed = $canRelease && $document !== null && $inBatch;
        $reason = ! $canRelease ? 'The assigned Guidance account lacks current result-release authority or institution/office/cycle scope.'
            : ($document === null ? 'This case has no current ready result document; no old or unrelated paper is substituted.'
                : (! $inBatch ? 'The current assigned grading session does not include scored records for this case.'
                    : 'The actual current document and exact grading print workspace are permitted.'));
        if ($allowed) {
            $printed = ResultDocumentPrintReceipt::query()->where('institution_id', $attempt->institution_id)
                ->where('admission_attempt_id', $attempt->id)->where('result_document_id', $document->id)
                ->where('is_current', true)->where('operation', 'confirmed')
                ->whereNotNull('confirmed_by_user_id')->whereNotNull('confirmed_at')->exists();
            $reason .= $printed ? ' Attributable current print confirmation exists; actual paper delivery/participant reading is not inferred.'
                : ' Current attributable physical print confirmation is missing. A PDF/workspace is not printed paper or delivery.';
            if (ResultSheetTemplate::active() === null) {
                $reason .= ' The native print renderer also requires an active result-sheet template, which is missing.';
            }
        }

        return $this->row($set, 'print', 'guidance', 'Current case result paper', $allowed, $reason,
            'release.print.batches.applicant', ['gradingSession' => $grading?->id, 'attempt' => $attempt->id], $metadata);
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function row(PracticeFixtureSet $set, string $key, string $actor, string $label, bool $available, string $reason, ?string $route = null, array $parameters = [], array $metadata = []): array
    {
        return [
            'key' => $key, 'actor' => $actor, 'label' => $label,
            'url' => $available && $route !== null ? route($route, [...$parameters, 'practice_set' => $set->id], absolute: false) : null,
            'available' => $available, 'reason' => $reason, ...$metadata,
        ];
    }

    /** @return array<string, array<string, mixed>> */
    private function unavailableRows(string $reason): array
    {
        $rows = [];
        foreach ([
            'applicant' => ['applicant', 'Own applicant portal'],
            'review' => ['registrar', 'Application review'],
            'grading' => ['guidance', 'Case scoring'],
            'omr' => ['guidance', 'Ordinary OMR scoring'],
            'consultation' => ['guidance', 'Guidance consultation'],
            'enrollment' => ['registrar', 'Enrollment eligibility and handoff facts'],
            'academic' => ['academic-leader', 'Own-program candidate workspace'],
            'monitoring' => ['guidance', 'Assigned exam monitoring'],
            'scheduling' => ['guidance', 'Staff-managed exam scheduling'],
            'inquiry' => ['applicant', 'Own office inquiry'],
            'print' => ['guidance', 'Current case result paper'],
        ] as $key => [$actor, $label]) {
            $rows[$key] = ['key' => $key, 'actor' => $actor, 'label' => $label, 'url' => null, 'available' => false, 'reason' => $reason];
        }

        return $rows;
    }
}
