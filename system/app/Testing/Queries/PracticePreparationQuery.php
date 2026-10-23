<?php

namespace App\Testing\Queries;

use App\Admission\Support\ApplicantOfficeInquiryAccess;
use App\Admission\Support\AttemptProgramFacts;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ApplicantIdentity;
use App\Models\Admission\EnrollmentStamp;
use App\Models\Examination\ExamSession;
use App\Models\ResultSheet\ResultDocumentPrintReceipt;
use App\Models\User;
use App\Notifications\NotificationDelivery;
use App\Testing\Actions\PreparePracticeExchange;
use App\Testing\PracticeFixtureSet;
use App\Testing\PracticeScenarioCatalog;
use Illuminate\Validation\ValidationException;

final class PracticePreparationQuery
{
    public function __construct(private PracticePrerequisites $prerequisites, private PracticeScenarioCatalog $catalog, private CapabilityAuthorizer $authorizer) {}

    public function get(User $actor, array $input): array
    {
        if (! $this->catalog->valid((string) ($input['purpose'] ?? ''), (string) ($input['scenario'] ?? ''))) {
            throw ValidationException::withMessages(['scenario' => 'Choose a fixed scenario for the selected purpose.']);
        }
        if (isset($input['booklet_version_id']) && ! $this->catalog->needsExam($input['scenario'])) {
            throw ValidationException::withMessages(['booklet_version_id' => 'This scenario does not use an examination material binding.']);
        }
        [$institution, $cycle] = $this->prerequisites->context($actor);
        $set = $this->prerequisites->selected($actor, $input);
        $snapshot = $this->prerequisites->snapshot($actor, $input + ['action' => 'prepare']);
        $sets = PracticeFixtureSet::query()->where('created_by_user_id', $actor->id)->where('institution_id', $institution->id)->where('academic_cycle_id', $cycle->id)->where('purpose', $input['purpose'])->where('scenario', $input['scenario'])->orderByDesc('created_at')->get();
        $active = $set !== null && $set->status === 'active';
        $reason = $this->prerequisites->reason($snapshot);
        $booklets = $this->catalog->needsExam($input['scenario']) ? app(InstitutionalOmrBookletQuery::class)->eligible()->load('booklet') : collect();
        $actions = [];
        foreach (PracticeScenarioCatalog::ACTIONS as $action) {
            $enabled = match ($action) {
                'prepare' => $set === null && $reason === null,
                'replace' => $active && $reason === null,
                'retire' => $active,
                'time', 'reminder' => $active && ExamSession::query()->where('practice_fixture_set_id', $set->id)->whereNotIn('status', ['completed', 'cancelled'])->exists(),
                'pair' => $active && $this->prerequisites->reason(['prerequisites' => app(PreparePracticeExchange::class)->prerequisites($set, [])]) === null,
            };
            $actions[] = ['key' => $action, 'label' => match ($action) {
                'prepare' => 'Prepare fresh practice set', 'replace' => 'Retire and replace practice set', 'retire' => 'Retire practice set', 'time' => 'Change selected practice timeline', 'reminder' => 'Dispatch selected practice reminder', 'pair' => 'Prepare permitted paired exchange'
            }, 'enabled' => $enabled, 'reason' => $enabled ? null : ($action === 'prepare' && $set !== null ? 'Choose New set to prepare a fresh set.' : ($action === 'pair' && $active ? ($this->prerequisites->reason(['prerequisites' => app(PreparePracticeExchange::class)->prerequisites($set, [])]) ?? 'Native case exchange is not available.') : ($reason ?? 'Select an active owned set with an eligible session or case.')))];
        }

        return [
            'purpose' => $input['purpose'], 'scenario' => $input['scenario'], 'scenarios' => $this->catalog->scenarios($input['purpose']),
            'context' => ['institution' => $institution->name, 'cycle' => $cycle->staffLabel()],
            'sets' => $sets->map(fn (PracticeFixtureSet $item): array => $this->summary($item))->all(),
            'selected_set' => $set ? $this->projection($set, $actor) : null,
            'prerequisites' => [...$this->prerequisites->publicChecks($snapshot), ...($set ? $this->caseChecks($set) : [])], 'actions' => $actions, 'preserved' => $this->catalog->preserved(),
            'materials' => [
                'booklets' => $booklets->map(static fn ($booklet): array => ['id' => (int) $booklet->id, 'label' => ($booklet->booklet?->name ?? 'Booklet').' v'.$booklet->version])->values()->all(),
                'selected_booklet_version_id' => $snapshot['material_snapshot']['booklet_version_id'] ?? null,
            ],
            'endpoints' => ['index' => route('demo.preparation.index', absolute: false), 'preview' => route('demo.preparation.preview', absolute: false), 'confirm_template' => route('demo.preparation.confirm', ['operation' => 'OPERATION_ID'], absolute: false)],
        ];
    }

