<?php

namespace App\Http\Controllers\Consultation;

use App\Admission\Support\AttemptProgramFacts;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Authorization\GovernanceAuthorizer;
use App\Consultation\Queries\ConsultationApplicantLookupQuery;
use App\Consultation\Queries\ConsultationDashboardQuery;
use App\Consultation\Queries\ReleaseDaySessionMonitoringQuery;
use App\Consultation\Support\AttemptResultReadiness;
use App\Consultation\Support\ConsultationMediaReadiness;
use App\Foundation\Actions\RecordAuditEvent;
use App\Http\Controllers\Controller;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Consultation\ConsultationEncounter;
use App\Models\Consultation\ConsultationPolicy;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Program;
use App\Models\ResultSheet\ResultDocumentPrintReceipt;
use App\Models\User;
use App\Testing\PracticeWorkspace;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Consultation Dashboard Controller (Domain: Consultation & Counselling Lifecycle)
 *
 * Architectural Purpose:
 * Manages the daily guidance counselling workflow, examinee status tracking,
 * and direct advisory lookups:
 * - Access Gate: Requires GUIDANCE_CONSULTATION_DECIDE authorization capability.
 * - Daily Queue Aggregation: Delegates to ConsultationDashboardQuery for live session rosters.
 * - Direct Candidate Lookup: Autocomplete search endpoint for walk-in students.
 * - Operational Backing: Backs the 'consultation-hub' Xiam interactive walkthrough tour.
 */
class ConsultationDashboardController extends Controller
{
    public function index(
        Request $request,
        CapabilityAuthorizer $authorizer,
        GovernanceAuthorizer $governanceAuthorizer,
        ConsultationDashboardQuery $dashboard,
        ReleaseDaySessionMonitoringQuery $releaseDaySessionMonitoring,
        ConsultationApplicantLookupQuery $applicantLookup,
        RecordAuditEvent $recordAuditEvent,
    ): Response|RedirectResponse {
        if (! $authorizer->allows($request->user(), CapabilityCatalog::GUIDANCE_CONSULTATION_DECIDE)) {
            return Inertia::render('Consultation/Dashboard', ['page_state' => 'denied', 'dashboard' => null]);
        }

        $actorScope = $request->user()->actorScope();
        $cycle = $actorScope->activeCycleCode;
        $cycleIsPermitted = $cycle !== null && AcademicCycle::query()
            ->where('institution_id', (int) $actorScope->institutionId)
            ->where('code', $cycle)
            ->where('is_active', true)
            ->exists();
        if (! $cycleIsPermitted) {
            return Inertia::render('Consultation/Dashboard', [
                'page_state' => 'empty',
                'dashboard' => null,
                'search_query' => '',
                'search_results' => [],
                'can_plan' => $authorizer->allows($request->user(), CapabilityCatalog::GUIDANCE_CONSULTATION_PLAN),
                'settings' => $this->settingsDestinations($request, $authorizer, $governanceAuthorizer, $cycle),
            ]);
        }
        $query = trim((string) $request->string('q'));

        $searchResults = [];
        if (mb_strlen($query) >= 2) {
            $matches = $applicantLookup->matchingAcceptedAttempts(
                $query,
                20,
                $actorScope->institutionId,
            );

            if ($matches->isNotEmpty()) {
                $this->auditApplicantLookup($request, $matches, $recordAuditEvent);
            }

            if ($matches->count() === 1) {
                return redirect()->route('admission-support.show', $matches->first());
            }

            $searchResults = $matches->map(fn (AdmissionAttempt $attempt) => [
                'attempt_id' => $attempt->id,
                'reference_code' => $attempt->reference_code,
                'name' => $attempt->identity ? trim($attempt->identity->first_name.' '.$attempt->identity->last_name) : $attempt->reference_code,
            ])->values()->all();
        }

        $dashboardData = $dashboard->for((int) $actorScope->institutionId, $cycle);
        $dashboardData['other_sessions'] = $releaseDaySessionMonitoring->for(
            (int) $actorScope->institutionId,
            $cycle,
        );

        return Inertia::render('Consultation/Dashboard', [
            'page_state' => 'ready',
            'dashboard' => $dashboardData,
            'search_query' => $query,
            'search_results' => $searchResults,
            'can_plan' => $authorizer->allows($request->user(), CapabilityCatalog::GUIDANCE_CONSULTATION_PLAN),
            'settings' => $this->settingsDestinations($request, $authorizer, $governanceAuthorizer, $cycle),
        ]);
    }

