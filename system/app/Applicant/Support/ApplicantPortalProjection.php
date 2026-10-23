<?php

namespace App\Applicant\Support;

use App\Admission\Support\ApplicantScope;
use App\Admission\Support\AttemptProgramFacts;
use App\Assessment\Support\IndividualScoringReadiness;
use App\Consultation\Queries\ApplicantConsultationCalendar;
use App\Consultation\Support\AttemptConsultationStatus;
use App\Examination\Actions\ReconcileOverdueExamSessions;
use App\Examination\Queries\ApplicantSlotCalendar;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ProgramSelection;
use App\Models\Assessment\AssessmentResultSet;
use App\Models\Assessment\GradingSession;
use App\Models\Consultation\ConsultationAssignment;
use App\Models\Examination\AssessmentAdministration;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamAttendanceRecord;
use App\Models\Examination\ExamSession;
use App\Models\Release\ConsultationDecision;
use App\Models\Release\ResultRelease;
use App\Models\User;
use App\Release\Actions\ReleaseResult as ReleaseResultAction;
use Illuminate\Support\Carbon;

/**
 * Applicant Status-Spine Projection (Domain: Student / Applicant Experience)
 *
 * Architectural Purpose:
 * Produces an authoritative, server-driven lifecycle projection consumed by the
 * Applicant Portal UI (`Applicant/Portal.svelte`) and guided by the Kuya Xiam
 * (`applicant-mentor` persona) walkthrough system.
 *
 * Domain Responsibilities:
 * 1. Identity Projection: Resolves the logged-in user's ApplicantIdentity and active
 *    AdmissionAttempt record with proper data-scoping.
 * 2. Pipeline Projection: Groups existing authorized facts into four phases
 *    and seven operational presentation steps without creating lifecycle state.
 *    Existing submissions, review, examination, results, encounters, program
 *    choices, and clearance records retain their distinct state owners.
 * 3. Invariants & Safety:
 *    - Home receives lifecycle state only; score profiles are projected exclusively
 *      on the dedicated Results page.
 *    - Client receives server-authored states, completion dates, and detail routes.
 */
final class ApplicantPortalProjection
{
    public function __construct(
        private readonly ApplicantClearanceProjection $clearanceProjection,
        private readonly ApplicantSlotCalendar $examCalendar,
        private readonly ApplicantConsultationCalendar $consultationCalendar,
        private readonly ApplicantResultAccess $resultAccess,
        private readonly IndividualScoringReadiness $scoringReadiness,
    ) {}

