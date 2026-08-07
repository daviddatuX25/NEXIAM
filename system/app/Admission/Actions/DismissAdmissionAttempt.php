<?php

namespace App\Admission\Actions;

use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\AdmissionAttempt;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DismissAdmissionAttempt
{
    public function __construct(private readonly RecordAuditEvent $recordAuditEvent)
    {
    }

    public function handle(AdmissionAttempt $attempt, User $actor, string $reason, int $expectedVersion): AdmissionAttempt
    {
        return DB::transaction(function () use ($attempt, $actor, $reason, $expectedVersion): AdmissionAttempt {
            $attempt = AdmissionAttempt::query()->lockForUpdate()->findOrFail($attempt->id);

            if ((int) $attempt->version !== $expectedVersion) {
                throw new RuntimeException('Stale admission attempt version.');
            }

            $attempt->update([
                'status' => AdmissionAttempt::STATUS_DISMISSED,
                'dismissal_reason' => $reason,
                'dismissed_at' => now(),
                'version' => $attempt->version + 1,
            ]);

            $this->recordAuditEvent->handle('admission.attempt_dismissed', [
                'attempt_id' => $attempt->id,
                'reason' => $reason,
            ], (string) $actor->id);

            return $attempt->fresh();
        });
    }
}
