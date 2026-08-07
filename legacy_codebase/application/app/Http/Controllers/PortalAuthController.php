<?php

namespace App\Http\Controllers;

use App\Http\Requests\PortalForgotPasswordRequest;
use App\Http\Requests\PortalLoginRequest;
use App\Http\Requests\PortalSetupRequest;
use App\Mail\ApplicantResetPasswordMail;
use App\Models\Applicant;
use App\Models\Application;
use App\Models\ConsultationSummary;
use App\Models\ExamSession;
use App\Models\GradingSession;
use App\Models\SystemSetting;
use App\Services\AdmissionSlipService;
use App\Services\AuditService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PortalAuthController extends Controller
{
    /**
     * Show portal login form.
     */
    public function create(): Response|RedirectResponse
    {
        if (Auth::guard('applicant')->check()) {
            return redirect()->route('portal.dashboard');
        }

        return Inertia::render('Portal/Login', [
            'errors' => session()->get('errors')?->getBag('default')?->getMessages() ?? [],
        ]);
    }

    /**
     * Handle portal login. Per 08-API-SPEC: account must have password set.
     */
    public function store(PortalLoginRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $applicant = Applicant::where('email', $validated['email'])->first();

        if (! $applicant || ! $applicant->hasCompletedSetup()) {
            Log::info('Applicant login failed', [
                'email' => $validated['email'],
                'ip' => $request->ip(),
                'reason' => $applicant ? 'setup_not_complete' : 'not_found',
            ]);
            app(AuditService::class)->log('applicant.login_failed', null, null, [], [
                'email' => $validated['email'],
                'ip' => $request->ip(),
                'reason' => $applicant ? 'setup_not_complete' : 'not_found',
            ], 'Applicant login failed');

            return back()->withErrors([
                'email' => 'These credentials do not match our records or your account is not yet set up.',
            ])->onlyInput('email');
        }

        if (Auth::guard('applicant')->attempt(
            ['email' => $validated['email'], 'password' => $validated['password']],
            true
        )) {
            $request->session()->regenerate();
            Log::info('Applicant login', [
                'applicant_id' => Auth::guard('applicant')->id(),
                'ip' => $request->ip(),
            ]);
            app(AuditService::class)->log('applicant.login', Applicant::class, Auth::guard('applicant')->id(), [], [
                'applicant_id' => Auth::guard('applicant')->id(),
                'ip' => $request->ip(),
            ], 'Applicant login');

            return redirect()->intended(route('portal.dashboard'))->with('success', 'Welcome back! You are now signed in.');
        }

        Log::info('Applicant login failed', [
            'email' => $validated['email'],
            'ip' => $request->ip(),
        ]);
        app(AuditService::class)->log('applicant.login_failed', null, null, [], [
            'email' => $validated['email'],
            'ip' => $request->ip(),
        ], 'Applicant login failed');

        return back()->withErrors([
            'email' => 'These credentials do not match our records.',
        ])->onlyInput('email');
    }

    /**
     * Show password setup form (initial setup via token from acceptance email).
     */
    public function setupShow(string $token): Response|RedirectResponse
    {
        $applicant = Applicant::where('setup_token', $token)->first();

        if (! $applicant || ! $applicant->isSetupTokenValid()) {
            return redirect()->route('login')->with('error', 'This setup link is invalid or has expired.');
        }

        return Inertia::render('Portal/Setup', [
            'token' => $token,
            'email' => $applicant->email,
        ]);
    }

    /**
     * Set initial password and invalidate setup token.
     */
    public function setupStore(PortalSetupRequest $request, string $token): RedirectResponse
    {
        $applicant = Applicant::where('setup_token', $token)->first();

        if (! $applicant || ! $applicant->isSetupTokenValid()) {
            return redirect()->route('login')->with('error', 'This setup link is invalid or has expired.');
        }

        $applicant->password = Hash::make($request->validated('password'));
        $applicant->setup_token = null;
        $applicant->setup_token_expires_at = null;
        $applicant->save();

        Log::info('Applicant account activated', ['applicant_id' => $applicant->id]);
        app(AuditService::class)->log('applicant.setup_completed', Applicant::class, $applicant->id, [], [
            'applicant_id' => $applicant->id,
        ], 'Applicant setup completed');

        return redirect()->route('login')->with('success', 'Your password has been set. You can now sign in.');
    }

    /**
     * Show forgot password form.
     */
    public function forgotPasswordCreate(): Response
    {
        return Inertia::render('Portal/ForgotPassword');
    }

    /**
     * Send password reset link. Always return 200/success to prevent email enumeration.
     */
    public function forgotPasswordStore(PortalForgotPasswordRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $applicant = Applicant::where('email', $validated['email'])->first();

        if ($applicant && $applicant->hasCompletedSetup()) {
            $token = Str::random(64);
            $expiresAt = now()->addMinutes(config('auth.reset_token_expires_minutes', 15));

            DB::table('applicant_password_reset_tokens')->upsert(
                [
                    'email' => $applicant->email,
                    'token' => $token,
                    'expires_at' => $expiresAt,
                ],
                ['email'],
                ['token', 'expires_at']
            );

            Mail::to($applicant->email)->send(new ApplicantResetPasswordMail($applicant, $token));
        }

        return back()->with('success', 'If an account exists for that email, we have sent password reset instructions.');
    }

    /**
     * Show reset password form (from forgot-password email link).
     */
    public function resetShow(string $token): Response|RedirectResponse
    {
        $record = DB::table('applicant_password_reset_tokens')
            ->where('token', $token)
            ->where('expires_at', '>', now())
            ->first();

        if (! $record) {
            return redirect()->route('portal.forgot-password')->with('error', 'This reset link is invalid or has expired.');
        }

        return Inertia::render('Portal/Reset', [
            'token' => $token,
            'email' => $record->email,
        ]);
    }

    /**
     * Reset password and invalidate token.
     */
    public function resetStore(PortalSetupRequest $request, string $token): RedirectResponse
    {
        $record = DB::table('applicant_password_reset_tokens')
            ->where('token', $token)
            ->where('expires_at', '>', now())
            ->first();

        if (! $record) {
            return redirect()->route('portal.forgot-password')->with('error', 'This reset link is invalid or has expired.');
        }

        $applicant = Applicant::where('email', $record->email)->first();
        if (! $applicant) {
            return redirect()->route('portal.forgot-password')->with('error', 'Account not found.');
        }

        $applicant->password = Hash::make($request->validated('password'));
        $applicant->save();

        DB::table('applicant_password_reset_tokens')->where('email', $applicant->email)->delete();

        Log::info('Applicant password reset', ['applicant_id' => $applicant->id]);
        app(AuditService::class)->log('applicant.password_reset', Applicant::class, $applicant->id, [], [
            'applicant_id' => $applicant->id,
        ], 'Applicant password reset');

        return redirect()->route('login')->with('success', 'Your password has been reset. You can now sign in.');
    }

    /**
     * Portal dashboard with process status tracker, exam schedule, and score release.
     */
    public function dashboard(): Response
    {
        $applicant = Auth::guard('applicant')->user();
        $applicant->load([
            'application',
            'consultationSummary',
            'examSessions' => fn ($q) => $q
                ->where(function ($query) {
                    $query->where('status', 'published')
                        ->orWhere(function ($query) {
                            $query->where('type', 'direct')
                                ->where('status', 'in_progress');
                        });
                })
                ->with(['room', 'gradingSession'])
                ->orderBy('date'),
        ]);

        $application = $applicant->application;
        $name = $application
            ? trim(($application->first_name ?? '').' '.($application->middle_name ?? '').' '.($application->last_name ?? ''))
            : $applicant->email;
        $referenceNumber = $application?->reference_number ?? '—';

        $primarySession = $applicant->examSessions->first();
        $pivot = $primarySession?->pivot;
        $gradingSession = $primarySession?->gradingSession;
        $summary = $applicant->consultationSummary;

        $statusTracker = $this->buildStatusTracker(
            $applicant,
            $application,
            $primarySession,
            $pivot,
            $gradingSession,
            $summary
        );
        $examSchedule = $this->buildExamSchedule($primarySession);
        $scoreRelease = $this->buildScoreRelease($primarySession);

        $consultation = [
            'status' => $summary?->status ?? 'pending',
            'summary' => $summary && $summary->status === 'released' ? [
                'recommended_course_id' => $summary->recommended_course_id,
                'counselor_comments' => $summary->counselor_comments,
            ] : null,
        ];

        // R7 — If f2f mode, hide result data from the portal
        $releaseMode = SystemSetting::releaseMode();
        if ($releaseMode === 'f2f') {
            $consultation = ['status' => 'pending', 'summary' => null];
        }

        return Inertia::render('Portal/Dashboard', [
            'applicant' => [
                'name' => $name,
                'reference_number' => $referenceNumber,
                'email' => $applicant->email,
            ],
            'application' => $application ? [
                'is_editable' => $application->isEditableByApplicant(),
                'assigned_session_status' => $application->assignedSessionStatus(),
            ] : null,
            'status_tracker' => $statusTracker,
            'exam_schedule' => $examSchedule,
            'score_release' => $scoreRelease,
            'consultation' => $consultation,
            // Widget shows when AI companion is enabled via system setting
            'ai_companion_enabled' => SystemSetting::aiCompanionEnabled(),
            'results_blocked' => ($releaseMode === 'f2f'),
            'admission_slip_enabled' => SystemSetting::admissionSlipEnabled(),
        ]);
    }

    public function admissionSlipPdf()
    {
        if (! AdmissionSlipService::isEnabled()) {
            abort(403, 'Admission slip distribution is not enabled.');
        }

        $applicant = Auth::guard('applicant')->user();
        $application = $applicant->application;

        if (! $application || $application->status !== 'accepted') {
            abort(403, 'Admission slip is only available for accepted applications.');
        }

        $pdf = app(AdmissionSlipService::class)->generatePdf($application);
        $filename = "admission-slip-{$application->reference_number}.pdf";

        return $pdf->download($filename);
    }

    public function admissionSlipView(): Response
    {
        if (! AdmissionSlipService::isEnabled()) {
            abort(403, 'Admission slip distribution is not enabled.');
        }

        $applicant = Auth::guard('applicant')->user();
        $application = $applicant->application;

        if (! $application || $application->status !== 'accepted') {
            abort(403, 'Admission slip is only available for accepted applications.');
        }

        $templateHtml = app(AdmissionSlipService::class)->renderHtml($application);

        return Inertia::render('Applications/AdmissionSlipSingle', [
            'applicationId' => (string) $application->id,
            'printed' => false,
            'applicant' => [
                'name' => trim(implode(' ', array_filter([$application->first_name, $application->middle_name, $application->last_name, $application->suffix]))),
                'reference' => $application->reference_number,
            ],
            'templateHtml' => $templateHtml,
            'templateError' => null,
            'paperSize' => 'a4',
            'orientation' => 'portrait',
            'logicalUnit' => 'full',
            'paperOptions' => [],
        ]);
    }

    /**
     * Build ordered process stages for the applicant (portal status tracker).
     *
     * @return array<int, array{stage: string, completed: bool, timestamp: string|null}>
     */
    private function buildStatusTracker(
        Applicant $applicant,
        ?Application $application,
        ?ExamSession $primarySession,
        $pivot,
        ?GradingSession $gradingSession,
        ?ConsultationSummary $summary
    ): array {
        $stages = [];

        $stages[] = [
            'stage' => 'Application submitted',
            'completed' => $application && $application->submitted_at !== null,
            'timestamp' => $application?->submitted_at?->format('M j, Y g:i A'),
        ];

        $stages[] = [
            'stage' => 'Successfully admitted',
            'completed' => $application && $application->status === 'accepted',
            'timestamp' => $application && $application->status === 'accepted' ? $application->processed_at?->format('M j, Y g:i A') : null,
        ];

        $stages[] = [
            'stage' => 'Account set up',
            'completed' => $applicant->hasCompletedSetup(),
            'timestamp' => $applicant->hasCompletedSetup() ? ($application?->processed_at?->format('M j, Y g:i A') ?? null) : null,
        ];

        $isDirect = $primarySession && $primarySession->type === ExamSession::TYPE_DIRECT;

        if ($isDirect) {
            // Direct assessment: skip scheduling/attendance/submission, go straight to scored
            $stages[] = [
                'stage' => 'Direct assessment',
                'completed' => $primarySession !== null,
                'timestamp' => $primarySession?->created_at?->format('M j, Y g:i A'),
            ];
        } else {
            $assigned = $primarySession !== null;
            $stages[] = [
                'stage' => 'Scheduled for exam',
                'completed' => $assigned,
                'timestamp' => $assigned ? $primarySession->published_at?->format('M j, Y g:i A') ?? $primarySession->date?->format('M j, Y') : null,
            ];

            $attendanceConfirmed = $pivot && $pivot->attendance_status !== 'pending';
            $stages[] = [
                'stage' => 'Attendance confirmed',
                'completed' => (bool) $attendanceConfirmed,
                'timestamp' => $pivot?->attendance_marked_at?->format('M j, Y g:i A'),
            ];

            $examSubmitted = $pivot && $pivot->submission_status === 'submitted';
            $stages[] = [
                'stage' => 'Exam submitted',
                'completed' => (bool) $examSubmitted,
                'timestamp' => $pivot?->submitted_at?->format('M j, Y g:i A'),
            ];
        }

        $scoresFinalized = $gradingSession && $gradingSession->status === GradingSession::STATUS_FINALIZED;
        $stages[] = [
            'stage' => 'Scores processed',
            'completed' => (bool) $scoresFinalized,
            'timestamp' => $scoresFinalized ? $gradingSession->finalized_at?->format('M j, Y g:i A') : null,
        ];

        $resultsAvailable = $summary && $summary->status === 'released';
        $stages[] = [
            'stage' => 'Results available',
            'completed' => (bool) $resultsAvailable,
            'timestamp' => $resultsAvailable ? $summary->released_at?->format('M j, Y g:i A') : null,
        ];

        $stages[] = [
            'stage' => 'Consultation released',
            'completed' => $summary && $summary->status === 'released',
            'timestamp' => $summary && $summary->status === 'released' ? $summary->released_at?->format('M j, Y g:i A') : null,
        ];

        return $stages;
    }

    /**
     * Build exam schedule payload for the primary assigned session.
     *
     * @return array{assigned: bool, room: string, building: string, date: string, time: string}|null
     */
    private function buildExamSchedule(?ExamSession $session): ?array
    {
        if (! $session) {
            return null;
        }

        // Direct assessment has no room/date/time — don't show exam schedule card
        if ($session->type === ExamSession::TYPE_DIRECT) {
            return null;
        }

        $room = $session->room;

        return [
            'assigned' => true,
            'room' => $room?->name ?? '—',
            'building' => $room?->building ?? '—',
            'date' => $session->date?->format('M j, Y') ?? '—',
            'time' => $session->start_time ? Carbon::parse($session->start_time)->format('g:i A') : '—',
        ];
    }

    /**
     * Build score release payload from the primary session.
     *
     * @return array{date_set: bool, release_date: string}|null
     */
    private function buildScoreRelease(?ExamSession $session): ?array
    {
        return null;
    }

    /**
     * Portal logout.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $applicantId = Auth::guard('applicant')->id();
        if ($applicantId) {
            app(AuditService::class)->log('applicant.logout', Applicant::class, $applicantId, [], [
                'applicant_id' => $applicantId,
            ], 'Applicant logout');
        }
        Auth::guard('applicant')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
