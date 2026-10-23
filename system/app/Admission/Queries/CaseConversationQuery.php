<?php

namespace App\Admission\Queries;

use App\Admission\Support\CaseConversationToken;
use App\Admission\Support\RecordThreadService;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ApplicantIdentity;
use App\Models\Admission\RecordThread;
use App\Models\Admission\RecordThreadMessage;
use App\Models\Admission\RecordThreadRead;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Program;
use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CaseConversationQuery
{
    public function __construct(
        private readonly RecordThreadService $threads,
        private readonly RecordAuditEvent $audit,
        private readonly CapabilityAuthorizer $authorizer,
    ) {}

    /**
     * @return array{
     *     principal_key: string,
     *     attempt_key: string,
     *     thread: array{
     *         id: string,
     *         subject: string,
     *         thread_type: string,
     *         program_code: string,
     *         applicant?: array{display_name: string, reference_code: ?string},
     *         status: string,
     *         can_read: bool,
     *         can_send: bool,
     *         applicant?: array{display_name: string, reference_code: ?string}|null
     *     },
     *     messages: list<array{
     *         id: string,
     *         sequence: string,
     *         sender: array{display_name: string, is_self: bool},
     *         body: string,
     *         created_at: string
     *     }>,
     *     page: array{
     *         oldest_sequence: ?string,
     *         newest_sequence: ?string,
     *         has_older: bool,
     *         has_more_after: bool
     *     },
     *     latest_sequence: string,
     *     own_read_sequence: string,
     *     unread_count: int,
     *     observation_token: string,
     *     as_of: string
     * }
     */
    public function forThread(
        RecordThread $thread,
        User $actor,
        ?string $afterSequence = null,
        ?string $beforeSequence = null,
    ): array {
        return DB::transaction(function () use ($thread, $actor, $afterSequence, $beforeSequence): array {
            $attempt = AdmissionAttempt::query()
                ->whereKey($thread->admission_attempt_id)
                ->lockForUpdate()
                ->first();
            $freshThread = RecordThread::query()
                ->whereKey($thread->id)
                ->lockForUpdate()
                ->first();

            if ($attempt === null || $freshThread === null || ! $this->threads->canRead($freshThread, $actor)) {
                abort(404, 'Thread not found.');
            }

            $latestSequence = (string) $freshThread->latest_message_sequence;
            $afterSequence = $this->validateCursor($afterSequence, 'after_sequence', $latestSequence);
            $beforeSequence = $this->validateCursor($beforeSequence, 'before_sequence', $latestSequence);
            if ($afterSequence !== null && $beforeSequence !== null) {
                throw ValidationException::withMessages([
                    'cursor' => 'Only one sequence cursor may be supplied.',
                ]);
            }

            $this->audit->handle('thread.messages_viewed', [
                'thread_id' => $freshThread->id,
                'attempt_id' => $freshThread->admission_attempt_id,
                'program_id' => $freshThread->program_id,
                'actor_id' => $actor->id,
            ], (string) $actor->id);

            $query = RecordThreadMessage::query()
                ->select(['id', 'record_thread_id', 'sender_user_id', 'sequence', 'body', 'created_at'])
                ->with(['sender:id,name'])
                ->where('record_thread_id', $freshThread->id);

            if ($afterSequence !== null) {
                $messages = $query
                    ->where('sequence', '>', $afterSequence)
                    ->orderBy('sequence')
                    ->limit(51)
                    ->get();
                $hasMoreAfter = $messages->count() > 50;
                $messages = $messages->take(50)->values();
                $hasOlder = false;
            } elseif ($beforeSequence !== null) {
                $messages = $query
                    ->where('sequence', '<', $beforeSequence)
                    ->orderByDesc('sequence')
                    ->limit(51)
                    ->get();
                $hasOlder = $messages->count() > 50;
                $messages = $messages->take(50)->reverse()->values();
                $hasMoreAfter = false;
            } else {
                $messages = $query
                    ->orderByDesc('sequence')
                    ->limit(51)
                    ->get();
                $hasOlder = $messages->count() > 50;
                $messages = $messages->take(50)->reverse()->values();
                $hasMoreAfter = false;
            }

            $firstMessage = $messages->first();
            $lastMessage = $messages->last();
            $oldestSequence = $firstMessage ? (string) $firstMessage->sequence : null;
            $newestSequence = $lastMessage ? (string) $lastMessage->sequence : null;
            if ($afterSequence !== null && $oldestSequence !== null) {
                $hasOlder = RecordThreadMessage::query()
                    ->where('record_thread_id', $freshThread->id)
                    ->where('sequence', '<', $oldestSequence)
                    ->exists();
            }

            if ($afterSequence !== null && $newestSequence !== null && ! $hasMoreAfter) {
                $hasMoreAfter = RecordThreadMessage::query()
                    ->where('record_thread_id', $freshThread->id)
                    ->where('sequence', '>', $newestSequence)
                    ->exists();
            }

            if ($beforeSequence !== null && $oldestSequence !== null && ! $hasOlder) {
                $hasOlder = RecordThreadMessage::query()
                    ->where('record_thread_id', $freshThread->id)
                    ->where('sequence', '<', $oldestSequence)
                    ->exists();
            }

            $ownReadSequence = (string) (RecordThreadRead::query()
                ->where('record_thread_id', $freshThread->id)
                ->where('user_id', $actor->id)
                ->value('last_read_sequence') ?? 0);
            $unreadCount = RecordThreadMessage::query()
                ->where('record_thread_id', $freshThread->id)
                ->where('sequence', '>', $ownReadSequence)
                ->where('sender_user_id', '!=', $actor->id)
                ->count();

            return [
                'principal_key' => CaseConversationToken::presentationContext($actor),
                'attempt_key' => $this->attemptContextKey($freshThread, 'case'),
                'thread' => [
                    'id' => (string) $freshThread->id,
                    'subject' => (string) $freshThread->subject,
                    'thread_type' => (string) $freshThread->thread_type,
                    'program_code' => (string) ($freshThread->program()->value('code') ?? ''),
                    'status' => (string) $freshThread->status,
                    'can_read' => true,
                    'can_send' => $this->threads->canSend($freshThread, $actor),
                    ...$this->staffApplicantMetadata($freshThread, $actor),
                ],
                'messages' => $messages->map(fn (RecordThreadMessage $message): array => [
                    'id' => (string) $message->id,
                    'sequence' => (string) $message->sequence,
                    'sender' => [
                        'display_name' => $message->sender?->name ?? 'Participant',
                        'is_self' => (int) $message->sender_user_id === (int) $actor->id,
                    ],
                    'body' => (string) $message->body,
                    'created_at' => $message->created_at?->toISOString() ?? now()->toISOString(),
                ])->all(),
                'page' => [
                    'oldest_sequence' => $oldestSequence,
                    'newest_sequence' => $newestSequence,
                    'has_older' => $hasOlder,
                    'has_more_after' => $hasMoreAfter,
                ],
                'latest_sequence' => $latestSequence,
                'own_read_sequence' => $ownReadSequence,
                'unread_count' => $unreadCount,
                'observation_token' => CaseConversationToken::issue($actor, $freshThread->id, $newestSequence ?? '0'),
                'as_of' => now()->toISOString(),
            ];
        });
    }

    /**
     * @return array{
     *     data: list<array{
     *         principal_key: string,
     *         attempt_key: string,
     *         id: string,
     *         subject: string,
     *         thread_type: string,
     *         program_code: string,
     *         cycle_code: string,
     *         attempt_id: string,
     *         applicant?: array{display_name: string, reference_code: ?string},
     *         status: string,
     *         latest_sequence: string,
     *         unread_count: int,
     *         last_activity_at: ?string,
     *         can_read: bool,
     *         can_send: bool,
     *         snapshot_url: string,
     *         send_url: string,
     *         read_url: string,
     *         html_url: string
     *     }>,
     *     pagination: array{per_page: int, has_more: bool, next_cursor: ?string}
     * }
     */
    public function summariesForAttempt(
        AdmissionAttempt $attempt,
        string $threadType,
        User $actor,
        int $page = 1,
        int $perPage = 20,
        ?string $cursor = null,
    ): array {
        $attempt = AdmissionAttempt::query()->with('academicCycle')->find($attempt->id);
        if ($attempt === null || ! in_array($threadType, [
            RecordThread::TYPE_APPLICANT_SUPPORT,
            RecordThread::TYPE_INTERNAL_PH_GUIDANCE,
        ], true)) {
            abort(404, 'Thread collection not found.');
        }

        $scope = [
            'institution_id' => (string) $attempt->institution_id,
            'attempt_id' => (string) $attempt->id,
            'cycle_code' => (string) ($attempt->academicCycle?->code ?? ''),
            'thread_type' => $threadType,
        ];

        return $this->summaryPage(
            $this->candidateThreads()->where('record_threads.admission_attempt_id', $attempt->id)
                ->where('record_threads.thread_type', $threadType),
            $actor,
            'attempt_staff',
            $scope,
            null,
            $cursor,
            $perPage,
            $page,
            $threadType,
            true,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function guidanceSummaries(
        User $actor,
        ?string $search = null,
        ?string $cursor = null,
        int $perPage = 20,
    ): array {
        $freshActor = User::query()->find($actor->id);
        $profile = $freshActor?->staffProfile()->first();
        $actorScope = $freshActor?->actorScope();
        $institutionId = $profile?->institution_id;
        $cycleCode = $actorScope?->activeCycleCode;
        $cycle = $institutionId === null || ! is_string($cycleCode) || $cycleCode === ''
            ? null
            : AcademicCycle::query()
                ->where('institution_id', $institutionId)
                ->where('code', $cycleCode)
                ->where('is_active', true)
                ->first();

        if ($freshActor === null
            || $profile === null
            || ! $profile->isActive()
            || $profile->deactivated_at !== null
            || $actorScope?->office !== 'guidance'
            || $cycle === null
            || (int) $cycle->institution_id !== (int) $institutionId
            || ! $this->authorizer->decideFresh($freshActor, CapabilityCatalog::RECORD_THREAD_PARTICIPATE, [
                'institution_id' => $institutionId,
            ])->allowed
            || (! $this->authorizer->decideFresh($freshActor, CapabilityCatalog::GUIDANCE_CONSULTATION_PLAN)->allowed
                && ! $this->authorizer->decideFresh($freshActor, CapabilityCatalog::GUIDANCE_EXAM_SCHEDULE)->allowed)) {
            abort(404, 'Thread collection not found.');
        }

        $search = $this->normalizeSearch($search);
        $scope = [
            'institution_id' => (string) $institutionId,
            'cycle_id' => (string) $cycle->id,
            'cycle_code' => $cycleCode,
        ];

        return $this->summaryPage(
            $this->candidateThreads()
                ->join('admission_attempts', 'admission_attempts.id', '=', 'record_threads.admission_attempt_id')
                ->where('admission_attempts.institution_id', $institutionId)
                ->where('admission_attempts.academic_cycle_id', $cycle->id)
                ->where('record_threads.thread_type', RecordThread::TYPE_INTERNAL_PH_GUIDANCE),
            $freshActor,
            'guidance_case',
            $scope,
            $search,
            $cursor,
            $perPage,
            1,
            RecordThread::TYPE_INTERNAL_PH_GUIDANCE,
            true,
            'thread.summary_viewed',
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function programHeadSummaries(
        User $actor,
        ?string $search = null,
        ?string $cursor = null,
        int $perPage = 20,
        ?string $programCode = null,
        ?string $threadType = null,
    ): array {
        $freshActor = User::query()->find($actor->id);
        $profile = $freshActor?->staffProfile()->first();
        $actorScope = $freshActor?->actorScope();
        $programCodes = $actorScope?->programCodes ?? [];
        $institutionId = $profile?->institution_id;
        $cycleCode = $actorScope?->activeCycleCode;
        $cycle = $institutionId === null || ! is_string($cycleCode) || $cycleCode === ''
            ? null
            : AcademicCycle::query()
                ->where('institution_id', $institutionId)
                ->where('code', $cycleCode)
                ->where('is_active', true)
                ->first();

        if ($freshActor === null
            || $profile === null
            || ! $profile->isActive()
            || $profile->deactivated_at !== null
            || $actorScope?->office !== 'program'
            || $programCodes === []
            || $cycle === null
            || (int) $cycle->institution_id !== (int) $institutionId
            || ! $this->authorizer->decideFresh($freshActor, CapabilityCatalog::RECORD_THREAD_PARTICIPATE, [
                'institution_id' => $institutionId,
            ])->allowed
            || ($programCode !== null && ! in_array($programCode, $programCodes, true))
            || ($threadType !== null && ! in_array($threadType, [
                RecordThread::TYPE_APPLICANT_SUPPORT,
                RecordThread::TYPE_INTERNAL_PH_GUIDANCE,
            ], true))) {
            abort(404, 'Thread collection not found.');
        }

        $search = $this->normalizeSearch($search);
        $scope = [
            'institution_id' => (string) $institutionId,
            'program_codes' => implode(',', $programCodes),
            'program_code' => $programCode ?? '',
            'cycle_id' => (string) $cycle->id,
            'cycle_code' => $cycleCode,
            'thread_type' => $threadType ?? '',
        ];
        $query = $this->candidateThreads()
            ->join('admission_attempts', 'admission_attempts.id', '=', 'record_threads.admission_attempt_id')
            ->join('programs', 'programs.id', '=', 'record_threads.program_id')
            ->where('admission_attempts.institution_id', $institutionId)
            ->where('admission_attempts.academic_cycle_id', $cycle->id)
            ->where('programs.institution_id', $institutionId)
            ->where('programs.is_active', true)
            ->whereIn('programs.code', $programCodes);

        if ($programCode !== null) {
            $query->where('programs.code', $programCode);
        }
        if ($threadType !== null) {
            $query->where('record_threads.thread_type', $threadType);
        }

        return $this->summaryPage(
            $query,
            $freshActor,
            'program_head_case',
            $scope,
            $search,
            $cursor,
            $perPage,
            1,
            $threadType,
            true,
            'thread.summary_viewed',
        );
    }

    /**
     * @return array{
     *     data: list<array{
     *         principal_key: string,
     *         attempt_key: string,
     *         id: string,
     *         subject: string,
     *         thread_type: string,
     *         program_code: string,
     *         cycle_code: string,
     *         attempt_id: string,
     *         applicant?: array{display_name: string, reference_code: ?string},
     *         status: string,
     *         latest_sequence: string,
     *         unread_count: int,
     *         last_activity_at: ?string,
     *         can_read: bool,
     *         can_send: bool,
     *         snapshot_url: string,
     *         send_url: string,
     *         read_url: string,
     *         html_url: string
     *     }>,
     *     pagination: array{per_page: int, has_more: bool, next_cursor: ?string}
     * }
     */
    public function applicantSummaries(
        User $actor,
        ?string $search = null,
        ?string $cursor = null,
        int $perPage = 20,
    ): array {
        $identityId = User::query()->find($actor->id)?->applicantAccount()->value('applicant_identity_id');
        $identity = $identityId === null ? null : ApplicantIdentity::query()->find($identityId);
        if ($identity === null) {
            return $this->emptySummaryPage($perPage);
        }

        $search = $this->normalizeSearch($search);
        $scope = [
            'institution_id' => (string) $identity->institution_id,
            'identity_id' => (string) $identity->id,
        ];

        $query = $this->candidateThreads()
            ->where('record_threads.thread_type', RecordThread::TYPE_APPLICANT_SUPPORT)
            ->whereIn('record_threads.admission_attempt_id', AdmissionAttempt::query()
                ->select('id')
                ->where('applicant_identity_id', $identity->id)
                ->where('institution_id', $identity->institution_id));

        return $this->summaryPage(
            $query,
            $actor,
            'applicant_case',
            $scope,
            $search,
            $cursor,
            $perPage,
            1,
            RecordThread::TYPE_APPLICANT_SUPPORT,
            false,
        );
    }

    /**
     * @return array{
     *     data: list<array{
     *         principal_key: string,
     *         attempt_key: string,
     *         id: string,
     *         subject: string,
     *         thread_type: string,
     *         program_code: string,
     *         cycle_code: string,
     *         attempt_id: string,
     *         applicant?: array{display_name: string, reference_code: ?string},
     *         status: string,
     *         latest_sequence: string,
     *         unread_count: int,
     *         last_activity_at: ?string,
     *         can_read: bool,
     *         can_send: bool,
     *         snapshot_url: string,
     *         send_url: string,
     *         read_url: string,
     *         html_url: string
     *     }>,
     *     pagination: array{per_page: int, has_more: bool, next_cursor: ?string}
     * }
     */
    public function summariesForProgram(
        Program $program,
        string $cycleCode,
        User $actor,
        ?string $search = null,
        ?string $cursor = null,
        int $perPage = 20,
    ): array {
        $freshProgram = Program::query()->find($program->id);
        $freshActor = User::query()->find($actor->id);
        $actorScope = $freshActor?->actorScope();
        $cycle = $freshProgram === null
            ? null
            : AcademicCycle::query()
                ->where('institution_id', $freshProgram->institution_id)
                ->where('code', $cycleCode)
                ->where('is_active', true)
                ->first();
        if ($freshProgram === null
            || ! $freshProgram->is_active
            || $actorScope === null
            || ! in_array($freshProgram->code, $actorScope->programCodes, true)
            || $actorScope->activeCycleCode !== $cycleCode
            || $cycle === null
            || (int) $cycle->institution_id !== (int) $freshProgram->institution_id) {
            abort(404, 'Thread collection not found.');
        }

        $search = $this->normalizeSearch($search);
        $queryScope = [
            'institution_id' => (string) $freshProgram->institution_id,
            'program_id' => (string) $freshProgram->id,
            'program_code' => (string) $freshProgram->code,
            'cycle_id' => (string) $cycle->id,
            'cycle_code' => $cycleCode,
        ];

        return $this->summaryPage(
            $this->candidateThreads()
                ->join('admission_attempts', 'admission_attempts.id', '=', 'record_threads.admission_attempt_id')
                ->where('record_threads.program_id', $freshProgram->id)
                ->where('admission_attempts.academic_cycle_id', $cycle->id)
                ->where('admission_attempts.institution_id', $freshProgram->institution_id)
                ->where('record_threads.thread_type', RecordThread::TYPE_APPLICANT_SUPPORT),
            $actor,
            'program_case',
            $queryScope,
            $search,
            $cursor,
            $perPage,
            1,
            RecordThread::TYPE_APPLICANT_SUPPORT,
            true,
        );
    }

    /**
     * Resolve only the explicitly selected root and context; never substitute a
     * different thread when the selected one is unavailable.
     *
     * @return array<string, mixed>
     */
    public function selectedSummary(
        string $threadId,
        User $actor,
        ?string $threadType = null,
        ?string $attemptId = null,
        ?string $programCode = null,
        ?string $cycleCode = null,
    ): array {
        return DB::transaction(function () use ($threadId, $actor, $threadType, $attemptId, $programCode, $cycleCode): array {
            if (! preg_match('/^[1-9][0-9]*$/D', $threadId)
                || strlen($threadId) > 19
                || (strlen($threadId) === 19 && strcmp($threadId, '9223372036854775807') > 0)) {
                abort(404, 'Thread not found.');
            }

            $thread = $this->candidateThreads()
                ->with(['attempt.academicCycle', 'program'])
                ->whereKey($threadId)
                ->first();

            if ($thread === null
                || ! $this->threads->canRead($thread, $actor)
                || ($threadType !== null && $thread->thread_type !== $threadType)
                || ($attemptId !== null && (string) $thread->admission_attempt_id !== $attemptId)
                || ($programCode !== null && $thread->program?->code !== $programCode)
                || ($cycleCode !== null && $thread->attempt?->academicCycle?->code !== $cycleCode)) {
                abort(404, 'Thread not found.');
            }

            $this->auditSummaryRead($thread, $actor, 'thread.summary_viewed');

            return $this->summaryRow($thread, $actor);
        });
    }

    /**
     * Record scoped access before protected summary metadata is serialized.
     */
    private function auditSummaryRead(RecordThread $thread, User $actor, string $event): void
    {
        $this->audit->handle($event, [
            'thread_id' => $thread->id,
            'attempt_id' => $thread->admission_attempt_id,
            'program_id' => $thread->program_id,
            'actor_id' => $actor->id,
        ], (string) $actor->id);
    }

    /**
     * @return Builder<RecordThread>
     */
    private function candidateThreads(): Builder
    {
        return RecordThread::query()
            ->select([
                'record_threads.id',
                'record_threads.admission_attempt_id',
                'record_threads.program_id',
                'record_threads.thread_type',
                'record_threads.subject',
                'record_threads.status',
                'record_threads.latest_message_sequence',
                'record_threads.created_at',
            ])
            ->with(['attempt.academicCycle', 'program:id,code'])
            ->addSelect([
                'last_activity_at' => RecordThreadMessage::query()
                    ->select('created_at')
                    ->whereColumn('record_thread_id', 'record_threads.id')
                    ->orderByDesc('sequence')
                    ->limit(1),
            ]);
    }

    /**
     * @param  Builder<RecordThread>  $query
     * @param  array<string, string>  $scope
     * @return array<string, mixed>
     */
    private function summaryPage(
        Builder $query,
        User $actor,
        string $domain,
        array $scope,
        ?string $search,
        ?string $cursor,
        int $perPage,
        int $page,
        ?string $threadType,
        bool $staffOnly,
        string $summaryReadEvent = 'thread.summaries_viewed',
    ): array {
        return DB::transaction(function () use (
            $query,
            $actor,
            $domain,
            $scope,
            $search,
            $cursor,
            $perPage,
            $page,
            $threadType,
            $staffOnly,
            $summaryReadEvent,
        ): array {
            $perPage = min(max($perPage, 1), 50);
            $page = max($page, 1);
            $afterCursor = $this->decodeCollectionCursor($cursor, $domain, $scope, $search, $actor);
            if ($domain === 'attempt_staff' && (($page === 1) !== ($cursor === null))) {
                throw ValidationException::withMessages([
                    'cursor' => 'A continuation cursor is required for later summary pages.',
                ]);
            }
            $query->whereIn('record_threads.id', $this->threads->readableThreadsQuery($actor)->select('record_threads.id'));
            $batchSize = $perPage + 1;

            if ($search !== null) {
                $escapedSearch = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search);
                $query->whereRaw("record_threads.subject LIKE ? ESCAPE '!'", ['%'.$escapedSearch.'%']);
            }

            $readableThreads = [];
            $scanCursor = $afterCursor;
            $hasMore = false;
            $latestActivityOrderSql = '(SELECT created_at FROM record_thread_messages WHERE record_thread_id = record_threads.id ORDER BY sequence DESC LIMIT 1)';

            do {
                $batchQuery = clone $query;
                if ($scanCursor !== null) {
                    $this->applyAfterCursor($batchQuery, $scanCursor);
                }

                $candidates = $batchQuery
                    ->orderByRaw('CASE WHEN '.$latestActivityOrderSql.' IS NULL THEN 1 ELSE 0 END')
                    ->orderByRaw($latestActivityOrderSql.' DESC')
                    ->orderByDesc('record_threads.id')
                    ->limit($batchSize)
                    ->get();

                foreach ($candidates as $candidate) {
                    if (! $this->summaryCandidateIsReadable($candidate, $actor, $threadType, $staffOnly)) {
                        $scanCursor = $this->cursorPosition($candidate);

                        continue;
                    }

                    if (count($readableThreads) === $perPage) {
                        $hasMore = true;

                        break;
                    }

                    $readableThreads[] = $candidate;
                    $scanCursor = $this->cursorPosition($candidate);
                }

                if ($hasMore || $candidates->count() < $batchSize) {
                    break;
                }
            } while (true);

            $summaries = [];
            if ($readableThreads !== []) {
                if ($summaryReadEvent === 'thread.summary_viewed') {
                    foreach ($readableThreads as $thread) {
                        $this->auditSummaryRead($thread, $actor, $summaryReadEvent);
                    }
                } else {
                    $this->auditSummaryRead($readableThreads[0], $actor, $summaryReadEvent);
                }
                foreach ($readableThreads as $thread) {
                    $summaries[] = $this->summaryRow($thread, $actor);
                }
            }

            return [
                'data' => $summaries,
                'pagination' => [
                    'per_page' => $perPage,
                    'has_more' => $hasMore,
                    'next_cursor' => $hasMore
                        ? $this->encodeCollectionCursor($scanCursor, $domain, $scope, $search, $actor)
                        : null,
                ],
            ];
        });
    }

    /**
     * @param  array{activity: ?string, last_id: string}  $afterCursor
     */
    private function applyAfterCursor(Builder $query, array $afterCursor): void
    {
        $latestActivitySql = '(SELECT created_at FROM record_thread_messages WHERE record_thread_id = record_threads.id ORDER BY sequence DESC LIMIT 1)';
        $query->where(function (Builder $query) use ($afterCursor, $latestActivitySql): void {
            if ($afterCursor['activity'] === null) {
                $query->whereRaw($latestActivitySql.' IS NULL')
                    ->where('record_threads.id', '<', $afterCursor['last_id']);

                return;
            }

            $query->whereRaw($latestActivitySql.' < ?', [$afterCursor['activity']])
                ->orWhere(function (Builder $query) use ($afterCursor, $latestActivitySql): void {
                    $query->whereRaw($latestActivitySql.' = ?', [$afterCursor['activity']])
                        ->where('record_threads.id', '<', $afterCursor['last_id']);
                })
                ->orWhereRaw($latestActivitySql.' IS NULL');
        });
    }

    private function summaryCandidateIsReadable(
        RecordThread $candidate,
        User $actor,
        ?string $threadType,
        bool $staffOnly,
    ): bool {
        if ($threadType !== null && $candidate->thread_type !== $threadType) {
            return false;
        }

        if ($staffOnly) {
            $attempt = $candidate->attempt;
            if ($attempt === null
                || ! $this->threads->staffCanParticipate(
                    $actor,
                    $attempt,
                    $candidate->program_id === null ? null : (int) $candidate->program_id,
                    (string) $candidate->thread_type,
                )) {
                return false;
            }
        }

        return $this->threads->canRead($candidate, $actor);
    }

    /**
     * @return array<string, mixed>
     */
    private function summaryRow(RecordThread $thread, User $actor): array
    {
        $attempt = $thread->attempt;
        $program = $thread->program;
        $cycleCode = (string) ($attempt?->academicCycle?->code ?? '');
        $id = (string) $thread->id;
        $attemptId = (string) $thread->admission_attempt_id;
        $programCode = (string) ($program?->code ?? '');
        $lastActivity = $thread->getAttribute('last_activity_at');
        $activityAt = $lastActivity instanceof Carbon
            ? $lastActivity->toISOString()
            : ($lastActivity === null ? null : Carbon::parse($lastActivity)->toISOString());
        $ownReadSequence = (string) (RecordThreadRead::query()
            ->where('record_thread_id', $thread->id)
            ->where('user_id', $actor->id)
            ->value('last_read_sequence') ?? 0);
        $row = [
            'principal_key' => CaseConversationToken::presentationContext($actor),
            'attempt_key' => $this->attemptContextKey($thread, 'case'),
            'id' => $id,
            'subject' => (string) $thread->subject,
            'thread_type' => (string) $thread->thread_type,
            'program_code' => $programCode,
            'cycle_code' => $cycleCode,
            'attempt_id' => $attemptId,
            'status' => (string) $thread->status,
            'latest_sequence' => (string) $thread->latest_message_sequence,
            'unread_count' => RecordThreadMessage::query()
                ->where('record_thread_id', $thread->id)
                ->where('sequence', '>', $ownReadSequence)
                ->where('sender_user_id', '!=', $actor->id)
                ->count(),
            'last_activity_at' => $activityAt,
            'can_read' => true,
            'can_send' => $this->threads->canSend($thread, $actor),
            'snapshot_url' => route('case-conversations.show', ['thread' => $id]),
            'send_url' => route('case-conversations.messages.store', ['thread' => $id]),
            'read_url' => route('case-conversations.read', ['thread' => $id]),
            'html_url' => $actor->applicantAccount()->exists()
                ? route('applicant.admission-support', [
                    'thread_id' => $id,
                    'thread_type' => (string) $thread->thread_type,
                    'attempt_id' => $attemptId,
                    'program_code' => $programCode,
                    'cycle_code' => $cycleCode,
                ])
            : route('admission-support.show', [
                'attempt' => $attemptId,
                'thread_id' => $id,
                'thread_type' => (string) $thread->thread_type,
                'program_code' => $programCode,
                'cycle_code' => $cycleCode,
                'tab' => 'threads',
            ]),
        ];

        if (! $actor->applicantAccount()->exists()) {
            $row['applicant'] = $this->staffApplicantMetadata($thread, $actor)['applicant'] ?? null;
        }

        return $row;
    }

    private function attemptContextKey(RecordThread $thread, string $domain): string
    {
        $attempt = $thread->attempt;

        return json_encode([
            $domain,
            (string) ($attempt?->institution_id ?? ''),
            (string) $thread->admission_attempt_id,
            (string) ($attempt?->academicCycle?->code ?? ''),
            (string) ($thread->program?->code ?? 'office'),
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    /**
     * @return array{applicant: array{display_name: string, reference_code: ?string}}|array{}
     */
    private function staffApplicantMetadata(RecordThread $thread, User $actor): array
    {
        if ($actor->applicantAccount()->exists()) {
            return [];
        }

        $attempt = $thread->attempt;
        if ($attempt === null
            || ! $this->threads->staffCanParticipate(
                $actor,
                $attempt,
                $thread->program_id === null ? null : (int) $thread->program_id,
                (string) $thread->thread_type,
            )) {
            return [];
        }

        $identity = ApplicantIdentity::query()
            ->select(['id', 'institution_id', 'first_name', 'middle_name', 'last_name', 'suffix'])
            ->whereKey($attempt->applicant_identity_id)
            ->where('institution_id', $attempt->institution_id)
            ->first();
        if ($identity === null) {
            return [];
        }

        $name = trim(implode(' ', array_filter([
            $identity->first_name,
            $identity->middle_name,
            $identity->last_name,
            $identity->suffix,
        ], fn (?string $part): bool => filled($part))));

        return ['applicant' => [
            'display_name' => $name !== '' ? $name : 'Applicant',
            'reference_code' => $attempt->reference_code,
        ]];
    }

    /**
     * @return array{activity: ?string, last_id: string}
     */
    private function cursorPosition(RecordThread $thread): array
    {
        $activity = $thread->getAttribute('last_activity_at');

        return [
            'activity' => $activity === null ? null : (string) $activity,
            'last_id' => (string) $thread->id,
        ];
    }

    /**
     * @param  array{activity: ?string, last_id: string}  $position
     * @param  array<string, string>  $scope
     */
    private function encodeCollectionCursor(
        array $position,
        string $domain,
        array $scope,
        ?string $search,
        User $actor,
    ): string {
        return Crypt::encryptString(json_encode([
            'domain' => $domain,
            'scope' => $scope,
            'actor' => (string) $actor->id,
            'session' => session()->getId(),
            'search' => $search,
            'activity' => $position['activity'],
            'order' => 'last_message_activity_desc_id_desc_v1',
            'last_id' => $position['last_id'],
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * @param  array<string, string>  $scope
     * @return ?array{activity: ?string, last_id: string}
     */
    private function decodeCollectionCursor(
        ?string $cursor,
        string $domain,
        array $scope,
        ?string $search,
        User $actor,
    ): ?array {
        if ($cursor === null) {
            return null;
        }
        if (strlen($cursor) > 2048) {
            throw ValidationException::withMessages([
                'cursor' => 'The summary cursor is invalid.',
            ]);
        }

        try {
            $payload = json_decode(Crypt::decryptString($cursor), true, flags: JSON_THROW_ON_ERROR);
        } catch (DecryptException|\JsonException) {
            throw ValidationException::withMessages([
                'cursor' => 'The summary cursor is invalid.',
            ]);
        }

        $activity = is_array($payload) ? ($payload['activity'] ?? null) : null;
        if (! is_array($payload)
            || ($payload['domain'] ?? null) !== $domain
            || ($payload['scope'] ?? null) !== $scope
            || ($payload['actor'] ?? null) !== (string) $actor->id
            || ($payload['session'] ?? null) !== session()->getId()
            || ($payload['search'] ?? null) !== $search
            || ($payload['order'] ?? null) !== 'last_message_activity_desc_id_desc_v1'
            || ! array_key_exists('activity', $payload)
            || ($activity !== null && ! is_string($activity))
            || ! is_string($payload['last_id'] ?? null)
            || ! preg_match('/^[1-9][0-9]*$/D', $payload['last_id'])
            || strlen($payload['last_id']) > 19
            || (strlen($payload['last_id']) === 19 && strcmp($payload['last_id'], '9223372036854775807') > 0)) {
            throw ValidationException::withMessages([
                'cursor' => 'The summary cursor is invalid.',
            ]);
        }

        return ['activity' => $activity, 'last_id' => $payload['last_id']];
    }

    private function normalizeSearch(?string $search): ?string
    {
        if ($search === null) {
            return null;
        }
        if (! mb_check_encoding($search, 'UTF-8')) {
            throw ValidationException::withMessages([
                'search' => 'Search must be valid UTF-8.',
            ]);
        }

        $trimmed = preg_replace('/^\s+|\s+$/u', '', $search);
        if (! is_string($trimmed)) {
            throw ValidationException::withMessages([
                'search' => 'Search must be valid UTF-8.',
            ]);
        }
        $search = $trimmed;
        if ($search === '') {
            return null;
        }
        if (mb_strlen($search, 'UTF-8') > 120) {
            throw ValidationException::withMessages([
                'search' => 'Search must not exceed 120 characters.',
            ]);
        }

        return $search;
    }

    /**
     * @return array{data: list<never>, pagination: array{per_page: int, has_more: false, next_cursor: null}}
     */
    private function emptySummaryPage(int $perPage): array
    {
        return [
            'data' => [],
            'pagination' => [
                'per_page' => min(max($perPage, 1), 50),
                'has_more' => false,
                'next_cursor' => null,
            ],
        ];
    }

    private function validateCursor(?string $cursor, string $field, string $latestSequence): ?string
    {
        if ($cursor === null) {
            return null;
        }

        if (! preg_match('/^(0|[1-9][0-9]*)$/D', $cursor)
            || strlen($cursor) > 19
            || (strlen($cursor) === 19 && strcmp($cursor, '9223372036854775807') > 0)
            || $this->decimalIsGreaterThan($cursor, $latestSequence)) {
            throw ValidationException::withMessages([
                $field => 'The sequence cursor is invalid.',
            ]);
        }

        return $cursor;
    }

    private function decimalIsGreaterThan(string $value, string $other): bool
    {
        return strlen($value) > strlen($other)
            || (strlen($value) === strlen($other) && strcmp($value, $other) > 0);
    }
}
