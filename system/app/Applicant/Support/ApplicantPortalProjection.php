<?php

namespace App\Applicant\Support;

use App\Admission\Support\ApplicantScope;
use App\Admission\Support\AttemptProgramFacts;
use App\Consultation\Queries\ApplicantConsultationCalendar;
use App\Consultation\Queries\ConsultationAdmissionSupportQuery;
use App\Consultation\Support\AttemptConsultationStatus;
use App\Consultation\Support\AttemptResultReadiness;
use App\Examination\Queries\ApplicantSlotCalendar;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ProgramSelection;
use App\Models\Consultation\ConsultationAssignment;
use App\Models\Examination\ExamAssignment;
use App\Models\Release\ResultRelease;
use App\Models\User;
use App\Release\Actions\ReleaseResult as ReleaseResultAction;
use Illuminate\Support\Carbon;

/**
 * Applicant-portal hub projection (2026-08-15-applicant-portal-interface).
 *
 * One server-authored status spine: identity, ordered stages with
 * done/current/upcoming/action/auto states, per-stage actions, release-mode
 * placement, and notices. The client never reorders stages or invents actions.
 * Scores serialize ONLY for MODE_ONLINE releases (design §5).
 */
final class ApplicantPortalProjection
{
    public function __construct(
        private readonly ApplicantSlotCalendar $examCalendar,
        private readonly ApplicantConsultationCalendar $consultationCalendar,
        private readonly ConsultationAdmissionSupportQuery $supportQuery,
    ) {}

