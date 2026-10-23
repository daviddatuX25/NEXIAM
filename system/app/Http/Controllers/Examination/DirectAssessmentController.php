<?php

namespace App\Http\Controllers\Examination;

use App\Assessment\Queries\ApprovedTestInstrumentSelectionQuery;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Examination\Actions\CreateDirectAssessmentCohort;
use App\Http\Controllers\Controller;
use App\Http\Requests\Examination\FocusDirectAssessmentRequest;
use App\Http\Requests\Examination\StoreDirectAssessmentRequest;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Assessment\TestKitVersion;
use App\Models\Examination\ExamAssignment;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

final class DirectAssessmentController extends Controller
{
    public function create(
        FocusDirectAssessmentRequest $request,
        CapabilityAuthorizer $authorizer,
        ApprovedTestInstrumentSelectionQuery $instrumentQuery,
    ): Response {
        $user = $request->user();
        abort_unless(
            $authorizer->allows($user, CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE)
                || $authorizer->allows($user, CapabilityCatalog::REGISTRAR_APPLICATIONS_ACCEPT),
            403,
        );

        $institution = Institution::query()->findOrFail($user->actorScope()->institutionId);
        $academicCycles = $institution->academicCycles()->orderByDesc('id')->get();
        $cycle = $academicCycles->firstWhere('is_active', true);
        $kitVersions = $instrumentQuery->activeKitVersionsForInstitution((int) $institution->id);
        $applicants = $cycle === null
            ? []
            : AdmissionAttempt::query()->forWorkspace()
                ->where('institution_id', $institution->id)
                ->where('academic_cycle_id', $cycle->id)
                ->where('status', AdmissionAttempt::STATUS_ACCEPTED)
                ->whereDoesntHave('examAssignments', fn ($query) => $query->where('status', ExamAssignment::STATUS_ASSIGNED))
                ->with('identity:id,first_name,middle_name,last_name,suffix')
                ->orderBy('id')
                ->get()
                ->map(fn (AdmissionAttempt $attempt): array => [
                    'id' => $attempt->id,
                    'name' => $attempt->identity?->fullName() ?? '—',
                    'reference' => $attempt->reference_code ?? '—',
                    'course_preference' => $attempt->preferred_program_code ?? $attempt->course_preference_1 ?? '—',
                ])
                ->all();
        $requestedFocusId = $request->validated('focus_attempt_id');
        $focusAttemptId = $requestedFocusId !== null
            && in_array((int) $requestedFocusId, array_column($applicants, 'id'), true)
                ? (int) $requestedFocusId
                : null;

        return Inertia::render('Examination/DirectAssessment/Create', [
            'academicCycles' => $academicCycles->map(fn (AcademicCycle $academicCycle): array => [
                'id' => $academicCycle->id,
                'label' => $academicCycle->staffLabel(),
                'is_active' => $academicCycle->is_active,
            ])->all(),
            'applicants' => $applicants,
            'focusAttemptId' => $focusAttemptId,
            'activeCycleId' => $cycle?->id,
            'kitVersions' => $kitVersions,
            'storeRoute' => route('exams.direct-assessment.store'),
        ]);
    }

    public function store(
        StoreDirectAssessmentRequest $request,
        CreateDirectAssessmentCohort $action,
        CapabilityAuthorizer $authorizer,
    ): RedirectResponse {
        return $this->createAssessment($request, $action, $authorizer);
    }

    public function bulkStore(
        StoreDirectAssessmentRequest $request,
        CreateDirectAssessmentCohort $action,
        CapabilityAuthorizer $authorizer,
    ): RedirectResponse {
        return $this->createAssessment($request, $action, $authorizer);
    }

    private function createAssessment(
        StoreDirectAssessmentRequest $request,
        CreateDirectAssessmentCohort $action,
        CapabilityAuthorizer $authorizer,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless(
            $authorizer->allows($user, CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE)
                || $authorizer->allows($user, CapabilityCatalog::REGISTRAR_APPLICATIONS_ACCEPT),
            403,
        );
        /** @var User $user */
        $validated = $request->validated();
        $institution = Institution::query()->findOrFail($user->actorScope()->institutionId);
        $cycle = $institution->academicCycles()->findOrFail($validated['academic_cycle_id']);
        $date = $validated['date'] ?? null;
        $kitVersion = TestKitVersion::query()
            ->where('institution_id', $institution->id)
            ->findOrFail($validated['test_kit_version_id']);
        $result = $action->handle(
            $cycle,
            $validated['applicant_ids'],
            $user,
            $validated['label'] ?? null,
            $date !== null && isset($validated['start_time'])
                ? Carbon::parse("{$date} {$validated['start_time']}")
                : null,
            $date !== null && isset($validated['end_time'])
                ? Carbon::parse("{$date} {$validated['end_time']}")
                : null,
            null,
            $kitVersion,
        );
        if ($authorizer->allows($user, CapabilityCatalog::GUIDANCE_SCORING_MANUAL)
            || $authorizer->allows($user, CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE)) {
            return redirect()->route('grading.sessions.show', $result['grading_session']->id)
                ->with('status', 'Direct assessment session created. You can now encode scores.');
        }

        return redirect()->back()
            ->with('direct_assessment_created', true)
            ->with('status', 'Direct assessment session created successfully. Please contact a Test Administrator to begin score encoding.');
    }
}
