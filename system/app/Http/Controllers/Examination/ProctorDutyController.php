<?php

namespace App\Http\Controllers\Examination;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Examination\Actions\AssignProctorDuty;
use App\Examination\Actions\RecordExamAttendance;
use App\Examination\Actions\RecordExamSubmission;
use App\Examination\Actions\RevokeProctorDuty;
use App\Examination\Support\ProctorDutyGate;
use App\Http\Controllers\Controller;
use App\Http\Requests\Examination\IdempotentProctorRequest;
use App\Models\Examination\ExamAssignment;
use App\Models\Examination\ExamSession;
use App\Models\Examination\ProctorDuty;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Duty-scoped exam conduct (D-015/D-016). This is NOT a standalone proctor
 * page — it serves the Exam Monitoring surface: Test Administrators assign and
 * revoke proctor duties (GUIDANCE_PROCTOR_ASSIGN), and attendance/submission
 * are recorded by Test Administrators or by proctors carrying an active duty
 * on that session (ProctorDutyGate).
 */
class ProctorDutyController extends Controller
{
    public function assignDuty(Request $request, ExamSession $session, AssignProctorDuty $action): RedirectResponse
    {
        $validated = $request->validate([
            'proctor_user_id' => ['required', 'integer', 'exists:users,id'],
            'idempotency_key' => ['required', 'string', 'max:128'],
        ]);

        $proctor = User::query()->findOrFail((int) $validated['proctor_user_id']);

        try {
            $action->handle($session, $proctor, $request->user(), $validated['idempotency_key']);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['proctor' => $exception->getMessage()])->with('page_state', 'validation');
        }

        return back()->with('status', 'Proctor assigned.');
    }

    public function revoke(ProctorDuty $duty, Request $request, RevokeProctorDuty $action): RedirectResponse
    {
        try {
            $action->handle($duty, $request->user());
        } catch (RuntimeException $exception) {
            return back()->withErrors(['proctor' => $exception->getMessage()])->with('page_state', 'validation');
        }

        return back()->with('status', 'Proctor duty revoked.');
    }

    public function recordAttendance(
        IdempotentProctorRequest $request,
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
        IdempotentProctorRequest $request,
        ExamSession $session,
        ExamAssignment $assignment,
        RecordExamSubmission $action,
        CapabilityAuthorizer $authorizer,
    ): RedirectResponse {
        if (! $this->canRecord($request->user(), $session, $assignment, $authorizer)) {
            abort(403);
        }

        try {
            $action->handle($assignment, $request->user(), $request->string('idempotency_key')->toString());
        } catch (RuntimeException $exception) {
            return back()->withErrors(['submission' => $exception->getMessage()])->with('page_state', 'validation');
        }

        return back()->with('status', 'Submission recorded.');
    }

    /**
     * Attendance/submission is an exam-conduct act. Test Administrators hold it
     * inherently (schedule capability, D-016); a proctor holds it only for the
     * sessions where they carry an active duty (D-015).
     */
    private function canRecord(
        User $user,
        ExamSession $session,
        ExamAssignment $assignment,
        CapabilityAuthorizer $authorizer,
    ): bool {
        if ($assignment->exam_session_id !== $session->id) {
            return false;
        }

        if ($authorizer->allowsAny($user, CapabilityCatalog::examSessionManagementCapabilities())) {
            return true;
        }

        return ProctorDutyGate::hasActiveDuty($user, $session->id);
    }
}
