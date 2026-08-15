<?php

namespace App\Notifications;

use App\Models\User;

/**
 * Own-data bell projection (GF-21; GF-22 §5.5). Every query is hard-scoped to
 * `recipient_type = App\Models\User` + `recipient_id = auth()->id()` — a staff
 * or applicant user can never see another user's notification rows.
 */
final class NotificationProjection
{
    /**
     * @return array{unread_count: int, recent: list<array<string, mixed>>}
     */
    public function forUser(User $user, int $limit = 8): array
    {
        $unreadCount = $this->unreadCount($user);

        $recent = NotificationDelivery::forUser($user)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (NotificationDelivery $delivery): array => $this->row($delivery))
            ->all();

        return [
            'unread_count' => $unreadCount,
            'recent' => $recent,
        ];
    }

    public function unreadCount(User $user): int
    {
        return NotificationDelivery::forUser($user)
            ->whereNull('read_at')
            ->count();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function list(User $user, int $limit = 50): array
    {
        return NotificationDelivery::forUser($user)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (NotificationDelivery $delivery): array => $this->row($delivery))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function row(NotificationDelivery $delivery): array
    {
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
            'delivered_at' => $delivery->status === NotificationDelivery::STATUS_DELIVERED
                ? $delivery->updated_at?->toIso8601String()
                : null,
            'failed' => $delivery->status === NotificationDelivery::STATUS_FAILED_TERMINAL,
            'last_error' => $delivery->status === NotificationDelivery::STATUS_FAILED_TERMINAL
                ? $delivery->last_error
                : null,
            'subject' => (string) ($delivery->payload['subject'] ?? $delivery->template_key),
            'body' => (string) ($delivery->payload['body'] ?? ''),
            'action_label' => (string) ($delivery->payload['action_label'] ?? ''),
            'action_url' => (string) ($delivery->payload['action_url'] ?? ''),
        ];
    }
}
