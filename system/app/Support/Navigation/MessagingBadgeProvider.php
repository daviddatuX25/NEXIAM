<?php

namespace App\Support\Navigation;

use App\Admission\Support\ApplicantOfficeInquiryAccess;
use App\Admission\Support\RecordThreadService;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\ApplicantOfficeInquiry;
use App\Models\Admission\RecordThread;
use App\Models\Foundation\AcademicCycle;
use App\Models\User;
use App\Testing\PracticePurpose;
use App\Testing\PracticeWorkspace;
use Illuminate\Support\Facades\DB;

final class MessagingBadgeProvider
{
    public function __construct(
        private readonly ApplicantOfficeInquiryAccess $officeAccess,
        private readonly RecordThreadService $recordThreads,
    ) {}

    /**
     * @return array{
     *   badge: array{kind: 'count', count: int, unit: 'unread_messages', variant: 'info', accessible_label: string, groups: array{case_threads: int, program_support: int, offices: int}}|null,
     *   groups: array{case_threads: int, program_support: int, offices: int},
     *   watermark_signature: string
     * }
     */
    public function forUser(User $user): array
    {
        $groups = ['case_threads' => 0, 'program_support' => 0, 'offices' => 0];
        $watermarks = [];

        if ($this->officeAccess->isAvailable($user)) {
            [$groups['offices'], $officeWatermark] = $this->officeUnread($user);
            $watermarks['offices'] = $officeWatermark;
        }

        [$groups['case_threads'], $groups['program_support'], $threadWatermark] = $this->threadUnread($user);
        $watermarks['record_threads'] = $threadWatermark;

        $total = array_sum($groups);
        $badge = $total === 0 ? null : [
            'kind' => 'count',
            'count' => $total,
            'unit' => 'unread_messages',
            'variant' => 'info',
            'accessible_label' => $total === 1 ? '1 unread message' : $total.' unread messages',
            'groups' => $groups,
        ];

        return [
            'badge' => $badge,
            'groups' => $groups,
            'watermark_signature' => hash('sha256', json_encode($watermarks, JSON_THROW_ON_ERROR)),
        ];
    }

    /** @return array{int, array<string, mixed>} */
    private function officeUnread(User $user): array
    {
        if ($this->officeAccess->isApplicant($user)) {
            $attempt = $this->officeAccess->applicantAttempt($user);
            $inquiryQuery = ApplicantOfficeInquiry::query()
                ->where('admission_attempt_id', $attempt->id)
                ->where('applicant_identity_id', $attempt->applicant_identity_id)
                ->where('institution_id', $attempt->institution_id)
                ->where('academic_cycle_id', $attempt->academic_cycle_id)
                ->whereIn('status', ['open', 'closed']);
        } elseif ($this->officeAccess->isStaff($user)) {
            $profile = $user->staffProfile;
            if ($profile === null || ! $profile->isActive() || $profile->deactivated_at !== null) {
                return [0, ['count' => 0, 'max_id' => 0, 'read_sum' => 0, 'inquiries' => []]];
            }
            $cycle = AcademicCycle::query()
                ->where('institution_id', $profile->institution_id)
                ->where('code', $profile->active_cycle_code)
                ->where('is_active', true)
                ->first();
            if ($cycle === null) {
                return [0, ['count' => 0, 'max_id' => 0, 'read_sum' => 0, 'inquiries' => []]];
            }
            $inquiryQuery = ApplicantOfficeInquiry::query()
                ->where('institution_id', $profile->institution_id)
                ->where('academic_cycle_id', $cycle->id)
                ->where('office', $profile->office)
                ->whereIn('status', ['open', 'closed']);
            $setId = app(PracticeWorkspace::class)->currentId();
            if ($setId === null) {
                $inquiryQuery->where(function ($sub): void {
                    $sub->whereNull('applicant_office_inquiries.admission_attempt_id')
                        ->orWhereIn(
                            'applicant_office_inquiries.admission_attempt_id',
                            PracticePurpose::root(AdmissionAttempt::query(), 'admission_attempts', null)->select('admission_attempts.id'),
                        );
                });
            } else {
                PracticePurpose::attempts($inquiryQuery, 'applicant_office_inquiries.admission_attempt_id', $setId);
            }
            $readableInquiries = $inquiryQuery->get()
                ->filter(fn (ApplicantOfficeInquiry $inquiry): bool => $this->officeAccess->canRead($user, $inquiry));
            $readableIds = $readableInquiries->modelKeys();
            $inquiryQuery->whereKey($readableIds);
        } else {
            return [0, ['count' => 0, 'max_id' => 0, 'read_sum' => 0, 'inquiries' => []]];
        }

        $inquiries = $inquiryQuery->get(['id', 'latest_message_sequence']);
        $inquiryIds = $inquiries->modelKeys();
        if ($inquiryIds === []) {
            return [0, ['count' => 0, 'max_id' => 0, 'read_sum' => 0, 'inquiries' => []]];
        }

        $aggregate = DB::table('applicant_office_inquiry_messages as m')
            ->join('applicant_office_inquiries as i', 'i.id', '=', 'm.applicant_office_inquiry_id')
            ->leftJoin('applicant_office_inquiry_reads as r', function ($join) use ($user): void {
                $join->on('r.applicant_office_inquiry_id', '=', 'i.id')->where('r.user_id', '=', $user->id);
            })
            ->whereIn('i.id', $inquiryIds)
            ->whereColumn('m.sequence', '<=', 'i.latest_message_sequence')
            ->selectRaw('COUNT(*) as message_count, MAX(m.id) as max_id, SUM(COALESCE(r.last_read_sequence, 0)) as read_sum, SUM(CASE WHEN COALESCE(m.sender_user_id, 0) <> ? AND m.sequence > COALESCE(r.last_read_sequence, 0) THEN 1 ELSE 0 END) as unread_count', [$user->id])
            ->first();

        return [(int) ($aggregate->unread_count ?? 0), [
            'count' => (int) ($aggregate->message_count ?? 0),
            'max_id' => (int) ($aggregate->max_id ?? 0),
            'read_sum' => (int) ($aggregate->read_sum ?? 0),
            'inquiries' => $inquiries->map(fn (ApplicantOfficeInquiry $inquiry): array => [(int) $inquiry->id, (int) $inquiry->latest_message_sequence])->all(),
        ]];
    }

