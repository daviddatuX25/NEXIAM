<?php

namespace App\Admission\Queries;

use App\Authorization\CapabilityCatalog;
use App\Authorization\GovernanceAuthorizer;
use App\Models\Admission\ProgramGovernanceMessage;
use App\Models\Admission\ProgramGovernanceRead;
use App\Models\Admission\ProgramGovernanceThread;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Program;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use RuntimeException;

class ProgramGovernanceDiscussionQuery
{
    private const MAX_PAGE_SIZE = 100;

    public function __construct(private readonly GovernanceAuthorizer $authorizer) {}

    /**
     * @return array{threads: list<array<string, mixed>>, pagination: array{current_page: int, per_page: int, total: int, has_more: bool}, unread_threads: int, thread_count: int}
     */
    public function list(User $actor, Program $program, AcademicCycle $cycle, string $topic, int $page = 1, int $perPage = 25, string $search = ''): array
    {
        $this->assertTopic($topic);
        $program = Program::query()->findOrFail($program->getKey());
        $cycle = AcademicCycle::query()->findOrFail($cycle->getKey());
        $this->authorizer->authorize($actor, $this->readCapability($topic), $program, $cycle);
        [$page, $perPage] = $this->boundedPaging($page, $perPage);

        $baseQuery = ProgramGovernanceThread::query()
            ->where('institution_id', $program->institution_id)
            ->where('program_id', $program->id)
            ->where('academic_cycle_id', $cycle->id)
            ->where('topic', $topic);
        if (trim($search) !== '') {
            $escapedSearch = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], trim($search));
            $baseQuery->where('subject', 'like', '%'.$escapedSearch.'%');
        }
        $total = (clone $baseQuery)->count();
        $canSend = $this->authorizer->allows($actor, $this->sendCapability($topic), $program, $cycle, true);
        $canManageState = $this->authorizer->allows($actor, $this->manageStateCapability($topic), $program, $cycle, true);
        $rows = $baseQuery
            ->withCount('messages')
            ->with(['createdBy:id,name', 'readMarkers' => fn ($query) => $query->where('user_id', $actor->id), 'readMarkers.lastReadMessage'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        $threads = [];
        foreach ($rows as $thread) {
            $this->assertThreadScope($thread, $program, $cycle, $topic);
            $threads[] = $this->threadProjection($thread, $actor, $canSend, $canManageState);
        }

        $summary = $this->summaryForAuthorizedContext($actor, $program, $cycle, $topic);

        return [
            'threads' => $threads,
            'can_read' => true,
            'can_send' => $canSend,
            'can_manage_state' => $canManageState,
            ...$summary,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'has_more' => $page * $perPage < $total,
            ],
        ];
    }

    /**
     * Page one is the newest page. Messages inside each page are returned oldest-first
     * so the consumer can append them directly to a chat transcript.
     *
     * @return array{thread: array<string, mixed>, messages: list<array<string, mixed>>, pagination: array{current_page: int, per_page: int, total: int, has_more: bool, next_before_message_id: ?int}, unread_count: int, can_read: bool, can_send: bool, can_manage_state: bool}
     */
    public function history(
        ProgramGovernanceThread $thread,
        User $actor,
        Program $routeProgram,
        AcademicCycle $routeCycle,
        int $page = 1,
        int $perPage = 50,
        ?int $beforeMessageId = null,
    ): array {
        $thread = ProgramGovernanceThread::query()->findOrFail($thread->getKey());
        $routeProgram = Program::query()->findOrFail($routeProgram->getKey());
        $routeCycle = AcademicCycle::query()->findOrFail($routeCycle->getKey());
        $this->assertThreadScope($thread, $routeProgram, $routeCycle, $thread->topic);
        $this->authorizer->authorize($actor, $this->readCapability($thread->topic), $routeProgram, $routeCycle);
        [$page, $perPage] = $this->boundedPaging($page, $perPage);

        $allMessages = ProgramGovernanceMessage::query()->where('program_governance_thread_id', $thread->id);
        $total = (clone $allMessages)->count();
        $pageQuery = clone $allMessages;
        if ($beforeMessageId !== null) {
            $cursor = (clone $allMessages)->find($beforeMessageId);
            if ($cursor === null) {
                throw new RuntimeException('Message cursor is not part of this discussion.');
            }
            $page = 1;
            $pageQuery->where(function (Builder $query) use ($cursor): void {
                $query->where('created_at', '<', $cursor->created_at)
                    ->orWhere(function (Builder $tie) use ($cursor): void {
                        $tie->where('created_at', $cursor->created_at)->where('id', '<', $cursor->id);
                    });
            });
        } else {
            $pageQuery->offset(($page - 1) * $perPage);
        }

        $descendingMessages = $pageQuery
            ->with('sender:id,name')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($perPage + 1)
            ->get();
        $hasMore = $descendingMessages->count() > $perPage;
        $messageModels = $descendingMessages->take($perPage)->reverse()->values();
        $messages = $messageModels
            ->map(fn (ProgramGovernanceMessage $message): array => [
                'id' => (int) $message->id,
                'sender' => ['id' => (int) $message->sender_user_id, 'name' => $message->sender?->name],
                'body' => $message->body,
                'created_at' => $message->created_at?->toISOString(),
            ])
            ->all();

        $readMarker = ProgramGovernanceRead::query()
            ->where('program_governance_thread_id', $thread->id)
            ->where('user_id', $actor->id)
            ->first();
        $unreadQuery = ProgramGovernanceMessage::query()
            ->where('program_governance_thread_id', $thread->id)
            ->where('sender_user_id', '!=', $actor->id);
        if ($readMarker?->last_read_message_id !== null) {
            $lastRead = ProgramGovernanceMessage::query()
                ->where('program_governance_thread_id', $thread->id)
                ->find($readMarker->last_read_message_id);
            if ($lastRead !== null) {
                $unreadQuery->where(function (Builder $query) use ($lastRead): void {
                    $query->where('created_at', '>', $lastRead->created_at)
                        ->orWhere(function (Builder $tie) use ($lastRead): void {
                            $tie->where('created_at', $lastRead->created_at)->where('id', '>', $lastRead->id);
                        });
                });
            }
        }
        $canSend = $thread->status === ProgramGovernanceThread::STATUS_OPEN
            && $this->authorizer->allows($actor, $this->sendCapability($thread->topic), $routeProgram, $routeCycle, true);
        $canManageState = $this->authorizer->allows($actor, $this->manageStateCapability($thread->topic), $routeProgram, $routeCycle, true);

        return [
            'thread' => $this->threadProjection($thread, $actor, $canSend, $canManageState),
            'messages' => $messages,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'has_more' => $hasMore,
                'next_before_message_id' => $hasMore ? (int) $messageModels->first()->id : null,
            ],
            'unread_count' => $unreadQuery->count(),
            'can_read' => true,
            'can_send' => $canSend,
            'can_manage_state' => $canManageState,
        ];
    }

    /**
     * @return array{unread_threads: int, thread_count: int}
     */
    public function summary(User $actor, Program $program, AcademicCycle $cycle, string $topic): array
    {
        $this->assertTopic($topic);
        $program = Program::query()->findOrFail($program->getKey());
        $cycle = AcademicCycle::query()->findOrFail($cycle->getKey());
        $this->authorizer->authorize($actor, $this->readCapability($topic), $program, $cycle);

        return $this->summaryForAuthorizedContext($actor, $program, $cycle, $topic);
    }

    /** @return array{unread_threads: int, thread_count: int} */
    private function summaryForAuthorizedContext(User $actor, Program $program, AcademicCycle $cycle, string $topic): array
    {
        $threads = ProgramGovernanceThread::query()
            ->where('institution_id', $program->institution_id)
            ->where('program_id', $program->id)
            ->where('academic_cycle_id', $cycle->id)
            ->where('topic', $topic);
        $threadCount = (clone $threads)->count();
        $unreadThreads = 0;
        foreach ((clone $threads)->withCount('messages')
            ->with(['createdBy:id,name', 'readMarkers' => fn ($query) => $query->where('user_id', $actor->id), 'readMarkers.lastReadMessage'])
            ->get() as $thread) {
            $projection = $this->threadProjection($thread, $actor, false, false);
            if ($projection['unread']) {
                $unreadThreads++;
            }
        }

        return ['unread_threads' => $unreadThreads, 'thread_count' => $threadCount];
    }

    /** @return array<string, mixed> */
    private function threadProjection(ProgramGovernanceThread $thread, User $actor, bool $canSend, bool $canManageState): array
    {
        $marker = $thread->relationLoaded('readMarkers')
            ? $thread->readMarkers->first()
            : ProgramGovernanceRead::query()
                ->where('program_governance_thread_id', $thread->id)
                ->where('user_id', $actor->id)
                ->first();
        $unreadQuery = $thread->messages()->getQuery()->where('sender_user_id', '!=', $actor->id);
        $lastRead = $marker?->last_read_message_id === null
            ? null
            : ($marker->relationLoaded('lastReadMessage')
                ? $marker->lastReadMessage
                : ProgramGovernanceMessage::query()
                    ->where('program_governance_thread_id', $thread->id)
                    ->find($marker->last_read_message_id));
        if ($lastRead !== null && (int) $lastRead->program_governance_thread_id === (int) $thread->id) {
            $unreadQuery->where(function (Builder $query) use ($lastRead): void {
                $query->where('created_at', '>', $lastRead->created_at)
                    ->orWhere(function (Builder $tie) use ($lastRead): void {
                        $tie->where('created_at', $lastRead->created_at)->where('id', '>', $lastRead->id);
                    });
            });
        }

        $messageCount = $thread->getAttribute('messages_count') ?? $thread->messages()->count();
        $unreadCount = (clone $unreadQuery)->count();
        $isUnread = $unreadCount > 0
            || ($messageCount === 0
                && (int) $thread->created_by_user_id !== (int) $actor->id
                && $marker?->last_read_at === null);

        return [
            'id' => (int) $thread->id,
            'institution_id' => (int) $thread->institution_id,
            'program_id' => (int) $thread->program_id,
            'academic_cycle_id' => (int) $thread->academic_cycle_id,
            'topic' => $thread->topic,
            'subject' => $thread->subject,
            'status' => $thread->status,
            'admission_benchmark_revision_id' => $thread->admission_benchmark_revision_id === null ? null : (int) $thread->admission_benchmark_revision_id,
            'quota_change_id' => $thread->quota_change_id === null ? null : (int) $thread->quota_change_id,
            'created_by' => ['id' => (int) $thread->created_by_user_id, 'name' => $thread->createdBy?->name],
            'created_at' => $thread->created_at?->toISOString(),
            'updated_at' => $thread->updated_at?->toISOString(),
            'message_count' => (int) $messageCount,
            'unread_count' => $unreadCount,
            'unread' => $isUnread,
            'has_applicant_details' => false,
            'can_read' => true,
            'can_send' => $thread->status === ProgramGovernanceThread::STATUS_OPEN && $canSend,
            'can_manage_state' => $canManageState,
        ];
    }

    private function assertThreadScope(ProgramGovernanceThread $thread, Program $program, AcademicCycle $cycle, string $topic): void
    {
        if (! in_array($topic, ProgramGovernanceThread::TOPICS, true)
            || $thread->topic !== $topic
            || (int) $thread->institution_id !== (int) $program->institution_id
            || (int) $thread->program_id !== (int) $program->id
            || (int) $thread->academic_cycle_id !== (int) $cycle->id
            || (int) $cycle->institution_id !== (int) $program->institution_id) {
            throw new RuntimeException('Discussion is not available in the selected context.');
        }
    }

    private function assertTopic(string $topic): void
    {
        if (! in_array($topic, ProgramGovernanceThread::TOPICS, true)) {
            throw new RuntimeException('Discussion topic is invalid.');
        }
    }

    /** @return array{int, int} */
    private function boundedPaging(int $page, int $perPage): array
    {
        return [max(1, $page), min(self::MAX_PAGE_SIZE, max(1, $perPage))];
    }

    private function readCapability(string $topic): string
    {
        return match ($topic) {
            ProgramGovernanceThread::TOPIC_BENCHMARK => CapabilityCatalog::ADMISSION_GOVERNANCE_DISCUSSION_BENCHMARK_READ,
            ProgramGovernanceThread::TOPIC_QUOTA => CapabilityCatalog::ADMISSION_GOVERNANCE_DISCUSSION_QUOTA_READ,
            ProgramGovernanceThread::TOPIC_ADVISING => CapabilityCatalog::ADMISSION_GOVERNANCE_DISCUSSION_ADVISING_READ,
            default => throw new RuntimeException('Discussion topic is invalid.'),
        };
    }

    private function sendCapability(string $topic): string
    {
        return match ($topic) {
            ProgramGovernanceThread::TOPIC_BENCHMARK => CapabilityCatalog::ADMISSION_GOVERNANCE_DISCUSSION_BENCHMARK_SEND,
            ProgramGovernanceThread::TOPIC_QUOTA => CapabilityCatalog::ADMISSION_GOVERNANCE_DISCUSSION_QUOTA_SEND,
            ProgramGovernanceThread::TOPIC_ADVISING => CapabilityCatalog::ADMISSION_GOVERNANCE_DISCUSSION_ADVISING_SEND,
            default => throw new RuntimeException('Discussion topic is invalid.'),
        };
    }

    private function manageStateCapability(string $topic): string
    {
        return match ($topic) {
            ProgramGovernanceThread::TOPIC_BENCHMARK => CapabilityCatalog::ADMISSION_GOVERNANCE_DISCUSSION_BENCHMARK_MANAGE_STATE,
            ProgramGovernanceThread::TOPIC_QUOTA => CapabilityCatalog::ADMISSION_GOVERNANCE_DISCUSSION_QUOTA_MANAGE_STATE,
            ProgramGovernanceThread::TOPIC_ADVISING => CapabilityCatalog::ADMISSION_GOVERNANCE_DISCUSSION_ADVISING_MANAGE_STATE,
            default => throw new RuntimeException('Discussion topic is invalid.'),
        };
    }
}
