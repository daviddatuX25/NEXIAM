<?php

namespace App\Ai\Orchestration;

use App\Models\Ai\NexiRun;
use App\Models\Ai\NexiRunEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class RunEventAppender
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function append(NexiRun $run, string $type, array $payload): NexiRunEvent
    {
        return DB::transaction(function () use ($run, $type, $payload): NexiRunEvent {
            $next = (int) NexiRunEvent::query()->where('run_id', $run->id)->max('seq') + 1;

            return NexiRunEvent::query()->create([
                'id' => (string) Str::uuid(),
                'run_id' => $run->id,
                'conversation_id' => $run->conversation_id,
                'seq' => $next,
                'type' => $type,
                'payload' => $payload,
                'created_at' => now(),
            ]);
        });
    }
}
