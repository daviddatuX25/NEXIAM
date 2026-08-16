<?php

namespace App\Http\Controllers\Applicant;

use App\Admission\Actions\AmendPendingAdmissionAttempt;
use App\Admission\Actions\AuthenticateApplicantWithAccessKey;
use App\Admission\Actions\ResubmitApplicationCorrection;
use App\Admission\Support\ApplicantScope;
use App\Applicant\Support\ApplicantPortalProjection;
use App\Http\Controllers\Controller;
use App\Http\Requests\Applicant\ApplicantLoginRequest;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Foundation\IntakeGradeField;
use App\Models\Foundation\Program;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

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
        try {
            $user = $action->handle(
                $request->string('access_key')->toString(),
                $request->date('birthdate')->format('Y-m-d'),
            );
        } catch (RuntimeException $exception) {
            return back()
                ->withInput($request->except('access_key'))
                ->withErrors(['access_key' => $exception->getMessage()]);
        }

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('applicant.portal'));
    }

    /** @deprecated D-085 — password setup removed; keep route as alias. */
    public function setupForm(): RedirectResponse
    {
        return redirect()->route('applicant.login');
    }

    public function dashboard(Request $request): Response
    {
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
        $grades = $attempt->gradeValues()->get()->map(fn ($value): array => [
            'label' => $value->field_label,
            'value' => $value->value,
        ])->values()->all();

        $preferences = array_values(array_filter([
            $attempt->program_preference_1_code,
            $attempt->program_preference_2_code,
            $attempt->program_preference_3_code,
        ]));

        return Inertia::render('Applicant/MyApplication', [
            'application' => [
                'reference_code' => $attempt->reference_code,
                'status' => $attempt->status,
                'submitted_at' => $attempt->created_at?->toIso8601String(),
                'needs_correction' => $attempt->status === AdmissionAttempt::STATUS_NEEDS_CORRECTION,
                'correction_notes' => $attempt->correction_notes,
                'correction_href' => $attempt->status === AdmissionAttempt::STATUS_NEEDS_CORRECTION
                    ? route('applicant.correction', ['attempt' => $attempt->id])
                    : null,
                'personal' => [
                    'name' => trim((string) ($identity->first_name ?? '').' '.(string) ($identity->last_name ?? '')),
                    'birthdate' => $identity->birthdate?->toDateString(),
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
                    'gwa' => $attempt->gwa,
                ],
                'preferences' => $preferences,
                'grades' => $grades,
            ],
        ]);
    }

    public function correctionForm(Request $request, AdmissionAttempt $attempt): Response|RedirectResponse
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

        $attempt->loadMissing([
            'identity:id,first_name,middle_name,last_name,suffix,birthdate,sex,email,phone',
            'gradeValues:id,admission_attempt_id,field_code,value',
        ]);

        $identity = $attempt->identity;
        $catalog = $this->intakeCatalogForInstitution((int) $attempt->institution_id);

        return Inertia::render('Applicant/Correction', [
            'page_state' => 'ready',
            'mode' => $mode,
            'programs' => $catalog['programs'],
            'grade_fields' => $catalog['grade_fields'],
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
                'grades' => $attempt->gradeValues
                    ->map(fn ($g): array => [
                        'code' => $g->field_code,
                        'value' => (string) $g->value,
                    ])
                    ->values()
                    ->all(),
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

        $rules = [
            'expected_version' => ['required', 'integer', 'min:1'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:64'],
            'applicant_type' => ['nullable', Rule::in(['new', 'transferee'])],
            'program_preference_1_code' => [
                'nullable',
                'string',
                'max:32',
                Rule::exists('programs', 'code')->where(fn ($q) => $q->where('is_active', true)),
            ],
            'program_preference_2_code' => [
                'nullable',
                'string',
                'max:32',
                'different:program_preference_1_code',
                Rule::exists('programs', 'code')->where(fn ($q) => $q->where('is_active', true)),
            ],
            'program_preference_3_code' => [
                'nullable',
                'string',
                'max:32',
                'different:program_preference_1_code',
                'different:program_preference_2_code',
                Rule::exists('programs', 'code')->where(fn ($q) => $q->where('is_active', true)),
            ],
            'address_line' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'province' => ['nullable', 'string', 'max:120'],
            'zip_code' => ['nullable', 'string', 'max:32'],
            'last_school_enrolled' => ['nullable', 'string', 'max:255'],
            'strand' => ['nullable', 'string', 'max:255'],
            'resubmission_notes' => ['nullable', 'string', 'max:2000'],
            'grades' => ['nullable', 'array'],
        ];

        foreach (IntakeGradeField::activeForInstitution((int) $attempt->institution_id) as $field) {
            $rules['grades.'.$field->code] = [
                'nullable',
                'numeric',
                'min:'.(float) $field->min_value,
                'max:'.(float) $field->max_value,
            ];
        }

        $validated = $request->validate($rules);

        $gradesInput = $validated['grades'] ?? [];
        $grades = [];
        if (is_array($gradesInput)) {
            foreach ($gradesInput as $code => $value) {
                if (! is_string($code) || $value === null || $value === '') {
                    continue;
                }
                $grades[$code] = (string) $value;
            }
        }
        $validated['grades'] = $grades;

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
