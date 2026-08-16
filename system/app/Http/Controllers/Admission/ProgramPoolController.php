<?php

namespace App\Http\Controllers\Admission;

use App\Admission\Actions\OpenProgramFollowUp;
use App\Admission\Actions\SetGuidanceProgramPool;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admission\SetGuidanceProgramPoolRequest;
use App\Models\Admission\AdmissionAttempt;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

class ProgramPoolController extends Controller
{
    public function setPool(SetGuidanceProgramPoolRequest $request, AdmissionAttempt $attempt, SetGuidanceProgramPool $action): RedirectResponse
    {
        try {
            $action->handle(
                $attempt,
                $request->user(),
                $request->string('default_program_code')->toString(),
                $request->array('options'),
                $request->input('reason'),
                $request->string('idempotency_key')->toString(),
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors(['pool' => $exception->getMessage()]);
        }

        return back()->with('status', 'Program pool and advised default set.');
    }

    public function openFollowUp(Request $request, AdmissionAttempt $attempt, OpenProgramFollowUp $action): RedirectResponse
    {
        try {
            $action->handle(
                $attempt,
                $request->user(),
                $request->string('program_code')->toString(),
                $request->string('reason')->toString(),
                $request->string('idempotency_key')->toString(),
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors(['follow_up' => $exception->getMessage()]);
        }

        return back()->with('status', 'Targeted follow-up opened.');
    }
}
