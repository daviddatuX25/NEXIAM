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

final class RevokeTestKitVersion
{
    public function __construct(
        private readonly CapabilityAuthorizer $authorizer,
        private readonly TestKitCommandReceipt $receipts,
        private readonly RecordAuditEvent $audit,
    ) {}

    /**
     * @param  int|array{expected_revision?:int|string|null,reason?:string|null,idempotency_key?:string|null}  $expectedRevision
     */
    public function handle(
        User $actor,
        int $testKitId,
        int $versionId,
        int|array $expectedRevision,
        ?string $reason = null,
        ?string $idempotencyKey = null,
    ): TestKitVersion {
        if (is_array($expectedRevision)) {
            $input = $expectedRevision;
            $rawRevision = $input['expected_revision'] ?? null;
            $rawReason = $input['reason'] ?? $reason;
            $rawKey = $input['idempotency_key'] ?? $idempotencyKey;
        } else {
            $rawRevision = $expectedRevision;
            $rawReason = $reason;
            $rawKey = $idempotencyKey;
        }

        if ($rawRevision === null
            || (! is_int($rawRevision) && (! is_string($rawRevision) || preg_match('/^(0|[1-9][0-9]*)$/D', $rawRevision) !== 1))) {
            throw ValidationException::withMessages([
                'expected_revision' => 'A valid expected revision is required.',
            ]);
        }
        $revision = (int) $rawRevision;

        $revokeReason = trim((string) $rawReason);
        if ($revokeReason === '') {
            throw ValidationException::withMessages(['reason' => 'A reason is required to revoke a Test Kit version.']);
        }

        if ($rawKey === null || trim((string) $rawKey) === '') {
            throw ValidationException::withMessages([
                'idempotency_key' => 'An idempotency key is required.',
            ]);
        }
        $key = trim((string) $rawKey);

        return DB::transaction(function () use ($actor, $testKitId, $versionId, $revision, $revokeReason, $key): TestKitVersion {
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
            $receipt = $this->receipts->claim(
                $principal,
                'test_kit.revoke',
                (string) $kit->id,
                [
                    'test_kit_id' => (int) $kit->id,
                    'test_kit_version_id' => (int) $version->id,
                    'expected_revision' => $revision,
                    'reason' => $revokeReason,
                ],
                $key,
            );
            if (($result = $this->receipts->result($receipt)) !== null) {
                return TestKitVersion::query()->where('test_kit_id', $kit->id)->findOrFail($result['test_kit_version_id']);
            }

            if (! in_array($version->status, [TestKitVersion::STATUS_CANDIDATE, TestKitVersion::STATUS_ACTIVE, TestKitVersion::STATUS_RETIRED], true)) {
                throw ValidationException::withMessages(['version' => 'This Test Kit version cannot be revoked from its current state.']);
            }
            if ($version->revoked_at !== null) {
                throw ValidationException::withMessages(['version' => 'This Test Kit version has already been revoked.']);
            }
            if ((int) $version->revision !== $revision) {
                throw ValidationException::withMessages(['expected_revision' => 'The Test Kit version changed. Reload it before revocation.']);
            }

            $version->forceFill([
                'revoked_at' => now(),
                'revoked_by_user_id' => (int) $principal->id,
                'revocation_reason' => $revokeReason,
                'revision' => (int) $version->revision + 1,
            ])->save();
            $version->refresh();
            $this->receipts->remember($receipt, $version);
            $this->audit->handle('assessment.test_kit.version_revoked', [
                'test_kit_id' => (int) $kit->id,
                'test_kit_version_id' => (int) $version->id,
                'reason' => $revokeReason,
                'revision' => (int) $version->revision,
            ], (string) $principal->id);

            return $version;
        });
    }
}
