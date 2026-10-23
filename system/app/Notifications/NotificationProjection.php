<?php

namespace App\Notifications;

use App\Admission\Support\ApplicantOfficeInquiryNotice;
use App\Models\Admission\ApplicantIdentity;
use App\Models\Admission\RecordThread;
use App\Models\User;
use App\Notifications\Templates\NoticeDeliverySchema3;
use App\Release\Support\ReleaseDayDecisionNotice;
use App\Testing\PracticeWorkspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Own-data projection of in-app rows. User recipients and the linked applicant
 * identity are resolved by NotificationDelivery::forUser; transport rows are
 * not duplicated as inbox items.
 */
final class NotificationProjection
{
    private const OFFICE_INQUIRY_EVENT = 'admission.office_inquiry_message.v1';

    private const RELEASE_SCHEDULE_EVENTS = [
        'consultation.session_published.v1',
        'consultation.slot_reserved.v1',
    ];

    /**
     * @return array{unread_count: int, recent: list<array<string, mixed>>, revision: string, queue_active: bool}
     */
    public function forUser(User $user, int $limit = 8): array
    {
        $recent = $this->visibleDeliveries($user, $limit);

        $this->markSurfaced($recent);
        $mailDeliveries = $this->mailDeliveriesFor($user, $recent);
        $unreadCount = $this->unreadCount($user);
        $rows = $recent
            ->map(fn (NotificationDelivery $delivery): array => $this->row(
                $delivery,
                $mailDeliveries->get($this->deliveryKey($delivery)),
            ))
            ->all();
        $queueActive = $recent->contains(
            fn (NotificationDelivery $delivery): bool => in_array($delivery->status, [
                NotificationDelivery::STATUS_PENDING,
                NotificationDelivery::STATUS_PROCESSING,
                NotificationDelivery::STATUS_RETRYING,
            ], true)
        ) || $mailDeliveries->contains(
            fn (NotificationDelivery $delivery): bool => in_array($delivery->status, [
                NotificationDelivery::STATUS_PENDING,
                NotificationDelivery::STATUS_PROCESSING,
                NotificationDelivery::STATUS_RETRYING,
            ], true)
        );
        $setId = app(PracticeWorkspace::class)->current()?->id;
        $revision = hash('sha256', json_encode([
            $unreadCount,
            $queueActive,
            $setId ?? 'operational',
            $recent->map(static fn (NotificationDelivery $delivery): array => [
                $delivery->id,
                $delivery->created_at?->toIso8601String(),
                $delivery->read_at?->toIso8601String(),
                $delivery->status,
            ])->all(),
        ], JSON_THROW_ON_ERROR));

        return [
            'unread_count' => $unreadCount,
            'recent' => $rows,
            'revision' => $revision,
            'queue_active' => $queueActive,
        ];
    }

