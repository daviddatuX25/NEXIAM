<?php

namespace App\Admission\Actions;

use App\Admission\Support\AttemptRequirementChecklistBoundary;
use App\Admission\Support\CommandIdempotency;
use App\Authorization\CapabilityCatalog;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Admission\AdmissionAttemptRequirementItem;
use App\Models\Admission\AdmissionAttemptRequirementSet;
use App\Models\Foundation\IntakeRequirement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class InitializeAttemptRequirementSet
{
    public function __construct(
        private readonly AttemptRequirementChecklistBoundary $boundary,
        private readonly CommandIdempotency $idempotency,
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {}

    public function handle(
        AdmissionAttempt $attempt,
        User $actor,
        int $expectedVersion,
        string $idempotencyKey,
    ): AdmissionAttemptRequirementSet {
        if (trim($idempotencyKey) === '' || mb_strlen($idempotencyKey) > 128) {
            throw new RuntimeException('A valid idempotency key is required to initialize the checklist.');
        }

        return DB::transaction(function () use ($attempt, $actor, $expectedVersion, $idempotencyKey): AdmissionAttemptRequirementSet {
            $lockedAttempt = $this->boundary->lockAttempt((int) $attempt->getKey());
            $freshActor = $this->boundary->authorizeFresh($actor, $lockedAttempt, CapabilityCatalog::REGISTRAR_DOCUMENTS_VERIFY);
            $operation = $this->idempotency->claim(
                $freshActor,
                'admission.documents.initialize',
                'attempt:'.$lockedAttempt->getKey(),
                [
                    'attempt_id' => (int) $lockedAttempt->getKey(),
                    'expected_version' => $expectedVersion,
                ],
                $idempotencyKey,
            );

            $receiptSetId = $operation->payload['receipt_set_id'] ?? null;
            if ($receiptSetId !== null) {
                return AdmissionAttemptRequirementSet::query()
                    ->whereKey($receiptSetId)
                    ->where('admission_attempt_id', $lockedAttempt->getKey())
                    ->with(['items' => fn ($query) => $query->orderBy('sort_order')->orderBy('id')])
                    ->firstOrFail();
            }

            if ((int) $lockedAttempt->version !== $expectedVersion) {
                throw new RuntimeException('The admission attempt changed. Refresh and review before initializing the checklist.');
            }

            $this->boundary->assertMutable($lockedAttempt);
            $track = $this->boundary->currentTrack($lockedAttempt);
            if ($track === null) {
                throw new RuntimeException('The applicant classification is unknown. The requirement checklist cannot be initialized.');
            }

            $currentSet = AdmissionAttemptRequirementSet::query()
                ->where('admission_attempt_id', $lockedAttempt->getKey())
                ->where('is_current', true)
                ->with(['items' => fn ($query) => $query->orderBy('sort_order')->orderBy('id')])
                ->first();

            if ($currentSet !== null) {
                if ($currentSet->applicant_track !== $track || $currentSet->items->isEmpty()) {
                    throw new RuntimeException('The current requirement set is stale or empty and requires reviewed reconciliation.');
                }

                $operation->forceFill(['payload' => [...$operation->payload, 'receipt_set_id' => $currentSet->getKey()]])->save();

                return $currentSet;
            }

            if (AdmissionAttemptRequirementSet::query()->where('admission_attempt_id', $lockedAttempt->getKey())->exists()) {
                throw new RuntimeException('Historical requirement sets require explicit reconciliation before a new set can be initialized.');
            }

            $requirements = IntakeRequirement::query()
                ->where('institution_id', $lockedAttempt->institution_id)
                ->active()
                ->forTrack($track)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();

            if ($requirements->isEmpty()) {
                throw new RuntimeException('No active intake requirements apply to the resolved applicant classification.');
            }

            $set = AdmissionAttemptRequirementSet::query()->create([
                'admission_attempt_id' => $lockedAttempt->getKey(),
                'applicant_track' => $track,
                'catalog_version' => 1,
                'is_current' => true,
                'initialized_by_user_id' => $freshActor->getKey(),
                'initialized_at' => now(),
            ]);

            foreach ($requirements as $requirement) {
                AdmissionAttemptRequirementItem::query()->create([
                    'requirement_set_id' => $set->getKey(),
                    'intake_requirement_id' => $requirement->getKey(),
                    'code' => $requirement->code,
                    'label' => $requirement->label,
                    'item_kind' => $requirement->item_kind ?? 'document',
                    'is_mandatory' => (bool) $requirement->is_mandatory,
                    'sort_order' => (int) ($requirement->sort_order ?? 100),
                ]);
            }

            $lockedAttempt->forceFill(['version' => ((int) $lockedAttempt->version) + 1])->save();
            $operation->forceFill(['payload' => [...$operation->payload, 'receipt_set_id' => $set->getKey()]])->save();
            $this->recordAuditEvent->handle('admission.requirement_set_initialized', [
                'attempt_id' => (int) $lockedAttempt->getKey(),
                'requirement_set_id' => (int) $set->getKey(),
                'applicant_track' => $track,
                'catalog_version' => 1,
                'requirement_count' => $requirements->count(),
                'attempt_version' => (int) $lockedAttempt->version,
            ], (string) $freshActor->getKey());

            return $set->load(['items' => fn ($query) => $query->orderBy('sort_order')->orderBy('id')]);
        });
    }
}
