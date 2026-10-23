<?php

namespace App\Assessment\Actions;

use App\Assessment\Support\TestKitCommandReceipt;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\TestKit;
use App\Models\Assessment\TestKitVersion;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RetireTestKitVersion
{
    public function __construct(
        private readonly CapabilityAuthorizer $authorizer,
        private readonly TestKitCommandReceipt $receipts,
        private readonly RecordAuditEvent $audit,
    ) {}

    /**
     * @param  int|array{expected_revision?:int|string|null,idempotency_key?:string|null,reason?:string|null}  $expectedRevision
     */
    public function handle(
        User $actor,
        int $testKitId,
        int $versionId,
        int|array $expectedRevision,
        ?string $idempotencyKey = null,
        ?string $reason = null,
    ): TestKitVersion {
        if (is_array($expectedRevision)) {
            $input = $expectedRevision;
            $rawRevision = $input['expected_revision'] ?? null;
            $rawKey = $input['idempotency_key'] ?? $idempotencyKey;
            $rawReason = $input['reason'] ?? $reason;
        } else {
            $rawRevision = $expectedRevision;
            $rawKey = $idempotencyKey;
            $rawReason = $reason;
        }

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

        $retireReason = is_string($rawReason) && trim($rawReason) !== '' ? trim($rawReason) : null;

        return DB::transaction(function () use ($actor, $testKitId, $versionId, $revision, $key, $retireReason): TestKitVersion {
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
                'expected_revision' => $revision,
            ];
            if ($retireReason !== null) {
                $payload['reason'] = $retireReason;
            }

            $receipt = $this->receipts->claim(
                $principal,
                'test_kit.retire',
                (string) $kit->id,
                $payload,
                $key,
            );
            if (($result = $this->receipts->result($receipt)) !== null) {
                return TestKitVersion::query()->where('test_kit_id', $kit->id)->findOrFail($result['test_kit_version_id']);
            }

            $previousStatus = $version->status;
            if (! in_array($previousStatus, [TestKitVersion::STATUS_CANDIDATE, TestKitVersion::STATUS_ACTIVE], true)
                || $version->revoked_at !== null) {
                throw ValidationException::withMessages(['version' => 'Only a candidate or active, non-revoked Test Kit version can be retired.']);
            }
            if ((int) $version->revision !== $revision) {
                throw ValidationException::withMessages(['expected_revision' => 'The Test Kit version changed. Reload it before retirement.']);
            }
            $version->forceFill([
                'status' => TestKitVersion::STATUS_RETIRED,
                'retired_at' => now(),
                'retired_by_user_id' => (int) $principal->id,
                'revision' => (int) $version->revision + 1,
            ])->save();
            $version->refresh();
            $this->receipts->remember($receipt, $version);
            $auditPayload = [
                'test_kit_id' => (int) $kit->id,
                'test_kit_version_id' => (int) $version->id,
                'previous_status' => $previousStatus,
                'revision' => (int) $version->revision,
            ];
            if ($retireReason !== null) {
                $auditPayload['reason'] = $retireReason;
            }
            $this->audit->handle('assessment.test_kit.version_retired', $auditPayload, (string) $principal->id);

            return $version;
        });
    }
}
