<?php

declare(strict_types=1);

namespace App\Communications\Queries;

use App\Communications\PublicPolicySourceAuthorizer;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Communications\PublicPolicy;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Program;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

final class PublicPolicyQuery
{
    public function __construct(private readonly PublicPolicySourceAuthorizer $sources) {}

    /** @return list<array<string, mixed>> */
    public function publicPolicies(?Program $program = null): array
    {
        return $this->eligiblePublicBulletinPreviews($program);
    }

    /** @return list<array<string, mixed>> */
    public function eligiblePublicBulletinPreviews(?Program $program = null, ?int $limit = null): array
    {
        $institution = Institution::query()->where('code', 'SYN-INST-001')->first();
        if ($institution === null || ($program !== null && (! $program->is_active || (int) $program->institution_id !== (int) $institution->id))) {
            return [];
        }
        $query = PublicPolicy::query()->where('institution_id', $institution->id)->where('status', 'published')
            ->whereNotNull('published_revision_id')
            ->whereHas('publishedRevision', fn ($revision) => $revision
                ->where('institution_id', $institution->id)
                ->where('state', 'published'));
        if ($program === null) {
            $query->whereNull('program_id');
        } else {
            $query->where('program_id', $program->id);
        }
        $rows = $query->with(['publishedRevision', 'program'])
            ->orderByDesc('published_at')->orderByDesc('id')->get()
            ->filter(fn (PublicPolicy $policy): bool => $policy->publishedRevision !== null
                && ($program === null || ($policy->program !== null && $policy->program->is_active && (int) $policy->program->institution_id === (int) $institution->id)));
        if ($limit !== null) {
            $rows = $rows->take($limit);
        }

        return $rows->map(fn (PublicPolicy $policy): array => $this->publicDto($policy))->values()->all();
    }

    /** @return array<string, mixed> */
    public function eligiblePublicDetail(int $id): array
    {
        $institution = Institution::query()->where('code', 'SYN-INST-001')->first();
        abort_if($institution === null, 404);
        $policy = PublicPolicy::query()->whereKey($id)->where('institution_id', $institution->id)
            ->where('status', 'published')->whereNotNull('published_revision_id')
            ->whereHas('publishedRevision', fn ($revision) => $revision
                ->where('institution_id', $institution->id)
                ->where('state', 'published'))
            ->with(['publishedRevision', 'program'])->firstOrFail();
        $program = $policy->program;
        abort_if($policy->program_id !== null && ($program === null || ! $program->is_active || (int) $program->institution_id !== (int) $institution->id), 404);

        return [
            'policy' => $this->publicDto($policy, true),
            'owningProgram' => $program === null ? null : ['id' => (int) $program->id, 'code' => $program->code, 'name' => $program->name],
        ];
    }

