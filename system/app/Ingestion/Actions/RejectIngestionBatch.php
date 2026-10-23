<?php

namespace App\Ingestion\Actions;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Ingestion\ImportBatch;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class RejectIngestionBatch
{
    public function __construct(private readonly RecordAuditEvent $recordAuditEvent) {}

    public function handle(
        ImportBatch $batch,
        string $reason,
        User $actor,
        CapabilityAuthorizer $authorizer,
    ): ImportBatch {
        if (! $authorizer->allows($actor, CapabilityCatalog::REGISTRAR_APPLICATIONS_IMPORT)) {
            throw new AuthorizationException('Registrar applications import capability required to reject a batch.');
        }

        $reason = trim($reason);
        if ($reason === '') {
            throw new \InvalidArgumentException('Rejection reason is required.');
        }

        return DB::transaction(function () use ($batch, $reason, $actor): ImportBatch {
            $lockedBatch = ImportBatch::query()->lockForUpdate()->findOrFail($batch->id);
            if (! in_array($lockedBatch->import_type, [ImportBatch::TYPE_ALL_IN_ONE, ImportBatch::TYPE_MULTI_SESSION], true)) {
                throw new \DomainException("Import batch {$lockedBatch->id} is not a supported Registrar-consent batch.");
            }
            if ($lockedBatch->status === ImportBatch::STATUS_REJECTED) {
                $decision = ($lockedBatch->topology_meta ?? [])['rejection'] ?? [];
                if ((int) ($decision['rejected_by_user_id'] ?? 0) === (int) $actor->id
                    && ($decision['reason'] ?? null) === $reason) {
                    return $lockedBatch->fresh('rows');
                }
                throw new ConflictHttpException('This import batch was already rejected with a different decision.');
            }
            if ($lockedBatch->status !== ImportBatch::STATUS_PENDING_APPROVAL) {
                throw new \DomainException("Import batch {$lockedBatch->id} is not pending Registrar consent.");
            }

            $rejectedAt = now();
            $metadata = $lockedBatch->topology_meta ?? [];
            $metadata['rejection'] = [
                'reason' => $reason,
                'rejected_by_user_id' => $actor->id,
                'rejected_at' => $rejectedAt->toIso8601String(),
            ];
            $lockedBatch->update([
                'status' => ImportBatch::STATUS_REJECTED,
                'topology_meta' => $metadata,
            ]);

            $this->recordAuditEvent->handle('import.batch_rejected', [
                'batch_id' => $lockedBatch->id,
                'rejected_by_user_id' => $actor->id,
                'rejected_at' => $rejectedAt->toIso8601String(),
                'reason' => $reason,
            ], (string) $actor->id);

            return $lockedBatch->fresh('rows');
        });
    }
}