    /**
     * Append minimized evidence before returning any applicant projection.
     *
     * @param  Collection<int, AdmissionAttempt>  $attempts
     */
    private function auditApplicantLookup(Request $request, Collection $attempts, RecordAuditEvent $recordAuditEvent): void
    {
        /** @var User $actor */
        $actor = $request->user();
        $recordAuditEvent->handle('consultation.applicant_lookup', [
            'purpose' => 'consultation.applicant_lookup',
            'category' => 'protected_applicant_record_lookup',
            'attempt_ids' => $attempts->modelKeys(),
            'scope' => [
                'institution_id' => $actor->actorScope()->institutionId,
                'practice_fixture_set_id' => app(PracticeWorkspace::class)->currentId(),
            ],
            'relationship_basis' => 'accepted_attempt_institution_workspace',
            'correlation_id' => (string) Str::uuid(),
            'outcome' => 'matched',
            'result_count' => $attempts->count(),
        ], (string) $actor->id);
    }

    /**
     * Resolve each destination against its own current capability and resource scope.
     *
     * @return array{policy_url: ?string, admission_support_url: ?string}
     */
    private function settingsDestinations(
        Request $request,
        CapabilityAuthorizer $authorizer,
        GovernanceAuthorizer $governanceAuthorizer,
        ?string $activeCycleCode,
    ): array {
        $actor = $request->user();
        $institution = Institution::current();
        $cycle = $institution === null || $activeCycleCode === null
            ? null
            : AcademicCycle::query()
                ->where('institution_id', $institution->id)
                ->where('code', $activeCycleCode)
                ->where('is_active', true)
                ->first();
        $returnTo = route('consultation.index');
        $canOpenPolicy = $authorizer->decideFresh($actor, CapabilityCatalog::GUIDANCE_CONSULTATION_PLAN)->allowed;
        $policyUrl = $canOpenPolicy
            ? route('setup.consultation-policy.index', array_filter([
                'cycle_id' => $cycle?->id,
                'return_to' => $returnTo,
            ], static fn (mixed $value): bool => $value !== null))
            : null;
        $admissionSupportUrl = null;

        if ($institution !== null && $cycle !== null) {
            $programs = Program::query()
                ->where('institution_id', $institution->id)
                ->where('is_active', true)
                ->where('is_admission_offered', true)
                ->orderBy('code')
                ->get(['id', 'code', 'institution_id', 'is_active']);
            $studioCapabilities = [
                CapabilityCatalog::ADMISSION_BENCHMARK_GOVERNANCE_VIEW,
                CapabilityCatalog::ADMISSION_BENCHMARK_DRAFT_EDIT,
                CapabilityCatalog::ADMISSION_BENCHMARK_PREVIEW,
                CapabilityCatalog::ADMISSION_BENCHMARK_PUBLISH,
                CapabilityCatalog::ADMISSION_BENCHMARK_ACTIVATE,
                CapabilityCatalog::ADMISSION_ADVISING_NOTE_VIEW,
                CapabilityCatalog::ADMISSION_ADVISING_NOTE_DRAFT_EDIT,
                CapabilityCatalog::ADMISSION_ADVISING_NOTE_PUBLISH,
                CapabilityCatalog::ADMISSION_GOVERNANCE_DISCUSSION_BENCHMARK_READ,
                CapabilityCatalog::ADMISSION_GOVERNANCE_DISCUSSION_BENCHMARK_SEND,
                CapabilityCatalog::ADMISSION_GOVERNANCE_DISCUSSION_BENCHMARK_MANAGE_STATE,
                CapabilityCatalog::ADMISSION_GOVERNANCE_DISCUSSION_ADVISING_READ,
                CapabilityCatalog::ADMISSION_GOVERNANCE_DISCUSSION_ADVISING_SEND,
                CapabilityCatalog::ADMISSION_GOVERNANCE_DISCUSSION_ADVISING_MANAGE_STATE,
            ];
            foreach ($programs as $program) {
                foreach ($studioCapabilities as $capability) {
                    if ($governanceAuthorizer->allows($actor, $capability, $program, $cycle)) {
                        $admissionSupportUrl = route('setup.decision-support.index', [
                            'program_id' => $program->id,
                            'cycle_id' => $cycle->id,
                            'return_to' => $returnTo,
                        ]);
                        break 2;
                    }
                }
            }
        }

        return [
            'policy_url' => $policyUrl,
            'admission_support_url' => $admissionSupportUrl,
        ];
    }

