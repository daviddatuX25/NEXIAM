<?php

namespace App\Assessment\Actions;

use App\Assessment\Support\TestInstrumentAccess;
use App\Assessment\Support\TestInstrumentCommandReceipt;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\TestInstrument;
use App\Models\Assessment\TestInstrumentVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RetireTestInstrumentVersion
{
    public function __construct(
        private readonly TestInstrumentAccess $access,
        private readonly TestInstrumentCommandReceipt $receipts,
        private readonly RecordAuditEvent $audit,
    ) {}

    public function handle(
        User $actor,
        int $instrumentId,
        int $versionId,
        int $expectedRevision,
        string $requestId,
        string $reason,
    ): TestInstrumentVersion {
        return DB::transaction(function () use ($actor, $instrumentId, $versionId, $expectedRevision, $requestId, $reason): TestInstrumentVersion {
            $institutionId = $this->access->institutionId($actor);
            $freshActor = User::query()->findOrFail($actor->getKey());
            $instrument = TestInstrument::query()
                ->where('institution_id', $institutionId)
                ->whereKey($instrumentId)
                ->lockForUpdate()
                ->firstOrFail();
            $version = TestInstrumentVersion::query()
                ->where('test_instrument_id', $instrument->id)
                ->whereKey($versionId)
                ->lockForUpdate()
                ->firstOrFail();

            $reason = trim($reason);
            $receipt = $this->receipts->claim(
                $freshActor,
                'assessment.test-instrument.retire-version',
                'test-instrument:'.$instrument->id.':version:'.$version->id,
                [
                    'institution_id' => $institutionId,
                    'instrument_id' => $instrument->id,
                    'version_id' => $version->id,
                    'expected_revision' => $expectedRevision,
                    'reason_sha256' => hash('sha256', $reason),
                ],
                $requestId,
            );

            $replay = $this->receipts->result($receipt);
            if ($replay !== null) {
                return $this->versionForInstitution($institutionId, $replay['instrument_id'], $replay['version_id']);
            }

            if ($version->status !== TestInstrumentVersion::STATUS_APPROVED
                || (int) $instrument->current_approved_version_id !== (int) $version->id) {
                throw ValidationException::withMessages(['version' => 'Only the current approved version can be retired.']);
            }
            if ((int) $version->lock_version !== $expectedRevision) {
                throw ValidationException::withMessages(['expected_revision' => 'The approved version changed. Reload it before retirement.']);
            }
            if ($reason === '') {
                throw ValidationException::withMessages(['reason' => 'A reason is required to retire an approved version.']);
            }

            $version->status = TestInstrumentVersion::STATUS_RETIRED;
            $version->retired_by_user_id = $freshActor->id;
            $version->retired_at = now();
            $version->save();
            $instrument->current_approved_version_id = null;
            $instrument->save();

            $version = $version->fresh(['instrument']) ?? $version;
            $this->receipts->remember($receipt, $version);
            $this->audit->handle('assessment.test_instrument_version_retired', [
                'instrument_id' => (int) $instrument->id,
                'version_id' => (int) $version->id,
                'version_number' => (int) $version->version_number,
                'reason_sha256' => hash('sha256', $reason),
            ], (string) $freshActor->id);

            return $version;
        });
    }

    private function versionForInstitution(int $institutionId, int $instrumentId, int $versionId): TestInstrumentVersion
    {
        return TestInstrumentVersion::query()
            ->whereKey($versionId)
            ->whereHas('instrument', static fn ($query) => $query
                ->whereKey($instrumentId)
                ->where('institution_id', $institutionId))
            ->firstOrFail();
    }
}
