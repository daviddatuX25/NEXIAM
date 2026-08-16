<?php

namespace App\Http\Controllers\Registrar;

use App\Admission\Actions\ReverseEnrollmentStamp;
use App\Admission\Actions\StampAttemptEnrolled;
use App\Admission\Actions\StampAttemptEnrolling;
use App\Http\Controllers\Controller;
use App\Models\Admission\AdmissionAttempt;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

class EnrollmentStampController extends Controller
{
    public function stampEnrolling(Request $request, AdmissionAttempt $attempt, StampAttemptEnrolling $action): RedirectResponse
    {
        try {
            $action->handle(
                $attempt,
                $request->user(),
                $request->input('provisional_program_code'),
                $request->input('paper_reference'),
                $request->string('reason')->toString(),
                $request->string('idempotency_key')->toString(),
                (bool) $request->boolean('acknowledge_pending_default'),
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors(['enrolling' => $exception->getMessage()]);
        }

        return back()->with('status', 'Enrollment processing started.');
    }

    public function stampEnrolled(Request $request, AdmissionAttempt $attempt, StampAttemptEnrolled $action): RedirectResponse
    {
        try {
            $action->handle(
                $attempt,
                $request->user(),
                $request->string('final_program_code')->toString(),
                $request->string('paper_reference')->toString(),
                $request->string('paper_hash')->toString(),
                $request->string('verifier')->toString(),
                $request->string('reason')->toString(),
                $request->string('idempotency_key')->toString(),
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors(['enrolled' => $exception->getMessage()]);
        }

        return back()->with('status', 'Final enrollment recorded.');
    }

    public function reverse(Request $request, AdmissionAttempt $attempt, ReverseEnrollmentStamp $action): RedirectResponse
    {
        try {
            $action->handle(
                $attempt,
                $request->user(),
                $request->string('reason')->toString(),
                $request->string('idempotency_key')->toString(),
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors(['reverse' => $exception->getMessage()]);
        }

        return back()->with('status', 'Enrollment stamp reversed.');
    }
}
