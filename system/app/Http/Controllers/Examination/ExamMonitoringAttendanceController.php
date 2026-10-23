<?php

namespace App\Http\Controllers\Examination;

use App\Admission\Support\ApplicantReferenceResolver;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Examination\Actions\AccommodateApplicantExamSession;
use App\Examination\Actions\RecordExamAttendance;
use App\Examination\Actions\RecordExamSubmission;
use App\Examination\Actions\RecordExamSubmissionBulk;
use App\Examination\Actions\SessionQrCheckinAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Examination\AccommodateApplicantRequest;
use App\Http\Requests\Examination\IdempotentExamConductRequest;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\Institution;
use App\Models\User;
use App\Testing\PracticeWorkspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

class ExamMonitoringAttendanceController extends Controller
{
    public function recordAttendance(
        IdempotentExamConductRequest $request,
        ExamSession $session,
        ExamAssignment $assignment,
        RecordExamAttendance $action,
        CapabilityAuthorizer $authorizer,
    ): RedirectResponse {
        if (! $this->canRecord($request->user(), $session, $assignment, $authorizer)) {
            abort(403);
        }

        try {
            $action->handle(
                $assignment,
                $request->user(),
                $request->string('outcome')->toString(),
                $request->string('idempotency_key')->toString(),
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors(['attendance' => $exception->getMessage()])->with('page_state', 'validation');
        }

        return back()->with('status', 'Attendance recorded.');
    }

    public function recordSubmission(
        IdempotentExamConductRequest $request,
        ExamSession $session,
        ExamAssignment $assignment,
        RecordExamSubmission $action,
        CapabilityAuthorizer $authorizer,
    ): RedirectResponse {
        if (! $this->canRecord($request->user(), $session, $assignment, $authorizer)) {
            abort(403);
        }

        try {
            $action->handle(
                $assignment,
                $request->user(),
                $request->string('idempotency_key')->toString(),
                $request->filled('repair_reason') ? $request->string('repair_reason')->toString() : null,
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors(['submission' => $exception->getMessage()])->with('page_state', 'validation');
        }

        return back()->with('status', 'Submission recorded.');
    }

    public function accommodateApplicant(
        AccommodateApplicantRequest $request,
        ExamSession $session,
        AccommodateApplicantExamSession $action,
        CapabilityAuthorizer $authorizer,
    ): RedirectResponse|JsonResponse {
        $sessionInstitutionId = $this->assertSessionScope($request->user(), $session);
        if (! $authorizer->allowsAny(
            $request->user(),
            CapabilityCatalog::examSessionManagementCapabilities(),
            ['institution_id' => $sessionInstitutionId, 'office' => 'guidance'],
        )) {
            abort(403);
        }

        $codes = collect($request->validated('reference_codes') ?? [])
            ->whenEmpty(fn ($c) => $c->push($request->validated('reference_code')))
            ->map(fn ($code) => trim((string) $code))
            ->filter()
            ->unique()
            ->values();

        $attempts = $codes->map(function (string $code) use ($sessionInstitutionId, $session): AdmissionAttempt {
            $query = AdmissionAttempt::query()->forWorkspace()
                ->where('institution_id', $sessionInstitutionId)
                ->where('academic_cycle_id', $session->academic_cycle_id);
            ApplicantReferenceResolver::applyMatch($query, $code, $session->academicCycle);

            return $query->firstOrFail();
        });

        $assignments = [];
        try {
            foreach ($attempts as $attempt) {
                $assignments[] = $action->handle(
                    $session,
                    $attempt,
                    $request->user(),
                    $request->validated('reason_category'),
                    $request->validated('reason_notes'),
                );
            }
        } catch (RuntimeException $exception) {
            if ($request->expectsJson() && ! $request->header('X-Inertia')) {
                return response()->json([
                    'message' => $exception->getMessage(),
                    'errors' => ['accommodate' => [$exception->getMessage()]],
                ], 422);
            }

            return back()->withErrors(['accommodate' => $exception->getMessage()])->with('page_state', 'validation');
        }

        $count = count($assignments);
        $statusMessage = $count === 1
            ? 'Applicant successfully accommodated into this session.'
            : "{$count} applicants successfully accommodated into this session.";

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'status' => 'ok',
                'message' => $statusMessage,
                'assignment_id' => $assignments[0]->id ?? null,
                'assignment_ids' => array_map(fn ($a) => $a->id, $assignments),
                'count' => $count,
            ]);
        }

