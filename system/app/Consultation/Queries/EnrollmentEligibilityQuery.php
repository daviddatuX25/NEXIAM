<?php

namespace App\Consultation\Queries;

use App\Admission\Queries\AttemptRequirementChecklistQuery;
use App\Admission\Support\AdmissionClearanceReadiness;
use App\Admission\Support\AttemptProgramFacts;
use App\Consultation\Support\AttemptConsultationStatus;
use App\Consultation\Support\AttemptResultReadiness;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\AdmissionClearanceReceipt;
use App\Models\Admission\AdmissionDocumentCorrectionRequest;
use App\Models\Admission\EnrollmentSheetHandoffReceipt;
use App\Models\Admission\EnrollmentStamp;
use App\Models\Admission\ProgramFollowUp;
use App\Models\Admission\ProgramSelection;
use App\Models\Assessment\GradingSession;
use App\Models\Examination\ExamAssignment;
use App\Models\Release\ResultRelease;
use App\Models\User;
use App\Support\Search\ModularSortScope;
use App\Support\Search\TokenizedInnerMatchScope;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Registrar eligibility surface (A3, founder 2026-08-14): a status search —
 * the Registrar counter is where students without phones check their status.
 * Shows pipeline status facts only (accepted / individual result ready / batch
 * finalized / released / advised); never scores or counselor notes. Every card lookup is
 * audited.
 */
class EnrollmentEligibilityQuery
{
    public function __construct(private readonly RecordAuditEvent $recordAuditEvent) {}

    public static function determineClearanceStatus(AdmissionAttempt $attempt): string
    {
        if (AttemptProgramFacts::isClearanceConfirmed($attempt)) {
            return 'confirmed';
        }

        $readiness = AdmissionClearanceReadiness::evaluate($attempt);
        if ($readiness['is_ready'] ?? false) {
            return 'ready';
        }

        return 'pending_requirements';
    }