    /**
     * @return array<string, mixed>|null  null when the user has no identity or attempt
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
        $online = $release?->mode === ReleaseResultAction::MODE_ONLINE;

        $name = trim((string) ($attempt->identity->first_name ?? '').' '.(string) ($attempt->identity->last_name ?? ''));

        return [
            'identity' => [
                'name' => $name !== '' ? $name : $user->name,
                'applicant_no' => $attempt->reference_code,
                'program_hint' => $attempt->preferred_program_code,
            ],
            'stages' => $this->stages($attempt, $release, $online),
            'notices' => [
                'needs_correction' => $attempt->status === AdmissionAttempt::STATUS_NEEDS_CORRECTION,
                'correction_notes' => $attempt->correction_notes,
                'correction_href' => $attempt->status === AdmissionAttempt::STATUS_NEEDS_CORRECTION
                    ? route('applicant.correction', ['attempt' => $attempt->id])
                    : null,
                'results_blocked' => $released && $release?->mode === ReleaseResultAction::MODE_F2F,
                'dismissed' => $attempt->status === AdmissionAttempt::STATUS_DISMISSED,
                'dismissal_reason' => $attempt->dismissal_reason,
                'archived' => $attempt->status === AdmissionAttempt::STATUS_ARCHIVED,
            ],
            'quick_links' => [
                ['key' => 'notifications', 'label' => 'Notifications', 'href' => '/notifications'],
                ['key' => 'qr', 'label' => 'My QR', 'href' => '/applicant/qr'],
                ['key' => 'admission_support', 'label' => 'Admission support', 'href' => '/applicant/admission-support'],
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function stages(AdmissionAttempt $attempt, ?ResultRelease $release, bool $online): array
    {
        if (in_array($attempt->status, [AdmissionAttempt::STATUS_DISMISSED, AdmissionAttempt::STATUS_ARCHIVED], true)) {
            return [];
        }

        $enrolling = AttemptProgramFacts::isEnrolling($attempt);
        $enrolled = AttemptProgramFacts::isEnrolled($attempt);

        return [
            $this->submittedStage($attempt),
            $this->reviewStage($attempt),
            $this->examStage($attempt),
            $this->resultsStage($attempt, $release, $online),
            $this->consultationStage($attempt),
            $this->programStage($attempt, $enrolling, $enrolled),
            $this->enrollingStage($enrolling, $enrolled),
            $this->enrolledStage($enrolled),
        ];
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

        return [
            'id' => 'submitted',
            'label' => 'Submitted',
            'status' => 'done',
            'at' => self::dateOrNull($attempt->created_at),
            'summary' => null,
            'actions' => [[
                'key' => 'view_application',
                'label' => 'View my application',
                'href' => route('applicant.application', ['attempt' => $attempt->id]),
            ]],
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
            ->with(['examSession.room:id,code,name'])
            ->where('admission_attempt_id', $attempt->id)
            ->first();

        if ($assignment !== null) {
            $session = $assignment->examSession;
            $room = $session?->room?->name ?: $session?->room?->code;

            return [
                'id' => 'exam',
                'label' => 'Exam',
                'status' => 'done',
                'at' => $session?->starts_at?->toDateString(),
                'summary' => $session
                    ? $session->starts_at?->format('M j').($room ? ' · '.$room : '').' · seat confirmed'
                    : 'Seat assigned — awaiting schedule publish.',
                'actions' => [[
                    'key' => 'view_seat',
                    'label' => 'My seat & schedule',
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
    private function resultsStage(AdmissionAttempt $attempt, ?ResultRelease $release, bool $online): array
    {
        if ($release === null) {
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

        return [
            'id' => 'results',
            'label' => 'Results',
            'status' => $online ? 'done' : 'action',
            'at' => self::dateOrNull($release->released_at),
            'summary' => $online
                ? 'Released — your scores are below.'
                : 'Results are ready — claim them at your consultation session.',
            'release' => [
                'mode' => $release->mode,
                'released_at' => self::isoOrNull($release->released_at),
                'version' => $release->release_version,
            ],
            'profile' => $online ? $this->supportQuery->releasedScoreProfile($attempt) : null,
            'actions' => $online ? [] : [[
                'key' => 'book_consultation',
                'label' => 'Book consultation',
                'href' => route('applicant.consultation'),
                'primary' => true,
            ]],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function consultationStage(AdmissionAttempt $attempt): array
    {
        if (! AttemptResultReadiness::isReady($attempt)) {
            return [
                'id' => 'consultation',
                'label' => 'Consultation',
                'status' => 'upcoming',
                'at' => null,
                'summary' => 'Unlocks after your result is ready.',
                'actions' => [],
            ];
        }

        if (AttemptConsultationStatus::isAdvised($attempt)) {
            return [
                'id' => 'consultation',
                'label' => 'Consultation',
                'status' => 'done',
                'at' => null,
                'summary' => 'Consultation completed.',
                'actions' => [[
                    'key' => 'view_consultation',
                    'label' => 'View consultation',
                    'href' => route('applicant.consultation'),
                ]],
            ];
        }

        $assignment = ConsultationAssignment::query()
            ->active()
            ->with(['consultationSession.room:id,code,name'])
            ->where('admission_attempt_id', $attempt->id)
            ->first();

        if ($assignment !== null) {
            $session = $assignment->consultationSession;

            return [
                'id' => 'consultation',
                'label' => 'Consultation',
                'status' => 'current',
                'at' => null,
                'summary' => $session?->scheduled_at
                    ? $session->scheduled_at->format('M j, g:i A').' · Guidance Office'
                    : 'Consultation scheduled.',
                'actions' => [[
                    'key' => 'view_booking',
                    'label' => 'View booking',
                    'href' => route('applicant.consultation'),
                ]],
            ];
        }

        if ($this->consultationCalendar->openSessions($attempt)->isNotEmpty()) {
            return [
                'id' => 'consultation',
                'label' => 'Consultation',
                'status' => 'current',
                'at' => null,
                'summary' => 'Book your consultation session with Guidance.',
                'actions' => [[
                    'key' => 'book',
                    'label' => 'Book consultation',
                    'href' => route('applicant.consultation'),
                    'primary' => true,
                ]],
            ];
        }

        return [
            'id' => 'consultation',
            'label' => 'Consultation',
            'status' => 'current',
            'at' => null,
            'summary' => 'No consultation session is open for booking yet.',
            'actions' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function programStage(AdmissionAttempt $attempt, bool $enrolling, bool $enrolled): array
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

        if ($enrolled || $enrolling) {
            return [
                'id' => 'program_choice',
                'label' => 'Program choice',
                'status' => 'done',
                'at' => null,
                'summary' => $program,
                'actions' => $enrolled ? [] : [[
                    'key' => 'view_program_choice',
                    'label' => 'View program choice',
                    'href' => $href,
                ]],
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
                'at' => null,
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
     * @return array<string, mixed>
     */
    private function enrollingStage(bool $enrolling, bool $enrolled): array
    {
        if (! $enrolling && ! $enrolled) {
            return [
                'id' => 'enrolling',
                'label' => 'Enrolling',
                'status' => 'upcoming',
                'at' => null,
                'summary' => 'Registrar processes your enrollment sheet after program choice.',
                'actions' => [],
            ];
        }

        if ($enrolled) {
            return [
                'id' => 'enrolling',
                'label' => 'Enrolling',
                'status' => 'done',
                'at' => null,
                'summary' => 'Enrollment sheet received and processed.',
                'actions' => [],
            ];
        }

        return [
            'id' => 'enrolling',
            'label' => 'Enrolling',
            'status' => 'current',
            'at' => null,
            'summary' => 'Registrar is processing your enrollment sheet.',
            'actions' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function enrolledStage(bool $enrolled): array
    {
        if (! $enrolled) {
            return [
                'id' => 'enrolled',
                'label' => 'Enrolled',
                'status' => 'upcoming',
                'at' => null,
                'summary' => 'Final enrollment confirmation.',
                'actions' => [],
            ];
        }

        return [
            'id' => 'enrolled',
            'label' => 'Enrolled',
            'status' => 'done',
            'at' => null,
            'summary' => 'Enrolled — welcome to ISPSC.',
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
