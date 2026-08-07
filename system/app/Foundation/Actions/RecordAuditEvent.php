<?php

namespace App\Foundation\Actions;

use App\Models\Foundation\AuditEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RecordAuditEvent
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(string $eventType, array $payload, string $actor = 'system'): AuditEvent
    {
        return DB::transaction(function () use ($eventType, $payload, $actor): AuditEvent {
            $previous = AuditEvent::query()->orderByDesc('id')->lockForUpdate()->first();
            $prevHash = $previous?->event_hash;
            $eventId = (string) Str::uuid();

            $canonical = json_encode([
                'id' => $eventId,
                'event_type' => $eventType,
                'actor' => $actor,
                'payload' => $payload,
                'prev_hash' => $prevHash,
            ], JSON_THROW_ON_ERROR);

            return AuditEvent::query()->create([
                'id' => $eventId,
                'event_type' => $eventType,
                'actor' => $actor,
                'payload' => $payload,
                'prev_hash' => $prevHash,
                'event_hash' => hash('sha256', $canonical),
            ]);
        });
    }
}