    /**
     * @param  array{status?: string, search?: string, placement?: string, page?: int}  $filters
     * @return array<string, mixed>
     */
    public function staffIndex(User $actor, array $filters = []): array
    {
        $status = (string) ($filters['status'] ?? 'all');
        $search = trim((string) ($filters['search'] ?? ''));
        $placement = (string) ($filters['placement'] ?? 'all');
        $page = (int) ($filters['page'] ?? 1);
        $fresh = User::query()->findOrFail($actor->getKey());
        $publishers = $this->sources->publishers($fresh);
        abort_unless($publishers !== [], 404);
        $keys = array_column($publishers, 'key');
        $institutionId = (int) $fresh->actorScope()->institutionId;
        $programs = $this->sources->targetPrograms($fresh);
        $programIds = array_column($programs, 'id');
        $currentSelection = PublicPolicy::currentIntakeSelection($institutionId);
        $currentSelectionId = $currentSelection === null ? null : (int) $currentSelection->getKey();
        $baseQuery = PublicPolicy::query()->select([
            'id', 'institution_id', 'publisher_key', 'publisher_label', 'program_id', 'kind', 'is_designated_intake', 'title',
            'revision', 'published_revision_id', 'status', 'published_at', 'withdrawal_reason', 'updated_at',
        ])->where('institution_id', $institutionId)->whereIn('publisher_key', $keys)
            ->where(fn ($scope) => $scope->whereNull('program_id')->orWhereIn('program_id', $programIds))
            ->with([
                'program:id,code,name,institution_id',
                'publishedRevision:id,public_policy_id,institution_id,publisher_key,publisher_label,program_id,revision,kind,state,title',
            ]);
        $countQuery = clone $baseQuery;
        if ($placement === 'general') {
            $countQuery->whereNull('program_id');
        } elseif ($placement === 'program') {
            $countQuery->whereNotNull('program_id');
        }
        if ($search !== '') {
            $pattern = '%'.$search.'%';
            $countQuery->where(static function (Builder $matches) use ($pattern): void {
                $matches->whereLike('title', $pattern)
                    ->orWhereLike('publisher_label', $pattern)
                    ->orWhereHas('program', static function (Builder $program) use ($pattern): void {
                        $program->whereLike('code', $pattern)
                            ->orWhereLike('name', $pattern);
                    });
            });
        }
        $statusCounts = [
            'all' => (clone $countQuery)->count(),
            'published' => (clone $countQuery)->where('status', 'published')->count(),
            'draft' => (clone $countQuery)->where('status', 'draft')->count(),
            'withdrawn' => (clone $countQuery)->where('status', 'withdrawn')->count(),
        ];
        $query = clone $countQuery;
        if ($status !== 'all') {
            $query->where('status', $status);
        }
        $permissions = [];
        foreach ($publishers as $publisher) {
            $permissions[$publisher['key']] = [
                'manage' => true,
                'publish' => $this->sources->canPublish($fresh, $publisher['key']),
            ];
        }
        $items = $query->orderByDesc('updated_at')->orderByDesc('id')->paginate(15, ['*'], 'page', $page)->withQueryString();
        $rows = $items->getCollection()->map(fn (PublicPolicy $policy): array => $this->staffDto(
            $policy,
            $fresh,
            permissions: $permissions[$policy->publisher_key] ?? ['manage' => false, 'publish' => false],
            currentIntakePolicyId: $currentSelectionId,
        ));

        return [
            'page_state' => $rows->isEmpty() ? 'empty' : 'ready',
            'policies' => $items->setCollection($rows)->toArray(),
            'publishers' => $publishers, 'programs' => $programs,
            'capabilities' => [
                'manage' => $publishers !== [],
                'publish' => collect($permissions)->contains(fn (array $grant): bool => $grant['publish']),
            ],
            'current_intake_selection' => $this->intakeSelectionDto($currentSelection, $fresh),
            'expected_intake_policy_id' => $currentSelectionId,
            'filters' => ['status' => $status, 'search' => $search, 'placement' => $placement],
            'status_counts' => $statusCounts,
        ];
    }

    /** @return array<string, mixed> */
    public function staffForm(User $actor, ?PublicPolicy $policy = null): array
    {
        $fresh = User::query()->findOrFail($actor->getKey());
        $publishers = $this->sources->publishers($fresh);
        abort_unless($publishers !== [], 404);
        $institutionId = (int) $fresh->actorScope()->institutionId;
        $currentSelection = PublicPolicy::currentIntakeSelection($institutionId);
        $currentSelectionId = $currentSelection === null ? null : (int) $currentSelection->getKey();
        $props = [
            'publishers' => $publishers,
            'programs' => $this->sources->targetPrograms($fresh),
            'isEdit' => $policy !== null,
            'current_intake_selection' => $this->intakeSelectionDto($currentSelection, $fresh),
            'expected_intake_policy_id' => $currentSelectionId,
        ];
        if ($policy !== null) {
            $this->assertReadable($fresh, $policy);
            $policy->load(['revisions', 'program', 'publishedRevision']);
            $props['policy'] = $this->staffDto($policy, $fresh, includeContent: true, currentIntakePolicyId: $currentSelectionId, includeKindLock: true);
            $props['revisions'] = $policy->revisions->map(fn ($revision): array => [
                'revision' => $revision->revision, 'title' => $revision->title, 'content' => $revision->content,
                'kind' => $revision->kind,
                'state' => $revision->state, 'actor_name' => $revision->actor_name,
                'reason' => $revision->reason, 'created_at' => $revision->created_at?->toIso8601String(),
            ])->all();
        }

        return $props;
    }

