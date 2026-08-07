<?php

namespace App\Http\Controllers\Admission;

use App\Admission\Actions\AcceptAdmissionAttempt;
use App\Admission\Actions\ArchiveAdmissionAttempt;
use App\Admission\Actions\AuthorizeAcceptedAdmissionAmendment;
use App\Admission\Actions\CorrectAdmissionAttempt;
use App\Admission\Actions\DismissAdmissionAttempt;
use App\Admission\Actions\ReconsiderAdmissionAttempt;
use App\Admission\Actions\ReissueApplicantAccessKey;
use App\Admission\Actions\ReopenAdmissionAttempt;
use App\Admission\Actions\RequestApplicationCorrection;
use App\Admission\Actions\SubmitAdmissionAttempt;
use App\Admission\Queries\ListAdmissionAttemptsQuery;
use App\Admission\Queries\ShowAdmissionAttemptQuery;
use App\Admission\Exceptions\DuplicateAdmissionAttemptException;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admission\AcceptAdmissionRequest;
use App\Http\Requests\Admission\AuthorizeAcceptedAmendmentRequest;
use App\Http\Requests\Admission\CorrectAdmissionRequest;
use App\Http\Requests\Admission\DismissAdmissionRequest;
use App\Http\Requests\Admission\SubmitAdmissionRequest;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\ApplicationWindow;
use App\Models\Foundation\Institution;
use App\Models\Foundation\IntakeGradeField;
use App\Models\Foundation\PrivacyPolicy;
use App\Models\Foundation\Program;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdmissionController extends Controller
{
    public function applyForm(): Response
    {
        $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();
        $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
        $window = ApplicationWindow::query()
            ->where('academic_cycle_id', $cycle->id)
            ->where('status', 'published')
            ->orderBy('opens_at')
            ->first();

        $programs = Program::query()
            ->where('institution_id', $institution->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['code', 'name']);

        $policy = PrivacyPolicy::active();
        $gradeFields = IntakeGradeField::activeForInstitution((int) $institution->id)->map(fn (IntakeGradeField $field): array => [
            'code' => $field->code,
            'label' => $field->label,
            'field_kind' => $field->field_kind,
            'is_required' => $field->is_required,
            'min_value' => (float) $field->min_value,
            'max_value' => (float) $field->max_value,
        ])->values();

        return Inertia::render('Admission/Apply', [
            'page_state' => $window === null ? 'empty' : 'ready',
            'institution' => [
                'id' => $institution->id,
                'name' => $institution->name,
            ],
            'cycle' => [
                'id' => $cycle->id,
                'name' => $cycle->name,
            ],
            'window' => $window === null ? null : [
                'id' => $window->id,
                'name' => $window->name,
                'opens_at' => $window->opens_at?->toIso8601String(),
                'closes_at' => $window->closes_at?->toIso8601String(),
            ],
            'programs' => $programs,
            'grade_fields' => $gradeFields,
            'privacy_policy' => $policy === null ? null : [
                'id' => $policy->id,
                'title' => $policy->title,
                'content' => $policy->content,
            ],
        ]);
    }

    public function submit(
        SubmitAdmissionRequest $request,
        SubmitAdmissionAttempt $submit,
    ): RedirectResponse {
        $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();
        $cycle = AcademicCycle::query()->findOrFail($request->integer('academic_cycle_id'));
        $window = ApplicationWindow::query()->findOrFail($request->integer('application_window_id'));

        try {
            $result = $submit->handle(
                $institution,
                $cycle,
                $window,
                $request->toIntakeData(),
            );
        } catch (DuplicateAdmissionAttemptException) {
            return back()
                ->withInput()
                ->withErrors(['identity' => 'An application for this person already exists for the current admission period.']);
        }

        return redirect()->route('apply.confirmation')->with([
            'reference_code' => $result['attempt']->reference_code,
            'access_key' => $result['access_token'],
        ]);
    }

    public function confirmation(Request $request): Response
    {
        return Inertia::render('Admission/Confirmation', [
            'page_state' => 'ready',
            // Pull once — plaintext key must not remain in session after render.
            'access_key' => $request->session()->pull('access_key'),
            'reference_code' => $request->session()->get('reference_code'),
        ]);
    }

    public function show(
        AdmissionAttempt $attempt,
        CapabilityAuthorizer $authorizer,
        ShowAdmissionAttemptQuery $showAttempt,
    ): Response {
        $user = request()->user();
        $decision = $authorizer->decide($user, CapabilityCatalog::REGISTRAR_APPLICATIONS_VIEW);

        if (! $decision->allowed) {
            abort(403);
        }

        $catalog = $this->intakeCatalogForInstitution((int) $attempt->institution_id);

        return Inertia::render('Admission/Show', [
            'page_state' => 'ready',
            'attempt' => $showAttempt->handle($attempt),
            'programs' => $catalog['programs'],
            'grade_fields' => $catalog['grade_fields'],
            // One-time plaintext after reissue — pulled so a refresh cannot leak it.
            'reissued_access_key' => request()->session()->pull('reissued_access_key'),
            'capabilities' => [
                'accept' => $authorizer->allows($user, CapabilityCatalog::REGISTRAR_APPLICATIONS_ACCEPT),
                'dismiss' => $authorizer->allows($user, CapabilityCatalog::REGISTRAR_APPLICATIONS_DISMISS),
                'correct' => $authorizer->allows($user, CapabilityCatalog::REGISTRAR_APPLICATIONS_CORRECT),
            ],
        ]);
    }

    public function index(
        Request $request,
        CapabilityAuthorizer $authorizer,
        ListAdmissionAttemptsQuery $listAttempts,
    ): Response {
        $user = $request->user();
        $decision = $authorizer->decide($user, CapabilityCatalog::REGISTRAR_APPLICATIONS_VIEW);

        if (! $decision->allowed) {
            return Inertia::render('Admission/Index', [
                'page_state' => 'denied',
                'attempts' => [
                    'data' => [],
                    'current_page' => 1,
                    'last_page' => 1,
                    'from' => null,
                    'to' => null,
                    'total' => 0,
                    'prev_page_url' => null,
                    'next_page_url' => null,
                ],
                'filters' => ['search' => null, 'status' => null, 'cycle' => null],
                'statuses' => [],
                'cycles' => [],
                'status_counts' => [],
                'capabilities' => [
                    'accept' => false,
                    'dismiss' => false,
                    'correct' => false,
                ],
            ]);
        }

        $payload = $listAttempts->handle($request);
        $emptyFilters = ($payload['filters']['search'] ?? null) === null
            && ($payload['filters']['status'] ?? null) === null
            && ($payload['filters']['cycle'] ?? null) === null;

        return Inertia::render('Admission/Index', [
            'page_state' => $payload['attempts']->total() === 0 && $emptyFilters ? 'empty' : 'ready',
            'attempts' => $payload['attempts'],
            'filters' => $payload['filters'],
            'statuses' => $payload['statuses'],
            'cycles' => $payload['cycles'],
            'status_counts' => $payload['status_counts'],
            'capabilities' => [
                'accept' => $authorizer->allows($user, CapabilityCatalog::REGISTRAR_APPLICATIONS_ACCEPT),
                'dismiss' => $authorizer->allows($user, CapabilityCatalog::REGISTRAR_APPLICATIONS_DISMISS),
                'correct' => $authorizer->allows($user, CapabilityCatalog::REGISTRAR_APPLICATIONS_CORRECT),
            ],
        ]);
    }

    public function accept(
        AcceptAdmissionRequest $request,
        AdmissionAttempt $attempt,
        AcceptAdmissionAttempt $action,
    ): RedirectResponse {
        $action->handle($attempt, $request->user(), $request->integer('expected_version'));

        return back()->with('status', 'Application accepted.');
    }

    public function dismiss(
        DismissAdmissionRequest $request,
        AdmissionAttempt $attempt,
        DismissAdmissionAttempt $action,
    ): RedirectResponse {
        $action->handle(
            $attempt,
            $request->user(),
            $request->string('reason')->toString(),
            $request->integer('expected_version'),
        );

        return back()->with('status', 'Application dismissed.');
    }

    public function correct(
        CorrectAdmissionRequest $request,
        AdmissionAttempt $attempt,
        CorrectAdmissionAttempt $action,
    ): RedirectResponse {
        $payload = $request->correctionPayload();
        $wasNeedsCorrection = $attempt->status === AdmissionAttempt::STATUS_NEEDS_CORRECTION;

        try {
            $action->handle(
                $attempt,
                $request->user(),
                (int) $payload['expected_version'],
                $payload,
            );
        } catch (\RuntimeException $e) {
            return back()->withErrors(['version' => $e->getMessage()]);
        }

        $message = $wasNeedsCorrection
            ? 'Office fix recorded — application returned to pending review.'
            : 'Application fields updated.';

        return back()->with('status', $message);
    }

    public function requestCorrection(
        Request $request,
        AdmissionAttempt $attempt,
        RequestApplicationCorrection $action,
    ): RedirectResponse {
        $validated = $request->validate([
            'expected_version' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        try {
            $action->handle(
                $attempt,
                $request->user(),
                (int) $validated['expected_version'],
                $validated['reason'],
            );
        } catch (\RuntimeException $e) {
            return back()->withErrors(['version' => $e->getMessage()]);
        }

        return back()->with('status', 'Applicant asked to correct this application.');
    }

    public function authorizeAmendment(
        AuthorizeAcceptedAmendmentRequest $request,
        AdmissionAttempt $attempt,
        AuthorizeAcceptedAdmissionAmendment $action,
    ): RedirectResponse {
        $payload = $request->amendmentPayload();

        try {
            $action->handle(
                $attempt,
                $request->user(),
                (int) $payload['expected_version'],
                (string) $payload['reason'],
                $payload,
            );
        } catch (\RuntimeException $e) {
            return back()->withErrors(['version' => $e->getMessage()]);
        }

        return back()->with(
            'status',
            'Authorized amendment recorded. Application stays accepted — exam assignment does not require a retake.',
        );
    }

    public function reconsider(
        Request $request,
        AdmissionAttempt $attempt,
        ReconsiderAdmissionAttempt $action,
    ): RedirectResponse {
        $validated = $request->validate([
            'expected_version' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        try {
            $action->handle(
                $attempt,
                $request->user(),
                (int) $validated['expected_version'],
                $validated['reason'],
            );
        } catch (\RuntimeException $e) {
            return back()->withErrors(['version' => $e->getMessage()]);
        }

        return back()->with(
            'status',
            'Acceptance reconsidered — returned to pending. Existing exam links preserved for reconciliation; new scheduling requires accept again.',
        );
    }

    public function reopen(
        Request $request,
        AdmissionAttempt $attempt,
        ReopenAdmissionAttempt $action,
    ): RedirectResponse {
        $validated = $request->validate([
            'expected_version' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        try {
            $action->handle(
                $attempt,
                $request->user(),
                (int) $validated['expected_version'],
                $validated['reason'],
            );
        } catch (\RuntimeException $e) {
            return back()->withErrors(['version' => $e->getMessage()]);
        }

        return back()->with('status', 'Application reopened without deleting history.');
    }

    public function archive(
        Request $request,
        AdmissionAttempt $attempt,
        ArchiveAdmissionAttempt $action,
    ): RedirectResponse {
        $validated = $request->validate([
            'expected_version' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $action->handle($attempt, $request->user(), (int) $validated['expected_version']);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['version' => $e->getMessage()]);
        }

        return back()->with('status', 'Application archived.');
    }

    public function reissueAccessKey(
        Request $request,
        AdmissionAttempt $attempt,
        ReissueApplicantAccessKey $action,
    ): RedirectResponse {
        $validated = $request->validate([
            'expected_version' => ['required', 'integer', 'min:1'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $result = $action->handle(
                $attempt,
                $request->user(),
                (int) $validated['expected_version'],
                (string) ($validated['reason'] ?? ''),
            );
        } catch (\RuntimeException $e) {
            return back()->withErrors(['version' => $e->getMessage()]);
        }

        return redirect()
            ->route('admissions.show', $result['attempt'])
            ->with('status', 'Access key reissued. Copy it now — it will not be shown again.')
            ->with('reissued_access_key', $result['access_token']);
    }

    /**
     * @return array{programs: \Illuminate\Support\Collection<int, Program>, grade_fields: \Illuminate\Support\Collection<int, array<string, mixed>>}
     */
    private function intakeCatalogForInstitution(int $institutionId): array
    {
        $programs = Program::query()
            ->where('institution_id', $institutionId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['code', 'name']);

        $gradeFields = IntakeGradeField::activeForInstitution($institutionId)->map(fn (IntakeGradeField $field): array => [
            'code' => $field->code,
            'label' => $field->label,
            'field_kind' => $field->field_kind,
            'is_required' => $field->is_required,
            'min_value' => (float) $field->min_value,
            'max_value' => (float) $field->max_value,
        ])->values();

        return [
            'programs' => $programs,
            'grade_fields' => $gradeFields,
        ];
    }
}
