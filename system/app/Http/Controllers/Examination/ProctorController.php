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
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class ProctorController extends Controller
{
    public function dashboard(Request $request, CapabilityAuthorizer $authorizer): Response
    {
        $user = $request->user();
        if (! $authorizer->allows($user, CapabilityCatalog::GUIDANCE_PROCTOR_ASSIGN)) {
            return Inertia::render('Examination/Proctor', ['page_state' => 'denied', 'duties' => []]);
        }

        $duties = ProctorDuty::query()
            ->where('user_id', $user->id)
            ->where('status', ProctorDuty::STATUS_ACTIVE)
            ->with('examSession:id,code')
            ->get();

        return Inertia::render('Examination/Proctor', [
            'page_state' => $duties->isEmpty() ? 'empty' : 'ready',
            'duties' => $duties,
        ]);
    }

    public function assignDuty(Request $request, ExamSession $session, AssignProctorDuty $action): RedirectResponse
    {
        $proctor = User::query()->findOrFail($request->integer('proctor_user_id'));
        $action->handle($session, $proctor, $request->user(), $request->string('idempotency_key')->toString());

        return back()->with('status', 'Proctor assigned.');
    }

    public function revoke(ProctorDuty $duty, Request $request, RevokeProctorDuty $action): RedirectResponse
    {
        $action->handle($duty, $request->user());

        return back()->with('status', 'Proctor duty revoked.');
    }

    public function recordAttendance(IdempotentProctorRequest $request, ExamAssignment $assignment, RecordExamAttendance $action): RedirectResponse
    {
        if (! ProctorDutyGate::hasActiveDuty($request->user(), $assignment->exam_session_id)) {
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

    public function recordSubmission(IdempotentProctorRequest $request, ExamAssignment $assignment, RecordExamSubmission $action): RedirectResponse
    {
        if (! ProctorDutyGate::hasActiveDuty($request->user(), $assignment->exam_session_id)) {
            abort(403);
        }

        try {
            $action->handle($assignment, $request->user(), $request->string('idempotency_key')->toString());
        } catch (RuntimeException $exception) {
            return back()->withErrors(['submission' => $exception->getMessage()])->with('page_state', 'validation');
        }

        return back()->with('status', 'Submission recorded.');
    }
}
