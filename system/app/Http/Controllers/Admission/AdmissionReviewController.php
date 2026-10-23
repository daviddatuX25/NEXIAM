<?php

namespace App\Http\Controllers\Admission;

use App\Admission\Actions\AcceptAdmissionAttempt;
use App\Admission\Actions\BulkVerifyAdmissionAttemptDocuments;
use App\Admission\Actions\EnsureAttemptRequirementChecklist;
use App\Admission\Actions\PunchInAdmissionAttempt;
use App\Admission\Actions\RequestApplicationCorrection;
use App\Admission\Queries\IntakeCatalogQuery;
use App\Admission\Queries\ListAdmissionAttemptsQuery;
use App\Admission\Queries\ShowAdmissionAttemptQuery;
use App\Admission\Support\AttemptProgramFacts;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Foundation\Queries\ApplicationSuggestionListsQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admission\BulkAdmissionActionRequest;
use App\Http\Requests\Admission\StoreAdminAdmissionRequest;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\ApplicationWindow;
use App\Models\Foundation\Institution;
use App\Models\Foundation\IntakeRequirement;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

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
        $institutionId = $user instanceof User ? (int) ($user->actorScope()->institutionId ?? 0) : 0;
        $decision = $authorizer->decideFresh($user, CapabilityCatalog::REGISTRAR_APPLICATIONS_VIEW, [
            'office' => 'registrar',
            'institution_id' => $institutionId,
        ]);

        if (! $decision->allowed || ! ($user instanceof User)) {
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
                'filters' => ['search' => null, 'status' => null, 'cycle' => null, 'sort' => 'created_at', 'dir' => 'desc'],
                'statuses' => [],
                'cycles' => [],
                'sort_options' => [],
                'status_counts' => [],
                'can_import_applicants' => false,
                'capabilities' => [
                    'accept' => false,
                    'dismiss' => false,
                    'correct' => false,
                    'verify_documents' => false,
                ],
            ]);
        }

        $payload = $listAttempts->handle($request, $user);
        $emptyFilters = ($payload['filters']['search'] ?? null) === null
            && ($payload['filters']['status'] ?? null) === null
            && ($payload['filters']['cycle'] ?? null) === null;

        return Inertia::render('Admission/Index', [
            'page_state' => $payload['attempts']->total() === 0 && $emptyFilters ? 'empty' : 'ready',
            'attempts' => $payload['attempts'],
            'filters' => $payload['filters'],
            'statuses' => $payload['statuses'],
            'cycles' => $payload['cycles'],
            'sort_options' => $payload['sort_options'] ?? [],
            'can_import_applicants' => $authorizer->allows($user, CapabilityCatalog::REGISTRAR_APPLICATIONS_IMPORT),
            'can_create_applicant' => $authorizer->allows($user, CapabilityCatalog::REGISTRAR_APPLICATIONS_CORRECT),
            'status_counts' => $payload['status_counts'],
            'capabilities' => [
                'accept' => $authorizer->allows($user, CapabilityCatalog::REGISTRAR_APPLICATIONS_ACCEPT),
                'dismiss' => $authorizer->allows($user, CapabilityCatalog::REGISTRAR_APPLICATIONS_DISMISS),
                'correct' => $authorizer->allows($user, CapabilityCatalog::REGISTRAR_APPLICATIONS_CORRECT),
                'verify_documents' => $authorizer->allows($user, CapabilityCatalog::REGISTRAR_DOCUMENTS_VERIFY),
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
        $decision = $authorizer->decideFresh($user, CapabilityCatalog::REGISTRAR_APPLICATIONS_VIEW, ['office' => 'registrar']);

        if (! $decision->allowed || ! ($user instanceof User)) {
            abort(403);
        }

        $institutionId = (int) ($user->actorScope()->institutionId ?? 0);
        $attempt = AdmissionAttempt::query()
            ->forWorkspace()
            ->whereKey($attempt->getKey())
            ->where('institution_id', $institutionId)
            ->whereHas('academicCycle', fn ($cycle) => $cycle->where('institution_id', $institutionId))
            ->firstOrFail();
        $catalogData = $catalog->forInstitution((int) $attempt->institution_id);
        $attemptProjection = $showAttempt->handle($attempt, $user);
        $documentChecklist = $attemptProjection['document_checklist'];
        unset($attemptProjection['document_checklist']);
        $reissuedAccessKey = $request->session()->pull('reissued_access_key')
            ?? $request->session()->pull('punched_access_key');
        $credentialHandoff = $request->session()->pull('credential_handoff');

        return Inertia::render('Admission/Show', [
            'page_state' => 'ready',
            'attempt' => $attemptProjection,
            'document_checklist' => [
                ...$documentChecklist,
                'flags' => [
                    'documents_view' => $documentChecklist['documents_view'],
                    'documents_initialize' => $documentChecklist['documents_initialize'],
                    'documents_verify' => $documentChecklist['documents_verify'],
                    'documents_reconcile' => $documentChecklist['documents_reconcile'],
                    'documents_request' => $documentChecklist['documents_request'],
                ],
                'expected_version' => (int) $attempt->version,
                'is_clearance_confirmed' => AttemptProgramFacts::isClearanceConfirmed($attempt),
            ],
            'programs' => $catalogData['programs'],
            'grade_fields' => [],
            // One-time plaintext after creation or reissue — pulled so a refresh cannot leak it.
            'reissued_access_key' => $reissuedAccessKey,
            'credential_handoff' => $credentialHandoff,
            'capabilities' => [
                'accept' => $authorizer->allows($user, CapabilityCatalog::REGISTRAR_APPLICATIONS_ACCEPT),
                'dismiss' => $authorizer->allows($user, CapabilityCatalog::REGISTRAR_APPLICATIONS_DISMISS),
                'correct' => $authorizer->allows($user, CapabilityCatalog::REGISTRAR_APPLICATIONS_CORRECT),
            ],
        ]);
    }

    public function bulkAccept(
        BulkAdmissionActionRequest $request,
        AcceptAdmissionAttempt $action,
        CapabilityAuthorizer $authorizer,
        BulkVerifyAdmissionAttemptDocuments $bulkVerify,
        EnsureAttemptRequirementChecklist $ensureChecklist,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        $institutionId = (int) ($user->staffProfile?->institution_id ?? Institution::current()->id);
        abort_unless($authorizer->decideFresh($user, CapabilityCatalog::REGISTRAR_APPLICATIONS_ACCEPT, [
            'institution_id' => $institutionId,
            'office' => 'registrar',
        ])->allowed, 403);

        $verifyDocuments = $request->boolean('verify_documents');
        if ($verifyDocuments) {
            abort_unless($authorizer->decideFresh($user, CapabilityCatalog::REGISTRAR_DOCUMENTS_VERIFY, [
                'institution_id' => $institutionId,
                'office' => 'registrar',
            ])->allowed, 403);
        }

        $items = $request->validated()['attempts'];
        $attemptIds = array_column($items, 'id');
        $attemptsById = AdmissionAttempt::query()
            ->forWorkspace()
            ->where('institution_id', $institutionId)
            ->whereHas('academicCycle', fn ($cycle) => $cycle->where('institution_id', $institutionId))
            ->whereIn('id', $attemptIds)
            ->get()
            ->keyBy('id');
        abort_unless($attemptsById->count() === count($attemptIds), 404);

        $accepted = 0;
        $verifiedTotal = 0;
        $errors = [];

        foreach ($items as $item) {
            try {
                $attempt = $attemptsById->get($item['id']);
                $acceptedAttempt = $action->handle($attempt, $user, expectedVersion: (int) $item['version']);
                $accepted++;

                if ($verifyDocuments) {
                    $acceptedAttempt->refresh();
                    $set = $ensureChecklist->handle($acceptedAttempt, $user);
                    if ($set !== null) {
                        $unverifiedItems = $set->items()
                            ->whereDoesntHave('latestVerification', fn ($q) => $q->where('status', 'verified'))
                            ->get();
                        if ($unverifiedItems->isNotEmpty()) {
                            $itemsPayload = $unverifiedItems->map(fn ($reqItem) => [
                                'requirement_item_id' => (int) $reqItem->id,
                                'expected_latest_verification_id' => $reqItem->latestVerification?->id,
                            ])->all();

                            $bulkVerify->handle(
                                attempt: $acceptedAttempt,
                                requirementSetId: (int) $set->id,
                                items: $itemsPayload,
                                actor: $user,
                                expectedVersion: (int) $acceptedAttempt->version,
                                idempotencyKey: 'bulk-accept-verify-'.$acceptedAttempt->id.'-'.$acceptedAttempt->version,
                            );
                            $verifiedTotal += $unverifiedItems->count();
                        }
                    }
                }
            } catch (RuntimeException $exception) {
                $errors[] = "Application {$item['id']}: {$exception->getMessage()}";
            }
        }

        $message = $verifyDocuments
            ? "{$accepted} application(s) accepted and {$verifiedTotal} document requirement(s) verified."
            : "{$accepted} application(s) accepted.";
        if ($errors !== []) {
            $message .= ' '.count($errors).' skipped.';
        }

        return back()->with('status', $message)->withErrors($errors);
    }

    public function bulkRequestCorrection(
        BulkAdmissionActionRequest $request,
        RequestApplicationCorrection $action,
        CapabilityAuthorizer $authorizer,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        $institutionId = (int) ($user->staffProfile?->institution_id ?? Institution::current()->id);
        abort_unless($authorizer->decideFresh($user, CapabilityCatalog::REGISTRAR_APPLICATIONS_CORRECT, [
            'institution_id' => $institutionId,
            'office' => 'registrar',
        ])->allowed, 403);

        $validated = $request->validated();
        $reason = (string) ($validated['reason'] ?? '');
        if ($reason === '') {
            return back()->withErrors(['reason' => 'Correction reason is required.']);
        }

        $items = $validated['attempts'];
        $attemptIds = array_column($items, 'id');
        $attemptsById = AdmissionAttempt::query()
            ->forWorkspace()
            ->where('institution_id', $institutionId)
            ->whereHas('academicCycle', fn ($cycle) => $cycle->where('institution_id', $institutionId))
            ->whereIn('id', $attemptIds)
            ->get()
            ->keyBy('id');
        abort_unless($attemptsById->count() === count($attemptIds), 404);

        $count = 0;
        $skippedCount = 0;
        $errors = [];

        foreach ($items as $item) {
            try {
                $attempt = $attemptsById->get($item['id']);
                $action->handle(
                    $attempt,
                    $user,
                    expectedVersion: (int) $item['version'],
                    reason: $reason,
                );
                $count++;
            } catch (RuntimeException|ValidationException $exception) {
                $skippedCount++;
                $errors[] = "Application {$item['id']}: {$exception->getMessage()}";
            }
        }

        $message = "{$count} application(s) sent for correction.";
        if ($skippedCount > 0) {
            $message .= " {$skippedCount} skipped.";
        }

        return back()->with('status', $message)->withErrors($errors);
    }

    public function create(
        Request $request,
        IntakeCatalogQuery $catalog,
        CapabilityAuthorizer $authorizer,
        ApplicationSuggestionListsQuery $suggestions,
    ): Response {
        $user = $request->user();
        abort_unless($user instanceof User && $authorizer->allows($user, CapabilityCatalog::REGISTRAR_APPLICATIONS_CORRECT), 403);

        $institutionId = (int) ($user->actorScope()->institutionId ?? 0);
        $institution = Institution::query()->find($institutionId) ?? Institution::current();
        $cycle = AcademicCycle::query()
            ->where('institution_id', $institution->id)
            ->where('is_active', true)
            ->first();

        $catalogData = $catalog->forInstitution((int) $institution->id);
        $requirements = IntakeRequirement::query()
            ->where('institution_id', $institution->id)
            ->active()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'code', 'label', 'applicant_track', 'item_kind', 'is_mandatory'])
            ->map(static fn (IntakeRequirement $requirement): array => [
                'id' => (int) $requirement->id,
                'code' => (string) $requirement->code,
                'label' => (string) $requirement->label,
                'applicant_track' => (string) $requirement->applicant_track,
                'item_kind' => (string) $requirement->item_kind,
                'is_mandatory' => (bool) $requirement->is_mandatory,
            ])
            ->all();

        return Inertia::render('Admission/Create', [
            'page_state' => 'ready',
            'programs' => $catalogData['programs'],
            'intake_suggestions' => $suggestions->forPublic((int) $institution->getKey()),
            'requirements' => $requirements,
            'can_verify_documents' => $authorizer->allows($user, CapabilityCatalog::REGISTRAR_DOCUMENTS_VERIFY),
            'application_conflict' => $request->session()->get('application_conflict'),
            'grade_fields' => [],
            'cycle' => $cycle ? [
                'id' => $cycle->id,
                'name' => $cycle->name,
                'code' => $cycle->code,
                'school_year' => $cycle->school_year,
                'semester' => $cycle->semester,
            ] : null,
        ]);
    }

    public function store(
        StoreAdminAdmissionRequest $request,
        PunchInAdmissionAttempt $punchIn,
        CapabilityAuthorizer $authorizer,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user instanceof User && $authorizer->allows($user, CapabilityCatalog::REGISTRAR_APPLICATIONS_CORRECT), 403);

        $institutionId = (int) ($user->actorScope()->institutionId ?? 0);
        $institution = Institution::query()->find($institutionId) ?? Institution::current();
        $cycle = AcademicCycle::query()
            ->where('institution_id', $institution->id)
            ->where('is_active', true)
            ->firstOrFail();

        $window = ApplicationWindow::query()
            ->where('academic_cycle_id', $cycle->id)
            ->where('status', 'published')
            ->first();

        $result = $punchIn->handle(
            $institution,
            $cycle,
            $window,
            $request->validated(),
            $user,
        );

        session()->flash('reissued_access_key', $result['access_key']);
        session()->flash('credential_handoff', $result['credential_handoff'] ?? null);

        return redirect()
            ->route('admissions.show', $result['attempt']->id)
            ->with('success', "Application created successfully with reference {$result['attempt']->reference_code}.");
    }

    public function edit(
        AdmissionAttempt $attempt,
        Request $request,
        CapabilityAuthorizer $authorizer,
        ShowAdmissionAttemptQuery $showAttempt,
        IntakeCatalogQuery $catalog,
        ApplicationSuggestionListsQuery $suggestions,
    ): Response|RedirectResponse {
        $user = $request->user();
        abort_unless($user instanceof User && $authorizer->allows($user, CapabilityCatalog::REGISTRAR_APPLICATIONS_VIEW), 403);

        $institutionId = (int) ($user->actorScope()->institutionId ?? 0);
        $attempt = AdmissionAttempt::query()
            ->forWorkspace()
            ->whereKey($attempt->getKey())
            ->where('institution_id', $institutionId)
            ->whereHas('academicCycle', fn ($cycle) => $cycle->where('institution_id', $institutionId))
            ->firstOrFail();

        if (in_array($attempt->status, [AdmissionAttempt::STATUS_DISMISSED, AdmissionAttempt::STATUS_ARCHIVED], true)) {
            return redirect()
                ->route('admissions.show', $attempt)
                ->withErrors(['version' => 'Dismissed or archived applications cannot be edited. Revert to pending first.']);
        }

        if (
            AttemptProgramFacts::isClearanceConfirmed($attempt)
            || AttemptProgramFacts::activeStamp($attempt) !== null
        ) {
            return redirect()
                ->route('admissions.show', $attempt)
                ->withErrors(['version' => 'Admission clearance is confirmed. Reopen clearance with a reason before amending this application.']);
        }

        if ($attempt->status === AdmissionAttempt::STATUS_ACCEPTED) {
            abort_unless($authorizer->allows($user, CapabilityCatalog::REGISTRAR_APPLICATIONS_ACCEPT), 403);
        } else {
            abort_unless($authorizer->allows($user, CapabilityCatalog::REGISTRAR_APPLICATIONS_CORRECT), 403);
        }

        $catalogData = $catalog->forInstitution((int) $attempt->institution_id);
        $attemptProjection = $showAttempt->handle($attempt, $user);
        unset($attemptProjection['document_checklist']);

        return Inertia::render('Admission/Edit', [
            'page_state' => 'ready',
            'attempt' => $attemptProjection,
            'programs' => $catalogData['programs'],
            'intake_suggestions' => $suggestions->forPublic((int) $attempt->institution_id),
            'grade_fields' => [],
            'expected_version' => (int) $attempt->version,
            'capabilities' => [
                'accept' => $authorizer->allows($user, CapabilityCatalog::REGISTRAR_APPLICATIONS_ACCEPT),
                'correct' => $authorizer->allows($user, CapabilityCatalog::REGISTRAR_APPLICATIONS_CORRECT),
            ],
        ]);
    }
}