    /** @return array{int, int, array<string, mixed>} */
    private function threadUnread(User $user): array
    {
        $query = $this->recordThreads->readableThreadsQuery($user);
        $threadIds = [];
        $threadSignature = hash_init('sha256');
        $aggregateSignature = hash_init('sha256');
        $unread = [
            RecordThread::TYPE_INTERNAL_PH_GUIDANCE => 0,
            RecordThread::TYPE_APPLICANT_SUPPORT => 0,
        ];

        $aggregateBatch = function () use (&$threadIds, $user, &$unread, $aggregateSignature): void {
            if ($threadIds === []) {
                return;
            }

            $aggregate = DB::table('record_thread_messages as m')
                ->join('record_threads as t', 't.id', '=', 'm.record_thread_id')
                ->leftJoin('record_thread_reads as r', function ($join) use ($user): void {
                    $join->on('r.record_thread_id', '=', 't.id')->where('r.user_id', '=', $user->id);
                })
                ->whereIn('t.id', $threadIds)
                ->whereColumn('m.sequence', '<=', 't.latest_message_sequence')
                ->selectRaw('t.thread_type, COUNT(*) as message_count, MAX(m.id) as max_id, SUM(COALESCE(r.last_read_sequence, 0)) as read_sum, SUM(CASE WHEN m.sender_user_id <> ? AND m.sequence > COALESCE(r.last_read_sequence, 0) THEN 1 ELSE 0 END) as unread_count', [$user->id])
                ->groupBy('t.thread_type')
                ->orderBy('t.thread_type')
                ->get();

            foreach ($aggregate as $row) {
                $unread[$row->thread_type] += (int) $row->unread_count;
                hash_update($aggregateSignature, json_encode([
                    $row->thread_type,
                    (int) $row->message_count,
                    (int) ($row->max_id ?? 0),
                    (int) ($row->read_sum ?? 0),
                ], JSON_THROW_ON_ERROR));
            }

            $threadIds = [];
        };

        foreach ($query->lazyById(100, 'record_threads.id', 'id') as $thread) {
            $threadIds[] = (int) $thread->id;
            hash_update($threadSignature, $thread->id.':'.$thread->latest_message_sequence.';');
            if (count($threadIds) === 100) {
                $aggregateBatch();
            }
        }
        $aggregateBatch();

        return [
            $unread[RecordThread::TYPE_INTERNAL_PH_GUIDANCE],
            $unread[RecordThread::TYPE_APPLICANT_SUPPORT],
            [
                'aggregate' => hash_final($aggregateSignature),
                'threads' => hash_final($threadSignature),
            ],
        ];
    }
}
