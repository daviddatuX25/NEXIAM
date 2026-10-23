<?php

declare(strict_types=1);

namespace App\Assessment\Actions;

use App\Assessment\Support\TestKitCommandReceipt;
use App\Assessment\Support\TestKitCurrentAcceptanceStatus;
use App\Assessment\Support\TestKitEligibility;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\TestKit;
use App\Models\Assessment\TestKitAcceptanceReceipt;
use App\Models\Assessment\TestKitVersion;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SelectTestKitAcceptanceReceipt
{
    public function __construct(
        private readonly CapabilityAuthorizer $authorizer,
        private readonly TestKitCommandReceipt $receipts,
        private readonly RecordAuditEvent $audit,
        private readonly TestKitCurrentAcceptanceStatus $acceptanceStatus,
        private readonly ?TestKitEligibility $eligibility = null,
    ) {}

    /**
     * @param  array{acceptance_receipt_id?:int|string|null,expected_revision?:int|string|null,idempotency_key?:string|null}|int  $receiptIdOrInput
     */
    public function handle(
        User $actor,
        int $testKitId,
        int $versionId,
        int|array $receiptIdOrInput,
        int|string|null $expectedRevision = null,
        ?string $idempotencyKey = null,
    ): TestKitVersion {
        if (is_array($receiptIdOrInput)) {
            $input = $receiptIdOrInput;
            $rawReceiptId = $input['acceptance_receipt_id'] ?? null;
            $rawRevision = $input['expected_revision'] ?? $expectedRevision;
            $rawKey = $input['idempotency_key'] ?? $idempotencyKey;
        } else {
            $rawReceiptId = $receiptIdOrInput;
            $rawRevision = $expectedRevision;
            $rawKey = $idempotencyKey;
        }

        if ($rawReceiptId === null || ! is_numeric($rawReceiptId) || (int) $rawReceiptId < 1) {
            throw ValidationException::withMessages([
                'acceptance_receipt' => 'An acceptance receipt ID is required.',
            ]);
        }
        $receiptId = (int) $rawReceiptId;

        if ($rawRevision === null
            || (! is_int($rawRevision) && (! is_string($rawRevision) || preg_match('/^(0|[1-9][0-9]*)$/D', $rawRevision) !== 1))) {
            throw ValidationException::withMessages([
                'expected_revision' => 'A valid expected revision is required.',
            ]);
        }
        $revision = (int) $rawRevision;

        if ($rawKey === null || trim((string) $rawKey) === '') {
            throw ValidationException::withMessages([
                'idempotency_key' => 'An idempotency key is required.',
            ]);
        }
        $key = trim((string) $rawKey);

        return DB::transaction(function () use ($actor, $testKitId, $versionId, $receiptId, $revision, $key): TestKitVersion {
            $kit = TestKit::query()->lockForUpdate()->findOrFail($testKitId);
            $principal = User::query()->find($actor->getKey());
            if ($principal === null || ! $this->authorizer->decideFresh(
                $principal,
                CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG,
                ['office' => 'guidance', 'institution_id' => (int) $kit->institution_id],
            )->allowed) {
                throw new AuthorizationException('Guidance assessment-catalog authority for the Test Kit institution is required.');
            }

            $version = TestKitVersion::query()
                ->where('test_kit_id', $kit->id)
                ->whereKey($versionId)
                ->lockForUpdate()
                ->firstOrFail();

            $payload = [
                'test_kit_id' => (int) $kit->id,
                'test_kit_version_id' => (int) $version->id,
                'acceptance_receipt_id' => $receiptId,
                'expected_revision' => $revision,
            ];

            $operation = $this->receipts->claim(
                $principal,
                'test_kit.select_receipt',
                (string) $kit->id,
                $payload,
                $key,
            );
            if (($result = $this->receipts->result($operation)) !== null) {
                return TestKitVersion::query()
                    ->where('test_kit_id', $kit->id)
                    ->findOrFail($result['test_kit_version_id']);
            }

            if (! ($this->eligibility ?? app(TestKitEligibility::class))->allowsReacceptance($version)) {
                throw ValidationException::withMessages([
                    'version' => 'Only non-revoked Candidate, Active, or operationally pinned Retired versions may select acceptance evidence.',
                ]);
            }

            if ((int) $version->revision !== $revision) {
                throw ValidationException::withMessages([
                    'expected_revision' => 'The Test Kit version changed. Reload it before selecting an acceptance receipt.',
                ]);
            }

            $receipt = TestKitAcceptanceReceipt::query()
                ->where('test_kit_version_id', $version->id)
                ->whereKey($receiptId)
                ->lockForUpdate()
                ->first();

            if ($receipt === null) {
                throw ValidationException::withMessages([
                    'acceptance_receipt' => 'The acceptance receipt does not belong to this Test Kit version.',
                ]);
            }

            $status = $this->acceptanceStatus->forReceipt($version, $receipt, true);
            if (! $status['current']) {
                throw ValidationException::withMessages([
                    'acceptance_receipt' => 'The acceptance receipt is not complete, current, or bound to this exact Kit and protocol.',
                ]);
            }
            $version->forceFill([
                'selected_acceptance_receipt_id' => (int) $receipt->id,
                'revision' => (int) $version->revision + 1,
            ])->save();
            $version->refresh();

            $this->receipts->remember($operation, $version);
            $this->audit->handle('assessment.test_kit.acceptance_receipt_selected', [
                'test_kit_id' => (int) $kit->id,
                'test_kit_version_id' => (int) $version->id,
                'selected_acceptance_receipt_id' => (int) $receipt->id,
                'revision' => (int) $version->revision,
            ], (string) $principal->id);

            return $version;
        });
    }
}
