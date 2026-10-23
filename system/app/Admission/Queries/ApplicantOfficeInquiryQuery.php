<?php

namespace App\Admission\Queries;

use App\Admission\Support\ApplicantOfficeInquiryAccess;
use App\Admission\Support\CaseConversationToken;
use App\Admission\Support\InquirySubjectPresets;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ApplicantOfficeInquiry;
use App\Models\Admission\ApplicantOfficeInquiryMessage;
use App\Models\Admission\ApplicantOfficeInquiryRead;
use App\Models\Foundation\AcademicCycle;
use App\Models\User;
use App\Testing\PracticePurpose;
use App\Testing\PracticeWorkspace;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use JsonException;
use RuntimeException;

final class ApplicantOfficeInquiryQuery
{
    public function __construct(
        private readonly ApplicantOfficeInquiryAccess $access,
        private readonly RecordAuditEvent $audit,
    ) {}

    /** @return array{destinations: list<array{code: string, label: string}>, synthetic_only: true} */
    public function options(User $actor): array
    {
        return DB::transaction(function () use ($actor): array {
            $actor = $this->access->actor($actor);
            $attempt = $this->access->applicantAttempt($actor);
            AdmissionAttempt::query()->whereKey($attempt->id)->lockForUpdate()->first(['id']);
            $attempt = $this->access->applicantAttempt($actor);
            $destinations = [];
            foreach (['registrar', 'guidance'] as $office) {
                if ($this->access->officeStaffFor($attempt, $office)->isNotEmpty()) {
                    $destinations[] = ['code' => $office, 'label' => $this->officeLabel($office)];
                }
            }
            $this->audit->handle('office_inquiry.options_viewed', [
                'attempt_id' => $attempt->id,
                'actor_id' => $actor->id,
            ], (string) $actor->id);

            return ['destinations' => $destinations, 'synthetic_only' => true];
        });
    }

