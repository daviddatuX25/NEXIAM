<?php

namespace App\Http\Controllers\Proctor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Proctor\MarkAttendanceRequest;
use App\Http\Requests\Proctor\MarkSubmissionBulkRequest;
use App\Http\Requests\Proctor\MarkSubmissionRequest;
use App\Models\Applicant;
use App\Models\ExamSession;
use App\Models\User;
use App\Notifications\ExamSessionCompleted;
use App\Notifications\ExamSessionStarted;
use App\Services\ApplicationPipelineService;
use App\Services\AuditService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SessionRosterController extends Controller
{
    public function show(ExamSession $exam_session): Response
    {
        $this->authorize('viewRoster', $exam_session);

        $exam_session->load(['room:id,name,building,capacity', 'proctors:id,name']);

        $applicants = $exam_session->applicants()
            ->with('application:id,reference_number,first_name,middle_name,last_name,suffix')
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'session_applicant_id' => $a->pivot->id,
                'name' => trim(implode(' ', array_filter([
                    $a->application?->first_name,
                    $a->application?->middle_name,
                    $a->application?->last_name,
                    $a->application?->suffix,
                ]))),
                'reference_number' => $a->application?->reference_number ?? '',
                'attendance_status' => $a->pivot->attendance_status ?? 'pending',
                'submission_status' => $a->pivot->submission_status ?? 'pending',
                'attendance_marked_at' => $a->pivot->attendance_marked_at
                    ? Carbon::parse($a->pivot->attendance_marked_at)->toIso8601String()
                    : null,
                'submitted_at' => $a->pivot->submitted_at
                    ? Carbon::parse($a->pivot->submitted_at)->toIso8601String()
                    : null,
            ]);

        $stats = [
            'total' => $applicants->count(),
            'present' => $applicants->where('attendance_status', 'present')->count(),
            'absent' => $applicants->where('attendance_status', 'absent')->count(),
            'pending' => $applicants->where('attendance_status', 'pending')->count(),
            'submitted' => $applicants->where('submission_status', 'submitted')->count(),
            'present_pending_submission' => $applicants
                ->where('attendance_status', 'present')
                ->where('submission_status', 'pending')
                ->count(),
        ];

        $user = request()->user();
        $isWithinStartWindow = $exam_session->isWithinStartWindow();
        $isAssignedProctor = $exam_session->proctors()->where('users.id', $user->id)->exists();
        $canOverrideSchedule = $user->hasAnyRole(['super_admin', 'test_administrator']);

        $isProctorView = $user->hasAnyRole(['proctor']) && ! $user->hasAnyRole(['super_admin', 'registrar_administrator']);
        $breadcrumbParent = $isProctorView
            ? ['label' => 'My Sessions', 'href' => '/proctor/my-sessions']
            : ['label' => 'Exam Monitoring', 'href' => '/admin/exam-monitoring'];

        return Inertia::render('Proctor/SessionRoster', [
            'session' => array_merge($exam_session->toArray(), [
                'date' => $exam_session->date?->format('Y-m-d'),
                'is_within_start_window' => $isWithinStartWindow,
                'can_override_schedule' => $canOverrideSchedule,
                'is_within_window' => $exam_session->isWithinExamWindow(),
                'is_past_end' => $exam_session->isPastEndTime(),
                'is_past_date' => $exam_session->isPastDate(),
                'extended_end_time' => $exam_session->extended_end_time,
                'system_notes' => $exam_session->system_notes,
            ]),
            'applicants' => $applicants->values()->all(),
            'stats' => $stats,
            'breadcrumbParent' => $breadcrumbParent,
        ]);
    }

    public function storeAttendance(MarkAttendanceRequest $request, ExamSession $exam_session): RedirectResponse|JsonResponse
    {
        $this->authorize('manageRoster', $exam_session);

        $session = $exam_session;
        if ($session->status !== ExamSession::STATUS_IN_PROGRESS) {
            if ($request->wantsJson()) {
                return response()->json(['message' => 'Session must be in progress to mark attendance.'], 409);
            }

            return back()->with('error', 'Session must be in progress to mark attendance.');
        }

        if ($session->isPastEndTime()) {
            $message = 'The exam window has ended. Attendance cannot be marked.';
            if ($request->wantsJson()) {
                return response()->json(['message' => $message], 422);
            }

            return back()->with('error', $message);
        }

        $applicantId = (int) $request->validated('applicant_id');
        $pivot = DB::table('exam_session_applicant')
            ->where('exam_session_id', $session->id)
            ->where('applicant_id', $applicantId)
            ->first();

        if (! $pivot) {
            if ($request->wantsJson()) {
                return response()->json(['message' => 'Applicant not assigned to this session.'], 404);
            }

            return back()->with('error', 'Applicant not assigned to this session.');
        }

        $currentStatus = $pivot->attendance_status ?? 'pending';
        if ($currentStatus !== 'pending') {
            if ($request->wantsJson()) {
                return response()->json(['message' => 'Attendance already marked.'], 409);
            }

            return response()->json(['message' => 'Attendance already marked.'], 409);
        }

        $status = $request->validated('status');
        DB::table('exam_session_applicant')
            ->where('id', $pivot->id)
            ->update([
                'attendance_status' => $status,
                'attendance_marked_at' => now(),
                'attendance_marked_by' => $request->user()->id,
                'updated_at' => now(),
            ]);

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Attendance updated.'], 200);
        }

        // Pipeline hook: advance to attended when marked present
        if ($status === 'present') {
            $applicant = Applicant::with('application')->find($applicantId);
            if ($applicant?->application) {
                app(ApplicationPipelineService::class)->transition(
                    $applicant->application,
                    'attended',
                    ['session_id' => $exam_session->id, 'marked_at' => now()->toIso8601String()]
                );
            }
        }

        app(AuditService::class)->log('attendance.recorded', ExamSession::class, $exam_session->id, [], [
            'applicant_id' => $applicantId,
            'status' => $status,
        ]);

        return back()->with('success', 'Attendance marked.');
    }

    public function storeSubmission(MarkSubmissionRequest $request, ExamSession $exam_session): RedirectResponse|JsonResponse
    {
        $session = $exam_session;
        if ($session->status !== ExamSession::STATUS_IN_PROGRESS) {
            $message = 'Session must be in progress.';

            return $request->wantsJson() ? response()->json(['message' => $message], 409) : response()->json(['message' => $message], 409);
        }

        if ($session->isPastEndTime()) {
            return response()->json(['message' => 'The exam window has ended. Submissions cannot be recorded.'], 422);
        }

        $applicantId = (int) $request->validated('applicant_id');
        $pivot = DB::table('exam_session_applicant')
            ->where('exam_session_id', $session->id)
            ->where('applicant_id', $applicantId)
            ->first();

        if (! $pivot) {
            if ($request->wantsJson()) {
                return response()->json(['message' => 'Applicant not assigned to this session.'], 404);
            }

            return back()->with('error', 'Applicant not assigned to this session.');
        }

        if (($pivot->attendance_status ?? 'pending') !== 'present') {
            return response()->json(['message' => 'Applicant must be marked present first.'], 409);
        }

        if (($pivot->submission_status ?? 'pending') === 'submitted') {
            return response()->json(['message' => 'Already submitted.'], 409);
        }

        DB::table('exam_session_applicant')
            ->where('id', $pivot->id)
            ->update([
                'submission_status' => 'submitted',
                'submitted_at' => now(),
                'submitted_to' => $request->user()->id,
                'updated_at' => now(),
            ]);

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Submission recorded.'], 200);
        }

        // Pipeline hook: advance to submitted
        $applicant = Applicant::with('application')->find($applicantId);
        if ($applicant?->application) {
            app(ApplicationPipelineService::class)->transition(
                $applicant->application,
                'submitted',
                ['session_id' => $exam_session->id, 'submitted_at' => now()->toIso8601String()]
            );
        }

        app(AuditService::class)->log('submission.recorded', ExamSession::class, $exam_session->id, [], [
            'applicant_id' => $applicantId,
        ]);

        return back()->with('success', 'Submission recorded.');
    }

    public function storeSubmissionBulk(MarkSubmissionBulkRequest $request, ExamSession $exam_session): RedirectResponse|JsonResponse
    {
        $session = $exam_session;
        if ($session->status !== ExamSession::STATUS_IN_PROGRESS) {
            $message = 'Session must be in progress.';

            return $request->wantsJson() ? response()->json(['message' => $message], 409) : back()->with('error', $message);
        }

        if ($session->isPastEndTime()) {
            $message = 'The exam window has ended. Submissions cannot be recorded.';

            return $request->wantsJson() ? response()->json(['message' => $message], 422) : back()->with('error', $message);
        }

        $applicantIds = $request->validated('applicant_ids') ?? [];
        $pivots = DB::table('exam_session_applicant')
            ->where('exam_session_id', $session->id)
            ->where('attendance_status', 'present')
            ->where('submission_status', 'pending');

        if (! empty($applicantIds)) {
            $applicantIds = array_map('intval', $applicantIds);
            $pivots->whereIn('applicant_id', $applicantIds);
        }

        $pivots = $pivots->get();
        $user = $request->user();
        $now = now();

        foreach ($pivots as $pivot) {
            DB::table('exam_session_applicant')
                ->where('id', $pivot->id)
                ->update([
                    'submission_status' => 'submitted',
                    'submitted_at' => $now,
                    'submitted_to' => $user->id,
                    'updated_at' => $now,
                ]);
        }

        $count = count($pivots);
        $message = $count === 0
            ? 'No applicants to mark as submitted.'
            : "Submission recorded for {$count} applicant(s).";

        // Pipeline hook: advance each to submitted
        if ($count > 0) {
            $pipeline = app(ApplicationPipelineService::class);
            $bulkApplicantIds = collect($pivots)->pluck('applicant_id')->all();
            Applicant::whereIn('id', $bulkApplicantIds)->with('application')->get()
                ->each(function (Applicant $applicant) use ($pipeline, $exam_session) {
                    if ($applicant->application) {
                        $pipeline->transition(
                            $applicant->application,
                            'submitted',
                            ['session_id' => $exam_session->id, 'submitted_at' => now()->toIso8601String()]
                        );
                    }
                });
        }

        app(AuditService::class)->log('submission.bulk_recorded', ExamSession::class, $exam_session->id, [], [
            'count' => $count,
        ], "Bulk recorded {$count} submissions");

        if ($request->wantsJson()) {
            return response()->json(['message' => $message, 'count' => $count], 200);
        }

        return back()->with('success', $message);
    }

    public function start(ExamSession $exam_session): RedirectResponse|JsonResponse
    {
        $this->authorize('manageRoster', $exam_session);

        if ($exam_session->status !== ExamSession::STATUS_PUBLISHED) {
            return response()->json(['message' => 'Session must be published to start.'], 409);
        }

        if ($exam_session->hasNoApplicants()) {
            $message = 'Cannot start a session with no applicants assigned.';
            if (request()->wantsJson()) {
                return response()->json(['message' => $message], 409);
            }

            return back()->with('error', $message);
        }

        $withinWindow = $exam_session->isWithinStartWindow();
        $user = request()->user();
        $canOverride = $user->hasAnyRole(['super_admin', 'test_administrator']);

        if (! $withinWindow && ! $canOverride) {
            $message = 'Outside scheduled window. Only an admin can start the session outside the schedule.';
            if (request()->wantsJson()) {
                return response()->json(['message' => $message], 409);
            }

            return back()->with('error', $message);
        }

        if (! $withinWindow && $canOverride) {
            // TODO: log session_start_override to audit when logging feature is implemented (actor_id, optional reason, timestamp).
        }

        $exam_session->update([
            'status' => ExamSession::STATUS_IN_PROGRESS,
            'started_at' => now(),
        ]);

        // Notify proctors and test_admins (D-07, D-08)
        $exam_session->load('proctors');
        $recipients = $exam_session->proctors;
        $testAdmins = User::whereHas('roles', fn ($q) => $q->where('name', 'test_administrator'))->get();
        Notification::send(
            $recipients->merge($testAdmins)->unique('id'),
            new ExamSessionStarted($exam_session)
        );

        app(AuditService::class)->log('session.proctor_started', ExamSession::class, $exam_session->id, [], [
            'session_id' => $exam_session->id,
        ]);

        if (request()->wantsJson()) {
            return response()->json(['message' => 'Session started.'], 200);
        }

        return back()->with('success', 'Session started.');
    }

    public function close(ExamSession $exam_session): RedirectResponse|JsonResponse
    {
        $this->authorize('manageRoster', $exam_session);

        if ($exam_session->status !== ExamSession::STATUS_IN_PROGRESS) {
            return response()->json(['message' => 'Session must be in progress to close.'], 409);
        }

        $exam_session->update([
            'status' => ExamSession::STATUS_COMPLETED,
            'closed_at' => now(),
        ]);

        // Notify proctors and test_admins (D-07, D-08)
        $exam_session->load('proctors');
        $recipients = $exam_session->proctors;
        $testAdmins = User::whereHas('roles', fn ($q) => $q->where('name', 'test_administrator'))->get();
        Notification::send(
            $recipients->merge($testAdmins)->unique('id'),
            new ExamSessionCompleted($exam_session)
        );

        app(AuditService::class)->log('session.proctor_closed', ExamSession::class, $exam_session->id, [], [
            'session_id' => $exam_session->id,
        ]);

        if (request()->wantsJson()) {
            return response()->json(['message' => 'Session closed.'], 200);
        }

        // Auto-open a grading session if one doesn't already exist
        $exam_session->load('applicants');
        $gradingSession = $exam_session->gradingSession
            ?? app(\App\Services\GradingSessionService::class)->openForExamSession($exam_session, auth()->user());

        return redirect()->route('admin.grading.sessions.show', $gradingSession->id)
            ->with('success', 'Session closed. Grading session is ready.');
    }

    /**
     * Bulk update attendance or submission status for a list of applicants in a session.
     */
    public function bulkAttendance(Request $request, ExamSession $exam_session): RedirectResponse
    {
        $this->authorize('manageRoster', $exam_session);

        $data = $request->validate([
            'applicant_ids' => ['required', 'array'],
            'applicant_ids.*' => ['integer'],
            'status' => ['required', Rule::in(['present', 'absent', 'submitted'])],
        ]);

        $column = $data['status'] === 'submitted' ? 'submission_status' : 'attendance_status';

        DB::table('exam_session_applicant')
            ->where('exam_session_id', $exam_session->id)
            ->whereIn('applicant_id', $data['applicant_ids'])
            ->update([$column => $data['status']]);

        // Pipeline hook: advance to attended when bulk-marking present
        if ($data['status'] === 'present') {
            $pipeline = app(ApplicationPipelineService::class);
            Applicant::whereIn('id', $data['applicant_ids'])->with('application')->get()
                ->each(function (Applicant $applicant) use ($pipeline, $exam_session) {
                    if ($applicant->application) {
                        $pipeline->transition($applicant->application, 'attended', [
                            'session_id' => $exam_session->id,
                        ]);
                    }
                });
        }

        app(AuditService::class)->log('attendance.bulk_recorded', ExamSession::class, $exam_session->id, [], [
            'count' => count($data['applicant_ids']),
            'status' => $data['status'],
        ]);

        return back()->with('success', 'Bulk update applied.');
    }

    public function extend(Request $request, ExamSession $exam_session): RedirectResponse|JsonResponse
    {
        $this->authorize('manageRoster', $exam_session);

        $data = $request->validate([
            'extension_minutes' => ['integer', 'min:1', 'max:120'],
        ]);

        $minutes = $data['extension_minutes'] ?? 30;

        // Calculate effective current end time
        $currentTime = $exam_session->extended_end_time ?? $exam_session->end_time;

        if (! $currentTime) {
            return response()->json(['message' => 'Session has no end time to extend.'], 400);
        }

        $newEndTime = Carbon::createFromFormat('H:i:s', $currentTime)->addMinutes($minutes)->format('H:i:s');

        $exam_session->update([
            'extended_end_time' => $newEndTime,
        ]);

        app(AuditService::class)->log('session.extended', ExamSession::class, $exam_session->id, [], [
            'minutes' => $minutes,
            'new_end_time' => $newEndTime,
        ]);

        $message = "Session extended by {$minutes} minutes.";
        if ($request->wantsJson()) {
            return response()->json(['message' => $message, 'new_end_time' => $newEndTime], 200);
        }

        return back()->with('success', $message);
    }
}