    private function assertReadable(User $actor, PublicPolicy $policy): void
    {
        if ($policy->is_designated_intake) {
            abort_unless(
                (int) $policy->institution_id === (int) $actor->actorScope()->institutionId
                    && in_array($actor->actorScope()->office, ['admin', 'registrar', 'guidance'], true)
                    && $this->sources->canManagePolicy($actor, $policy),
                404,
            );

            return;
        }
        $source = $this->sources->resolve($actor, $policy->publisher_key);
        abort_unless(
            (int) $policy->institution_id === (int) $actor->actorScope()->institutionId
                && $source !== null && $this->sources->canManage($actor, $policy->publisher_key),
            404,
        );
        if ($source['office'] === 'program') {
            abort_unless($policy->program_id !== null, 404);
            $matchesOwnProgram = Program::query()->whereKey($policy->program_id)
                ->where('institution_id', $policy->institution_id)->where('is_active', true)
                ->where('code', $source['program_code'])->exists();
            abort_unless($matchesOwnProgram, 404);

            return;
        }
        if ($policy->program_id !== null) {
            $targetIds = array_column($this->sources->targetPrograms($actor), 'id');
            abort_unless(in_array((int) $policy->program_id, $targetIds, true), 404);
        }
    }

    /** @param array{manage: bool, publish: bool}|null $permissions @return array<string, mixed> */
    private function staffDto(
        PublicPolicy $policy,
        User $actor,
        bool $includeContent = false,
        ?array $permissions = null,
        ?int $currentIntakePolicyId = null,
        bool $includeKindLock = false,
    ): array {
        $permissions ??= [
            'manage' => $this->sources->canManage($actor, $policy->publisher_key),
            'publish' => $this->sources->canPublish($actor, $policy->publisher_key),
        ];
        $canDesignateIntake = $permissions['publish']
            && $this->isValidPrivacyDesignationTarget($policy)
            && $currentIntakePolicyId !== (int) $policy->getKey();
        $kindLocked = $includeKindLock
            ? $policy->published_revision_id !== null || AdmissionAttempt::query()->where('public_policy_id', $policy->getKey())->exists()
            : null;

        return [
            'id' => (int) $policy->id,
            'publisher_key' => $policy->publisher_key,
            'publisher_label' => $policy->publisher_label,
            'program_id' => $policy->program_id === null ? null : (int) $policy->program_id,
            'program_code' => $policy->program?->code,
            'program_name' => $policy->program?->name,
            'kind' => $policy->kind,
            'published_kind' => $policy->publishedRevision?->kind,
            'published_title' => $policy->publishedRevision?->title,
            'is_valid_intake_selection' => (bool) $policy->is_designated_intake && $this->isValidPrivacyDesignationTarget($policy),
            'is_designated_intake' => (bool) $policy->is_designated_intake,
            'kind_locked' => $includeKindLock ? $kindLocked : null,
            'title' => $policy->title,
            ...($includeContent ? ['content' => $policy->content] : []),
            'revision' => (int) $policy->revision,
            'status' => $policy->status,
            'published_revision_id' => $policy->published_revision_id,
            'published_revision' => $policy->publishedRevision?->revision,
            'published_at' => $policy->published_at?->toIso8601String(),
            'withdrawal_reason' => $policy->withdrawal_reason,
            'can_manage' => $permissions['manage'],
            'can_publish' => $permissions['publish'],
            'can_designate_intake' => $canDesignateIntake,
        ];
    }