    /** @return array{inquiries: list<array<string, mixed>>, next_cursor: ?string, staff_mode: bool, office_label: string, attempt_key: string} */
    public function listFor(User $actor, ?string $beforeId = null, ?string $search = null): array
    {
        $search = $this->normalizeSearch($search);

        return DB::transaction(function () use ($actor, $beforeId, $search): array {
            $actor = $this->access->actor($actor);
            [$query, $scope, $staffMode, $officeLabel, $attemptKey] = $this->scopeFor($actor);
            $cursorScope = $scope.':search:'.hash('sha256', $search ?? '');
            $beforeId = $this->decodeCursor($actor, $cursorScope, $beforeId);
            $candidates = $query
                ->select(['id', 'institution_id', 'academic_cycle_id', 'admission_attempt_id', 'applicant_identity_id', 'office', 'subject', 'status', 'latest_message_sequence', 'public_reference_code', 'guest_token_hash', 'created_by_user_id', 'updated_at'])
                ->when($search !== null, fn (Builder $query): Builder => $query->whereRaw(
                    "LOWER(subject) LIKE LOWER(?) ESCAPE '!'",
                    ['%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%'],
                ))
                ->when($beforeId !== null, fn (Builder $query): Builder => $query->where('id', '<', $beforeId))
                ->orderByDesc('id')->limit(21)->get();
            $hasMore = $candidates->count() > 20;
            $candidates = $candidates->take(20);
            $attemptIds = $candidates->pluck('admission_attempt_id')->filter()->unique()->values();
            if ($attemptIds->isNotEmpty()) {
                AdmissionAttempt::query()->whereIn('id', $attemptIds)
                    ->orderBy('id')->lockForUpdate()->get(['id']);
            }
            $locked = ApplicantOfficeInquiry::query()->with(['attempt.identity'])->whereIn('id', $candidates->modelKeys())
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $latestMessageTimes = ApplicantOfficeInquiry::query()
                ->whereIn('id', $locked->modelKeys())
                ->select('id')
                ->addSelect(['latest_message_at' => ApplicantOfficeInquiryMessage::query()
                    ->select('created_at')
                    ->whereColumn('applicant_office_inquiry_id', 'applicant_office_inquiries.id')
                    ->whereColumn('sequence', '<=', 'applicant_office_inquiries.latest_message_sequence')
                    ->orderByDesc('sequence')->limit(1)])
                ->get()->keyBy('id');
            $unreadCounts = DB::table('applicant_office_inquiry_messages as messages')
                ->join('applicant_office_inquiries as inquiries', 'inquiries.id', '=', 'messages.applicant_office_inquiry_id')
                ->leftJoin('applicant_office_inquiry_reads as reads', function (JoinClause $join) use ($actor): void {
                    $join->on('reads.applicant_office_inquiry_id', '=', 'inquiries.id')->where('reads.user_id', $actor->id);
                })
                ->whereIn('inquiries.id', $locked->modelKeys())
                ->whereRaw('COALESCE(messages.sender_user_id, 0) <> ?', [$actor->id])
                ->whereColumn('messages.sequence', '<=', 'inquiries.latest_message_sequence')
                ->whereRaw('messages.sequence > COALESCE(reads.last_read_sequence, 0)')
                ->groupBy('messages.applicant_office_inquiry_id')
                ->selectRaw('messages.applicant_office_inquiry_id, COUNT(*) AS unread_count')
                ->pluck('unread_count', 'applicant_office_inquiry_id');
            $summaries = [];
            foreach ($candidates as $candidate) {
                $inquiry = $locked->get($candidate->id);
                if ($inquiry === null || ! $this->access->canRead($actor, $inquiry)) {
                    continue;
                }
                $latestMessageAt = $latestMessageTimes->get($inquiry->id)?->latest_message_at;
                $summary = [
                    'id' => (string) $inquiry->id,
                    'office' => (string) $inquiry->office,
                    'office_label' => $this->officeLabel($inquiry->office),
                    'subject' => (string) $inquiry->subject,
                    'status' => (string) $inquiry->status,
                    'latest_sequence' => (string) $inquiry->latest_message_sequence,
                    'unread_count' => (int) ($unreadCounts->get($inquiry->id) ?? 0),
                    'latest_message_at' => $latestMessageAt === null ? null : Carbon::parse($latestMessageAt)->toISOString(),
                    'updated_at' => $latestMessageAt === null ? null : Carbon::parse($latestMessageAt)->toISOString(),
                    'public_reference_code' => $inquiry->public_reference_code,
                    'is_pre_application' => $inquiry->isPreApplication(),
                ];
                if ($staffMode) {
                    $attempt = $inquiry->attempt;
                    $identity = $attempt?->identity;
                    $summary['applicant'] = [
                        'display_name' => $identity?->fullName() ?? ($inquiry->admission_attempt_id === null ? 'Public Inquiry' : 'Applicant'),
                        'reference_code' => (string) ($attempt?->reference_code ?? $inquiry->public_reference_code ?? 'PUBLIC'),
                    ];
                }
                $summaries[] = $summary;
            }
            [, $currentScope] = $this->scopeFor($this->access->actor($actor));
            if ($currentScope !== $scope) {
                throw new RuntimeException('office_inquiry_not_found');
            }
            $this->audit->handle('office_inquiry.list_viewed', [
                'actor_id' => $actor->id,
                'inquiry_ids' => array_column($summaries, 'id'),
            ], (string) $actor->id);

            return [
                'inquiries' => $summaries,
                'next_cursor' => $hasMore && $candidates->last() !== null
                    ? $this->encodeCursor($actor, $cursorScope, (string) $candidates->last()->id) : null,
                'staff_mode' => $staffMode,
                'office_label' => $officeLabel,
                'attempt_key' => $attemptKey,
            ];
        });
    }

