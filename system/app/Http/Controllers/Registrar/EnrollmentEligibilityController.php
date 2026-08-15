<?php

namespace App\Http\Controllers\Registrar;

use App\Consultation\Queries\EnrollmentEligibilityQuery;
use App\Http\Controllers\Controller;
use App\Models\Admission\AdmissionAttempt;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Registrar status search + eligibility card (A3): students may check their
 * status at the Registrar counter without phones. Status facts only —
 * assessment scores and counselor notes stay Guidance-owned.
 */
class EnrollmentEligibilityController extends Controller
{
    public function __construct(private readonly EnrollmentEligibilityQuery $query) {}

    public function index(Request $request): Response
    {
        $search = $request->string('q')->toString();

        return Inertia::render('Registrar/EnrollmentEligibility', [
            'page_state' => 'search',
            'query' => $search,
            'results' => $this->query->search($search),
        ]);
    }

    public function show(Request $request, AdmissionAttempt $attempt): Response
    {
        return Inertia::render('Registrar/EnrollmentEligibility', [
            'page_state' => 'card',
            'query' => $request->string('q')->toString(),
            'results' => [],
            'card' => $this->query->cardFor($attempt, $request->user()),
        ]);
    }
}
