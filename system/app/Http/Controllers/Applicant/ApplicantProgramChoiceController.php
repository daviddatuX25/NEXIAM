<?php

namespace App\Http\Controllers\Applicant;

use App\Admission\Actions\AcknowledgeAssistedProgramSelection;
use App\Admission\Actions\CancelApplicantProgramSelection;
use App\Admission\Actions\SelectApplicantProgram;
use App\Admission\Actions\TransferApplicantProgramSelection;
use App\Applicant\Support\ApplicantProgramChoiceProjection;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admission\AcknowledgeProgramSelectionRequest;
use App\Http\Requests\Admission\CancelProgramSelectionRequest;
use App\Http\Requests\Admission\ProgramSelectionRequest;
use App\Models\Admission\AdmissionAttempt;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class ApplicantProgramChoiceController extends Controller
{
    public function show(Request $request, ApplicantProgramChoiceProjection $projection): Response
    {
        $attempt = $this->attemptFor($request);

        if ($attempt === null) {
            return Inertia::render('Applicant/ProgramChoice', ['page_state' => 'denied']);
        }

        return Inertia::render('Applicant/ProgramChoice', [
            'page_state' => 'ready',
            'support' => $projection->for($attempt),
        ]);
    }

    public function acknowledge(
        AcknowledgeProgramSelectionRequest $request,
        AcknowledgeAssistedProgramSelection $action,
    ): RedirectResponse {
        $attempt = $this->attemptFor($request);

        if ($attempt === null) {
            return back()->withErrors(['selection' => 'No admission attempt is linked to this account.']);
        }

        try {
            $action->handle($attempt, $request->user(), $request->string('idempotency_key')->toString());
        } catch (RuntimeException $exception) {
            return back()->withErrors(['selection' => $exception->getMessage()]);
        }

        return back()->with('status', 'Guidance Recommendation acknowledged.');
    }

    public function select(
        ProgramSelectionRequest $request,
        SelectApplicantProgram $action,
    ): RedirectResponse {
        $attempt = $this->attemptFor($request);

        if ($attempt === null) {
            return back()->withErrors(['selection' => 'No admission attempt is linked to this account.']);
        }

        try {
            $action->handle(
                $attempt,
                $request->user(),
                $request->string('program_code')->toString(),
                $request->string('idempotency_key')->toString(),
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors(['selection' => $exception->getMessage()]);
        }

        return back()->with('status', 'Program selected.');
    }

    public function transfer(
        ProgramSelectionRequest $request,
        TransferApplicantProgramSelection $action,
    ): RedirectResponse {
        $attempt = $this->attemptFor($request);

        if ($attempt === null) {
            return back()->withErrors(['selection' => 'No admission attempt is linked to this account.']);
        }

        try {
            $action->handle(
                $attempt,
                $request->user(),
                $request->string('program_code')->toString(),
                $request->string('idempotency_key')->toString(),
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors(['selection' => $exception->getMessage()]);
        }

        return back()->with('status', 'Program choice transferred.');
    }

    public function cancel(
        CancelProgramSelectionRequest $request,
        CancelApplicantProgramSelection $action,
    ): RedirectResponse {
        $attempt = $this->attemptFor($request);

        if ($attempt === null) {
            return back()->withErrors(['selection' => 'No admission attempt is linked to this account.']);
        }

        try {
            $action->handle(
                $attempt,
                $request->user(),
                $request->string('reason')->toString(),
                $request->string('idempotency_key')->toString(),
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors(['selection' => $exception->getMessage()]);
        }

        return back()->with('status', 'Program choice cancelled.');
    }

    private function attemptFor(Request $request): ?AdmissionAttempt
    {
        $account = $request->user()?->applicantAccount;

        if ($account === null) {
            return null;
        }

        return AdmissionAttempt::query()
            ->where('applicant_identity_id', $account->applicant_identity_id)
            ->latest('id')
            ->first();
    }
}
