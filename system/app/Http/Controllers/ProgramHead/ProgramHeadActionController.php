<?php

namespace App\Http\Controllers\ProgramHead;

use App\Admission\Actions\DeclineApplicantProgramSelection;
use App\Admission\Actions\MarkAdmissionSupportMessageRead;
use App\Admission\Actions\ResolveProgramFollowUp;
use App\Admission\Actions\SendAdmissionSupportMessage;
use App\Admission\Support\RecordThreadService;
use App\Http\Controllers\Controller;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ProgramFollowUp;
use App\Models\Admission\RecordThread;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

class ProgramHeadActionController extends Controller
{
    public function declineSelection(
        Request $request,
        AdmissionAttempt $attempt,
        DeclineApplicantProgramSelection $action,
    ): RedirectResponse {
        try {
            $action->handle(
                $attempt,
                $request->user(),
                $request->string('program_code')->toString(),
                $request->string('reason')->toString(),
                $request->string('idempotency_key')->toString(),
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors(['decline' => $exception->getMessage()]);
        }

        return back()->with('status', 'Program selection declined.');
    }

    public function resolveFollowUp(
        Request $request,
        ProgramFollowUp $followUp,
        ResolveProgramFollowUp $action,
    ): RedirectResponse {
        try {
            $action->handle(
                $followUp,
                $request->user(),
                $request->string('outcome')->toString(),
                $request->input('reason'),
                $request->string('idempotency_key')->toString(),
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors(['follow_up' => $exception->getMessage()]);
        }

        return back()->with('status', 'Follow-up resolved.');
    }

    public function openInternalThread(
        Request $request,
        AdmissionAttempt $attempt,
        RecordThreadService $threads,
    ): RedirectResponse {
        try {
            $thread = $threads->openInternalThread(
                $attempt,
                $request->integer('program_id') ?: null,
                $request->string('subject')->toString(),
                $request->user(),
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors(['thread' => $exception->getMessage()]);
        }

        return back()->with('status', 'Internal case thread opened.');
    }

    public function sendMessage(
        Request $request,
        RecordThread $thread,
        SendAdmissionSupportMessage $action,
    ): RedirectResponse {
        try {
            $action->handle(
                $thread,
                $request->user(),
                $request->string('body')->toString(),
                $request->string('idempotency_key')->toString(),
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors(['message' => $exception->getMessage()]);
        }

        return back()->with('status', 'Message sent.');
    }

    public function markRead(Request $request, RecordThread $thread, MarkAdmissionSupportMessageRead $action): RedirectResponse
    {
        try {
            $action->handle($thread, $request->user());
        } catch (RuntimeException $exception) {
            return back()->withErrors(['thread' => $exception->getMessage()]);
        }

        return back();
    }
}
