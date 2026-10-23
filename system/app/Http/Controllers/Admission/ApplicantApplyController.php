<?php

namespace App\Http\Controllers\Admission;

use App\Admission\Actions\SubmitAdmissionAttempt;
use App\Admission\Exceptions\DuplicateAdmissionAttemptException;
use App\Admission\Queries\CurrentIntakeQuery;
use App\Admission\Queries\IntakeCatalogQuery;
use App\Admission\Queries\IntakeRequirementsForApplicantQuery;
use App\Admission\Support\ApplicantScope;
use App\Foundation\Queries\ApplicationSuggestionListsQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admission\SubmitAdmissionRequest;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\ApplicationWindow;
use App\Models\Foundation\Institution;
use App\Reports\Services\AdmissionSlipPrintService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * Public applicant apply flow: render the intake form, submit a new admission
 * attempt, and show the one-time confirmation with reference + access key.
 * No staff capability gates — this is the applicant-facing surface.
 */
class ApplicantApplyController extends Controller
{
    public function applyForm(Request $request, IntakeCatalogQuery $catalog, CurrentIntakeQuery $currentIntake, ApplicationSuggestionListsQuery $suggestionLists): Response
    {
        $request->session()->forget(['submission_guidance', 'confirmation_voucher_token']);
        $institution = Institution::current();
        $period = $currentIntake->forInstitution($institution);
        $cycle = $period['cycle'] ?? null;
        $window = $period['window'] ?? null;
        $policy = $period['policy'] ?? null;
        $publishedPolicyRevision = $policy?->publishedRevision;
        $catalogData = $catalog->forInstitution((int) $institution->id);
        $prefill = $request->session()->pull('application_opening_prefill');
        $prefilledEmail = '';
        if (
            is_array($prefill)
            && is_string($prefill['email'] ?? null)
            && filter_var($prefill['email'], FILTER_VALIDATE_EMAIL) !== false
            && is_numeric($prefill['expires_at'] ?? null)
            && (int) $prefill['expires_at'] > time()
        ) {
            $prefilledEmail = $prefill['email'];
        }

        return Inertia::render('Admission/Apply', [
            'opening_alerts_available' => (bool) config('application-opening-alerts.enabled', false),
            'prefilled_email' => $prefilledEmail,
            'page_state' => $window === null ? 'empty' : 'ready',
            'institution' => [
                'id' => $institution->id,
                ...$institution->toPublicArray(),
            ],
            'cycle' => $cycle === null ? null : [
                'id' => $cycle->id,
                'name' => $cycle->name,
            ],
            'window' => $window === null ? null : [
                'id' => $window->id,
                'name' => $window->name,
                'opens_at' => $window->opens_at?->toIso8601String(),
                'closes_at' => $window->closes_at?->toIso8601String(),
            ],
            'intake_suggestions' => $suggestionLists->forPublic((int) $institution->id),
            'programs' => $catalogData['programs'],
            'grade_fields' => [],
            'privacy_policy' => $policy === null || $publishedPolicyRevision === null ? null : [
                'id' => (int) $policy->getKey(),
                'revision_id' => (int) $publishedPolicyRevision->getKey(),
                'revision_number' => (int) $publishedPolicyRevision->revision,
                'title' => $publishedPolicyRevision->title,
                'content' => $publishedPolicyRevision->content,
                'digest' => $publishedPolicyRevision->contentDigest(),
            ],
            'application_conflict' => $request->session()->get('application_conflict'),
        ]);
    }

