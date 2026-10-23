<?php

namespace App\Assessment\Actions;

use App\Assessment\Support\TestKitPreparationReceipt;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\TestKit;
use App\Models\Assessment\TestKitPreparation;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class DiscardTestKitPreparation
{
    public function __construct(
        private readonly CapabilityAuthorizer $authorizer,
        private readonly TestKitPreparationReceipt $receipts,
        private readonly RecordAuditEvent $audit,
    ) {}

    /**
     * @param  array{preparation_id:int,expected_revision:int,idempotency_key:string}  $input
     */
    public function handle(User $actor, int $testKitId, array $input): TestKitPreparation
    {
        $allowed = ['preparation_id', 'expected_revision', 'idempotency_key'];
        if (array_diff($allowed, array_keys($input)) !== [] || array_diff(array_keys($input), $allowed) !== []) {
            throw ValidationException::withMessages(['preparation' => 'Submit the exact draft identity, revision, and idempotency key.']);
        }
        if (! is_string($input['idempotency_key']) || ! Str::isUuid($input['idempotency_key'])) {
            throw ValidationException::withMessages(['idempotency_key' => 'A valid idempotency UUID is required.']);
        }

        return DB::transaction(function () use ($actor, $testKitId, $input): TestKitPreparation {
            $kit = TestKit::query()->lockForUpdate()->findOrFail($testKitId);
            $principal = User::query()->find($actor->getKey());
            if ($principal === null || ! $this->authorizer->decideFresh(
                $principal,
                CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG,
                ['office' => 'guidance', 'institution_id' => (int) $kit->institution_id],
            )->allowed) {
                throw new AuthorizationException('Guidance assessment-catalog authority for the Test Kit institution is required.');
            }

            $preparation = TestKitPreparation::query()
                ->where('test_kit_id', $kit->id)
                ->lockForUpdate()
                ->findOrFail((int) $input['preparation_id']);
            $payload = [
                'test_kit_id' => (int) $kit->id,
                'preparation_id' => (int) $preparation->id,
                'expected_revision' => (int) $input['expected_revision'],
            ];
            $receipt = $this->receipts->claim(
                $principal,
                'test_kit.discard_preparation',
                (string) $preparation->id,
                $payload,
                $input['idempotency_key'],
            );
            if (($result = $this->receipts->result($receipt)) !== null) {
                if ($result['test_kit_id'] !== (int) $kit->id || $result['preparation_id'] !== (int) $preparation->id) {
                    throw new RuntimeException('The discard receipt belongs to another Test Kit preparation.');
                }

                return $this->receipts->preparationSnapshot($result);
            }
            if ($preparation->state !== TestKitPreparation::STATE_PREPARING) {
                throw ValidationException::withMessages(['preparation_id' => 'Only an open preparation can be discarded.']);
            }
            if ((int) $preparation->revision !== (int) $input['expected_revision']) {
                throw ValidationException::withMessages(['expected_revision' => 'This preparation changed. Refresh before discarding it.']);
            }

            $preparation->state = TestKitPreparation::STATE_DISCARDED;
            $preparation->revision = (int) $preparation->revision + 1;
            $preparation->updated_by_user_id = (int) $principal->id;
            $preparation->save();
            $this->receipts->remember($receipt, $preparation);
            $this->audit->handle('assessment.test_kit.preparation_discarded', [
                'test_kit_id' => (int) $kit->id,
                'preparation_id' => (int) $preparation->id,
                'revision' => (int) $preparation->revision,
                'discarded_by_user_id' => (int) $principal->id,
                'discarded_at' => $preparation->updated_at?->toISOString(),
            ], (string) $principal->id);

            return $preparation;
        });
    }
}
