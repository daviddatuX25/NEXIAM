<?php

namespace App\Admission\Events;

use App\Models\Admission\AdmissionAttempt;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when acceptance is pulled back to pending.
 * Scheduling/exam listeners should reconcile active assignments without cascade-delete.
 */
class AdmissionAttemptReconsidered
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly AdmissionAttempt $attempt,
        public readonly User $actor,
        public readonly string $reason,
        public readonly int $activeAssignmentCount,
        public readonly string $priorStatus,
    ) {}
}
