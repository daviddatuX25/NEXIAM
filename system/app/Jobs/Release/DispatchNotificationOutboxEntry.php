<?php

namespace App\Jobs\Release;

use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Release\NotificationOutbox;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DispatchNotificationOutboxEntry implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $outboxId)
    {
    }

    public function handle(RecordAuditEvent $recordAuditEvent): void
    {
        $entry = NotificationOutbox::query()->findOrFail($this->outboxId);

        if ($entry->status === NotificationOutbox::STATUS_SENT) {
            return;
        }

        $entry->update([
            'status' => NotificationOutbox::STATUS_SENT,
            'sent_at' => now(),
        ]);

        $recordAuditEvent->handle('notification.outbox_sent', [
            'outbox_id' => $entry->id,
            'idempotency_key' => $entry->idempotency_key,
        ], 'system');
    }
}