    private function normalizeSearch(?string $search): ?string
    {
        if ($search === null) {
            return null;
        }
        if (! mb_check_encoding($search, 'UTF-8')) {
            throw ValidationException::withMessages(['search' => 'The subject search is invalid.']);
        }
        $search = trim($search);
        if ($search === '') {
            return null;
        }
        if (mb_strlen($search) > 120) {
            throw ValidationException::withMessages(['search' => 'The subject search must not exceed 120 characters.']);
        }

        return $search;
    }

    /** @return array<string, mixed> */
    public function snapshot(User $actor, int $inquiryId, ?string $afterSequence = null, ?string $beforeSequence = null): array
    {
        return DB::transaction(function () use ($actor, $inquiryId, $afterSequence, $beforeSequence): array {
            $actor = $this->access->actor($actor);
            $inquiry = $this->lockedInquiry($actor, $inquiryId);
            $latest = (string) $inquiry->latest_message_sequence;
            $afterSequence = $this->validateSequence($afterSequence, 'after_sequence', $latest, true);
            $beforeSequence = $this->validateSequence($beforeSequence, 'before_sequence', $latest, false);
            if ($afterSequence !== null && $beforeSequence !== null) {
                throw ValidationException::withMessages(['cursor' => 'Only one sequence cursor may be supplied.']);
            }
            $maximum = min(100, max(1, (int) config('applicant_office_inquiries.max_message_page_size', 100)));
            $limit = min($maximum, max(1, (int) config('applicant_office_inquiries.message_page_size', 50)));
            $query = $this->messagesFor($inquiry);
            if ($afterSequence !== null) {
                $messages = $query->where('sequence', '>', $afterSequence)->orderBy('sequence')->limit($limit + 1)->get();
                $hasMoreAfter = $messages->count() > $limit;
                $messages = $messages->take($limit)->values();
                $hasOlder = $messages->first() !== null && (int) $messages->first()->sequence > 1;
            } else {
                $messages = $query->when($beforeSequence !== null, fn (Builder $query): Builder => $query->where('sequence', '<', $beforeSequence))
                    ->orderByDesc('sequence')->limit($limit + 1)->get();
                $hasOlder = $messages->count() > $limit;
                $messages = $messages->take($limit)->reverse()->values();
                $hasMoreAfter = $beforeSequence !== null && ($messages->last() === null
                    ? (int) $latest > 0 : (int) $messages->last()->sequence < (int) $latest);
            }
            $oldest = $messages->first() !== null ? (string) $messages->first()->sequence : null;
            $newest = $messages->last() !== null ? (string) $messages->last()->sequence : null;
            $ownRead = $this->ownReadSequence($actor, $inquiry);
            $this->audit->handle('office_inquiry.messages_viewed', [
                'inquiry_id' => $inquiry->id,
                'attempt_id' => $inquiry->admission_attempt_id,
                'actor_id' => $actor->id,
                'oldest_sequence' => $oldest,
                'newest_sequence' => $newest,
            ], (string) $actor->id);

            $cycleCode = AcademicCycle::query()->whereKey($inquiry->academic_cycle_id)
                ->where('institution_id', $inquiry->institution_id)->value('code');
            if (! is_string($cycleCode)) {
                throw new RuntimeException('office_inquiry_not_found');
            }

            $staffApplicant = null;
            if ($this->access->isStaff($actor)) {
                if ($inquiry->admission_attempt_id === null) {
                    $staffApplicant = [
                        'display_name' => 'Public Inquiry',
                        'reference_code' => (string) ($inquiry->public_reference_code ?? 'PUBLIC'),
                    ];
                } else {
                    $attempt = AdmissionAttempt::query()->whereKey($inquiry->admission_attempt_id)
                        ->where('institution_id', $inquiry->institution_id)
                        ->where('academic_cycle_id', $inquiry->academic_cycle_id)
                        ->where('applicant_identity_id', $inquiry->applicant_identity_id)
                        ->with('identity')->first(['id', 'institution_id', 'academic_cycle_id', 'applicant_identity_id', 'reference_code']);
                    if ($attempt !== null) {
                        $staffApplicant = [
                            'display_name' => $attempt->identity?->fullName() ?: 'Unknown',
                            'reference_code' => (string) $attempt->reference_code,
                        ];
                    }
                }
            }

            return [
                'thread' => [
                    'id' => (string) $inquiry->id,
                    'subject' => (string) $inquiry->subject,
                    'thread_type' => 'applicant_office_inquiry',
                    'program_code' => '',
                    'office' => (string) $inquiry->office,
                    'office_label' => $this->officeLabel($inquiry->office),
                    'status' => (string) $inquiry->status,
                    'can_read' => true,
                    'can_send' => $inquiry->status === 'open',
                    'public_reference_code' => $inquiry->public_reference_code,
                    'is_pre_application' => $inquiry->isPreApplication(),
                    ...($staffApplicant === null ? [] : ['applicant' => $staffApplicant]),
                ],
                'messages' => $messages->map(fn (ApplicantOfficeInquiryMessage $message): array => $this->projectMessage($actor, $message))->all(),
                'page' => [
                    'oldest_sequence' => $oldest,
                    'newest_sequence' => $newest,
                    'has_older' => $hasOlder,
                    'has_more_after' => $hasMoreAfter,
                ],
                'latest_sequence' => $latest,
                'own_read_sequence' => $ownRead,
                'unread_count' => $this->unreadCount($actor, $inquiry, $ownRead),
                'observation_token' => $newest === null ? '' : CaseConversationToken::issue($actor, 'office-inquiry:'.$inquiry->id, $newest),
                'as_of' => now()->toISOString(),
                'attempt_key' => json_encode(['office', (string) $inquiry->institution_id, (string) $inquiry->id, $cycleCode, (string) $inquiry->office], JSON_THROW_ON_ERROR),
            ];
        });
    }

