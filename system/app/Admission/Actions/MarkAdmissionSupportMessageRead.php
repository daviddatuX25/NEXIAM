<?php

namespace App\Admission\Actions;

use App\Admission\Support\RecordThreadService;
use App\Models\Admission\RecordThread;
use App\Models\User;
use RuntimeException;

/**
 * D-092: update the participant read watermark; denied when the actor no
 * longer passes current scope (revoked/expired-duty/cross-program).
 */
class MarkAdmissionSupportMessageRead
{
    public function __construct(private readonly RecordThreadService $threads) {}

    public function handle(RecordThread $thread, User $actor): void
    {
        if (! $this->threads->canParticipate($thread, $actor)) {
            throw new RuntimeException('You are not an active participant of this thread.');
        }

        $this->threads->markRead($thread, $actor);
    }
}