    public function lookup(
        Request $request,
        CapabilityAuthorizer $authorizer,
        ConsultationMediaReadiness $mediaReadiness,
        ConsultationApplicantLookupQuery $applicantLookup,
        RecordAuditEvent $recordAuditEvent,
    ): JsonResponse {
        if (! $authorizer->allows($request->user(), CapabilityCatalog::GUIDANCE_CONSULTATION_DECIDE)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $query = trim((string) $request->string('q'));
        if (mb_strlen($query) < 2) {
            return response()->json(['results' => []]);
        }

        $attempts = $applicantLookup->matchingAcceptedAttempts(
            $query,
            10,
            $request->user()->actorScope()->institutionId,
        );
        if ($attempts->isNotEmpty()) {
            $this->auditApplicantLookup($request, $attempts, $recordAuditEvent);
        }
        $cycleIds = $attempts->pluck('academic_cycle_id')->unique();
        $disabledCycleIds = ConsultationPolicy::query()
            ->whereIn('academic_cycle_id', $cycleIds)
            ->get()
            ->reject(fn (ConsultationPolicy $policy): bool => $policy->allowsOnlineConsultation())
            ->pluck('academic_cycle_id')
            ->flip();
        $enabledCycleIds = $cycleIds
            ->reject(fn (int $cycleId): bool => isset($disabledCycleIds[$cycleId]))
            ->flip();
        $activeEncounterAttemptIds = ConsultationEncounter::query()
            ->whereIn('admission_attempt_id', $attempts->pluck('id'))
            ->where('medium', ConsultationEncounter::MEDIUM_ONLINE)
            ->whereIn('status', [
                ConsultationEncounter::STATUS_INVITED,
                ConsultationEncounter::STATUS_WAITING,
                ConsultationEncounter::STATUS_IN_PROGRESS,
            ])
            ->pluck('admission_attempt_id')
            ->flip();
        $media = $mediaReadiness->report();

        $results = $attempts->map(function (AdmissionAttempt $attempt) use ($enabledCycleIds, $activeEncounterAttemptIds, $media): array {
            $isReady = AttemptResultReadiness::isReady($attempt);
            $isAdvised = AttemptProgramFacts::isAdvised($attempt);
            $onlineReason = match (true) {
                ! $isReady => 'Online consultation requires finalized grading and a prepared result.',
                $isAdvised => 'This applicant has already completed consultation.',
                ! isset($enabledCycleIds[$attempt->academic_cycle_id]) => 'Online consultation is off for this academic cycle.',
                ! $media['ready'] => $media['reason'],
                isset($activeEncounterAttemptIds[$attempt->id]) => 'An online consultation is already active for this applicant.',
                default => null,
            };
            $medium = 'f2f';
            $documentId = $attempt->current_result_document_id;
            $isPrinted = $documentId !== null && ResultDocumentPrintReceipt::query()
                ->where('admission_attempt_id', $attempt->id)
                ->where('result_document_id', $documentId)
                ->where('operation', 'confirmed')
                ->where('is_current', true)
                ->exists();

            if ($isAdvised) {
                $statusLabel = 'Already Advised';
                $badgeVariant = 'secondary';
                $canConsult = true;
            } elseif (! $isReady) {
                $statusLabel = 'Awaiting Scores';
                $badgeVariant = 'outline';
                $canConsult = false;
            } elseif ($medium === 'f2f' && ! $isPrinted) {
                $statusLabel = 'Needs Result Sheet Print';
                $badgeVariant = 'outline';
                $canConsult = false;
            } else {
                $statusLabel = 'Ready for Release Day';
                $badgeVariant = 'default';
                $canConsult = true;
            }

            return [
                'attempt_id' => $attempt->id,
                'reference_code' => $attempt->reference_code,
                'name' => $attempt->identity ? trim($attempt->identity->first_name.' '.$attempt->identity->last_name) : $attempt->reference_code,
                'status_label' => $statusLabel,
                'badge_variant' => $badgeVariant,
                'can_consult' => $canConsult,
                'can_start_online_consultation' => $onlineReason === null,
                'online_unavailable_reason' => $onlineReason,
                'url' => route('admission-support.show', $attempt),
            ];
        })->values()->all();

        return response()->json(['results' => $results]);
    }
}