        return back()->with('status', $statusMessage);
    }

    public function lookupApplicant(
        Request $request,
        ExamSession $session,
        CapabilityAuthorizer $authorizer,
    ): JsonResponse {
        $sessionInstitutionId = $this->assertSessionScope($request->user(), $session);
        if (! $this->canActOnSession($request->user(), $session, $authorizer)) {
            abort(403);
        }

        $code = trim((string) $request->query('reference_code'));

        if ($code === '') {
            return response()->json(['found' => false, 'message' => 'Reference code is required.'], 400);
        }

        $query = AdmissionAttempt::query()->forWorkspace()
            ->where('institution_id', $sessionInstitutionId)
            ->where('academic_cycle_id', $session->academic_cycle_id);
        ApplicantReferenceResolver::applyMatch($query, $code, $session->academicCycle);
        $attempt = $query
            ->with(['identity', 'examAssignments.examSession', 'examAssignments.attendanceRecord', 'examAssignments.submissionRecord'])
            ->first();

        if ($attempt === null) {
            return response()->json(['found' => false, 'message' => 'Applicant reference code not found.'], 404);
        }

        $activeAssignment = $attempt->examAssignments->first(fn ($assignment) => $assignment->isActive());
        $priorMissedAssignment = $activeAssignment === null
            ? $attempt->examAssignments->sortByDesc('id')->first(fn ($assignment) => in_array($assignment->attendanceRecord?->outcome, ['absent', 'void'], true))
            : null;
        $contextAssignment = $activeAssignment ?? $priorMissedAssignment;
        $anySubmission = $attempt->examAssignments->contains(fn ($assignment) => $assignment->submissionRecord !== null);
        $fullName = trim("{$attempt->identity?->first_name} {$attempt->identity?->last_name}");
        $alreadyInSession = $activeAssignment !== null && (int) $activeAssignment->exam_session_id === (int) $session->id;
        $otherSession = $contextAssignment?->examSession;
        $attendanceOutcome = $contextAssignment?->attendanceRecord?->outcome;
        $ineligibleReason = null;
        if (! $attempt->isExamEligible()) {
            $ineligibleReason = 'Ineligible: Applicant admission attempt is not yet accepted.';
        } elseif ($anySubmission) {
            $ineligibleReason = 'Ineligible: Applicant has already submitted an exam paper in another session.';
        } elseif ($alreadyInSession) {
            $ineligibleReason = 'Applicant is already assigned to this exam session.';
        } elseif ($activeAssignment !== null && $otherSession?->status === ExamSession::STATUS_COMPLETED && $attendanceOutcome !== 'absent') {
            $ineligibleReason = 'Ineligible: Cannot accommodate an examinee from a completed session unless marked absent.';
        } elseif ($activeAssignment !== null && $otherSession?->status === ExamSession::STATUS_IN_PROGRESS && in_array($attendanceOutcome, ['present', 'void'], true)) {
            $ineligibleReason = 'Ineligible: Examinee is already marked present or void in an in-progress session.';
        }

        return response()->json([
            'found' => true,
            'reference_code' => $attempt->reference_code,
            'name' => $fullName,
            'has_prior_submission' => $anySubmission,
            'already_in_session' => $alreadyInSession,
            'is_eligible' => $ineligibleReason === null,
            'ineligible_reason' => $ineligibleReason,
            'current_session_code' => $otherSession?->code,
            'current_session_status' => $otherSession?->status,
            'current_session_room' => $otherSession?->room?->name ?? $otherSession?->room?->code,
            'current_session_starts_at' => $otherSession?->starts_at?->toIso8601String(),
            'attendance_outcome' => $attendanceOutcome,
        ]);
    }

    private function canRecord(
        User $user,
        ExamSession $session,
        ExamAssignment $assignment,
        CapabilityAuthorizer $authorizer,
    ): bool {
        $sessionInstitutionId = $this->assertSessionScope($user, $session);
        if ($assignment->exam_session_id !== $session->id) {
            return false;
        }

        if ($authorizer->allowsAny(
            $user,
            CapabilityCatalog::examSessionManagementCapabilities(),
            ['institution_id' => $sessionInstitutionId, 'office' => 'guidance'],
        )) {
            return true;
        }

        return false;
    }

    public function qrCheckin(
        Request $request,
        ExamSession $session,
        SessionQrCheckinAction $action,
        CapabilityAuthorizer $authorizer,
    ): JsonResponse {
        if (! $this->canActOnSession($request->user(), $session, $authorizer)) {
            abort(403);
        }

        $validated = $request->validate([
            'qr_code' => ['required', 'string', 'max:512'],
            'idempotency_key' => ['required', 'string', 'max:128'],
        ]);

        try {
            $result = $action->handle(
                $session,
                $request->user(),
                $validated['qr_code'],
                $validated['idempotency_key'],
            );
        } catch (RuntimeException $exception) {
            return response()->json([
                'status' => 'error',
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json($result);
    }

    public function recordSubmissionBulk(
        Request $request,
        ExamSession $session,
        RecordExamSubmissionBulk $action,
        CapabilityAuthorizer $authorizer,
    ): RedirectResponse {
        if (! $this->canActOnSession($request->user(), $session, $authorizer)) {
            abort(403);
        }

        $validated = $request->validate([
            'batch_key' => ['required', 'string', 'max:128'],
        ]);

        try {
            $records = $action->handle(
                $session,
                $request->user(),
                $validated['batch_key'],
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors(['bulk_submission' => $exception->getMessage()])->with('page_state', 'validation');
        }

        return back()->with('status', "{$records->count()} submission(s) recorded.");
    }

    private function canActOnSession(
        User $user,
        ExamSession $session,
        CapabilityAuthorizer $authorizer,
    ): bool {
        $sessionInstitutionId = $this->assertSessionScope($user, $session);
        if ($authorizer->allowsAny(
            $user,
            CapabilityCatalog::examSessionManagementCapabilities(),
            ['institution_id' => $sessionInstitutionId, 'office' => 'guidance'],
        )) {
            return true;
        }

        return false;
    }

    private function assertSessionScope(User $user, ExamSession $session): int
    {
        $session->loadMissing('academicCycle', 'room');
        $sessionInstitutionId = (int) ($session->academicCycle?->institution_id ?? $session->room?->institution_id);
        $actorInstitutionId = (int) ($user->staffProfile?->institution_id ?? Institution::current()->id);

        if ($sessionInstitutionId !== $actorInstitutionId
            || (string) ($session->practice_fixture_set_id ?? '') !== (string) (app(PracticeWorkspace::class)->currentId() ?? '')) {
            abort(404);
        }

        return $sessionInstitutionId;
    }
}