    /** @return array{id: string, sequence: string, sender: array{display_name: string, is_self: bool}, body: string, created_at: string} */
    public function message(User $actor, int $inquiryId, int $messageId): array
    {
        return DB::transaction(function () use ($actor, $inquiryId, $messageId): array {
            $actor = $this->access->actor($actor);
            $inquiry = $this->lockedInquiry($actor, $inquiryId);
            $message = $this->messagesFor($inquiry)->whereKey($messageId)->first();
            if ($message === null) {
                throw new RuntimeException('office_inquiry_not_found');
            }
            $this->audit->handle('office_inquiry.message_viewed', [
                'inquiry_id' => $inquiry->id,
                'message_id' => $message->id,
                'actor_id' => $actor->id,
            ], (string) $actor->id);

            return $this->projectMessage($actor, $message);
        });
    }

    /** @return array{Builder<ApplicantOfficeInquiry>, string, bool, string, string} */
    private function scopeFor(User $actor): array
    {
        $query = ApplicantOfficeInquiry::query();
        if ($this->access->isApplicant($actor)) {
            $attempt = $this->access->applicantAttempt($actor);
            $query->where('admission_attempt_id', $attempt->id)
                ->where('applicant_identity_id', $attempt->applicant_identity_id)
                ->where('institution_id', $attempt->institution_id)->where('academic_cycle_id', $attempt->academic_cycle_id);

            return [$query, 'applicant:'.$attempt->id, false, '', json_encode(['office', (string) $attempt->institution_id, (string) $attempt->id, (string) $attempt->academicCycle->code, 'applicant'], JSON_THROW_ON_ERROR)];
        }
        $profile = $actor->staffProfile;
        $cycle = AcademicCycle::query()->where('institution_id', $profile->institution_id)
            ->where('code', $profile->active_cycle_code)->where('is_active', true)->first();
        if ($cycle === null) {
            throw new RuntimeException('office_inquiry_not_found');
        }
        $query->where('institution_id', $profile->institution_id)->where('academic_cycle_id', $cycle->id)->where('office', $profile->office);
        $setId = app(PracticeWorkspace::class)->currentId();
        if ($setId === null) {
            $query->where(function (Builder $sub): void {
                $sub->whereNull('applicant_office_inquiries.admission_attempt_id')
                    ->orWhereIn(
                        'applicant_office_inquiries.admission_attempt_id',
                        PracticePurpose::root(AdmissionAttempt::query(), 'admission_attempts', null)->select('admission_attempts.id'),
                    );
            });
        } else {
            PracticePurpose::attempts($query, 'applicant_office_inquiries.admission_attempt_id', $setId);
        }
        $scope = 'staff:'.$profile->institution_id.':'.$cycle->id.':'.$profile->office.':'.($setId ?? 'operational');

        return [$query, $scope, true, $this->officeLabel($profile->office), json_encode(['office', (string) $profile->institution_id, '', (string) $cycle->code, (string) $profile->office], JSON_THROW_ON_ERROR)];
    }

