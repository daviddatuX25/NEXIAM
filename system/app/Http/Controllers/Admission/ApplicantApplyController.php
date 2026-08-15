<?php

namespace App\Http\Controllers\Admission;

use App\Admission\Actions\SubmitAdmissionAttempt;
use App\Admission\Exceptions\DuplicateAdmissionAttemptException;
use App\Admission\Queries\IntakeCatalogQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admission\SubmitAdmissionRequest;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\ApplicationWindow;
use App\Models\Foundation\Institution;
use App\Models\Foundation\PrivacyPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public applicant apply flow: render the intake form, submit a new admission
 * attempt, and show the one-time confirmation with reference + access key.
 * No staff capability gates — this is the applicant-facing surface.
 */
class ApplicantApplyController extends Controller
{
    public function applyForm(IntakeCatalogQuery $catalog): Response
    {
        $institution = Institution::current();
        $cycle = AcademicCycle::query()->where('code', 'SYN-CYCLE-2026')->firstOrFail();
        $window = ApplicationWindow::query()
            ->where('academic_cycle_id', $cycle->id)
            ->where('status', 'published')
            ->orderBy('opens_at')
            ->first();

        $policy = PrivacyPolicy::active();
        $catalogData = $catalog->forInstitution((int) $institution->id);

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
            'programs' => $catalogData['programs'],
            'grade_fields' => $catalogData['grade_fields'],
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
        $institution = Institution::current();
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
}