    private function summary(PracticeFixtureSet $set): array
    {
        return ['id' => $set->id, 'label' => $set->label, 'scenario' => $set->scenario, 'purpose' => $set->purpose, 'status' => $set->status, 'revision' => $set->revision];
    }

    private function caseChecks(PracticeFixtureSet $set): array
    {
        $stages = $set->material_snapshot['stages'] ?? [];
        $gates = collect($stages)->flatMap(static fn (array $stage): array => $stage['gates'] ?? [])->unique('key');

        return $gates->map(function (array $gate) use ($set, $stages): array {
            $ids = collect($stages)->filter(static fn (array $stage): bool => collect($stage['gates'] ?? [])->contains('key', $gate['key']))->flatMap(static fn (array $stage): array => $stage['attempt_ids'] ?? [])->unique()->values()->all();
            $designated = $set->material_snapshot['designated_cases'] ?? [];
            $taskCase = match ($set->scenario) {
                'student' => $gate['key'] === 'student_notice' ? ($designated['student_notice'] ?? null) : ($designated['student_primary'] ?? null),
                'registrar' => $designated['registrar_progressed'] ?? null,
                'guidance', 'academic-leader' => $designated['pair'] ?? null,
                default => null,
            };
            if ($set->purpose === 'research' && $taskCase !== null && in_array((int) $taskCase, array_map('intval', $ids), true)) {
                $ids = [(int) $taskCase];
            }
            $attempts = AdmissionAttempt::query()->where('practice_fixture_set_id', $set->id)->whereIn('id', $ids)->get();
            $available = match ($gate['key']) {
                'physical_print' => $attempts->isNotEmpty() && $attempts->every(static fn (AdmissionAttempt $attempt): bool => $attempt->current_result_document_id !== null && ResultDocumentPrintReceipt::query()->where('result_document_id', $attempt->current_result_document_id)->where('operation', 'confirmed')->where('is_current', true)->whereNotNull('confirmed_by_user_id')->whereNotNull('confirmed_at')->exists()),
                'consultation_outcome' => $attempts->isNotEmpty() && $attempts->every(static fn (AdmissionAttempt $attempt): bool => AttemptProgramFacts::isAdvised($attempt)),
                'signed_enrollment_slip' => $attempts->isNotEmpty() && $attempts->every(static function (AdmissionAttempt $attempt): bool {
                    $stamp = AttemptProgramFacts::activeStamp($attempt);

                    return $stamp !== null && $stamp->stamp_type === EnrollmentStamp::STAMP_ENROLLED && $stamp->paper_reference !== null && $stamp->paper_hash !== null && $stamp->verifier !== null;
                }),
                'student_notice' => $attempts->contains('status', AdmissionAttempt::STATUS_NEEDS_CORRECTION),
                default => false,
            };

            return ['key' => 'case-'.$gate['key'], 'label' => match ($gate['key']) {
                'physical_print' => 'Assigned case physical printing', 'consultation_outcome' => 'Actual consultation outcomes', 'signed_enrollment_slip' => 'Verified signed enrollment slips', 'student_notice' => 'Actual assigned portal notice', default => 'Actual match/model provenance'
            }, 'status' => $available ? 'available' : $gate['status'], 'reason' => $available ? 'Current attributable ordinary case evidence exists. Other gates remain independent.' : $gate['reason']];
        })->values()->all();
    }

