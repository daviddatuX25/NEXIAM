<?php

namespace App\Http\Controllers\Applicant;

use App\Admission\Actions\AmendPendingAdmissionAttempt;
use App\Admission\Actions\AuthenticateApplicantWithAccessKey;
use App\Admission\Actions\RedeemApplicantLoginVoucher;
use App\Admission\Actions\ResubmitApplicationCorrection;
use App\Admission\Queries\AttemptRequirementChecklistQuery;
use App\Admission\Support\ApplicantAccessKey;
use App\Admission\Support\ApplicantScope;
use App\Applicant\Support\ApplicantPortalProjection;
use App\Auth\Support\StaffSurface;
use App\Foundation\Queries\ApplicationSuggestionListsQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Applicant\ApplicantLoginRequest;
use App\Http\Requests\Applicant\RedeemApplicantLoginVoucherRequest;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\AdmissionDocumentCorrectionRequest;
use App\Models\Foundation\Program;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/**
 * Domain 1: Applicant & Student Admission Journey
 *
 * Controls applicant self-service access, dossier verification, and milestone progression.
 *
 * Security Architecture:
 * - Passwordless 2FA using high-entropy 8-character Access Keys + Date of Birth.
 * - Dual-layer rate limiting (IP and key-hash buckets) to neutralize brute-force attacks.
 *
 * Key Lifecycles & State Transitions:
 * - Application Review -> Exam Scheduling -> Test Day -> Day of Release / Academic Counseling -> Program Endorsement -> Enrollment Clearance.
 * - Evaluator correction notices trigger targeted dossier amendments without full application re-entry.
 *
 * Pedagogical / Interactive Walkthrough Pairing:
 * - Paired with 'applicant-portal' and 'applicant-my-application' tours via Xiam NPC (applicant-mentor persona).
 */
class ApplicantPortalController extends Controller
{
    public function loginForm(): Response
    {
        $demo = null;
        if (app()->environment(['local', 'testing'])) {
            // Temporary default (2026-08-15): prefill the richest portal demo
            // scenario so the founder can click through most hub states without
            // typing. PORT-DEFL = released online + consultation booked +
            // Guidance default ✦. Other keys can be typed manually.
            $demo = [
                'access_key' => 'PORT-DEFL',
                'birthdate' => '2003-09-25',
                'label' => 'Portal demo (default) — released online + Guidance default ✦',
                'portals' => [
                    ['key' => 'PORT-ONLN', 'label' => 'Released online · consultation booked'],
                    ['key' => 'PORT-F2F2', 'label' => 'Released f2f · claim at consultation'],
                    ['key' => 'PORT-DEFL', 'label' => 'Guidance default ✦ · ack optional'],
                ],
            ];
        }

        return Inertia::render('Applicant/Login', [
            'page_state' => 'ready',
            'demo' => $demo,
        ]);
    }

    public function login(
        ApplicantLoginRequest $request,
        AuthenticateApplicantWithAccessKey $action,
    ): RedirectResponse {
        $accessKey = (string) $request->input('access_key');
        $keyHash = ApplicantAccessKey::hash($accessKey);
        $keyThrottle = 'applicant-login:key:'.$keyHash;
        $ipThrottle = 'applicant-login:ip:'.$request->ip();

        // 1. Check IP rate limit (10 attempts per minute)
        if (RateLimiter::tooManyAttempts($ipThrottle, 10)) {
            $seconds = RateLimiter::availableIn($ipThrottle);

            return back()
                ->withInput($request->except('access_key'))
                ->withErrors(['access_key' => "Too many attempts from this IP address. Please try again in {$seconds} seconds."]);
        }

        // 2. Check Per-Key Lockout (3 failed attempts -> 15-minute lock)
        if (RateLimiter::tooManyAttempts($keyThrottle, 3)) {
            $seconds = RateLimiter::availableIn($keyThrottle);
            $minutes = (int) ceil($seconds / 60);

            return back()
                ->withInput($request->except('access_key'))
                ->withErrors(['access_key' => "This access key is temporarily locked due to repeated failed attempts. Please try again in {$minutes} minute(s)."]);
        }

        try {
            $user = $action->handle(
                $accessKey,
                $request->date('birthdate')->format('Y-m-d'),
            );

            RateLimiter::clear($keyThrottle);
            RateLimiter::clear($ipThrottle);
        } catch (RuntimeException $exception) {
            RateLimiter::hit($keyThrottle, 900);
            RateLimiter::hit($ipThrottle, 60);

            $remaining = RateLimiter::remaining($keyThrottle, 3);
            $warning = $remaining > 0
                ? " ({$remaining} attempt".($remaining === 1 ? '' : 's').' remaining before lockout)'
                : '';

            return back()
                ->withInput($request->except('access_key'))
                ->withErrors(['access_key' => 'Invalid access key or birthdate.'.$warning]);
        }

        $this->establishApplicantSession($request, $user);

        return redirect()->intended(route('applicant.portal'));
    }

