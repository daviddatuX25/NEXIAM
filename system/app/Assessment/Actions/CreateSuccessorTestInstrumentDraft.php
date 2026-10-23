<?php

namespace App\Assessment\Actions;

use App\Assessment\Support\TestInstrumentAccess;
use App\Assessment\Support\TestInstrumentCommandReceipt;
use App\Assessment\Support\TestInstrumentDraftWriter;
use App\Assessment\Support\TestInstrumentProfileValidator;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\TestInstrument;
use App\Models\Assessment\TestInstrumentVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CreateSuccessorTestInstrumentDraft
{
    public function __construct(
        private readonly TestInstrumentAccess $access,
        private readonly TestInstrumentProfileValidator $validator,
        private readonly TestInstrumentDraftWriter $draftWriter,
        private readonly TestInstrumentCommandReceipt $receipts,
        private readonly RecordAuditEvent $audit,
    ) {}

    public function handle(
        User $actor,
        int $instrumentId,
        int $approvedVersionId,
        int $expectedRevision,
        string $requestId,
    ): TestInstrumentVersion {
        return DB::transaction(function () use ($actor, $instrumentId, $approvedVersionId, $expectedRevision, $requestId): TestInstrumentVersion {
            $institutionId = $this->access->institutionId($actor);
            $freshActor = User::query()->findOrFail($actor->getKey());
            $instrument = TestInstrument::query()
                ->where('institution_id', $institutionId)
                ->whereKey($instrumentId)
                ->lockForUpdate()
                ->firstOrFail();
            $source = TestInstrumentVersion::query()
                ->where('test_instrument_id', $instrument->id)
                ->whereKey($approvedVersionId)
                ->lockForUpdate()
                ->firstOrFail();

            $receipt = $this->receipts->claim(
                $freshActor,
                'assessment.test-instrument.create-successor-draft',
                'test-instrument:'.$instrument->id.':version:'.$source->id,
                [
                    'institution_id' => $institutionId,
                    'instrument_id' => $instrument->id,
                    'source_version_id' => $source->id,
                    'expected_revision' => $expectedRevision,
                ],
                $requestId,
            );

            $replay = $this->receipts->result($receipt);
            if ($replay !== null) {
                return $this->versionForInstitution($institutionId, $replay['instrument_id'], $replay['version_id']);
            }

            if ($source->status !== TestInstrumentVersion::STATUS_APPROVED
                || (int) $instrument->current_approved_version_id !== (int) $source->id) {
                throw ValidationException::withMessages(['version' => 'Only the current approved version can be copied into a successor draft.']);
            }
            if ((int) $source->lock_version !== $expectedRevision) {
                throw ValidationException::withMessages(['expected_revision' => 'The approved version changed. Reload it before creating a successor draft.']);
            }
            if (TestInstrumentVersion::query()
                ->where('test_instrument_id', $instrument->id)
                ->where('status', TestInstrumentVersion::STATUS_DRAFT)
                ->exists()) {
                throw ValidationException::withMessages(['version' => 'A draft already exists for this instrument. Open and edit that draft.']);
            }

            $normalized = $this->validator->normalizeVersion($source);
            $this->validator->assertApprovable($normalized, $normalized['profile']);
            $sourceDigest = $this->validator->digest($normalized);
            if (! hash_equals((string) $source->content_sha256, $sourceDigest)) {
                throw ValidationException::withMessages(['version' => 'The approved profile failed its stored content-integrity check.']);
            }

            $nextVersionNumber = ((int) TestInstrumentVersion::query()
                ->where('test_instrument_id', $instrument->id)
                ->max('version_number')) + 1;
            $draft = new TestInstrumentVersion;
            $draft->test_instrument_id = $instrument->id;
            $draft->version_number = $nextVersionNumber;
            $draft->status = TestInstrumentVersion::STATUS_DRAFT;
            $draft->predecessor_version_id = $source->id;
            $draft->lock_version = 1;
            $draft->created_by_user_id = $freshActor->id;
            $this->draftWriter->save($draft, $normalized);

            $draft = $draft->fresh(['instrument']) ?? $draft;
            $this->receipts->remember($receipt, $draft);
            $this->audit->handle('assessment.test_instrument_successor_draft_created', [
                'instrument_id' => (int) $instrument->id,
                'source_version_id' => (int) $source->id,
                'draft_version_id' => (int) $draft->id,
                'draft_version_number' => (int) $draft->version_number,
            ], (string) $freshActor->id);

            return $draft;
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