    /**
     * @return array<string, mixed>|null null when the user has no identity or attempt
     */
    public function for(User $user): ?array
    {
        $identityId = ApplicantScope::identityIdForUser($user);

        if ($identityId === null) {
            return null;
        }

        $attempt = AdmissionAttempt::query()
            ->with(['identity:id,first_name,last_name,email'])
            ->where('applicant_identity_id', $identityId)
            ->orderByDesc('id')
            ->first();

        if ($attempt === null) {
            return null;
        }

        $released = AttemptProgramFacts::isResultReleased($attempt);
        $release = $released ? ResultRelease::query()
            ->where('admission_attempt_id', $attempt->id)
            ->where('is_current', true)
            ->first() : null;
        $resultFacts = $this->resultAccess->forAttempt($attempt);

        $name = trim((string) ($attempt->identity->first_name ?? '').' '.(string) ($attempt->identity->last_name ?? ''));

        $clearance = $this->clearanceProjection->for($attempt, $user);
        $clearanceSummary = $this->clearancePayload($clearance);
        $stages = $this->stages($attempt, $release, $clearanceSummary, $resultFacts);

        return [
            'identity' => [
                'name' => $name !== '' ? $name : $user->name,
                'applicant_no' => $attempt->reference_code,
                'program_hint' => $attempt->preferred_program_code,
            ],
            'stages' => $stages,
            'pipeline' => $this->operationalPipeline($stages, $attempt, $resultFacts['available'] || $resultFacts['pending'] || $resultFacts['retracted'] || $resultFacts['completed_f2f'] || $resultFacts['artifact_failed'] || $release?->mode === ReleaseResultAction::MODE_ONLINE),
            'admission_clearance' => $clearanceSummary,
            'notices' => [
                'needs_correction' => $attempt->status === AdmissionAttempt::STATUS_NEEDS_CORRECTION,
                'correction_notes' => $attempt->correction_notes,
                'correction_href' => $attempt->status === AdmissionAttempt::STATUS_NEEDS_CORRECTION
                    ? route('applicant.correction', ['attempt' => $attempt->id])
                    : null,
                'results_blocked' => $released
                    && $release?->mode === ReleaseResultAction::MODE_F2F
                    && ! AttemptConsultationStatus::isAdvised($attempt),
                'dismissed' => $attempt->status === AdmissionAttempt::STATUS_DISMISSED,
                'dismissal_reason' => $attempt->dismissal_reason,
                'archived' => $attempt->status === AdmissionAttempt::STATUS_ARCHIVED,
            ],
            'quick_links' => [
                ['key' => 'notifications', 'label' => 'Notifications', 'href' => '/notifications'],
                ['key' => 'qr', 'label' => 'My QR', 'href' => '/applicant/qr'],
                ['key' => 'admission_support', 'label' => 'Messages', 'href' => '/applicant/admission-support'],
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function stages(
        AdmissionAttempt $attempt,
        ?ResultRelease $release,
        array $clearance,
        array $resultFacts,
    ): array {
        if (in_array($attempt->status, [AdmissionAttempt::STATUS_DISMISSED, AdmissionAttempt::STATUS_ARCHIVED], true)) {
            return [];
        }

        return [
            $this->submittedStage($attempt),
            $this->reviewStage($attempt),
            $this->examStage($attempt),
            $this->resultsStage($attempt, $release, $resultFacts),
            $this->consultationStage($attempt),
            $this->programStage($attempt, $clearance),
            $this->clearanceStage($clearance),
        ];
    }

    /**
     * Present the four macro-phases and seven operational steps using the
     * existing applicant lifecycle records without changing their state owners.
     *
     * @param  list<array<string, mixed>>  $stages
     * @return list<array{id: string, label: string, steps: list<array<string, mixed>>}>
     */
    private function operationalPipeline(
        array $stages,
        AdmissionAttempt $attempt,
        bool $myResultsAvailable,
    ): array {
        $detailHrefs = [
            'application' => route('applicant.application', ['attempt' => $attempt->id]),
            'exam_scheduling' => route('applicant.schedule'),
            'exam_monitoring' => route('applicant.exam-day'),
            'scoring' => route('applicant.exam-scoring'),
            'release_scheduling' => route('applicant.consultation'),
            'release_encounter' => route($myResultsAvailable ? 'applicant.results.show' : 'applicant.release-day'),
            'admission_clearance' => route('applicant.admission-clearance'),
        ];

        $step = static fn (string $id, string $label, array $stage, ?string $summaryOverride = null): array => [
            'id' => $id,
            'label' => $label,
            'status' => $stage['status'] ?? 'upcoming',
            'at' => $stage['at'] ?? null,
            'summary' => $summaryOverride ?? ($stage['summary'] ?? null),
            'href' => $detailHrefs[$id],
            'detail_label' => $id === 'release_encounter' && $myResultsAvailable ? 'View My Results' : 'View details',
        ];

        $byId = [];
        foreach ($stages as $stage) {
            $byId[$stage['id']] = $stage;
        }

        $application = $byId['submitted'] ?? [];
        $review = $byId['review'] ?? [];
        $exam = $byId['exam'] ?? [];
        $encounter = $byId['consultation'] ?? [];
        $clearanceStage = $byId['admission_clearance'] ?? [];

        $examConductIsCurrent = ($exam['assigned'] ?? false) && ($exam['status'] ?? null) === 'current';

        $monitoringSummary = match (true) {
            ($exam['checked_in'] ?? false) => 'Attendance recorded. View your exam schedule for session and venue details.',
            $examConductIsCurrent => 'Your exam session is in progress. View Exam Day for attendance and live session details.',
            ($exam['assigned'] ?? false) => 'View your assigned exam schedule and venue details.',
            default => 'Exam Day details appear after scheduling.',
        };

        $scoringEvidence = $this->currentScoringEvidence($attempt);
        $scoringComplete = $scoringEvidence !== null;
        $scoringStatus = $scoringComplete ? 'done' : 'upcoming';
        $scoringSummary = $scoringComplete
            ? 'Scoring is complete; result release details appear in Release of Results.'
            : 'Scoring is not yet finalized.';

        $monitoringStatus = ($exam['exam_completed'] ?? false)
            ? 'done'
            : ($examConductIsCurrent ? 'current' : 'upcoming');
        $monitoring = [
            'status' => $monitoringStatus,
            'at' => $exam['monitoring_at'] ?? null,
            'summary' => $monitoringSummary,
            'actions' => [],
        ];

        $scoring = [
            'status' => $scoringStatus,
            'at' => $scoringComplete ? self::dateOrNull($scoringEvidence?->finalized_at) : null,
            'summary' => $scoringSummary,
            'actions' => [],
        ];

        return [
            [
                'id' => 'application',
                'label' => 'Application',
                'steps' => [[
                    ...$step('application', 'My Application', ($application['status'] ?? null) === 'action' ? $application : $review),
                    'summary' => ($application['status'] ?? null) === 'action'
                        ? ($application['summary'] ?? $review['summary'] ?? null)
                        : ($review['summary'] ?? $application['summary'] ?? null),
                    'at' => $review['at'] ?? $application['at'] ?? null,
                ]],
            ],
            [
                'id' => 'examination',
                'label' => 'Examination',
                'steps' => [
                    [
                        ...$step('exam_scheduling', 'Exam Scheduling', $exam),
                        'status' => ($exam['assigned'] ?? false) ? 'done' : ($exam['status'] ?? 'upcoming'),
                    ],
                    $step('exam_monitoring', 'Exam Day', $monitoring),
                    $step('scoring', 'Exam Scoring', $scoring),
                ],
            ],
            [
                'id' => 'release_of_results',
                'label' => 'Release of Results',
                'steps' => [
                    $step(
                        'release_scheduling',
                        'Release Day Scheduling',
                        [
                            ...$encounter,
                            'status' => $encounter['schedule_status'] ?? 'upcoming',
                            'at' => null,
                        ],
                        $encounter['schedule_summary'] ?? 'No Release Day appointment recorded.',
                    ),
                    $step(
                        'release_encounter',
                        'Release Day',
                        $encounter,
                        $encounter['summary'] ?? null,
                    ),
                ],
            ],
            [
                'id' => 'admission_clearance',
                'label' => 'Admission Clearance',
                'steps' => [[
                    ...$step('admission_clearance', 'Admission Clearance', $clearanceStage),
                ]],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function clearancePayload(array $projection): array
    {
        $latestReceipt = $projection['latest_receipt'];
        $historicalSheetIssue = $projection['historical_sheet_issue'];
        $isConfirmed = $projection['status'] === 'confirmed';
        $sheetReceiptNumber = $historicalSheetIssue['receipt_number'] ?? null;
        $summary = match ($projection['status']) {
            'confirmed' => 'Admission clearance confirmed (Ref: '.($latestReceipt['receipt_number'] ?? 'CONFIRMED').'). You are officially cleared for admission.',
            'revoked' => 'Admission clearance was reopened by Registrar for verification adjustments.',
            'ready' => 'All prerequisites and document requirements verified. Ready for official Registrar clearance confirmation.',
            default => 'Unlocks after Release of Results is completed and all document requirements are verified.',
        };

        return [
            'status' => $projection['status'],
            'is_confirmed' => $isConfirmed,
            'receipt_number' => $isConfirmed ? $latestReceipt['receipt_number'] : null,
            'confirmed_at' => $latestReceipt['confirmed_at'] ?? null,
            'historical_sheet_issue' => $historicalSheetIssue,
            'historical_sheet_issued_at' => $historicalSheetIssue['issued_at'] ?? null,
            'historical_sheet_receipt_number' => $sheetReceiptNumber,
            'historical_sheet_belongs_to_latest_clearance' => $historicalSheetIssue['belongs_to_latest_clearance'] ?? false,
            'summary' => $summary,
        ];
    }

    private function currentScoringEvidence(AdmissionAttempt $attempt): AssessmentResultSet|GradingSession|null
    {
        $assignment = ExamAssignment::query()
            ->active()
            ->where('admission_attempt_id', $attempt->id)
            ->latest('id')
            ->first();

        if ($assignment === null) {
            return null;
        }

        $administration = AssessmentAdministration::query()
            ->active()
            ->where('exam_assignment_id', $assignment->id)
            ->where('admission_attempt_id', $attempt->id)
            ->latest('id')
            ->first();

        if ($administration !== null) {
            return AssessmentResultSet::query()
                ->where('administration_id', $administration->id)
                ->where('status', AssessmentResultSet::STATUS_FINALIZED)
                ->latest('id')
                ->first();
        }

        return GradingSession::query()
            ->where('exam_session_id', $assignment->exam_session_id)
            ->where('status', GradingSession::STATUS_FINALIZED)
            ->latest('id')
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function submittedStage(AdmissionAttempt $attempt): array
    {
        if ($attempt->status === AdmissionAttempt::STATUS_NEEDS_CORRECTION) {
            return [
                'id' => 'submitted',
                'label' => 'Submitted',
                'status' => 'action',
                'at' => self::dateOrNull($attempt->created_at),
                'summary' => $attempt->correction_notes ?: 'Registrar asked you to fix something in your application.',
                'actions' => [[
                    'key' => 'fix_application',
                    'label' => 'Fix application',
                    'href' => route('applicant.correction', ['attempt' => $attempt->id]),
                    'primary' => true,
                ]],
            ];
        }

        $actions = [[
            'key' => 'view_application',
            'label' => 'View my application',
            'href' => route('applicant.application', ['attempt' => $attempt->id]),
        ]];

        if ($attempt->status === AdmissionAttempt::STATUS_PENDING) {
            $actions[] = [
                'key' => 'amend_application',
                'label' => 'Update details',
                'href' => route('applicant.correction', ['attempt' => $attempt->id]),
            ];
        }

        return [
            'id' => 'submitted',
            'label' => 'Submitted',
            'status' => 'done',
            'at' => self::dateOrNull($attempt->created_at),
            'summary' => null,
            'actions' => $actions,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function reviewStage(AdmissionAttempt $attempt): array
    {
        if ($attempt->status === AdmissionAttempt::STATUS_ACCEPTED) {
            return [
                'id' => 'review',
                'label' => 'Review',
                'status' => 'done',
                'at' => self::dateOrNull($attempt->accepted_at),
                'summary' => 'Accepted by the Registrar.',
                'actions' => [],
            ];
        }

        if ($attempt->status === AdmissionAttempt::STATUS_NEEDS_CORRECTION) {
            return [
                'id' => 'review',
                'label' => 'Review',
                'status' => 'current',
                'at' => null,
                'summary' => 'Waiting for your correction before review continues.',
                'actions' => [],
            ];
        }

        return [
            'id' => 'review',
            'label' => 'Review',
            'status' => 'current',
            'at' => null,
            'summary' => 'Registrar is checking your application.',
            'actions' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function examStage(AdmissionAttempt $attempt): array
    {
        if (! $attempt->isExamEligible()) {
            return [
                'id' => 'exam',
                'label' => 'Exam',
                'status' => 'upcoming',
                'at' => null,
                'summary' => 'Scheduling opens after your application is accepted.',
                'actions' => [],
            ];
        }

        $assignment = ExamAssignment::query()
            ->active()
            ->with([
                'examSession.room:id,code,name',
                'attendanceRecord',
                'submissionRecord',
            ])
            ->where('admission_attempt_id', $attempt->id)
            ->first();

        if ($assignment !== null && $assignment->examSession !== null) {
            $reconciled = app(ReconcileOverdueExamSessions::class)->reconcileSessionIfOverdue($assignment->examSession);
            if ($reconciled->status !== $assignment->examSession->status) {
                $assignment = $assignment->fresh(['examSession.room:id,code,name', 'attendanceRecord', 'submissionRecord']);
            }
            if ($assignment !== null && ! $assignment->isActive()) {
                $assignment = null;
            }
        }

        if ($assignment !== null) {
            $session = $assignment->examSession;
            $room = $session?->room?->name ?: $session?->room?->code;
            $examCompleted = $assignment->submissionRecord?->submitted_at !== null
                || ($session?->status === ExamSession::STATUS_COMPLETED
                    && $assignment->attendanceRecord?->outcome === ExamAttendanceRecord::OUTCOME_PRESENT);
            $checkedIn = $assignment->attendanceRecord?->outcome === ExamAttendanceRecord::OUTCOME_PRESENT;

            return [
                'id' => 'exam',
                'label' => 'Exam',
                'assigned' => true,
                'status' => $examCompleted
                    ? 'done'
                    : ($checkedIn || $session?->status === ExamSession::STATUS_IN_PROGRESS ? 'current' : 'done'),
                'exam_completed' => $examCompleted,
                'checked_in' => $checkedIn,
                'at' => self::dateOrNull($session?->starts_at),
                'monitoring_at' => $checkedIn ? self::dateOrNull($assignment->attendanceRecord?->recorded_at) : null,
                'summary' => $session
                    ? $session->starts_at?->format('M j').($room ? ' · '.$room : '').($checkedIn && ! $examCompleted
                        ? ' · attendance recorded'
                        : ' · appointment confirmed')
                    : 'Slot assigned — awaiting schedule publish.',
                'actions' => [[
                    'key' => 'view_seat',
                    'label' => 'My exam schedule',
                    'href' => route('applicant.schedule'),
                ]],
            ];
        }

        if ($this->examCalendar->openSlots($attempt)->isNotEmpty()) {
            return [
                'id' => 'exam',
                'label' => 'Exam',
                'status' => 'current',
                'at' => null,
                'summary' => 'Choose your exam slot before the window closes.',
                'actions' => [[
                    'key' => 'reserve',
                    'label' => 'Choose exam slot',
                    'href' => route('applicant.schedule'),
                    'primary' => true,
                ]],
            ];
        }

        return [
            'id' => 'exam',
            'label' => 'Exam',
            'status' => 'current',
            'at' => null,
            'summary' => 'Reservation window not open yet — check back soon.',
            'actions' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resultsStage(
        AdmissionAttempt $attempt,
        ?ResultRelease $release,
        array $resultFacts,
    ): array {
        if ($release === null && ! $resultFacts['pending'] && ! $resultFacts['retracted'] && ! $resultFacts['completed_f2f'] && ! $resultFacts['artifact_failed']) {
            return [
                'id' => 'results',
                'label' => 'Results',
                'status' => 'upcoming',
                'at' => null,
                'summary' => AttemptProgramFacts::isResultReady($attempt)
                    ? 'Your result is ready to release — it will appear here.'
                    : 'Results appear here after grading and release.',
                'actions' => [],
            ];
        }
        if (
            $release?->mode === ReleaseResultAction::MODE_F2F
            && ! AttemptConsultationStatus::isAdvised($attempt)
            && ! $resultFacts['completed_f2f']
        ) {
            return [
                'id' => 'results',
                'label' => 'Results',
                'status' => 'action',
                'at' => self::dateOrNull($release->released_at),
                'summary' => 'Results are ready — claim them on Release Day.',
                'release' => [
                    'mode' => $release->mode,
                    'released_at' => self::isoOrNull($release->released_at),
                    'version' => $release->release_version,
                ],
                'actions' => [[
                    'key' => 'book_consultation',
                    'label' => 'View Release of Results schedule',
                    'href' => route('applicant.consultation'),
                    'primary' => true,
                ]],
            ];
        }

        $unlocked = $resultFacts['available'];
        $pending = $resultFacts['pending'];

        return [
            'id' => 'results',
            'label' => 'Results',
            'status' => $unlocked ? 'done' : 'action',
            'at' => self::dateOrNull($release?->released_at),
            'summary' => $unlocked
                ? 'Released — your official result PDF is ready to download.'
                : ($pending
                    ? 'Your Release Day encounter is complete; the official PDF is being prepared.'
                    : 'The current result PDF is unavailable for portal access.'),
            'release' => $release === null ? null : [
                'mode' => $release->mode,
                'released_at' => self::isoOrNull($release->released_at),
                'version' => $release->release_version,
            ],
            'actions' => [[
                'key' => 'view_results',
                'label' => 'View My Results',
                'href' => route('applicant.results.show'),
                'primary' => true,
            ]],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function consultationStage(AdmissionAttempt $attempt): array
    {
        $assignment = ConsultationAssignment::query()
            ->active()
            ->with(['consultationSession.room:id,code,name'])
            ->where('admission_attempt_id', $attempt->id)
            ->first();
        $scheduledAt = $assignment?->consultationSession?->scheduled_at;
        $scheduleSummary = $scheduledAt !== null
            ? $scheduledAt->format('F j, Y · g:i A').' · Guidance Office'
            : ($assignment === null ? 'No Release Day appointment recorded.' : 'Release Day appointment assigned.');
        if (AttemptConsultationStatus::isAdvised($attempt)) {
            $decision = ConsultationDecision::query()
                ->where('admission_attempt_id', $attempt->id)
                ->orderByDesc('id')
                ->first();

            return [
                'id' => 'consultation',
                'label' => 'Release Day',
                'status' => 'done',
                'schedule_status' => $assignment === null ? 'upcoming' : 'done',
                'schedule_summary' => $scheduleSummary,
                'at' => self::dateOrNull($decision?->decided_at ?? $decision?->created_at),
                'summary' => 'Release Day encounter completed.',
                'actions' => [[
                    'key' => 'view_consultation',
                    'label' => 'View Release Day details',
                    'href' => route('applicant.consultation'),
                ]],
            ];
        }

        if ($assignment !== null) {
            $scoring = $this->scoringReadiness->forAttempt($attempt);
            $scoringBlocker = $scoring['ready'] ? null : $scoring['reason'];

            return [
                'id' => 'consultation',
                'label' => 'Release Day',
                'status' => 'current',
                'schedule_status' => 'done',
                'schedule_summary' => $scheduleSummary,
                'scoring_blocker' => $scoringBlocker,
                'at' => null,
                'summary' => $scoringBlocker === null
                    ? 'No completed Release Day encounter is recorded yet.'
                    : 'Your appointment remains held while individual scoring is resolved: '.$scoringBlocker,
                'actions' => [[
                    'key' => 'view_booking',
                    'label' => 'View Release Day appointment',
                    'href' => route('applicant.consultation'),
                ]],
            ];
        }

        if (! $this->scoringReadiness->forAttempt($attempt)['ready']) {
            return [
                'id' => 'consultation',
                'label' => 'Release Day',
                'status' => 'upcoming',
                'schedule_status' => 'upcoming',
                'schedule_summary' => $scheduleSummary,
                'at' => null,
                'summary' => 'No completed Release Day encounter is recorded yet.',
                'actions' => [],
            ];
        }
        if ($this->consultationCalendar->openSessions($attempt)->isNotEmpty()) {
            return [
                'id' => 'consultation',
                'label' => 'Release Day',
                'status' => 'current',
                'schedule_status' => 'current',
                'schedule_summary' => 'Release Day appointments are available to book.',
                'at' => null,
                'summary' => 'No completed Release Day encounter is recorded yet.',
                'actions' => [[
                    'key' => 'book',
                    'label' => 'Choose Release Day appointment',
                    'href' => route('applicant.consultation'),
                    'primary' => true,
                ]],
            ];
        }

        return [
            'id' => 'consultation',
            'label' => 'Release Day',
            'status' => 'upcoming',
            'schedule_status' => 'upcoming',
            'schedule_summary' => $scheduleSummary,
            'at' => null,
            'summary' => 'No completed Release Day encounter is recorded yet.',
            'actions' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function programStage(AdmissionAttempt $attempt, array $clearance): array
    {
        $selection = AttemptProgramFacts::currentSelection($attempt);
        $href = route('applicant.program-choice');

        if ($selection === null) {
            return [
                'id' => 'program_choice',
                'label' => 'Program choice',
                'status' => 'upcoming',
                'at' => null,
                'summary' => 'Unlocks when Guidance finalizes your options.',
                'actions' => [],
            ];
        }

        $selection->loadMissing('program');
        $program = $selection->program?->name ?? $selection->program?->code ?? 'Program';

        if ($clearance['is_confirmed']) {
            return [
                'id' => 'program_choice',
                'label' => 'Program choice',
                'status' => 'done',
                'at' => self::dateOrNull($selection->updated_at ?? $selection->created_at),
                'summary' => $program,
                'actions' => [],
            ];
        }

        if ($selection->status === ProgramSelection::STATUS_PENDING_ACKNOWLEDGEMENT
            && $selection->source === ProgramSelection::SOURCE_GUIDANCE_DEFAULT) {
            return [
                'id' => 'program_choice',
                'label' => 'Program choice',
                'status' => 'auto',
                'at' => null,
                'summary' => $program.' — set by Guidance · projection active',
                'actions' => [[
                    'key' => 'ack_default',
                    'label' => 'This is my choice',
                    'href' => $href,
                ]],
            ];
        }

        if ($selection->isNonTerminal()) {
            return [
                'id' => 'program_choice',
                'label' => 'Program choice',
                'status' => 'done',
                'at' => self::dateOrNull($selection->updated_at ?? $selection->created_at),
                'summary' => $program,
                'actions' => [[
                    'key' => 'view_program_choice',
                    'label' => 'View or change',
                    'href' => $href,
                ]],
            ];
        }

        return [
            'id' => 'program_choice',
            'label' => 'Program choice',
            'status' => 'upcoming',
            'at' => null,
            'summary' => 'Choose your program when options are ready.',
            'actions' => [],
        ];
    }

    /**
     * @param  array{status: string, is_confirmed: bool, receipt_number: ?string, confirmed_at: ?string, historical_sheet_issue: ?array{receipt_number: string, issued_at: ?string, belongs_to_latest_clearance: bool}, summary: string}  $clearance
     * @return array<string, mixed>
     */
    private function clearanceStage(array $clearance): array
    {
        if ($clearance['is_confirmed']) {
            return [
                'id' => 'admission_clearance',
                'label' => 'Admission clearance',
                'status' => 'done',
                'at' => $clearance['confirmed_at'],
                'summary' => $clearance['summary'],
                'actions' => [],
            ];
        }

        if ($clearance['status'] === 'ready') {
            return [
                'id' => 'admission_clearance',
                'label' => 'Admission clearance',
                'status' => 'current',
                'at' => null,
                'summary' => $clearance['summary'],
                'actions' => [],
            ];
        }

        if ($clearance['status'] === 'revoked') {
            return [
                'id' => 'admission_clearance',
                'label' => 'Admission clearance',
                'status' => 'action',
                'at' => null,
                'summary' => $clearance['summary'],
                'actions' => [],
            ];
        }

        return [
            'id' => 'admission_clearance',
            'label' => 'Admission clearance',
            'status' => 'upcoming',
            'at' => null,
            'summary' => $clearance['summary'],
            'actions' => [],
        ];
    }

    private static function isoOrNull(mixed $value): ?string
    {
        return $value === null ? null : Carbon::parse($value)->toIso8601String();
    }

    private static function dateOrNull(mixed $value): ?string
    {
        return $value === null ? null : Carbon::parse($value)->toDateString();
    }
}