    private function lockedInquiry(User $actor, int $inquiryId): ApplicantOfficeInquiry
    {
        $attemptId = ApplicantOfficeInquiry::query()->whereKey($inquiryId)->value('admission_attempt_id');
        if ($attemptId !== null && AdmissionAttempt::query()->whereKey($attemptId)->lockForUpdate()->first(['id']) === null) {
            throw new RuntimeException('office_inquiry_not_found');
        }
        $inquiryQuery = ApplicantOfficeInquiry::query()->whereKey($inquiryId);
        if ($attemptId !== null) {
            $inquiryQuery->where('admission_attempt_id', $attemptId);
        } else {
            $inquiryQuery->whereNull('admission_attempt_id');
        }
        $inquiry = $inquiryQuery->lockForUpdate()->first();
        if ($inquiry === null) {
            throw new RuntimeException('office_inquiry_not_found');
        }
        $this->access->assertRead($actor, $inquiry);

        return $inquiry;
    }

    /** @return Builder<ApplicantOfficeInquiryMessage> */
    private function messagesFor(ApplicantOfficeInquiry $inquiry): Builder
    {
        return ApplicantOfficeInquiryMessage::query()
            ->select(['id', 'applicant_office_inquiry_id', 'sender_user_id', 'sender_role', 'sequence', 'body', 'created_at'])
            ->with('sender:id,name')->where('applicant_office_inquiry_id', $inquiry->id)
            ->where('sequence', '<=', $inquiry->latest_message_sequence);
    }

    private function ownReadSequence(User $actor, ApplicantOfficeInquiry $inquiry): string
    {
        return (string) (ApplicantOfficeInquiryRead::query()->where('applicant_office_inquiry_id', $inquiry->id)
            ->where('user_id', $actor->id)->value('last_read_sequence') ?? 0);
    }

    private function unreadCount(User $actor, ApplicantOfficeInquiry $inquiry, string $ownRead): int
    {
        return ApplicantOfficeInquiryMessage::query()->where('applicant_office_inquiry_id', $inquiry->id)
            ->where('sequence', '>', $ownRead)->where('sequence', '<=', $inquiry->latest_message_sequence)
            ->where(fn (Builder $query): Builder => $query->whereNull('sender_user_id')->orWhere('sender_user_id', '!=', $actor->id))
            ->count();
    }