    private function isValidPublishedSnapshot(PublicPolicy $policy): bool
    {
        $revision = $policy->publishedRevision;

        return $revision !== null
            && (int) $revision->public_policy_id === (int) $policy->getKey()
            && (int) $revision->institution_id === (int) $policy->institution_id
            && $revision->publisher_key === $policy->publisher_key
            && $revision->publisher_label === $policy->publisher_label
            && $revision->program_id === $policy->program_id
            && $revision->state === 'published';
    }

    private function isValidPrivacyDesignationTarget(PublicPolicy $policy): bool
    {
        return str_starts_with($policy->publisher_key, 'office:')
            && $policy->kind === 'privacy'
            && $policy->program_id === null
            && $policy->status === 'published'
            && $policy->publishedRevision?->kind === 'privacy'
            && $this->isValidPublishedSnapshot($policy);
    }

    /** @return array<string, mixed>|null */
    private function intakeSelectionDto(?PublicPolicy $policy, ?User $actor = null): ?array
    {
        if ($policy === null) {
            return null;
        }
        $policy->loadMissing(['revisions', 'publishedRevision']);
        $hasPublicSnapshot = $policy->status === 'published'
            && $policy->program_id === null
            && str_starts_with($policy->publisher_key, 'office:')
            && $this->isValidPublishedSnapshot($policy);
        $revision = $hasPublicSnapshot ? $policy->publishedRevision : null;
        $canManage = $actor !== null && $this->sources->canManagePolicy($actor, $policy);
        $canPublish = $actor !== null && $this->sources->canPublishPolicy($actor, $policy);
        $hasPendingDraft = (int) $policy->revision > (int) ($policy->publishedRevision?->revision ?? 0);

        return [
            'id' => (int) $policy->getKey(),
            'root_kind' => $revision === null ? null : $policy->kind,
            'status' => $policy->status,
            'kind' => $revision?->kind,
            'title' => $revision?->title,
            'draft_title' => $policy->title,
            'content' => $policy->content,
            'revision' => $revision === null ? null : (int) $revision->revision,
            'draft_revision' => (int) $policy->revision,
            'published_revision' => $revision === null ? null : (int) $revision->revision,
            'published_at' => $policy->published_at?->toIso8601String(),
            'snapshot_available' => $hasPublicSnapshot,
            'has_pending_draft' => $hasPendingDraft,
            'can_manage' => $canManage,
            'can_publish' => $canPublish,
            'revisions' => $policy->revisions->map(fn ($rev): array => [
                'revision' => (int) $rev->revision,
                'title' => $rev->title,
                'state' => $rev->state,
                'actor_name' => $rev->actor_name,
                'created_at' => $rev->created_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function publicDto(PublicPolicy $policy, bool $includeContent = false): array
    {
        $revision = $policy->publishedRevision;

        return [
            'id' => (int) $policy->id, 'kind' => $revision->kind, 'title' => $revision->title,
            'excerpt' => Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags($revision->content))), 200, '…'),
            ...($includeContent ? ['content' => $revision->content] : []),
            'revision' => (int) $revision->revision, 'revision_id' => (int) $revision->getKey(),
            'revision_digest' => $revision->contentDigest(), 'reference' => 'Version '.$revision->revision,
            'publisher_key' => $revision->publisher_key, 'publisher_label' => $revision->publisher_label,
            'program_id' => $revision->program_id === null ? null : (int) $revision->program_id,
            'program_code' => $policy->program?->code, 'program_name' => $policy->program?->name,
            'placement' => $policy->program !== null ? $policy->program->code.' program' : 'General',
            'href' => '/policies/'.$policy->id,
            'published_at' => $policy->published_at?->toIso8601String(),
        ];
    }
}
