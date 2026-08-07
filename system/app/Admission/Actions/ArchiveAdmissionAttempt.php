<?php

namespace App\Admission\Actions;

use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\AdmissionAttempt;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Soft archive — retains identity and operational history. */
class ArchiveAdmissionAttempt
{
    public function __construct(private readonly RecordAuditEvent $recordAuditEvent)
    {
    }

    public function handle(AdmissionAttempt $attempt, User $actor, int $expectedVersion): AdmissionAttempt
    {
        return DB::transaction(function () use ($attempt, $actor, $expectedVersion): AdmissionAttempt {
            $attempt = AdmissionAttempt::query()->lockForUpdate()->findOrFail($attempt->id);

            if ((int) $attempt->version !== $expectedVersion) {
                throw new RuntimeException('Stale admission attempt version.');
            }

            if ($attempt->status === AdmissionAttempt::STATUS_ARCHIVED) {
                return $attempt;
            }

            $attempt->update([
                'status' => AdmissionAttempt::STATUS_ARCHIVED,
                'archived_at' => now(),
                'version' => $attempt->version + 1,
            ]);

            $this->recordAuditEvent->handle('admission.attempt_archived', [
                'attempt_id' => $attempt->id,
                'reference_code' => $attempt->reference_code,
            ], (string) $actor->id);

            return $attempt->fresh();
        });
    }
}
