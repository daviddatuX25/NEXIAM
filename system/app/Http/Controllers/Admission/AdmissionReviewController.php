<?php

namespace App\Http\Controllers\Admission;

use App\Admission\Queries\IntakeCatalogQuery;
use App\Admission\Queries\ListAdmissionAttemptsQuery;
use App\Admission\Queries\ShowAdmissionAttemptQuery;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Http\Controllers\Controller;
use App\Models\Admission\AdmissionAttempt;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Registrar application review reads: the paged list and the attempt detail.
 * Both gate on `registrar.applications.view`; the detail page also surfaces
 * which state transitions the actor may take.
 */
class AdmissionReviewController extends Controller
{
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

    public function show(
        Request $request,
        AdmissionAttempt $attempt,
        CapabilityAuthorizer $authorizer,
        ShowAdmissionAttemptQuery $showAttempt,
        IntakeCatalogQuery $catalog,
    ): Response {
        $user = $request->user();
        $decision = $authorizer->decide($user, CapabilityCatalog::REGISTRAR_APPLICATIONS_VIEW);

        if (! $decision->allowed) {
            abort(403);
        }

        $catalogData = $catalog->forInstitution((int) $attempt->institution_id);

        return Inertia::render('Admission/Show', [
            'page_state' => 'ready',
            'attempt' => $showAttempt->handle($attempt),
            'programs' => $catalogData['programs'],
            'grade_fields' => $catalogData['grade_fields'],
            // One-time plaintext after reissue — pulled so a refresh cannot leak it.
            'reissued_access_key' => $request->session()->pull('reissued_access_key'),
            'capabilities' => [
                'accept' => $authorizer->allows($user, CapabilityCatalog::REGISTRAR_APPLICATIONS_ACCEPT),
                'dismiss' => $authorizer->allows($user, CapabilityCatalog::REGISTRAR_APPLICATIONS_DISMISS),
                'correct' => $authorizer->allows($user, CapabilityCatalog::REGISTRAR_APPLICATIONS_CORRECT),
            ],
        ]);
    }
}