    public function unreadCount(User $user): int
    {
        $unread = $this->purposeScope(NotificationDelivery::forUser($user), $user)
            ->where('channel', NotificationDelivery::CHANNEL_IN_APP)
            ->where('status', '!=', NotificationDelivery::STATUS_SUPERSEDED)
            ->whereNull('read_at');
        $inquiryCount = $unread->lazyById(100)
            ->filter(fn (NotificationDelivery $delivery): bool => $this->isVisible($delivery))
            ->count();

        return $inquiryCount;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function list(User $user, int $limit = 50): array
    {
        $deliveries = $this->visibleDeliveries($user, $limit);

        $this->markSurfaced($deliveries);
        $mailDeliveries = $this->mailDeliveriesFor($user, $deliveries);

        return $deliveries
            ->map(fn (NotificationDelivery $delivery): array => $this->row(
                $delivery,
                $mailDeliveries->get($this->deliveryKey($delivery)),
            ))
            ->all();
    }

    /**
     * @return Collection<int, NotificationDelivery>
     */
    private function visibleDeliveries(User $user, int $limit): Collection
    {
        if ($limit < 1) {
            return collect();
        }

        return $this->purposeScope(NotificationDelivery::forUser($user), $user)
            ->where('channel', NotificationDelivery::CHANNEL_IN_APP)
            ->where('status', '!=', NotificationDelivery::STATUS_SUPERSEDED)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->lazy(100)
            ->filter(fn (NotificationDelivery $delivery): bool => $this->isVisible($delivery))
            ->take($limit)
            ->collect()
            ->values();
    }

    /** @param Builder<NotificationDelivery> $query
     * @return Builder<NotificationDelivery>
     */
    private function purposeScope(Builder $query, User $user): Builder
    {
        $setId = app(PracticeWorkspace::class)->currentId()
            ?? $user->applicantAccount?->identity?->practice_fixture_set_id;

        return $setId === null
            ? $query->whereNull('payload->practice_fixture_set_id')
            : $query->where('payload->practice_fixture_set_id', $setId);
    }

    private function isVisible(NotificationDelivery $delivery): bool
    {
        if (in_array($delivery->event_type, [
            'admission.case_message_sent.v1',
            'admission.document_correction_requested.v1',
            'admission.academic_referral_opened.v1',
            ReleaseDayDecisionNotice::EVENT,
            'admission.clearance.confirmed.v1',
        ], true)) {
            return app(NotificationBusinessValidity::class)->isCurrent($delivery);
        }

        if ($delivery->status !== NotificationDelivery::STATUS_DELIVERED
            && in_array($delivery->event_type, [
                'exam.session_started_early.v1',
                'exam.session_expected_end_changed.v1',
                'exam.session_reassigned.v1',
                'exam.session_start_reverted.v1',
            ], true)) {
            return app(NotificationBusinessValidity::class)->isCurrent($delivery);
        }

        if (! app(PracticeNotificationSafety::class)->deliveryIsSafe($delivery)) {
            return false;
        }

        return $delivery->event_type !== self::OFFICE_INQUIRY_EVENT
            || app(ApplicantOfficeInquiryNotice::class)->isCurrent($delivery);
    }

    /**
     * `seen_at` means the authenticated projection first included the row; it
     * does not claim the recipient comprehended the message.
     *
     * @param  Collection<int, NotificationDelivery>  $deliveries
     */
    private function markSurfaced(Collection $deliveries): void
    {
        $unseenIds = $deliveries
            ->filter(fn (NotificationDelivery $delivery): bool => $delivery->seen_at === null)
            ->pluck('id');

        if ($unseenIds->isEmpty()) {
            return;
        }

        $surfacedAt = now();

        NotificationDelivery::query()
            ->whereIn('id', $unseenIds)
            ->whereNull('seen_at')
            ->update(['seen_at' => $surfacedAt]);

        $deliveries->each(function (NotificationDelivery $delivery) use ($unseenIds, $surfacedAt): void {
            if ($unseenIds->contains($delivery->id)) {
                $delivery->setAttribute('seen_at', $surfacedAt);
            }
        });
    }

    /**
     * @param  Collection<int, NotificationDelivery>  $inAppDeliveries
     * @return Collection<string, NotificationDelivery>
     */
    private function mailDeliveriesFor(User $user, Collection $inAppDeliveries): Collection
    {
        $eventIds = $inAppDeliveries->pluck('event_id')->unique()->values();

        if ($eventIds->isEmpty()) {
            return collect();
        }

        return NotificationDelivery::forUser($user)
            ->where('channel', NotificationDelivery::CHANNEL_MAIL)
            ->whereIn('event_id', $eventIds)
            ->get()
            ->filter(fn (NotificationDelivery $delivery): bool => $this->isVisible($delivery))
            ->keyBy(fn (NotificationDelivery $delivery): string => $this->deliveryKey($delivery));
    }

    private function deliveryKey(NotificationDelivery $delivery): string
    {
        return implode('|', [
            $delivery->recipient_type,
            (string) $delivery->recipient_id,
            $delivery->event_id,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(
        NotificationDelivery $delivery,
        ?NotificationDelivery $mailDelivery = null,
    ): array {
        $snapshotSchema = $delivery->payload['snapshot_schema_version'] ?? null;
        $schema3Message = $snapshotSchema === 'notice-delivery-3'
            && NoticeDeliverySchema3::valid($delivery->payload);
        $portal = $delivery->event_type !== self::OFFICE_INQUIRY_EVENT
            && ($snapshotSchema === 'notice-delivery-2' || $schema3Message)
                ? $delivery->payload['portal']
                : null;
        if ($delivery->event_type === self::OFFICE_INQUIRY_EVENT) {
            $portal = [
                'title' => 'New office inquiry message',
                'body' => 'A new message is available in your office inquiry.',
                'action_label' => 'View inquiry',
                'action_url' => route(
                    $delivery->recipient_type === ApplicantIdentity::class ? 'applicant.inquiries.show' : 'staff.inquiries.show',
                    ['inquiry' => $delivery->payload['inquiry_id']],
                    false,
                ),
            ];
        } elseif ($delivery->event_type === 'admission.case_message_sent.v1') {
            $portal = $this->caseMessagePortal($delivery) ?? [
                'title' => (string) ($delivery->payload['subject'] ?? 'New case message'),
                'body' => (string) ($delivery->payload['body'] ?? 'A new message is available in your case conversation.'),
                'action_label' => 'View conversation',
                'action_url' => '',
            ];
        }

        $actionUrl = (string) ($portal['action_url'] ?? $delivery->payload['action_url'] ?? '');
        if ($delivery->recipient_type === ApplicantIdentity::class
            && in_array($delivery->event_type, self::RELEASE_SCHEDULE_EVENTS, true)) {
            $actionUrl = route('applicant.consultation', [], false);
        }

        $documentRequest = null;
        if ($delivery->event_type === 'admission.document_correction_requested.v1') {
            $snapshot = $delivery->payload['event_resource']['document_correction_request']
                ?? $delivery->payload['document_request_snapshot'] ?? null;
            if (is_array($snapshot) && is_array($snapshot['items'] ?? null)) {
                $documentRequest = [
                    'id' => (int) ($snapshot['id'] ?? 0),
                    'recorded_at' => is_string($snapshot['recorded_at'] ?? null) ? $snapshot['recorded_at'] : null,
                    'overall_note' => is_string($snapshot['overall_note'] ?? null) ? $snapshot['overall_note'] : null,
                    'items' => array_values(array_map(static fn (array $item): array => [
                        'requirement_item_id' => (int) ($item['requirement_item_id'] ?? 0),
                        'label' => (string) ($item['label'] ?? ''),
                        'observed_status' => (string) ($item['observed_status'] ?? ''),
                        'expected_latest_verification_id' => isset($item['expected_latest_verification_id'])
                            ? (int) $item['expected_latest_verification_id'] : null,
                        'applicant_instruction' => (string) ($item['applicant_instruction'] ?? ''),
                    ], array_filter($snapshot['items'], 'is_array'))),
                ];
            }
        }

        $subject = $portal !== null ? ($portal['title'] ?? null) : ($delivery->payload['subject'] ?? null);
        $body = $portal !== null ? ($portal['body'] ?? null) : ($delivery->payload['body'] ?? null);
        $subject = is_string($subject) ? trim($subject) : '';
        $body = is_string($body) ? trim($body) : '';

        return [
            'id' => $delivery->id,
            'event_type' => $delivery->event_type,
            'event_version' => $delivery->event_version,
            'template_key' => $delivery->template_key,
            'template_version' => $delivery->template_version,
            'channel' => $delivery->channel,
            'status' => $delivery->status,
            'attempts' => $delivery->attempts,
            'read' => $delivery->read_at !== null,
            'created_at' => $delivery->created_at?->toIso8601String(),
            'delivered_at' => $delivery->delivered_at?->toIso8601String(),
            'failed' => $delivery->status === NotificationDelivery::STATUS_FAILED_TERMINAL,
            'outcome_unknown' => $delivery->status === NotificationDelivery::STATUS_OUTCOME_UNKNOWN,
            'email_delivery' => $mailDelivery === null ? null : [
                'status' => $mailDelivery->status,
                'attempts' => $mailDelivery->attempts,
                'next_attempt_at' => $mailDelivery->next_attempt_at?->toIso8601String(),
                'delivered_at' => $mailDelivery->delivered_at?->toIso8601String(),
                'failed' => $mailDelivery->status === NotificationDelivery::STATUS_FAILED_TERMINAL,
                'outcome_unknown' => $mailDelivery->status === NotificationDelivery::STATUS_OUTCOME_UNKNOWN,
            ],
            'subject' => $subject !== '' ? $subject : 'Notification update',
            'body' => $body !== '' ? $body : 'Open your notifications to review this update.',
            'action_label' => (string) ($portal['action_label'] ?? $delivery->payload['action_label'] ?? ''),
            'action_url' => $actionUrl,
            ...($documentRequest === null ? [] : ['document_correction_request' => $documentRequest]),
        ];
    }

    /** @return array{title:string,body:string,action_label:string,action_url:string}|null */
    private function caseMessagePortal(NotificationDelivery $delivery): ?array
    {
        $thread = RecordThread::query()
            ->with(['attempt.academicCycle', 'program'])
            ->find($delivery->payload['thread_id'] ?? null);
        if ($thread === null || $thread->attempt === null || $thread->program === null) {
            return null;
        }

        $actionUrl = match ($delivery->recipient_type) {
            ApplicantIdentity::class => route('applicant.admission-support', [
                'group' => 'program_support',
                'thread_id' => $thread->id,
                'attempt_id' => $thread->admission_attempt_id,
                'program_code' => $thread->program->code,
                'cycle_code' => $thread->attempt->academicCycle?->code,
            ], false),
            User::class => route('admission-support.show', [
                'attempt' => $thread->admission_attempt_id,
                'program_code' => $thread->program->code,
                'cycle_code' => $thread->attempt->academicCycle?->code,
                'tab' => 'threads',
                'thread_id' => $thread->id,
                'thread_type' => $thread->thread_type,
            ], false),
            default => '',
        };

        if ($actionUrl === '') {
            return null;
        }

        return [
            'title' => (string) ($delivery->payload['subject'] ?? 'New case message'),
            'body' => (string) ($delivery->payload['body'] ?? 'A new message is available in your case conversation.'),
            'action_label' => 'View conversation',
            'action_url' => $actionUrl,
        ];
    }
}
