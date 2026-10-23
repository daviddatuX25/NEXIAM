<?php

namespace App\Http\Controllers\Applicant;

use App\Admission\Support\ApplicantScope;
use App\Applicant\Support\ApplicantResultAccess;
use App\Consultation\Services\JitsiMediaAdapter;
use App\Consultation\Support\AttemptConsultationStatus;
use App\Consultation\Support\ConsultationMediaReadiness;
use App\Http\Controllers\Controller;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ProgramFollowUp;
use App\Models\Consultation\ConsultationAssignment;
use App\Models\Consultation\ConsultationEncounter;
use App\Models\Consultation\ConsultationSession;
use App\Models\Release\ConsultationDecision;
use App\Models\Release\ResultRelease;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class ApplicantReleaseDayController extends Controller
{
    public function show(
        Request $request,
        JitsiMediaAdapter $media,
        ConsultationMediaReadiness $mediaReadiness,
        ApplicantResultAccess $resultAccess,
    ): Response {
        $user = $request->user();
        $identityId = $user instanceof User ? ApplicantScope::identityIdForUser($user) : null;
        $attempt = $identityId === null
            ? null
            : AdmissionAttempt::query()
                ->where('applicant_identity_id', $identityId)
                ->latest('id')
                ->first();

        if ($attempt === null || ! ($user instanceof User)) {
            return Inertia::render('Applicant/ReleaseDay', [
                'page_state' => 'empty',
                'release_day' => null,
            ]);
        }

        return Inertia::render('Applicant/ReleaseDay', [
            'page_state' => 'ready',
            'release_day' => $this->releaseDay($attempt, $media, $mediaReadiness, $resultAccess),
        ]);
    }

    /**
     * @return array{
     *     appointment: array{status: string, scheduled_at: ?string, room: ?string}|null,
     *     completion: array{completed: bool, completed_at: ?string},
     *     decision: array{state: string, recorded_at: ?string},
     *     result: array{available: bool, review_available: bool, href: ?string, unavailable_reason: ?string},
     *     program_choice: array{available: bool, href: ?string, unavailable_reason: ?string},
     *     follow_ups: list<array{status: 'review_pending'|'appearance_requested', program: ?string, office: ?array{id:int,name:string,building:?string,floor:?string,coordinates_available:bool}, instruction_recorded: bool}>,
     *     online_encounter: array{status: string, href: string, join_available: bool, unavailable_reason: ?string, access_starts_at: ?string, access_ends_at: ?string}|null
     * }
     */
    private function releaseDay(
        AdmissionAttempt $attempt,
        JitsiMediaAdapter $media,
        ConsultationMediaReadiness $mediaReadiness,
        ApplicantResultAccess $resultAccess,
    ): array {
        $assignment = ConsultationAssignment::query()
            ->with(['consultationSession.room'])
            ->where('admission_attempt_id', $attempt->id)
            ->whereIn('status', [ConsultationAssignment::STATUS_ASSIGNED, ConsultationAssignment::STATUS_CONSULTED])
            ->latest('id')
            ->first();
        $latestDecision = ConsultationDecision::query()
            ->where('admission_attempt_id', $attempt->id)
            ->latest('id')
            ->first();
        $completed = AttemptConsultationStatus::isAdvised($attempt);
        $officeMappingAvailable = Schema::hasColumn('departments', 'office_room_id');
        $followUpQuery = ProgramFollowUp::query()
            ->with('program.department')
            ->where('admission_attempt_id', $attempt->id)
            ->whereIn('status', ProgramFollowUp::OPEN_STATUSES)
            ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [ProgramFollowUp::STATUS_APPEARANCE_REQUESTED])
            ->latest('id');
        if ($officeMappingAvailable) {
            $followUpQuery->with('program.department.officeRoom');
        }
        $followUps = $followUpQuery->get()
            ->map(fn (ProgramFollowUp $followUp): array => $this->followUpProjection($followUp, $attempt, $officeMappingAvailable))
            ->all();
        $resultFacts = $resultAccess->forAttempt($attempt);
        $reviewAvailable = $resultFacts['available']
            || $resultFacts['pending']
            || $resultFacts['retracted']
            || $resultFacts['completed_f2f']
            || $resultFacts['artifact_failed']
            || $assignment?->status === ConsultationAssignment::STATUS_CONSULTED
            || ResultRelease::query()
                ->where('admission_attempt_id', $attempt->id)
                ->where('is_current', true)
                ->exists();

        return [
            'appointment' => $assignment === null ? null : [
                'status' => $assignment->status,
                'published' => in_array($assignment->consultationSession?->status, [ConsultationSession::STATUS_PUBLISHED, ConsultationSession::STATUS_IN_PROGRESS, ConsultationSession::STATUS_COMPLETED], true) || $assignment->consultationSession?->published_at !== null,
                'scheduled_at' => $assignment->consultationSession?->scheduled_at?->toIso8601String(),
                'room' => $assignment->consultationSession?->room?->name
                    ?: $assignment->consultationSession?->room?->code,
            ],
            'completion' => [
                'completed' => $completed,
                'completed_at' => $completed ? $latestDecision?->created_at?->toIso8601String() : null,
            ],
            'decision' => [
                'state' => match (true) {
                    $completed => 'completed',
                    $latestDecision?->decision_type === 'defer' => 'deferred',
                    $latestDecision?->decision_type === 'refer' => 'referred',
                    default => 'unrecorded',
                },
                'recorded_at' => $latestDecision?->created_at?->toIso8601String(),
            ],
            'follow_ups' => $followUps,
            'result' => [
                'available' => $resultFacts['available'],
                'review_available' => $reviewAvailable,
                'href' => $reviewAvailable ? route('applicant.results.show') : null,
                'unavailable_reason' => $resultFacts['available']
                    ? null
                    : ($resultFacts['pending']
                        ? 'Your official PDF is being prepared.'
                        : (($resultFacts['retracted'] || $resultFacts['artifact_failed'])
                            ? 'Your result is currently unavailable.'
                            : 'Your result is not yet released.')),
            ],
            'program_choice' => [
                'available' => true,
                'href' => route('applicant.program-choice'),
                'unavailable_reason' => null,
            ],
            'online_encounter' => $this->onlineEncounter($attempt, $media, $mediaReadiness),
        ];
    }

    /**
     * @return array{status: 'review_pending'|'appearance_requested', program: ?string, office: ?array{id:int,name:string,building:?string,floor:?string,coordinates_available:bool}, instruction_recorded: bool}
     */
    private function followUpProjection(
        ProgramFollowUp $followUp,
        AdmissionAttempt $attempt,
        bool $officeMappingAvailable,
    ): array {
        $program = $followUp->program;
        $department = $program?->department;
        $officeRoom = $officeMappingAvailable ? $department?->officeRoom : null;
        if ($officeRoom !== null && (
            ! $officeRoom->is_active
            || (int) $officeRoom->institution_id !== (int) $attempt->institution_id
            || (int) $department->institution_id !== (int) $attempt->institution_id
        )) {
            $officeRoom = null;
        }

        return [
            'status' => $followUp->status === ProgramFollowUp::STATUS_APPEARANCE_REQUESTED
                ? 'appearance_requested'
                : 'review_pending',
            'program' => $program !== null && (int) $program->institution_id === (int) $attempt->institution_id
                ? (string) $program->name
                : null,
            'office' => $officeRoom === null ? null : [
                'id' => (int) $officeRoom->id,
                'name' => (string) $officeRoom->name,
                'building' => $officeRoom->building_name,
                'floor' => $officeRoom->floor_level,
                'coordinates_available' => $officeRoom->hasSpatialCoordinates(),
            ],
            'instruction_recorded' => false,
        ];
    }

    /**
     * @return array{status: string, href: ?string, join_available: bool, unavailable_reason: ?string, access_starts_at: ?string, access_ends_at: ?string}|null
     */
    private function onlineEncounter(
        AdmissionAttempt $attempt,
        JitsiMediaAdapter $media,
        ConsultationMediaReadiness $mediaReadiness,
    ): ?array {
        $encounter = ConsultationEncounter::query()
            ->where('admission_attempt_id', $attempt->id)
            ->where('medium', ConsultationEncounter::MEDIUM_ONLINE)
            ->whereIn('status', [
                ConsultationEncounter::STATUS_INVITED,
                ConsultationEncounter::STATUS_WAITING,
                ConsultationEncounter::STATUS_IN_PROGRESS,
            ])
            ->latest('id')
            ->first();

        if ($encounter === null) {
            return null;
        }

        $mediaReport = $mediaReadiness->report();
        $windowEnd = $media->accessWindowEnd($encounter);
        $joinAvailable = $windowEnd !== null && $mediaReport['ready'];

        return [
            'status' => $encounter->status,
            'href' => $joinAvailable ? route('consultation.encounter.show', $encounter) : null,
            'join_available' => $joinAvailable,
            'unavailable_reason' => ! $mediaReport['ready']
                ? $mediaReport['reason']
                : ($windowEnd === null ? 'The online encounter window is not currently open.' : null),
            'access_starts_at' => $encounter->access_starts_at?->toIso8601String(),
            'access_ends_at' => $encounter->access_ends_at?->toIso8601String(),
        ];
    }
}
