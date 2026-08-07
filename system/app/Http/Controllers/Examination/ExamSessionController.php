<?php

namespace App\Http\Controllers\Examination;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Examination\Actions\AssignApplicantToExamSession;
use App\Examination\Actions\CloseExamSession;
use App\Examination\Actions\CreateExamSession;
use App\Examination\Actions\ExtendExamSession;
use App\Examination\Actions\PublishExamSession;
use App\Http\Controllers\Controller;
use App\Http\Requests\Examination\AssignExamRequest;
use App\Http\Requests\Examination\CreateExamSessionRequest;
use App\Http\Requests\Examination\PublishExamSessionRequest;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Examination\ExamSession;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Room;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class ExamSessionController extends Controller
{
    public function index(Request $request, CapabilityAuthorizer $authorizer): Response
    {
        if (! $authorizer->allows($request->user(), CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE)) {
            return Inertia::render('Examination/Sessions', ['page_state' => 'denied', 'sessions' => []]);
        }

        $sessions = ExamSession::query()->with('room:id,code')->latest()->limit(30)->get();

        return Inertia::render('Examination/Sessions', [
            'page_state' => $sessions->isEmpty() ? 'empty' : 'ready',
            'sessions' => $sessions,
        ]);
    }

    public function store(CreateExamSessionRequest $request, CreateExamSession $action): RedirectResponse
    {
        try {
            $action->handle(
                AcademicCycle::query()->findOrFail($request->integer('academic_cycle_id')),
                Room::query()->findOrFail($request->integer('room_id')),
                $request->string('code')->toString(),
                $request->string('starts_at')->toString(),
                $request->string('effective_end_at')->toString(),
                $request->user(),
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors(['schedule' => $exception->getMessage()])->with('page_state', 'conflict');
        }

        return back()->with('status', 'Exam session created.');
    }

    public function assign(AssignExamRequest $request, ExamSession $session, AssignApplicantToExamSession $action): RedirectResponse
    {
        try {
            $action->handle(
                $session,
                AdmissionAttempt::query()->findOrFail($request->integer('admission_attempt_id')),
                $request->user(),
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors(['assign' => $exception->getMessage()])->with('page_state', 'conflict');
        }

        return back()->with('status', 'Applicant assigned.');
    }

    public function publish(PublishExamSessionRequest $request, ExamSession $session, PublishExamSession $action): RedirectResponse
    {
        try {
            $action->handle($session, $request->user(), $request->integer('expected_version'));
        } catch (RuntimeException $exception) {
            return back()->withErrors(['publish' => $exception->getMessage()])->with('page_state', 'conflict');
        }

        return back()->with('status', 'Session published.');
    }

    public function close(Request $request, ExamSession $session, CloseExamSession $action): RedirectResponse
    {
        $validated = $request->validate([
            'expected_version' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $action->handle($session, $request->user(), (int) $validated['expected_version']);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['close' => $exception->getMessage()])->with('page_state', 'conflict');
        }

        return back()->with('status', 'Session closed; grading opened.');
    }

    public function extend(Request $request, ExamSession $session, ExtendExamSession $action): RedirectResponse
    {
        $validated = $request->validate([
            'expected_version' => ['required', 'integer', 'min:1'],
            'effective_end_at' => ['required', 'date'],
        ]);

        try {
            $action->handle(
                $session,
                $request->user(),
                (int) $validated['expected_version'],
                \Illuminate\Support\Carbon::parse($validated['effective_end_at']),
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors(['extend' => $exception->getMessage()])->with('page_state', 'conflict');
        }

        return back()->with('status', 'Session deadline extended.');
    }
}
