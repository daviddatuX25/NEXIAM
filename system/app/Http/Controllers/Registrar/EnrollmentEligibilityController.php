<?php

namespace App\Http\Controllers\Registrar;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Http\Controllers\Controller;
use App\Models\Admission\AdmissionAttempt;
use App\Models\User;
use App\Registrar\Queries\EnrollmentEligibilityQuery;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Registrar read adapter for advised-examinee eligibility and post-admission handoff.
 *
 * Domain: Registrar & Post-Admission Handoff.
 *
 * Responsibilities in this workflow:
 * - Present final document-clearance facts so Registrar staff can assess
 *   whether an examinee is ready for matriculation processing.
 * - Surface the official admitted, enrolling, and enrolled status context
 *   used during enrollment handoff.
 * - Support program-quota validation through the eligibility projection, so
 *   staff can make enrollment decisions against current program availability.
 * - The Four Clearance Milestones:
 *   1. Application Verified: High school report cards and identity records verified.
 *   2. Exam Attended: Validated examination scores and aptitude percentiles recorded.
 *   3. Guidance Consulted: Official 1-on-1 career advising completed with counselor.
 *   4. Program Endorsed: Department head has accepted applicant within quota limits.
 *
 * Pedagogical / Interactive Walkthrough Pairing:
 * - Paired with 'enrollment-eligibility' tour via Xiam NPC (staff-copilot persona).
 *
 * State-changing enrollment stamps are handled by the dedicated enrollment-stamping boundary;
 * this read adapter serves query and search projections for admissions clearance staff.
 */
class EnrollmentEligibilityController extends Controller
{
    public function __construct(private readonly EnrollmentEligibilityQuery $query) {}

    public function index(Request $request, CapabilityAuthorizer $authorizer): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        $institutionId = (int) ($user->actorScope()->institutionId ?? 0);
        abort_unless($authorizer->decideFresh($user, CapabilityCatalog::REGISTRAR_ENROLLMENT_ELIGIBILITY, [
            'office' => 'registrar',
            'institution_id' => $institutionId,
        ])->allowed, 403);
        $payload = $this->query->paginate($request, $user);
        $search = $payload['filters']['search'] ?? '';

        return Inertia::render('Registrar/EnrollmentEligibility', [
            'page_state' => 'search',
            'query' => $search,
            'results' => $payload['results'],
            'filters' => $payload['filters'],
            'sort_options' => $payload['sort_options'],
            'counts' => $payload['counts'],
            'pagination' => [
                'current_page' => $payload['results']->currentPage(),
                'last_page' => $payload['results']->lastPage(),
                'total' => $payload['results']->total(),
                'per_page' => $payload['results']->perPage(),
                'from' => $payload['results']->firstItem(),
                'to' => $payload['results']->lastItem(),
                'prev_page_url' => $payload['results']->previousPageUrl(),
                'next_page_url' => $payload['results']->nextPageUrl(),
            ],
        ]);
    }

    public function show(Request $request, AdmissionAttempt $attempt, CapabilityAuthorizer $authorizer): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        $institutionId = (int) ($user->actorScope()->institutionId ?? 0);
        abort_unless($authorizer->decideFresh($user, CapabilityCatalog::REGISTRAR_ENROLLMENT_ELIGIBILITY, [
            'office' => 'registrar',
            'institution_id' => (int) $attempt->institution_id,
        ])->allowed, 403);
        $attempt = AdmissionAttempt::query()
            ->forWorkspace()
            ->whereKey($attempt->getKey())
            ->where('institution_id', $institutionId)
            ->whereHas('academicCycle', fn ($cycle) => $cycle->where('institution_id', $institutionId))
            ->firstOrFail();

        return Inertia::render('Registrar/EnrollmentEligibility', [
            'page_state' => 'card',
            'query' => $request->string('q')->toString(),
            'results' => [],
            'card' => $this->query->cardFor($attempt, $user),
        ]);
    }
}
