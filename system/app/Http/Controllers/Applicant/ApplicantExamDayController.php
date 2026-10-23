<?php

namespace App\Http\Controllers\Applicant;

use App\Admission\Support\ApplicantScope;
use App\Examination\Actions\ReconcileOverdueExamSessions;
use App\Http\Controllers\Controller;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Examination\ExamAssignment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class ApplicantExamDayController extends Controller
{
    public function show(Request $request, ReconcileOverdueExamSessions $reconcileOverdue): Response|JsonResponse
    {
        $user = $request->user();
        $identityId = $user === null ? null : ApplicantScope::identityIdForUser($user);
        $attempt = $identityId === null ? null : AdmissionAttempt::query()
            ->where('applicant_identity_id', $identityId)
            ->latest('id')
            ->first();

        if ($user === null || $identityId === null) {
            $examDay = $this->emptyProjection('denied');
        } elseif ($attempt === null) {
            $examDay = $this->emptyProjection('empty');
        } else {
            $assignment = ExamAssignment::query()
                ->active()
                ->with([
                    'examSession:id,code,status,starts_at,effective_end_at,started_at,closed_at,version',
                    'attendanceRecord.recordedBy:id,name',
                ])
                ->where('admission_attempt_id', $attempt->id)
                ->latest('id')
                ->first();
            $session = $assignment?->examSession;
            if ($session !== null) {
                $reconciledSession = $reconcileOverdue->reconcileSessionIfOverdue($session);
                if ($reconciledSession->status !== $session->status) {
                    $assignment->refresh()->load([
                        'examSession:id,code,status,starts_at,effective_end_at,started_at,closed_at,version',
                        'attendanceRecord.recordedBy:id,name',
                    ]);
                    $session = $assignment->examSession;
                }
            }
            $attendance = $assignment?->attendanceRecord;
            $now = now();

            $examDay = [
                'page_state' => $assignment === null ? 'empty' : 'ready',
                'attendance' => $attendance === null ? null : [
                    'outcome' => $attendance->outcome,
                    'recorded_at' => $attendance->recorded_at?->toIso8601String(),
                    'recorder' => $attendance->recordedBy?->name,
                ],
                'session' => $session === null ? null : [
                    'code' => $session->code,
                    'status' => $session->status,
                    'scheduled_starts_at' => $session->starts_at?->toIso8601String(),
                    'scheduled_date' => $session->starts_at?->toDateString(),
                    'scheduled_start_time' => $session->starts_at?->format('H:i'),
                    'scheduled_end_time' => $session->effective_end_at?->format('H:i'),
                    'started_at' => $session->started_at?->toIso8601String(),
                    'effective_end_at' => $session->effective_end_at?->toIso8601String(),
                    'closed_at' => $session->closed_at?->toIso8601String(),
                    'version' => $session->version,
                ],
                'server_now' => $now->toIso8601String(),
                'help_href' => '/applicant/admission-support?group=offices&draft_reason=exam_time_extension',
            ];
        }

        if ($request->header('X-Applicant-Snapshot') === 'true') {
            return response()
                ->json(['exam_day' => $examDay])
                ->header('Cache-Control', 'private, no-store')
                ->header('Vary', 'X-Applicant-Snapshot');
        }

        return Inertia::render('Applicant/ExamDay', ['exam_day' => $examDay]);
    }

    /** @return array{page_state:string,attendance:null,session:null,server_now:string,help_href:string} */
    private function emptyProjection(string $state): array
    {
        return [
            'page_state' => $state,
            'attendance' => null,
            'session' => null,
            'server_now' => now()->toIso8601String(),
            'help_href' => '/applicant/admission-support?group=offices&draft_reason=exam_time_extension',
        ];
    }
}