    /**
     * @return Collection<int, array{id: int, reference_number: string, name: string, status: string, clearance_status: string}>
     */
    public function search(string $query, ?User $actor = null): Collection
    {
        $q = trim($query);

        if ($q === '') {
            return collect();
        }

        $institutionId = $actor instanceof User ? (int) ($actor->actorScope()->institutionId ?? 0) : 0;
        $queryBuilder = AdmissionAttempt::query()->forWorkspace()
            ->when($institutionId > 0, fn ($b) => $b->where('institution_id', $institutionId)
                ->whereHas('academicCycle', fn ($cycle) => $cycle->where('institution_id', $institutionId)))
            ->with(['identity', 'clearanceReceipts', 'latestClearanceReceipt']);

        TokenizedInnerMatchScope::apply(
            $queryBuilder,
            $q,
            [
                'admission_attempts.reference_code',
                'admission_attempts.contact_email',
                'admission_attempts.preferred_program_code',
                function (EloquentBuilder $sub, string $likePattern): void {
                    $sub->orWhereHas('identity', function (EloquentBuilder $iq) use ($likePattern): void {
                        $iq->where(function (EloquentBuilder $nameSub) use ($likePattern): void {
                            TokenizedInnerMatchScope::orWhereColumnLike($nameSub, 'applicant_identities.first_name', $likePattern);
                            TokenizedInnerMatchScope::orWhereColumnLike($nameSub, 'applicant_identities.middle_name', $likePattern);
                            TokenizedInnerMatchScope::orWhereColumnLike($nameSub, 'applicant_identities.last_name', $likePattern);
                        });
                    });
                },
                function (EloquentBuilder $sub, string $likePattern): void {
                    $sub->orWhereHas('clearanceReceipts', function (EloquentBuilder $rq) use ($likePattern): void {
                        TokenizedInnerMatchScope::orWhereColumnLike($rq, 'admission_clearance_receipts.receipt_number', $likePattern);
                    });
                },
            ],
        );

        return $queryBuilder
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn (AdmissionAttempt $attempt) => [
                'id' => $attempt->id,
                'reference_number' => $attempt->reference_code,
                'name' => trim(implode(' ', array_filter([
                    $attempt->identity?->first_name,
                    $attempt->identity?->middle_name,
                    $attempt->identity?->last_name,
                ]))) ?: ($attempt->reference_code ?? ''),
                'status' => $attempt->status,
                'clearance_status' => self::determineClearanceStatus($attempt),
            ]);
    }

    /**
     * @return array{
     *     results: LengthAwarePaginator,
     *     filters: array{search: ?string, status: string, sort: string, dir: string},
     *     sort_options: list<array{value: string, label: string}>,
     *     counts: array{all: int, ready: int, confirmed: int, pending_requirements: int}
     * }
     */
    public function paginate(Request|array $requestOrFilters = [], ?User $actor = null): array
    {
        if ($requestOrFilters instanceof Request) {
            $actor = $actor ?? $requestOrFilters->user();
            $search = $requestOrFilters->filled('q')
                ? $requestOrFilters->string('q')->trim()->toString()
                : $requestOrFilters->string('search')->trim()->toString();
            $status = $requestOrFilters->string('status')->trim()->toString();
            $sort = $requestOrFilters->string('sort')->trim()->toString();
            $dir = $requestOrFilters->string('dir')->trim()->toString();
        } else {
            $search = trim((string) ($requestOrFilters['search'] ?? $requestOrFilters['q'] ?? ''));
            $status = trim((string) ($requestOrFilters['status'] ?? ''));
            $sort = trim((string) ($requestOrFilters['sort'] ?? ''));
            $dir = trim((string) ($requestOrFilters['dir'] ?? ''));
        }

        $status = in_array($status, ['ready', 'confirmed', 'pending_requirements'], true) ? $status : 'all';
        $institutionId = $actor instanceof User ? (int) ($actor->actorScope()->institutionId ?? 0) : 0;

        $baseQuery = AdmissionAttempt::query()->forWorkspace()
            ->when($institutionId > 0, fn ($b) => $b->where('institution_id', $institutionId)
                ->whereHas('academicCycle', fn ($cycle) => $cycle->where('institution_id', $institutionId)))
            ->with(['identity', 'clearanceReceipts', 'latestClearanceReceipt']);

        if ($search !== '') {
            TokenizedInnerMatchScope::apply(
                $baseQuery,
                $search,
                [
                    'admission_attempts.reference_code',
                    'admission_attempts.contact_email',
                    'admission_attempts.preferred_program_code',
                    function (EloquentBuilder $sub, string $likePattern): void {
                        $sub->orWhereHas('identity', function (EloquentBuilder $iq) use ($likePattern): void {
                            $iq->where(function (EloquentBuilder $nameSub) use ($likePattern): void {
                                TokenizedInnerMatchScope::orWhereColumnLike($nameSub, 'applicant_identities.first_name', $likePattern);
                                TokenizedInnerMatchScope::orWhereColumnLike($nameSub, 'applicant_identities.middle_name', $likePattern);
                                TokenizedInnerMatchScope::orWhereColumnLike($nameSub, 'applicant_identities.last_name', $likePattern);
                            });
                        });
                    },
                    function (EloquentBuilder $sub, string $likePattern): void {
                        $sub->orWhereHas('clearanceReceipts', function (EloquentBuilder $rq) use ($likePattern): void {
                            TokenizedInnerMatchScope::orWhereColumnLike($rq, 'admission_clearance_receipts.receipt_number', $likePattern);
                        });
                    },
                ],
            );
        }

        $allCandidates = (clone $baseQuery)->get();
        $statusMap = [];
        $counts = [
            'all' => 0,
            'ready' => 0,
            'confirmed' => 0,
            'pending_requirements' => 0,
        ];

        foreach ($allCandidates as $candidate) {
            $st = self::determineClearanceStatus($candidate);
            $statusMap[$candidate->id] = $st;
            $counts['all']++;
            $counts[$st]++;
        }

        $query = clone $baseQuery;

        if ($status !== 'all') {
            $matchingIds = array_keys(array_filter($statusMap, fn ($s) => $s === $status));
            $query->whereIn('admission_attempts.id', $matchingIds);
        }

        $allowedSorts = [
            'id' => 'admission_attempts.id',
            'name' => fn ($q, $dir) => $q->orderByRaw('(SELECT LOWER(COALESCE(last_name, \'\')) || \' \' || LOWER(COALESCE(first_name, \'\')) FROM applicant_identities WHERE applicant_identities.id = admission_attempts.applicant_identity_id) '.$dir),
            'reference_code' => 'admission_attempts.reference_code',
        ];

        $sortParams = ModularSortScope::apply(
            $query,
            $sort !== '' ? $sort : null,
            $dir !== '' ? $dir : null,
            $allowedSorts,
            'id',
            'desc',
            'admission_attempts.id',
        );

        $paginator = $query->paginate(15)->withQueryString();

        $paginator->setCollection($paginator->getCollection()->map(fn (AdmissionAttempt $attempt) => [
            'id' => $attempt->id,
            'reference_number' => $attempt->reference_code,
            'name' => trim(implode(' ', array_filter([
                $attempt->identity?->first_name,
                $attempt->identity?->middle_name,
                $attempt->identity?->last_name,
            ]))) ?: ($attempt->reference_code ?? ''),
            'status' => $attempt->status,
            'clearance_status' => $statusMap[$attempt->id] ?? self::determineClearanceStatus($attempt),
            'preferred_program_code' => $attempt->preferred_program_code,
            'contact_email' => $attempt->contact_email,
        ]));

        $sortOptions = [
            ['value' => 'id', 'label' => 'ID'],
            ['value' => 'name', 'label' => 'Applicant Name'],
            ['value' => 'reference_code', 'label' => 'Reference No.'],
        ];

        $results = new class($paginator->items(), $paginator->total(), $paginator->perPage(), $paginator->currentPage(), ['path' => $paginator->path(), 'query' => $paginator->getOptions()['query'] ?? []]) extends LengthAwarePaginator
        {
            public function toArray(): array
            {
                return $this->items->all();
            }

            public function jsonSerialize(): array
            {
                return $this->items->all();
            }
        };

        return [
            'results' => $results,
            'filters' => [
                'search' => $search !== '' ? $search : null,
                'status' => $status,
                'sort' => $sortParams['sort'],
                'dir' => $sortParams['dir'],
            ],
            'sort_options' => $sortOptions,
            'counts' => $counts,
        ];
    }

    /**
     * Status-fact card for one attempt. Audited per lookup.
     *
     * @return array{
     *     id: int,
     *     reference_number: string,
     *     name: string,
     *     status: string,
     *     facts: array{accepted: bool, grading_finalized: bool, result_ready: bool, result_released: bool, advised: bool},
     *     released_at: ?string,
     *     eligible: bool,
     *     missing: list<string>,
     *     selection: ?array{program_code: ?string, status: string, source: string, acknowledged: bool},
     *     follow_ups: list<array{program_code: ?string, status: string}>,
     *     enrollment_stamps: list<array{
     *         id: int,
     *         version: int,
     *         state: string,
     *         provisional_program: ?string,
     *         final_program: ?string,
     *         paper_reference: ?string,
     *         reason: ?string,
     *         created_at: ?string
     *     }>,
     *     checklist: array{
     *         set: ?array{
     *             id: int,
     *             admission_attempt_id: int,
     *             applicant_track: string,
     *             catalog_version: int,
     *             is_current: bool,
     *             initialized_by_user_id: ?int,
     *             initialized_at: ?string
     *         },
     *         items: list<array{
     *             id: int,
     *             code: string,
     *             label: string,
     *             item_kind: string,
     *             is_mandatory: bool,
     *             sort_order: int,
     *             latest_status: string,
     *             latest_verification_id: ?int,
     *             latest_verified_at: ?string,
     *             applicant_instruction: ?string,
     *             internal_remarks: ?string,
     *             history: list<array<string, mixed>>
     *         }>,
     *         all_mandatory_verified: bool,
     *         mandatory_pending_count: int,
     *         status: string,
     *         current_track: ?string,
     *         mutation_block_reason: ?string,
     *         documents_view: bool,
     *         documents_initialize: bool,
     *         documents_verify: bool,
     *         documents_reconcile: bool,
     *         documents_request: bool,
     *         suggestions: list<array{code: string, label: string, template: string}>
     *     },
     *     clearance_readiness: array{
     *         is_ready: bool,
     *         checks: array<string, bool>,
     *         blocking_reasons: list<string>,
     *         program_id: ?int,
     *         evidence_vector: array<string, mixed>
     *     },
     *     latest_clearance: ?array{
     *         id: int,
     *         receipt_number: string,
     *         status: string,
     *         program_code: ?string,
     *         program_name: ?string,
     *         confirmed_at: ?string,
     *         confirmed_by_name: ?string,
     *         revoked_at: ?string,
     *         revoked_by_name: ?string,
     *         revocation_reason: ?string
     *     },
     *     historical_latest_sheet_handoff: ?array{
     *         id: int,
     *         receipt_number: string,
     *         paper_reference: ?string,
     *         program_code: ?string,
     *         program_name: ?string,
     *         issued_at: ?string,
     *         issued_by_name: ?string
     *     }
     * }
     */
    public function cardFor(AdmissionAttempt $attempt, User $actor): array
    {
        $this->recordAuditEvent->handle('registrar.eligibility_lookup', [
            'attempt_id' => $attempt->id,
            'reference_code' => $attempt->reference_code,
        ], (string) $actor->id);

        $gradingFinalized = ExamAssignment::query()
            ->where('admission_attempt_id', $attempt->id)
            ->whereHas('examSession.gradingSession', fn ($grading) => $grading->where('status', GradingSession::STATUS_FINALIZED))
            ->exists();

        $release = ResultRelease::query()
            ->where('admission_attempt_id', $attempt->id)
            ->where('is_current', true)
            ->first();

        $facts = [
            'accepted' => $attempt->status === AdmissionAttempt::STATUS_ACCEPTED,
            'grading_finalized' => $gradingFinalized,
            'result_ready' => AttemptResultReadiness::isReady($attempt),
            'result_released' => $release !== null,
            'advised' => AttemptConsultationStatus::isAdvised($attempt),
        ];

        $missing = collect($facts)
            ->except('grading_finalized')
            ->filter(fn (bool $done): bool => ! $done)
            ->keys()
            ->values()
            ->all();

        // D-090/D-091 admission facts: selection, follow-up, stamp state.
        $selection = ProgramSelection::query()
            ->where('admission_attempt_id', $attempt->id)
            ->whereIn('status', ProgramSelection::NON_TERMINAL_STATUSES)
            ->with('program')
            ->latest('id')
            ->first();

        $followUps = ProgramFollowUp::query()
            ->where('admission_attempt_id', $attempt->id)
            ->whereIn('status', ProgramFollowUp::OPEN_STATUSES)
            ->with('program')
            ->get();

        $enrollmentStamps = $attempt->enrollmentStamps()
            ->with(['provisionalProgram', 'finalProgram'])
            ->orderBy('version', 'desc')
            ->get();

        $checklist = app(AttemptRequirementChecklistQuery::class)->get($attempt, $actor);
        $requestHistory = AdmissionDocumentCorrectionRequest::applicantProjectionFor($attempt)['requests'];
        $clearanceReadiness = AdmissionClearanceReadiness::evaluate($attempt);

        $latestClearance = AdmissionClearanceReceipt::query()
            ->where('admission_attempt_id', $attempt->id)
            ->with(['program', 'confirmedBy', 'revokedBy'])
            ->latest('id')
            ->first();

        $latestSheetHandoff = EnrollmentSheetHandoffReceipt::query()
            ->where('admission_attempt_id', $attempt->id)
            ->with(['program', 'issuedBy'])
            ->latest('id')
            ->first();

        return [
            'id' => $attempt->id,
            'reference_number' => $attempt->reference_code,
            'name' => trim(implode(' ', array_filter([
                $attempt->identity?->first_name,
                $attempt->identity?->middle_name,
                $attempt->identity?->last_name,
            ]))) ?: ($attempt->reference_code ?? ''),
            'status' => $attempt->status,
            'version' => (int) $attempt->version,
            'facts' => $facts,
            'released_at' => $release?->getAttributeValue('released_at')?->toIso8601String(),
            'eligible' => $missing === [],
            'missing' => $missing,
            'selection' => $selection === null ? null : [
                'program_code' => $selection->program?->code,
                'status' => $selection->status,
                'source' => $selection->source,
                'acknowledged' => $selection->applicant_acknowledged_at !== null,
            ],
            'follow_ups' => $followUps->map(fn ($f) => [
                'program_code' => $f->program?->code,
                'status' => $f->status,
            ])->all(),
            'enrollment_stamps' => $enrollmentStamps->map(fn (EnrollmentStamp $stamp) => [
                'id' => (int) $stamp->id,
                'version' => (int) $stamp->version,
                'state' => (string) $stamp->stamp_type,
                'provisional_program' => $stamp->provisionalProgram?->code,
                'final_program' => $stamp->finalProgram?->code,
                'paper_reference' => $stamp->paper_reference,
                'reason' => $stamp->reason,
                'created_at' => $stamp->created_at?->toIso8601String(),
            ])->all(),
            'checklist' => $checklist,
            'document_checklist' => [
                ...$checklist,
                'flags' => [
                    'documents_view' => $checklist['documents_view'],
                    'documents_initialize' => $checklist['documents_initialize'],
                    'documents_verify' => $checklist['documents_verify'],
                    'documents_reconcile' => $checklist['documents_reconcile'],
                    'documents_request' => $checklist['documents_request'],
                ],
                'request_history' => $requestHistory,
                'expected_version' => (int) $attempt->version,
                'is_clearance_confirmed' => AttemptProgramFacts::isClearanceConfirmed($attempt),
            ],
            'clearance_readiness' => $clearanceReadiness,
            'latest_clearance' => $latestClearance === null ? null : [
                'id' => $latestClearance->id,
                'receipt_number' => $latestClearance->receipt_number,
                'status' => $latestClearance->status,
                'program_code' => $latestClearance->program?->code,
                'program_name' => $latestClearance->program?->name,
                'confirmed_at' => $latestClearance->confirmed_at?->toIso8601String(),
                'confirmed_by_name' => $latestClearance->confirmedBy?->name,
                'revoked_at' => $latestClearance->revoked_at?->toIso8601String(),
                'revoked_by_name' => $latestClearance->revokedBy?->name,
                'revocation_reason' => $latestClearance->revocation_reason,
            ],
            'historical_latest_sheet_handoff' => $latestSheetHandoff === null ? null : [
                'id' => $latestSheetHandoff->id,
                'receipt_number' => $latestSheetHandoff->receipt_number,
                'paper_reference' => $latestSheetHandoff->paper_reference,
                'program_code' => $latestSheetHandoff->program?->code,
                'program_name' => $latestSheetHandoff->program?->name,
                'issued_at' => $latestSheetHandoff->issued_at?->toIso8601String(),
                'issued_by_name' => $latestSheetHandoff->issuedBy?->name,
            ],
        ];
    }
}
