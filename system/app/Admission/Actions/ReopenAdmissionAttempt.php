<?php

namespace App\Admission\Actions;

use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\AdmissionAttempt;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Non-destructive reopen — never cascade-deletes identity/history (legacy REJECT).
 */
class ReopenAdmissionAttempt
{
    public function __construct(private readonly RecordAuditEvent $recordAuditEvent) {}

    public function handle(AdmissionAttempt $attempt, User $actor, int $expectedVersion, string $reason): AdmissionAttempt
    {
        return DB::transaction(function () use ($attempt, $actor, $expectedVersion, $reason): AdmissionAttempt {
            $attempt = AdmissionAttempt::query()->lockForUpdate()->findOrFail($attempt->id);

            if ((int) $attempt->version !== $expectedVersion) {
                throw new RuntimeException('Stale admission attempt version.');
            }

            if (! in_array($attempt->status, [
                AdmissionAttempt::STATUS_DISMISSED,
                AdmissionAttempt::STATUS_ARCHIVED,
            ], true)) {
                throw new RuntimeException('Attempt is not eligible for reopen.');
            }

            $attempt->update([
                'status' => AdmissionAttempt::STATUS_PENDING,
                'dismissal_reason' => null,
                'dismissed_at' => null,
                'archived_at' => null,
                'correction_notes' => trim(($attempt->correction_notes ? $attempt->correction_notes."\n" : '').'Reopened: '.$reason),
                'version' => $attempt->version + 1,
            ]);

            $this->recordAuditEvent->handle('admission.attempt_reopened', [
                'attempt_id' => $attempt->id,
                'reference_code' => $attempt->reference_code,
                'reason' => $reason,
            ], (string) $actor->id);

            return $attempt->fresh();
        });
    }
}