    public function redeemVoucher(
        RedeemApplicantLoginVoucherRequest $request,
        RedeemApplicantLoginVoucher $redeem,
    ): RedirectResponse {
        $validated = $request->validated();

        $user = $redeem->handle($validated['voucher_token']);

        if ($user === null) {
            return redirect()
                ->route('applicant.login')
                ->withErrors([
                    'voucher' => 'This temporary QR login voucher has expired or was already used. Enter your birthdate below to sign in with your access key.',
                ]);
        }

        $this->establishApplicantSession($request, $user);

        return redirect()->intended(route('applicant.portal'));
    }

    public function enterFromConfirmation(
        Request $request,
        RedeemApplicantLoginVoucher $redeem,
    ): RedirectResponse {
        $referenceCode = $request->session()->get('confirmation_reference_code');
        $attempt = is_string($referenceCode)
            ? AdmissionAttempt::query()->where('reference_code', $referenceCode)->first()
            : null;
        $user = $request->user();

        if ($user !== null) {
            if ($user->applicantAccount()->exists() && ! $user->staffProfile()->exists()) {
                if ($attempt === null || ApplicantScope::attemptBelongsToUser($attempt, $user)) {
                    return redirect()->route('applicant.portal');
                }

                return redirect()->route('apply.confirmation')->with(
                    'portal_entry_error',
                    'This confirmation belongs to a different applicant account. Sign out and reopen your own confirmation.',
                );
            }

            return redirect()->route('apply.confirmation')->with(
                'portal_entry_error',
                'This browser is signed in to a different account. Sign out before entering the applicant portal.',
            );
        }

        $voucherToken = $request->session()->pull('confirmation_voucher_token');
        if (! is_string($voucherToken) || $voucherToken === '') {
            return redirect()->route('applicant.login')->withErrors([
                'voucher' => 'This one-time portal handoff is no longer available. Sign in with your access key and birthdate.',
            ]);
        }

        $user = $redeem->handle($voucherToken);
        if ($user === null) {
            return redirect()->route('applicant.login')->withErrors([
                'voucher' => 'This one-time portal handoff expired or was already used. Sign in with your access key and birthdate.',
            ]);
        }

        if ($attempt === null || ! ApplicantScope::attemptBelongsToUser($attempt, $user)) {
            return redirect()->route('apply.confirmation')->with(
                'portal_entry_error',
                'The portal handoff does not match this confirmation. Sign in with your applicant access key.',
            );
        }

        $this->establishApplicantSession($request, $user);

        return redirect()->intended(route('applicant.portal'));
    }

    private function establishApplicantSession(Request $request, User $user): void
    {
        $intendedUrl = $request->session()->get('url.intended');
        $intendedPath = is_string($intendedUrl) ? parse_url($intendedUrl, PHP_URL_PATH) : null;

        $request->session()->invalidate();
        Auth::guard('web')->login($user);
        $request->session()->regenerate();
        $request->session()->put('auth.surface', 'applicant');

        if (is_string($intendedUrl) && is_string($intendedPath) && ($intendedPath === '/applicant' || str_starts_with($intendedPath, '/applicant/'))) {
            $request->session()->put('url.intended', $intendedUrl);
        }
    }

    /** @deprecated D-085 — password setup removed; keep route as alias. */
    public function setupForm(): RedirectResponse
    {
        return redirect()->route('applicant.login');
    }

    public function dashboard(Request $request, StaffSurface $staffSurface): Response|RedirectResponse
    {
        if ($staffSurface->allows($request)) {
            return redirect()->route('staff.home');
        }

        $projection = app(ApplicantPortalProjection::class)->for($request->user());

        if ($projection === null) {
            return Inertia::render('Applicant/Portal', [
                'page_state' => 'empty',
            ]);
        }

        return Inertia::render('Applicant/Portal', [
            'page_state' => 'ready',
            ...$projection,
        ]);
    }

