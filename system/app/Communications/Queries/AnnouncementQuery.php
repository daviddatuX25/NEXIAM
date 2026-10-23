<?php

declare(strict_types=1);

namespace App\Communications\Queries;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Communications\AnnouncementSourceAuthorizer;
use App\Models\Communications\Announcement;
use App\Models\Communications\PublicPolicy;
use App\Models\Foundation\Institution;
use App\Models\Foundation\Program;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

final class AnnouncementQuery
{
    public function __construct(
        private readonly AnnouncementSourceAuthorizer $sources,
        private readonly CapabilityAuthorizer $authorizer,
        private readonly ProgramPublicProfileQuery $profiles,
        private readonly PublicPolicyQuery $policies,
    ) {}

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
        $publishers = $this->sources->publishers($actor);
        $fresh = User::query()->findOrFail($actor->getKey());
        $institutionId = $fresh->actorScope()->institutionId;
        $baseQuery = Announcement::query()->where('institution_id', $institutionId)
            ->whereIn('publisher_key', collect($publishers)->pluck('key'))
            ->with('program')->orderByDesc('updated_at')->orderByDesc('id');
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
                    ->orWhereLike('summary', $pattern)
                    ->orWhereLike('publisher_label', $pattern)
                    ->orWhereHas('program', static function (Builder $program) use ($pattern): void {
                        $program->whereLike('code', $pattern)
                            ->orWhereLike('name', $pattern);
                    });
            });
        }

        $now = now();
        $statusCounts = [
            'all' => (clone $countQuery)->count(),
            'published' => (clone $countQuery)->where('status', 'published')
                ->where(fn (Builder $b) => $b->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
                ->where(fn (Builder $b) => $b->whereNull('ends_at')->orWhere('ends_at', '>', $now))->count(),
            'draft' => (clone $countQuery)->where('status', 'draft')->count(),
            'scheduled' => (clone $countQuery)->where('status', 'published')->where('starts_at', '>', $now)->count(),
            'expired' => (clone $countQuery)->where('status', 'published')->whereNotNull('ends_at')->where('ends_at', '<=', $now)->count(),
            'withdrawn' => (clone $countQuery)->where('status', 'withdrawn')->count(),
        ];

        $query = clone $countQuery;
        $this->applyStatus($query, $status);
        $announcements = $query->paginate(15, ['*'], 'page', $page)->withQueryString();
        $permissions = [];
        foreach ($publishers as $publisher) {
            $source = $this->sources->resolve($fresh, (int) $institutionId, $publisher['key']);
            if ($source !== null) {
                $permissions[$publisher['key']] = [
                    'manage' => true,
                    'publish' => $this->sources->canPublish($fresh, $source),
                ];
            }
        }

        return [
            'page_state' => $announcements->total() === 0 ? 'empty' : 'ready',
            'announcements' => $this->page($announcements, fn (Announcement $item): array => $this->staffDto($item, $permissions[$item->publisher_key] ?? ['manage' => false, 'publish' => false])),
            'filters' => ['status' => $status, 'search' => $search, 'placement' => $placement],
            'status_counts' => $statusCounts,
            'capabilities' => $this->capabilities($fresh),
            'publishers' => $publishers,
            'publicPolicies' => $this->publicPolicies(),
            'canManagePrivacy' => $institutionId !== null
                && $this->authorizer->decideFresh($fresh, CapabilityCatalog::SETUP_INSTITUTION_MANAGE, ['institution_id' => $institutionId])->allowed,
        ];
    }

    /** @return array<string, mixed> */
    public function staffForm(User $actor, ?Announcement $announcement = null): array
    {
        $fresh = User::query()->findOrFail($actor->getKey());
        $publishers = $this->sources->publishers($fresh);
        abort_if($publishers === [], 403);

        $props = [
            'capabilities' => $this->capabilities($fresh),
            'publishers' => $publishers,
            'programs' => $this->sources->targetPrograms($fresh),
        ];
        if ($announcement !== null) {
            $source = $this->sources->assertCanSee($fresh, $announcement->institution_id, $announcement->publisher_key);
            abort_unless($this->sources->canManage($fresh, $source), 404);
            $this->sources->assertCanTarget($fresh, $source, $announcement->program_id === null ? null : (int) $announcement->program_id);
            $announcement->load(['revisions', 'program']);
            $props['announcement'] = $this->staffDto($announcement, [
                'manage' => true,
                'publish' => $this->sources->canPublish($fresh, $source),
            ]);
            $props['revisions'] = $announcement->revisions->map(fn ($revision): array => [
                'revision' => $revision->revision,
                'title' => $revision->title,
                'summary' => $revision->summary,
                'body' => $revision->body,
                'program_id' => $revision->program_id,
                'status' => $revision->status,
                'actor_name' => $revision->actor_name,
                'reason' => $revision->reason,
                'created_at' => $revision->created_at?->utc()->toISOString(),
            ])->values()->all();
        }

        return $props;
    }

    /** @return array<string, mixed> */
    public function publicHome(): array
    {
        $institution = Institution::query()->where('code', 'SYN-INST-001')->first();
        if ($institution === null) {
            return ['alerts' => [], 'publicPolicies' => array_slice($this->publicPolicies(), 0, 6)];
        }

        $now = now();
        $base = Announcement::query()->publiclyEligible($institution->id, $now)->whereNull('program_id');
        $featured = (clone $base)->where('is_featured', true)
            ->orderByRaw("CASE WHEN priority = 'urgent' THEN 0 ELSE 1 END")
            ->orderByDesc('published_at')->orderByDesc('id')->limit(3)->with('program')->get();

        return [
            'alerts' => $featured->map(fn (Announcement $item): array => $this->publicDto($item, $now))->all(),
            'publicPolicies' => array_slice($this->publicPolicies(), 0, 6),
        ];
    }

    /** @return array<string, mixed> */
    public function publicIndex(int $page = 1): array
    {
        $institution = Institution::query()->where('code', 'SYN-INST-001')->first();
        $announcements = $institution === null
            ? Announcement::query()->whereRaw('1 = 0')->paginate(12, ['*'], 'page', $page)
            : Announcement::query()->publiclyEligible($institution->id)->whereNull('program_id')->with('program')->orderByDesc('published_at')->orderByDesc('id')->paginate(12, ['*'], 'page', $page)->withQueryString();

        return [
            'page_state' => $announcements->total() === 0 ? 'empty' : 'ready',
            'institution' => $this->institutionDto($institution),
            'alerts' => $this->publicHome()['alerts'],
            'announcements' => $this->page($announcements, fn (Announcement $item): array => $this->publicDto($item)),
        ];
    }

    /** @return array<string, mixed> */
    public function publicDetail(int $id): array
    {
        $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();
        $announcement = Announcement::query()->publiclyEligible($institution->id)->with('program')->whereKey($id)->firstOrFail();
        $program = $announcement->program;

        return [
            'institution' => $this->institutionDto($institution),
            'program' => $program === null ? null : ['id' => (int) $program->id, 'code' => $program->code, 'name' => $program->name],
            'alerts' => $program === null ? $this->publicHome()['alerts'] : $this->programAlerts((int) $program->id, $institution->id),
            'announcement' => $this->publicDto($announcement),
        ];
    }

    /** @return array<string, mixed> */
    public function publicProgram(string $code): array
    {
        $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();
        $program = Program::query()->active()
            ->where('institution_id', $institution->id)
            ->where('code', $code)
            ->firstOrFail();

        return [
            'institution' => $this->institutionDto($institution),
            'program' => ['id' => (int) $program->id, 'code' => $program->code, 'name' => $program->name],
            'publicProfile' => $this->profiles->publicProfile($program),
            'alerts' => $this->programAlerts((int) $program->id, $institution->id),
            'announcements' => Announcement::query()->publiclyEligible($institution->id)
                ->where('program_id', $program->id)->with('program')
                ->orderByDesc('published_at')->orderByDesc('id')->limit(6)->get()
                ->map(fn (Announcement $item): array => $this->publicDto($item))->all(),
            'programPolicies' => $this->policies->publicPolicies($program),
            'homeHref' => '/',
            'programIndex' => '/programs/'.rawurlencode($program->code).'/announcements',
            'programPolicyIndex' => '/programs/'.rawurlencode($program->code).'/policies',
        ];
    }

    /** @return array<string, mixed> */
    public function publicProgramAnnouncements(string $code, int $page = 1): array
    {
        $institution = Institution::query()->where('code', 'SYN-INST-001')->firstOrFail();
        $program = Program::query()->active()
            ->where('institution_id', $institution->id)
            ->where('code', $code)
            ->firstOrFail();
        $announcements = Announcement::query()->publiclyEligible($institution->id)
            ->where('program_id', $program->id)->with('program')
            ->orderByDesc('published_at')->orderByDesc('id')
            ->paginate(12, ['*'], 'page', $page)->withQueryString();

        return [
            'institution' => $this->institutionDto($institution),
            'program' => ['id' => (int) $program->id, 'code' => $program->code, 'name' => $program->name],
            'alerts' => $this->programAlerts((int) $program->id, $institution->id),
            'announcements' => $this->page($announcements, fn (Announcement $item): array => $this->publicDto($item)),
            'homeHref' => '/',
            'programHref' => '/programs/'.rawurlencode($program->code),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function programAlerts(int $programId, int $institutionId): array
    {
        return Announcement::query()->publiclyEligible($institutionId)
            ->where('program_id', $programId)->where('is_featured', true)
            ->with('program')->orderByRaw("CASE WHEN priority = 'urgent' THEN 0 ELSE 1 END")
            ->orderByDesc('published_at')->orderByDesc('id')->limit(3)->get()
            ->map(fn (Announcement $item): array => $this->publicDto($item))->all();
    }

    /** @return array<string, mixed> */
    public function publicPoliciesPage(): array
    {
        return [
            'institution' => $this->institutionDto(),
            'alerts' => $this->publicHome()['alerts'],
            'publicPolicies' => $this->publicPolicies(),
        ];
    }

    /** @return array<string, mixed> */
    public function publicPrivacyPolicy(): array
    {
        $policy = PublicPolicy::designatedIntake((int) Institution::current()->getKey());
        $revision = $policy?->publishedRevision;

        return [
            'institution' => $this->institutionDto(),
            'alerts' => $this->publicHome()['alerts'],
            'policy' => $policy === null || $revision === null ? null : [
                'id' => (int) $policy->getKey(),
                'title' => $revision->title,
                'content' => $revision->content,
                'publisher_key' => $revision->publisher_key,
                'publisher_label' => $revision->publisher_label,
                'published_at' => $policy->published_at?->toIso8601String(),
            ],
        ];
    }

    /** @return list<array<string, mixed>> */
    public function publicPolicies(): array
    {
        return $this->policies->publicPolicies();
    }

    /** @return array{manage: bool, publish: bool} */
    private function capabilities(User $actor): array
    {
        $scope = $actor->actorScope();
        $institutionId = $scope->institutionId;

        return [
            'manage' => $institutionId !== null && $this->authorizer->decideFresh($actor, CapabilityCatalog::COMMUNICATIONS_ANNOUNCEMENTS_MANAGE, ['institution_id' => $institutionId])->allowed,
            'publish' => $institutionId !== null && $this->authorizer->decideFresh($actor, CapabilityCatalog::COMMUNICATIONS_ANNOUNCEMENTS_PUBLISH, ['institution_id' => $institutionId])->allowed,
        ];
    }

    /** @param array{manage: bool, publish: bool} $permissions
     * @return array<string, mixed>
     */
    private function staffDto(Announcement $item, array $permissions): array
    {
        $officeSource = in_array($item->publisher_key, ['office:admin', 'office:registrar', 'office:guidance'], true);
        $targetValid = $item->program_id === null
            ? $officeSource
            : $item->program !== null && $item->program->is_active
                && $item->program->institution_id === $item->institution_id
                && ($officeSource || $item->publisher_key === 'program:'.$item->program->code);
        $manage = $targetValid && $permissions['manage'];
        $publish = $targetValid && $permissions['publish'];
        $dto = $this->dto($item);

        return [
            ...$dto,
            'can_view' => $manage,
            'can_edit' => $manage && ($item->status === 'draft' || ($item->status === 'published' && $publish)),
            'can_publish' => $item->status === 'draft' && $publish,
            'can_withdraw' => $item->status === 'published' && $publish,
        ];
    }

    /** @return array<string, mixed> */
    private function publicDto(Announcement $item, ?Carbon $now = null): array
    {
        return $this->dto($item, $now);
    }

    /** @return array<string, mixed> */
    private function dto(Announcement $item, ?Carbon $now = null): array
    {
        $office = str_starts_with($item->publisher_key, 'office:') ? substr($item->publisher_key, 7) : null;

        return [
            'id' => (string) $item->getKey(),
            'title' => $item->title,
            'summary' => $item->summary,
            'body' => $item->body,
            'publisher_key' => $item->publisher_key,
            'publisher_label' => $item->publisher_label,
            'source_tag' => $office === 'registrar' ? 'Registrar' : ($office === 'guidance' ? 'Guidance' : ($item->program?->code ?? $item->publisher_label)),
            'program_id' => $item->program_id === null ? null : (int) $item->program_id,
            'program_code' => $item->program?->code,
            'status' => $item->status,
            'effective_status' => $item->effectiveStatus($now),
            'priority' => $item->priority,
            'is_featured' => $item->is_featured,
            'starts_at' => $item->starts_at?->utc()->toISOString(),
            'ends_at' => $item->ends_at?->utc()->toISOString(),
            'published_at' => $item->published_at?->utc()->toISOString(),
            'revision' => $item->revision,
            'can_edit' => false,
            'can_publish' => false,
            'can_withdraw' => false,
            'href' => '/announcements/'.$item->getKey(),
        ];
    }

    private function applyStatus(Builder $query, string $status): void
    {
        $now = now();
        match ($status) {
            'draft', 'withdrawn' => $query->where('status', $status),
            'scheduled' => $query->where('status', 'published')->where('starts_at', '>', $now),
            'expired' => $query->where('status', 'published')->whereNotNull('ends_at')->where('ends_at', '<=', $now),
            'published' => $query->where('status', 'published')
                ->where(fn (Builder $builder) => $builder->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
                ->where(fn (Builder $builder) => $builder->whereNull('ends_at')->orWhere('ends_at', '>', $now)),
            default => null,
        };
    }

    /** @param LengthAwarePaginator<Announcement> $paginator @param callable(Announcement): array<string, mixed> $project
     * @return array<string, mixed>
     */
    private function page(LengthAwarePaginator $paginator, callable $project): array
    {
        $page = $paginator->toArray();
        $page['data'] = $paginator->getCollection()->map($project)->values()->all();

        return $page;
    }

    /**
     * @return array<string, mixed>
     */
    private function institutionDto(?Institution $institution = null): array
    {
        $institution ??= Institution::query()->where('code', 'SYN-INST-001')->first();

        return $institution?->toPublicArray() ?? config('public-site.institution');
    }
}