    public function submit(
        SubmitAdmissionRequest $request,
        SubmitAdmissionAttempt $submit,
        IntakeRequirementsForApplicantQuery $requirements,
    ): RedirectResponse {
        $institution = Institution::current();
        $intakeRequirements = $requirements->forApplicantType(
            (int) $institution->id,
            (string) $request->validated('applicant_type'),
        );
        $cycle = AcademicCycle::query()->findOrFail($request->integer('academic_cycle_id'));
        $window = ApplicationWindow::query()->findOrFail($request->integer('application_window_id'));

        try {
            $result = $submit->handle(
                $institution,
                $cycle,
                $window,
                $request->toIntakeData(),
            );
        } catch (DuplicateAdmissionAttemptException $e) {
            $conflictData = null;
            if ($e->existingAttempt !== null) {
                $conflictData = [
                    'reference_code' => $e->existingAttempt->reference_code,
                    'cycle_name' => $e->existingAttempt->academicCycle?->name ?? $cycle->name,
                    'status' => $e->existingAttempt->status,
                    'applicant_name' => $e->existingAttempt->identity?->fullName() ?? trim("{$request->validated('first_name')} {$request->validated('last_name')}"),
                    'created_at' => $e->existingAttempt->created_at?->format('F d, Y'),
                ];
            }

            return back()
                ->withInput()
                ->with('application_conflict', $conflictData)
                ->withErrors(['identity' => 'An application for this person already exists for the current admission period.']);
        }

        $request->session()->put('confirmation_reference_code', (string) $result['attempt']->reference_code);
        $request->session()->put('submission_guidance', [
            'intake_requirements' => $intakeRequirements,
        ]);

        $request->session()->put(
            'confirmation_voucher_token',
            (string) $result['credential_handoff']['voucher_token'],
        );

        return redirect()->route('apply.confirmation')->with([
            'reference_code' => $result['attempt']->reference_code,
            'access_key' => $result['access_token'],
            'credential_handoff' => $result['credential_handoff'] ?? null,
        ])->withCookie($this->applicantSubmittedCookie($request));
    }

    public function confirmation(Request $request): Response
    {
        $submissionGuidance = $request->session()->get('submission_guidance', []);
        $referenceCode = $request->session()->get('reference_code') ?? $request->session()->get('confirmation_reference_code');

        $printUrl = null;
        if ($referenceCode !== null) {
            $printUrl = route('apply.confirmation.print', [
                'reference' => $referenceCode,
                'hash' => hash_hmac('sha256', (string) $referenceCode, (string) config('app.key')),
            ]);
        }

        cookie()->queue($this->applicantSubmittedCookie($request));

        return Inertia::render('Admission/Confirmation', [
            'page_state' => 'ready',
            // Pull once — plaintext key must not remain in session after render.
            'access_key' => $request->session()->pull('access_key'),
            'credential_handoff' => $request->session()->pull('credential_handoff'),
            'reference_code' => $referenceCode,
            'intake_requirements' => $submissionGuidance['intake_requirements'] ?? null,
            'print_url' => $printUrl,
            'portal_entry_error' => $request->session()->pull('portal_entry_error'),
        ]);
    }

    public function printConfirmationSlip(
        Request $request,
        AdmissionSlipPrintService $service
    ): \Symfony\Component\HttpFoundation\Response {
        $reference = (string) $request->query('reference');
        $hash = (string) $request->query('hash');

        $sessionRef = (string) ($request->session()->get('confirmation_reference_code') ?? $request->session()->get('reference_code') ?? '');
        $validHash = $reference !== '' && hash_equals(hash_hmac('sha256', $reference, (string) config('app.key')), $hash);
        $validSession = $reference !== '' && hash_equals($sessionRef, $reference);

        $attempt = null;
        if ($validHash || $validSession) {
            $attempt = AdmissionAttempt::query()
                ->where('reference_code', $reference)
                ->first();
        } elseif ($request->user()?->applicantAccount()->exists()) {
            $identityId = ApplicantScope::identityIdForUser($request->user());
            if ($identityId !== null) {
                $attempt = AdmissionAttempt::query()
                    ->where('applicant_identity_id', $identityId)
                    ->where('reference_code', $reference)
                    ->first();
            }
        }

        abort_if($attempt === null, 404);

        $pdfDownloadUrl = route('apply.confirmation.print', [
            'reference' => $reference,
            'hash' => $hash,
            'format' => 'pdf',
        ]);

        return $service->renderResponse($attempt, $request, $pdfDownloadUrl);
    }

    private function applicantSubmittedCookie(Request $request): Cookie
    {
        return cookie(
            name: 'nexiam_applicant_submitted',
            value: '1',
            minutes: 60 * 24 * 365,
            path: '/',
            domain: null,
            secure: $request->isSecure(),
            httpOnly: false,
            raw: false,
            sameSite: 'lax',
        );
    }
}