    private function projection(PracticeFixtureSet $set, User $operator): array
    {
        $attempts = AdmissionAttempt::query()->where('practice_fixture_set_id', $set->id)->where('institution_id', $set->institution_id)->where('academic_cycle_id', $set->academic_cycle_id)->with(['identity.applicantAccount.user', 'resultDocuments', 'examAssignments.examSession.gradingSession'])->orderBy('id')->get();
        $cases = [];
        $actors = [];
        $documents = [];
        $handoffQuery = app(PracticeCaseHandoffs::class);
        $credentialMap = $set->credentials ?? [];
        foreach ($set->actor_bindings ?? [] as $role => $userId) {
            $user = User::query()->find($userId);
            if ($user === null) {
                continue;
            }
            $capability = match ($role) {
                'registrar' => CapabilityCatalog::REGISTRAR_APPLICATIONS_VIEW, 'guidance' => CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE, 'academic-leader' => CapabilityCatalog::PROGRAM_HEAD_MESSAGE, default => CapabilityCatalog::DEMO_STAGE_CONTROL
            };
            $decision = $this->authorizer->decideFresh($user, $capability, ['institution_id' => $set->institution_id, 'cycle' => $set->academicCycle->code]);
            $actors[] = ['role' => $role, 'name' => $user->name, 'email' => $user->email, 'login_url' => route('login', absolute: false), 'checks' => [['label' => 'Current assigned authority and scope', 'allowed' => $decision->allowed, 'reason' => $decision->allowed ? 'Sign in through normal staff authentication. No password is supplied or changed.' : 'The assigned account no longer has the current required authority.']]];
        }
        foreach ($attempts as $attempt) {
            $credentials = $credentialMap[(string) $attempt->applicant_identity_id] ?? [];
            $applicant = $attempt->identity->applicantAccount?->user;
            $stage = $credentials['stage'] ?? null;
            if ($stage === null) {
                foreach ($set->material_snapshot['stages'] ?? [] as $stageKey => $stageSnapshot) {
                    if (in_array((int) $attempt->id, array_map('intval', $stageSnapshot['attempt_ids'] ?? []), true)) {
                        $stage = $stageKey;
                        break;
                    }
                }
            }
            $actors[] = ['role' => 'applicant', 'case_id' => (int) $attempt->id, 'stage' => $stage, 'name' => $attempt->identity->fullName(), 'email' => $attempt->identity->email, 'login_url' => route('applicant.login', absolute: false), 'access_key' => $credentials['access_key'] ?? null, 'birthdate' => $credentials['birthdate'] ?? null, 'checks' => [['label' => 'Assigned applicant account', 'allowed' => $applicant !== null && $set->status === 'active', 'reason' => $applicant !== null ? 'Owned account; use its generated key and birthdate.' : 'Applicant account registration is missing.']]];
            if ($set->scenario === 'student') {
                $inquiryAllowed = $set->status === 'active' && $applicant !== null
                    && $this->authorizer->decideFresh($applicant, CapabilityCatalog::APPLICANT_OFFICE_INQUIRY_USE)->allowed
                    && app(ApplicantOfficeInquiryAccess::class)->isAvailable($applicant);
            }
            $handoffs = $handoffQuery->for($set, $attempt);
            $primaryKey = match ($set->scenario) {
                'application-review' => 'review',
                'exam-scheduling-attendance' => $stage === 'conduct' ? 'monitoring' : 'scheduling',
                'scoring-results' => 'grading',
                'consultation-referral', 'case-coordination' => 'consultation',
                'enrollment-verification' => 'enrollment',
                'student' => 'applicant',
                'registrar' => $stage === 'review' ? 'review' : 'enrollment',
                'guidance' => match ($stage) {
                    'reservation', 'seating' => 'scheduling', 'conduct' => 'monitoring', 'grading' => 'omr', default => 'consultation'
                },
                'academic-leader' => 'academic',
                default => 'review',
            };
            $primary = collect($handoffs)->firstWhere('key', $primaryKey);
            $state = $attempt->status === AdmissionAttempt::STATUS_ACCEPTED ? AttemptProgramFacts::lifecycleTag($attempt) : $attempt->status;
            $cases[] = ['id' => (int) $attempt->id, 'name' => $attempt->identity->fullName(), 'state' => $state, 'stage' => $stage, 'url' => $primary['url'], 'actor' => $primary['actor'], 'available' => $primary['available'], 'reason' => $primary['reason'], 'handoffs' => $handoffs];
            $grading = $attempt->examAssignments->map(fn ($assignment) => $assignment->examSession?->gradingSession)->filter()->first();
            foreach ($attempt->resultDocuments->where('id', $attempt->current_result_document_id) as $document) {
                $receipt = ResultDocumentPrintReceipt::query()->where('result_document_id', $document->id)->where('operation', 'confirmed')->where('is_current', true)->whereNotNull('confirmed_by_user_id')->whereNotNull('confirmed_at')->exists();
                $guidance = isset($set->actor_bindings['guidance']) ? User::query()->find($set->actor_bindings['guidance']) : null;
                $allowed = $guidance !== null && $this->authorizer->decideFresh($guidance, CapabilityCatalog::GUIDANCE_RESULTS_RELEASE, ['institution_id' => $set->institution_id, 'cycle' => $set->academicCycle->code])->allowed;
                $documents[] = ['label' => $attempt->identity->fullName().' · result document '.$document->id, 'url' => $grading && $allowed ? route('release.print.batches.applicant', ['gradingSession' => $grading->id, 'attempt' => $attempt->id, 'practice_set' => $set->id], absolute: false) : null, 'print_url' => $grading && $allowed ? route('release.print.batches.index', ['gradingSession' => $grading->id, 'practice_set' => $set->id], absolute: false) : null, 'physical_status' => $receipt ? 'Attributable current print confirmation exists; handover is separate.' : 'Physical printing and handover unverified; no receipt is fabricated.'];
            }
        }
        $paired = app(PreparePracticeExchange::class)->projection($set);
        $pairedCase = collect($cases)->firstWhere('id', $paired['case_id'] ?? null);
        $paired['handoffs'] = $pairedCase === null ? [] : collect($pairedCase['handoffs'])->whereIn('key', ['consultation', 'academic'])->values()->all();
        $research = $set->purpose === 'research' ? app(PracticeResearchAssignments::class)->for($set, $attempts, $cases, $paired) : ['designated_case_id' => null, 'task_assignments' => []];
        $taskRows = collect($research['task_assignments']);
        foreach ($cases as &$case) {
            $case['task_ids'] = $taskRows->where('case_id', $case['id'])->pluck('task_id')->values()->all();
            $case['designated'] = (int) ($research['designated_case_id'] ?? 0) === $case['id'];
        }
        unset($case);
        foreach ($actors as &$actor) {
            $assignedTasks = $actor['role'] === 'applicant' ? $taskRows->where('actor', 'applicant')->where('case_id', $actor['case_id']) : $taskRows->where('actor', $actor['role']);
            $actor['task_ids'] = $assignedTasks->pluck('task_id')->values()->all();
            $actor['designated'] = $actor['task_ids'] !== [];
        }
        unset($actor);
        $assignments = $taskRows->groupBy(static fn (array $task): string => json_encode([$task['case_id'], $task['actor'], $task['available'], $task['reason'], $task['url'], $task['session_id'] ?? null, $task['document_id'] ?? null], JSON_THROW_ON_ERROR))->map(static function ($tasks): array {
            $first = $tasks->first();
            $user = isset($first['account_user_id']) ? User::query()->find($first['account_user_id']) : null;

            return ['task_ids' => $tasks->pluck('task_id')->values()->all(), 'case_id' => $first['case_id'], 'account_name' => $user?->name, 'actor' => $first['actor'], 'stage' => $first['stage'], 'url' => $first['url'], 'session_id' => $first['session_id'] ?? null, 'document_id' => $first['document_id'] ?? null, 'available' => $first['available'], 'reason' => $first['reason']];
        })->values()->all();
        $identities = $attempts->pluck('applicant_identity_id')->unique()->all();
        $deliveries = NotificationDelivery::query()->where('recipient_type', ApplicantIdentity::class)->whereIn('recipient_id', $identities)->orderByDesc('created_at')->get()->map(static fn (NotificationDelivery $delivery): array => ['id' => $delivery->id, 'window' => $delivery->payload['practice_reminder_window'] ?? $delivery->payload['event_resource']['window'] ?? $delivery->payload['window'] ?? $delivery->payload['reminder_window'] ?? null, 'channel' => $delivery->channel, 'status' => match ($delivery->status) {
            'pending', 'processing', 'retrying' => 'queued', 'delivered' => 'delivered', 'superseded' => 'suppressed', default => 'failed'
        }, 'reason' => $delivery->last_error ?? ($delivery->status === 'delivered' ? 'Actual channel delivery recorded.' : 'Delivery has not been confirmed.')])->all();

        return $this->summary($set) + [
            'cases' => $cases, 'actors' => $actors,
            'sessions' => ExamSession::query()->where('practice_fixture_set_id', $set->id)->where('academic_cycle_id', $set->academic_cycle_id)->orderBy('id')->get()->map(static fn (ExamSession $session): array => ['id' => $session->id, 'label' => $session->label ?: $session->code, 'status' => $session->status, 'starts_at' => $session->starts_at?->toIso8601String(), 'ends_at' => $session->effective_end_at?->toIso8601String()])->all(),
            'documents' => $documents, 'paired_exchange' => $paired, 'deliveries' => $deliveries,
            'designated_case_id' => $research['designated_case_id'], 'assignments' => $assignments,
        ];
    }
}
