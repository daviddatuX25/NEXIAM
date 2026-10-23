<?php

namespace App\Admission\Queries;

use App\Admission\Support\AttemptRequirementChecklistBoundary;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\AdmissionAttemptDocumentVerification;
use App\Models\Admission\AdmissionAttemptRequirementSet;
use App\Models\Foundation\AcademicCycle;
use App\Models\User;
use App\Support\Search\ModularSortScope;
use App\Support\Search\TokenizedInnerMatchScope;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Registrar applications queue — filterable, paginated list authority.
 */
final class ListAdmissionAttemptsQuery
{
    /**
     * @return array{
     *   attempts: LengthAwarePaginator,
     *   filters: array{search: ?string, status: ?string, cycle: ?string, sort: string, dir: string},
     *   statuses: list<array{value: string, label: string}>,
     *   cycles: list<array{id: int, is_active: bool, value: string, label: string}>,
     *   status_counts: array<string, int>,
     *   sort_options: list<array{value: string, label: string}>
     * }
     */
    public function handle(Request $request, User $actor): array
    {
        $search = $request->string('search')->trim()->toString();
        $search = $search !== '' ? $search : null;
        $status = $request->string('status')->trim()->toString();
        $status = $status !== '' ? $status : null;
        $cycle = $request->string('cycle')->trim()->toString();
        $cycle = $cycle !== '' ? $cycle : null;
        $sort = $request->string('sort')->trim()->toString();
        $sort = $sort !== '' ? $sort : null;
        $dir = $request->string('dir')->trim()->toString();
        $dir = $dir !== '' ? $dir : null;
        $institutionId = (int) ($actor->actorScope()->institutionId ?? 0);
        $base = AdmissionAttempt::query()->forWorkspace()
            ->where('institution_id', $institutionId)
            ->whereHas('academicCycle', fn ($query) => $query->where('institution_id', $institutionId))
            ->with([
                'identity:id,first_name,middle_name,last_name,suffix,email,phone',
                'academicCycle:id,code,name',
            ]);

        $statusCounts = [
            'all' => (clone $base)->count(),
            AdmissionAttempt::STATUS_PENDING => (clone $base)->where('status', AdmissionAttempt::STATUS_PENDING)->count(),
            AdmissionAttempt::STATUS_NEEDS_CORRECTION => (clone $base)->where('status', AdmissionAttempt::STATUS_NEEDS_CORRECTION)->count(),
            AdmissionAttempt::STATUS_ACCEPTED => (clone $base)->where('status', AdmissionAttempt::STATUS_ACCEPTED)->count(),
            AdmissionAttempt::STATUS_DISMISSED => (clone $base)->where('status', AdmissionAttempt::STATUS_DISMISSED)->count(),
            AdmissionAttempt::STATUS_ARCHIVED => (clone $base)->where('status', AdmissionAttempt::STATUS_ARCHIVED)->count(),
        ];

        $query = clone $base;

        if ($status !== null) {
            $query->where('status', $status);
        }

        if ($cycle !== null) {
            $query->whereHas('academicCycle', fn ($q) => $q->where('code', $cycle));
        }

        TokenizedInnerMatchScope::apply(
            $query,
            $search,
            [
                'admission_attempts.reference_code',
                'admission_attempts.contact_email',
                'admission_attempts.contact_phone',
                'admission_attempts.strand',
                'admission_attempts.last_school_enrolled',
                'admission_attempts.preferred_program_code',
                'admission_attempts.program_preference_1_code',
                'admission_attempts.program_preference_2_code',
                'admission_attempts.program_preference_3_code',
                function (EloquentBuilder $sub, string $likePattern): void {
                    $sub->orWhereHas('identity', function (EloquentBuilder $iq) use ($likePattern): void {
                        $iq->where(function (EloquentBuilder $nameSub) use ($likePattern): void {
                            TokenizedInnerMatchScope::orWhereColumnLike($nameSub, 'applicant_identities.first_name', $likePattern);
                            TokenizedInnerMatchScope::orWhereColumnLike($nameSub, 'applicant_identities.middle_name', $likePattern);
                            TokenizedInnerMatchScope::orWhereColumnLike($nameSub, 'applicant_identities.last_name', $likePattern);
                            TokenizedInnerMatchScope::orWhereColumnLike($nameSub, 'applicant_identities.suffix', $likePattern);
                            if (Schema::hasColumn('applicant_identities', 'name_extension')) {
                                TokenizedInnerMatchScope::orWhereColumnLike($nameSub, 'applicant_identities.name_extension', $likePattern);
                            }
                        });
                    });
                },
            ],
        );

        $allowedSorts = [
            'created_at' => 'created_at',
            'name' => fn ($q, $dir) => $q->orderByRaw('(SELECT LOWER(COALESCE(last_name, \'\')) || \' \' || LOWER(COALESCE(first_name, \'\')) FROM applicant_identities WHERE applicant_identities.id = admission_attempts.applicant_identity_id) '.$dir),
            'reference_code' => 'reference_code',
            'status' => 'status',
            'preferred_program_code' => 'preferred_program_code',
        ];

        $resolvedSort = ModularSortScope::apply(
            $query,
            $sort,
            $dir,
            $allowedSorts,
            'created_at',
            'desc',
        );

        $attempts = $query
            ->paginate(15)
            ->withQueryString();
        $pageAttempts = $attempts->getCollection();
        $latestSets = $pageAttempts->isEmpty()
            ? collect()
            : AdmissionAttemptRequirementSet::query()
                ->whereIn('id', AdmissionAttemptRequirementSet::query()
                    ->selectRaw('MAX(id)')
                    ->whereIn('admission_attempt_id', $pageAttempts->pluck('id'))
                    ->groupBy('admission_attempt_id'))
                ->with('items')
                ->get()
                ->keyBy('admission_attempt_id');
        $itemIds = $latestSets->flatMap(fn (AdmissionAttemptRequirementSet $set) => $set->items->modelKeys())->unique();
        $latestVerifications = $itemIds->isEmpty()
            ? collect()
            : AdmissionAttemptDocumentVerification::query()
                ->whereIn('admission_attempt_id', $pageAttempts->pluck('id'))
                ->whereIn('requirement_item_id', $itemIds)
                ->whereHas('requirementItem.requirementSet', fn ($query) => $query->whereColumn(
                    'admission_attempt_requirement_sets.admission_attempt_id',
                    'admission_attempt_document_verifications.admission_attempt_id',
                ))
                ->orderBy('id')
                ->get()
                ->groupBy('requirement_item_id')
                ->map(fn ($rows) => $rows->last());
        $attempts->setCollection($pageAttempts->map(fn (AdmissionAttempt $attempt): array => [
            ...$this->serialize($attempt),
            'document_summary' => $this->documentSummary($attempt, $latestSets->get($attempt->id), $latestVerifications),
        ]));

        $cycles = AcademicCycle::query()
            ->where('institution_id', $institutionId)
            ->orderByDesc('opens_on')
            ->get(['id', 'code', 'name', 'is_active'])
            ->map(fn (AcademicCycle $c): array => [
                'id' => (int) $c->id,
                'is_active' => (bool) $c->is_active,
                'value' => $c->code,
                'label' => $c->name,
            ])
            ->values()
            ->all();

        return [
            'attempts' => $attempts,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'cycle' => $cycle,
                'sort' => $resolvedSort['sort'],
                'dir' => $resolvedSort['dir'],
            ],
            'statuses' => [
                ['value' => AdmissionAttempt::STATUS_PENDING, 'label' => 'Pending'],
                ['value' => AdmissionAttempt::STATUS_NEEDS_CORRECTION, 'label' => 'Needs correction'],
                ['value' => AdmissionAttempt::STATUS_ACCEPTED, 'label' => 'Accepted'],
                ['value' => AdmissionAttempt::STATUS_DISMISSED, 'label' => 'Dismissed'],
                ['value' => AdmissionAttempt::STATUS_ARCHIVED, 'label' => 'Archived'],
            ],
            'cycles' => $cycles,
            'status_counts' => $statusCounts,
            'sort_options' => [
                ['value' => 'created_at', 'label' => 'Date Applied'],
                ['value' => 'name', 'label' => 'Applicant Name'],
                ['value' => 'reference_code', 'label' => 'Reference No.'],
                ['value' => 'status', 'label' => 'Status'],
                ['value' => 'preferred_program_code', 'label' => 'Program'],
            ],
        ];
    }

    /**
     * @return array{
     *     status: string,
     *     verified_count: int,
     *     missing_count: int,
     *     deficient_count: int,
     *     not_checked_count: int,
     *     mandatory_pending_count: int
     * }
     */
    private function documentSummary(AdmissionAttempt $attempt, ?AdmissionAttemptRequirementSet $set, Collection $latestVerifications): array
    {
        $counts = [
            'verified_count' => 0,
            'missing_count' => 0,
            'deficient_count' => 0,
            'not_checked_count' => 0,
            'mandatory_pending_count' => 0,
        ];

        $currentTrack = app(AttemptRequirementChecklistBoundary::class)->currentTrack($attempt);
        if ($currentTrack === null) {
            return ['status' => 'unknown_track', ...$counts];
        }

        if ($set === null) {
            return ['status' => 'uninitialized', ...$counts];
        }

        foreach ($set->items as $item) {
            $status = $latestVerifications->get($item->getKey())?->status ?? 'not_checked';
            $key = match ($status) {
                'verified' => 'verified_count',
                'missing' => 'missing_count',
                'deficient' => 'deficient_count',
                default => 'not_checked_count',
            };
            $counts[$key]++;

            if ($item->is_mandatory && $status !== 'verified') {
                $counts['mandatory_pending_count']++;
            }
        }

        return [
            'status' => $set->items->isEmpty()
                ? 'empty'
                : (! $set->is_current || $set->applicant_track !== $currentTrack ? 'stale' : 'current'),
            ...$counts,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(AdmissionAttempt $attempt): array
    {
        $identity = $attempt->identity;
        $name = trim(implode(' ', array_filter([
            $identity?->first_name,
            $identity?->middle_name,
            $identity?->last_name,
            $identity?->suffix,
        ])));

        return [
            'id' => $attempt->id,
            'reference_code' => $attempt->reference_code,
            'status' => $attempt->status,
            'version' => $attempt->version,
            'applicant_name' => $name !== '' ? $name : 'Unknown',
            'contact_email' => $attempt->contact_email ?? $identity?->email,
            'contact_phone' => $attempt->contact_phone ?? $identity?->phone,
            'preferred_program_code' => $attempt->program_preference_1_code
                ?? $attempt->preferred_program_code,
            'program_preferences' => array_values(array_filter([
                $attempt->program_preference_1_code,
                $attempt->program_preference_2_code,
                $attempt->program_preference_3_code,
            ])),
            'applicant_type' => $attempt->applicant_type,
            'cycle_code' => $attempt->academicCycle?->code,
            'cycle_name' => $attempt->academicCycle?->name,
            'submitted_at' => $attempt->created_at?->toIso8601String(),
            'correction_notes' => $attempt->correction_notes,
            'accepted_at' => $attempt->accepted_at?->toIso8601String(),
            'dismissed_at' => $attempt->dismissed_at?->toIso8601String(),
        ];
    }
}