    public function applicationShow(Request $request, AdmissionAttempt $attempt): Response
    {
        $user = $request->user();

        abort_unless(ApplicantScope::attemptBelongsToUser($attempt, $user), 403);

        $identity = $attempt->identity;

        $preferences = array_values(array_filter([
            $attempt->program_preference_1_code,
            $attempt->program_preference_2_code,
            $attempt->program_preference_3_code,
        ]));
        $documentChecklist = app(AttemptRequirementChecklistQuery::class)->forApplicant($attempt, $user);
        $requestHistory = AdmissionDocumentCorrectionRequest::applicantProjectionFor($attempt)['requests'];

        $hasExamAssignment = $attempt->examAssignments()->exists();
        $isPending = $attempt->status === AdmissionAttempt::STATUS_PENDING;
        $needsCorrection = $attempt->status === AdmissionAttempt::STATUS_NEEDS_CORRECTION;
        $canAmend = $isPending && ! $hasExamAssignment;
        $cannotAmendReason = null;
        if (! $canAmend && ! $needsCorrection) {
            if ($hasExamAssignment) {
                $cannotAmendReason = 'An examination session has already been assigned to this application.';
            } elseif ($attempt->status === AdmissionAttempt::STATUS_ACCEPTED) {
                $cannotAmendReason = 'This application has already been accepted and entered the examination pipeline.';
            } else {
                $cannotAmendReason = 'This application is locked and can no longer be edited from the portal.';
            }
        }

        return Inertia::render('Applicant/MyApplication', [
            'application' => [
                'reference_code' => $attempt->reference_code,
                'status' => $attempt->status,
                'submitted_at' => $attempt->created_at?->toIso8601String(),
                'needs_correction' => $needsCorrection,
                'can_amend' => $canAmend,
                'has_exam_session' => $hasExamAssignment,
                'amend_disabled_reason' => $cannotAmendReason,
                'correction_notes' => $attempt->correction_notes,
                'correction_href' => ($needsCorrection || $canAmend)
                    ? route('applicant.correction', ['attempt' => $attempt->id])
                    : null,
                'personal' => [
                    'name' => $identity?->fullName(),
                    'birthdate' => $identity?->birthdate?->toDateString(),
                    'sex' => $identity?->sex,
                    'contact_phone' => $attempt->contact_phone,
                    'contact_email' => $attempt->contact_email ?: $identity->email,
                    'address' => implode(', ', array_filter([
                        $attempt->address_line,
                        $attempt->city,
                        $attempt->province,
                        $attempt->zip_code,
                    ])),
                    'applicant_type' => $attempt->applicant_type,
                    'last_school' => $attempt->last_school_enrolled,
                    'strand' => $attempt->strand,
                ],
                'preferences' => $preferences,
                'grades' => [],
            ],
            'document_checklist' => [
                ...$documentChecklist,
                'request_history' => $requestHistory,
            ],
        ]);
    }

    public function correctionForm(Request $request, AdmissionAttempt $attempt, ApplicationSuggestionListsQuery $suggestions): Response|RedirectResponse
    {
        $user = $request->user();
        abort_unless(ApplicantScope::attemptBelongsToUser($attempt, $user), 403);

        $mode = match ($attempt->status) {
            AdmissionAttempt::STATUS_PENDING => 'amend',
            AdmissionAttempt::STATUS_NEEDS_CORRECTION => 'resubmit',
            default => null,
        };

        if ($mode === null) {
            return redirect()
                ->route('applicant.portal')
                ->with(
                    'status',
                    $attempt->status === AdmissionAttempt::STATUS_ACCEPTED
                        ? 'This application is already accepted and in the exam pipeline. Changes need registrar authority — contact the admissions office.'
                        : 'This application can no longer be edited from the portal.',
                );
        }

        if ($mode === 'amend' && $attempt->examAssignments()->exists()) {
            return redirect()
                ->route('applicant.application', $attempt)
                ->with('status', 'An examination session has already been assigned. Application details are locked.');
        }

        $attempt->loadMissing([
            'identity:id,first_name,middle_name,last_name,suffix,birthdate,sex,email,phone',
        ]);
        $identity = $attempt->identity;
        $catalog = $this->intakeCatalogForInstitution((int) $attempt->institution_id, array_values(array_filter([
            $attempt->preferred_program_code,
            $attempt->program_preference_1_code,
            $attempt->program_preference_2_code,
            $attempt->program_preference_3_code,
        ])));

        return Inertia::render('Applicant/Correction', [
            'page_state' => 'ready',
            'mode' => $mode,
            'programs' => $catalog['programs'],
            'intake_suggestions' => $suggestions->forPublic((int) $attempt->institution_id),
            'grade_fields' => [],
            'attempt' => [
                'id' => $attempt->id,
                'reference_code' => $attempt->reference_code,
                'status' => $attempt->status,
                'version' => $attempt->version,
                'correction_notes' => $attempt->correction_notes,
                'identity' => [
                    'first_name' => $identity?->first_name,
                    'middle_name' => $identity?->middle_name,
                    'last_name' => $identity?->last_name,
                    'suffix' => $identity?->suffix,
                    'birthdate' => $identity?->birthdate?->toDateString(),
                    'sex' => $identity?->sex,
                    'email' => $identity?->email,
                    'phone' => $identity?->phone,
                ],
                'applicant_type' => $attempt->applicant_type,
                'contact_email' => $attempt->contact_email,
                'contact_phone' => $attempt->contact_phone,
                'program_preference_1_code' => $attempt->program_preference_1_code,
                'program_preference_2_code' => $attempt->program_preference_2_code,
                'program_preference_3_code' => $attempt->program_preference_3_code,
                'address_line' => $attempt->address_line,
                'city' => $attempt->city,
                'province' => $attempt->province,
                'zip_code' => $attempt->zip_code,
                'last_school_enrolled' => $attempt->last_school_enrolled,
                'strand' => $attempt->strand,
                'grades' => [],
            ],
        ]);
    }

