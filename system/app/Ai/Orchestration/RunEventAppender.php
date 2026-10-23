<?php

namespace App\Ai\Orchestration;

use App\Models\Ai\XiamConversation;
use App\Models\Ai\XiamRun;
use App\Models\Ai\XiamRunEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class RunEventAppender
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function append(XiamRun $run, string $type, array $payload): XiamRunEvent
    {
        return DB::transaction(function () use ($run, $type, $payload): XiamRunEvent {
            $conversation = XiamConversation::query()
                ->lockForUpdate()
                ->findOrFail($run->conversation_id);
            $next = (int) XiamRunEvent::query()
                ->where('conversation_id', $conversation->id)
                ->max('seq') + 1;

            return XiamRunEvent::query()->create([
                'id' => (string) Str::uuid(),
                'run_id' => $run->id,
                'conversation_id' => $conversation->id,
                'seq' => $next,
                'type' => $type,
                'payload' => $payload,
                'created_at' => now(),
            ]);
        });
    }
}
