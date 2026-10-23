<?php

namespace App\Http\Controllers\Applicant;

use App\Admission\Support\ApplicantScope;
use App\Applicant\Queries\ApplicantAuditLogQuery;
use App\Http\Controllers\Controller;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ApplicantIdentity;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class ApplicantAuditLogController extends Controller
{
    public function index(Request $request, ApplicantAuditLogQuery $query): Response
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);

        $filters = Validator::make($request->query(), [
            'category' => ['sometimes', 'string', Rule::in(['all', 'application', 'examination', 'results_release', 'clearance', 'security'])],
            'actor_office' => ['sometimes', 'string', Rule::in(['all', 'registrar', 'guidance', 'program_head', 'self', 'system'])],
            'search' => ['sometimes', 'nullable', 'string', 'max:120'],
            'sort' => ['sometimes', 'string', Rule::in(['newest', 'oldest'])],
            'page' => ['sometimes', 'integer', 'min:1', 'max:100000'],
        ])->validate();

        $identityId = ApplicantScope::identityIdForUser($actor);
        $identity = $identityId === null ? null : ApplicantIdentity::query()->find($identityId);
        $attemptIds = $identity === null
            ? []
            : AdmissionAttempt::query()->where('applicant_identity_id', $identity->getKey())->pluck('id')->all();

        $currentFilters = [
            'category' => $filters['category'] ?? 'all',
            'actor_office' => $filters['actor_office'] ?? 'all',
            'search' => $filters['search'] ?? '',
            'sort' => $filters['sort'] ?? 'newest',
            'page' => (int) ($filters['page'] ?? 1),
        ];
        $result = $query->handle($actor, $identity, $attemptIds, $currentFilters);

        return Inertia::render('Applicant/AccessHistory', [
            'events' => $result['events'],
            'filters' => $currentFilters,
            'category_counts' => $result['category_counts'],
            'back_url' => route('applicant.admission-support', [], false),
        ]);
    }
}