    public function resubmitCorrection(
        Request $request,
        AdmissionAttempt $attempt,
        ResubmitApplicationCorrection $resubmit,
        AmendPendingAdmissionAttempt $amend,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless(ApplicantScope::attemptBelongsToUser($attempt, $user), 403);
        $preservedProgramCodes = array_values(array_filter([
            $attempt->preferred_program_code,
            $attempt->program_preference_1_code,
            $attempt->program_preference_2_code,
            $attempt->program_preference_3_code,
        ]));
        $programPreferenceRule = static fn () => Rule::exists('programs', 'code')->where(
            fn ($query) => $query->where('institution_id', $attempt->institution_id)
                ->where('is_active', true)
                ->where(fn ($eligibility) => $eligibility->where('is_admission_offered', true)
                    ->orWhereIn('code', $preservedProgramCodes)),
        );

        $rules = [
            'expected_version' => ['required', 'integer', 'min:1'],
            'identity_first_name' => ['nullable', 'string', 'max:120'],
            'identity_middle_name' => ['nullable', 'string', 'max:120'],
            'identity_last_name' => ['nullable', 'string', 'max:120'],
            'identity_suffix' => ['nullable', 'string', 'max:32'],
            'identity_sex' => ['nullable', Rule::in(['male', 'female'])],
            'identity_email' => ['nullable', 'email', 'max:255'],
            'identity_phone' => ['nullable', 'string', 'max:64'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:64'],
            'applicant_type' => ['nullable', Rule::in(['new', 'transferee'])],
            'program_preference_1_code' => [
                'nullable',
                'string',
                'max:32',
                $programPreferenceRule(),
            ],
            'program_preference_2_code' => [
                'nullable',
                'string',
                'max:32',
                'different:program_preference_1_code',
                $programPreferenceRule(),
            ],
            'program_preference_3_code' => [
                'nullable',
                'string',
                'max:32',
                'different:program_preference_1_code',
                'different:program_preference_2_code',
                $programPreferenceRule(),
            ],
            'address_line' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'province' => ['nullable', 'string', 'max:120'],
            'zip_code' => ['nullable', 'string', 'max:32'],
            'last_school_enrolled' => ['nullable', 'string', 'max:255'],
            'strand' => ['nullable', 'string', 'max:255'],
            'resubmission_notes' => ['nullable', 'string', 'max:2000'],
        ];

        $validated = $request->validate($rules);

        try {
            if ($attempt->status === AdmissionAttempt::STATUS_PENDING) {
                $amend->handle($attempt, $user, (int) $validated['expected_version'], $validated);

                return redirect()
                    ->route('applicant.portal')
                    ->with('status', 'Details updated. Your application is still pending review.');
            }

            if ($attempt->status === AdmissionAttempt::STATUS_NEEDS_CORRECTION) {
                $resubmit->handle($attempt, $user, (int) $validated['expected_version'], $validated);

                return redirect()
                    ->route('applicant.portal')
                    ->with('status', 'Correction resubmitted. Registrar will review again.');
            }
        } catch (RuntimeException $e) {
            return back()->withErrors(['version' => $e->getMessage()]);
        }

        return redirect()
            ->route('applicant.portal')
            ->withErrors([
                'version' => 'After acceptance, portal edits are frozen. Ask the admissions office for an authorized change.',
            ]);
    }

    /**
     * @return array{programs: Collection<int, Program>, grade_fields: array{}}
     */
    private function intakeCatalogForInstitution(int $institutionId, array $preservedProgramCodes = []): array
    {
        $programs = Program::query()
            ->where('institution_id', $institutionId)
            ->where('is_active', true)
            ->where(function ($query) use ($preservedProgramCodes): void {
                $query->where('is_admission_offered', true);
                if ($preservedProgramCodes !== []) {
                    $query->orWhereIn('code', $preservedProgramCodes);
                }
            })
            ->orderBy('name')
            ->get(['code', 'name']);

        return [
            'programs' => $programs,
            'grade_fields' => [],
        ];
    }
}