    /** @return array{id: string, sequence: string, sender: array{display_name: string, is_self: bool}, body: string, created_at: string} */
    private function projectMessage(User $actor, ApplicantOfficeInquiryMessage $message): array
    {
        $isApplicantActor = $this->access->isApplicant($actor);
        $isSelf = $message->sender_user_id !== null
            ? (string) $message->sender_user_id === (string) $actor->id
            : ($isApplicantActor && $message->sender_role === ApplicantOfficeInquiryMessage::ROLE_APPLICANT);
        $displayName = $message->sender?->name
            ?? ($message->sender_role === ApplicantOfficeInquiryMessage::ROLE_STAFF ? 'Office Staff' : 'Prospective Applicant');

        return [
            'id' => (string) $message->id,
            'sequence' => (string) $message->sequence,
            'sender' => ['display_name' => $displayName, 'is_self' => $isSelf],
            'body' => (string) $message->body,
            'created_at' => $message->created_at->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function preApplicationState(string $deviceTokenHash, ?int $selectedInquiryId = null): array
    {
        $context = $this->access->preApplicationContext();
        if ($context === null) {
            return [
                'available' => false,
                'destinations' => [],
                'subject_presets' => InquirySubjectPresets::catalog(),
                'inquiries' => [],
                'initial' => null,
                'principal_key' => '',
                'attempt_key' => '',
                'synthetic_only' => true,
            ];
        }

        $institution = $context['institution'];
        $cycle = $context['cycle'];
        $principalKey = CaseConversationToken::presentationContextForPrincipal('guest:'.$deviceTokenHash);
        $attemptKey = json_encode(['public-office', (string) $institution->id, (string) $cycle->code], JSON_THROW_ON_ERROR);

        $rows = ApplicantOfficeInquiry::query()
            ->where('institution_id', $institution->id)
            ->where('academic_cycle_id', $cycle->id)
            ->where('guest_token_hash', $deviceTokenHash)
            ->whereNull('created_by_user_id')
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        $inquiries = [];
        foreach ($rows as $row) {
            if (! $this->access->canReadPreApplication($deviceTokenHash, $row)) {
                continue;
            }
            $unreadCount = ApplicantOfficeInquiryMessage::query()
                ->where('applicant_office_inquiry_id', $row->id)
                ->where('sequence', '>', (int) $row->guest_last_read_sequence)
                ->where('sequence', '<=', (int) $row->latest_message_sequence)
                ->where('sender_role', ApplicantOfficeInquiryMessage::ROLE_STAFF)
                ->count();
            $inquiries[] = [
                'id' => (string) $row->id,
                'public_reference_code' => (string) ($row->public_reference_code ?? ''),
                'office' => (string) $row->office,
                'office_label' => $this->officeLabel((string) $row->office),
                'subject' => (string) $row->subject,
                'status' => (string) $row->status,
                'latest_sequence' => (string) $row->latest_message_sequence,
                'unread_count' => $unreadCount,
                'latest_message_at' => $row->updated_at?->toISOString(),
                'updated_at' => $row->updated_at?->toISOString(),
            ];
        }

        $targetId = $selectedInquiryId ?? (isset($inquiries[0]['id']) ? (int) $inquiries[0]['id'] : null);
        $initial = null;
        if ($targetId !== null) {
            try {
                $initial = $this->snapshotPreApplication($deviceTokenHash, $targetId);
            } catch (RuntimeException) {
                $initial = null;
            }
        }

        return [
            'available' => true,
            'destinations' => $context['destinations'],
            'subject_presets' => InquirySubjectPresets::catalog(),
            'inquiries' => $inquiries,
            'initial' => $initial,
            'principal_key' => $principalKey,
            'attempt_key' => $attemptKey,
            'synthetic_only' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshotPreApplication(
        string $deviceTokenHash,
        int $inquiryId,
        ?string $afterSequence = null,
        ?string $beforeSequence = null,
    ): array {
        return DB::transaction(function () use ($deviceTokenHash, $inquiryId, $afterSequence, $beforeSequence): array {
            $inquiry = ApplicantOfficeInquiry::query()->whereKey($inquiryId)->lockForUpdate()->first();
            if ($inquiry === null) {
                throw new RuntimeException('office_inquiry_not_found');
            }
            $this->access->assertReadPreApplication($deviceTokenHash, $inquiry);

            $latest = (string) $inquiry->latest_message_sequence;
            $afterSequence = $this->validateSequence($afterSequence, 'after_sequence', $latest, true);
            $beforeSequence = $this->validateSequence($beforeSequence, 'before_sequence', $latest, false);
            if ($afterSequence !== null && $beforeSequence !== null) {
                throw ValidationException::withMessages(['cursor' => 'Only one sequence cursor may be supplied.']);
            }
            $maximum = min(100, max(1, (int) config('applicant_office_inquiries.max_message_page_size', 100)));
            $limit = min($maximum, max(1, (int) config('applicant_office_inquiries.message_page_size', 50)));
            $query = $this->messagesFor($inquiry);
            if ($afterSequence !== null) {
                $messages = $query->where('sequence', '>', $afterSequence)->orderBy('sequence')->limit($limit + 1)->get();
                $hasMoreAfter = $messages->count() > $limit;
                $messages = $messages->take($limit)->values();
                $hasOlder = $messages->first() !== null && (int) $messages->first()->sequence > 1;
            } else {
                $messages = $query->when($beforeSequence !== null, fn (Builder $query): Builder => $query->where('sequence', '<', $beforeSequence))
                    ->orderByDesc('sequence')->limit($limit + 1)->get();
                $hasOlder = $messages->count() > $limit;
                $messages = $messages->take($limit)->reverse()->values();
                $hasMoreAfter = $beforeSequence !== null && ($messages->last() === null
                    ? (int) $latest > 0 : (int) $messages->last()->sequence < (int) $latest);
            }
            $oldest = $messages->first() !== null ? (string) $messages->first()->sequence : null;
            $newest = $messages->last() !== null ? (string) $messages->last()->sequence : null;
            $ownRead = (string) ((int) $inquiry->guest_last_read_sequence);
            $unreadCount = ApplicantOfficeInquiryMessage::query()
                ->where('applicant_office_inquiry_id', $inquiry->id)
                ->where('sequence', '>', (int) $ownRead)
                ->where('sequence', '<=', (int) $inquiry->latest_message_sequence)
                ->where('sender_role', ApplicantOfficeInquiryMessage::ROLE_STAFF)
                ->count();

            $cycleCode = AcademicCycle::query()->whereKey($inquiry->academic_cycle_id)
                ->where('institution_id', $inquiry->institution_id)->value('code');
            if (! is_string($cycleCode)) {
                throw new RuntimeException('office_inquiry_not_found');
            }

            return [
                'thread' => [
                    'id' => (string) $inquiry->id,
                    'public_reference_code' => (string) ($inquiry->public_reference_code ?? ''),
                    'is_pre_application' => true,
                    'subject' => (string) $inquiry->subject,
                    'thread_type' => 'applicant_office_inquiry',
                    'program_code' => '',
                    'office' => (string) $inquiry->office,
                    'office_label' => $this->officeLabel((string) $inquiry->office),
                    'status' => (string) $inquiry->status,
                    'can_read' => true,
                    'can_send' => $inquiry->status === 'open',
                ],
                'messages' => $messages->map(fn (ApplicantOfficeInquiryMessage $message): array => $this->projectPreApplicationMessage($message))->all(),
                'page' => [
                    'oldest_sequence' => $oldest,
                    'newest_sequence' => $newest,
                    'has_older' => $hasOlder,
                    'has_more_after' => $hasMoreAfter,
                ],
                'latest_sequence' => $latest,
                'own_read_sequence' => $ownRead,
                'unread_count' => $unreadCount,
                'observation_token' => $newest === null ? '' : CaseConversationToken::issueForPrincipal('guest:'.$deviceTokenHash, 'office-inquiry:'.$inquiry->id, $newest),
                'as_of' => now()->toISOString(),
                'attempt_key' => json_encode(['public-office', (string) $inquiry->institution_id, (string) $cycleCode], JSON_THROW_ON_ERROR),
            ];
        });
    }

    /**
     * @return array{id: string, sequence: string, sender: array{display_name: string, is_self: bool}, body: string, created_at: string}
     */
    public function messagePreApplication(string $deviceTokenHash, int $inquiryId, int $messageId): array
    {
        return DB::transaction(function () use ($deviceTokenHash, $inquiryId, $messageId): array {
            $inquiry = ApplicantOfficeInquiry::query()->whereKey($inquiryId)->lockForUpdate()->first();
            if ($inquiry === null) {
                throw new RuntimeException('office_inquiry_not_found');
            }
            $this->access->assertReadPreApplication($deviceTokenHash, $inquiry);
            $message = $this->messagesFor($inquiry)->whereKey($messageId)->first();
            if ($message === null) {
                throw new RuntimeException('office_inquiry_not_found');
            }

            return $this->projectPreApplicationMessage($message);
        });
    }

    /**
     * @return array{id: string, sequence: string, sender: array{display_name: string, is_self: bool}, body: string, created_at: string}
     */
    private function projectPreApplicationMessage(ApplicantOfficeInquiryMessage $message): array
    {
        $isSelf = $message->sender_role !== ApplicantOfficeInquiryMessage::ROLE_STAFF;
        $displayName = $isSelf ? 'You' : ($message->sender?->name ?? 'Office Staff');

        return [
            'id' => (string) $message->id,
            'sequence' => (string) $message->sequence,
            'sender' => ['display_name' => $displayName, 'is_self' => $isSelf],
            'body' => (string) $message->body,
            'created_at' => $message->created_at->toISOString(),
        ];
    }

    private function officeLabel(string $office): string
    {
        return match ($office) {
            'registrar' => 'Registrar',
            'guidance' => 'Guidance',
            default => throw new RuntimeException('office_inquiry_not_found'),
        };
    }

    private function validateSequence(?string $sequence, string $field, string $latest, bool $allowZero): ?string
    {
        if ($sequence !== null && (! $this->isDecimal($sequence, $allowZero) || (int) $sequence > (int) $latest)) {
            throw ValidationException::withMessages([$field => 'The sequence cursor is invalid.']);
        }

        return $sequence;
    }

    private function isDecimal(string $value, bool $allowZero = false): bool
    {
        return preg_match($allowZero ? '/^(0|[1-9][0-9]*)$/D' : '/^[1-9][0-9]*$/D', $value) === 1
            && strlen($value) <= strlen((string) PHP_INT_MAX)
            && (strlen($value) < strlen((string) PHP_INT_MAX) || strcmp($value, (string) PHP_INT_MAX) <= 0);
    }

    private function encodeCursor(User $actor, string $scope, string $id): string
    {
        return Crypt::encryptString(json_encode(['user' => (string) $actor->id, 'scope' => $scope, 'before_id' => $id], JSON_THROW_ON_ERROR));
    }

    private function decodeCursor(User $actor, string $scope, ?string $cursor): ?string
    {
        if ($cursor === null) {
            return null;
        }
        if ($cursor === '' || strlen($cursor) > 2048) {
            throw ValidationException::withMessages(['before_id' => 'The inquiry cursor is invalid.']);
        }
        try {
            $payload = json_decode(Crypt::decryptString($cursor), true, 8, JSON_THROW_ON_ERROR);
        } catch (DecryptException|JsonException) {
            throw ValidationException::withMessages(['before_id' => 'The inquiry cursor is invalid.']);
        }
        if (! is_array($payload) || ($payload['user'] ?? null) !== (string) $actor->id || ($payload['scope'] ?? null) !== $scope
            || ! is_string($payload['before_id'] ?? null) || ! $this->isDecimal($payload['before_id'])) {
            throw ValidationException::withMessages(['before_id' => 'The inquiry cursor is invalid.']);
        }

        return $payload['before_id'];
    }
}
