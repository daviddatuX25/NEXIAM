<?php

namespace App\Assessment\Actions;

use App\Assessment\Support\TestInstrumentAccess;
use App\Assessment\Support\TestInstrumentCommandReceipt;
use App\Assessment\Support\TestInstrumentProfileValidator;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\TestInstrument;
use App\Models\Assessment\TestInstrumentVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ApproveTestInstrumentVersion
{
    public function __construct(
        private readonly TestInstrumentAccess $access,
        private readonly TestInstrumentProfileValidator $validator,
        private readonly TestInstrumentCommandReceipt $receipts,
        private readonly RecordAuditEvent $audit,
    ) {}

    public function handle(
        User $actor,
        int $instrumentId,
        int $versionId,
        int $expectedRevision,
        string $requestId,
        bool $sourceReviewAttestation,
        bool $isDemo = false,
    ): TestInstrumentVersion {
        return DB::transaction(function () use ($actor, $instrumentId, $versionId, $expectedRevision, $requestId, $sourceReviewAttestation, $isDemo): TestInstrumentVersion {
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

            $receipt = $this->receipts->claim(
                $freshActor,
                'assessment.test-instrument.approve-version',
                'test-instrument:'.$instrument->id.':version:'.$version->id,
                [
                    'institution_id' => $institutionId,
                    'instrument_id' => $instrument->id,
                    'version_id' => $version->id,
                    'expected_revision' => $expectedRevision,
                    'source_review_attestation' => $sourceReviewAttestation,
                    'is_demo' => $isDemo,
                ],
                $requestId,
            );

            $replay = $this->receipts->result($receipt);
            if ($replay !== null) {
                return $this->versionForInstitution($institutionId, $replay['instrument_id'], $replay['version_id']);
            }
            if ($version->status !== TestInstrumentVersion::STATUS_DRAFT) {
                throw ValidationException::withMessages(['version' => 'Only a draft version can be approved. Create a successor draft for changes.']);
            }
            if ((int) $version->lock_version !== $expectedRevision) {
                throw ValidationException::withMessages(['expected_revision' => 'This draft changed. Reload it and review the current version before approval.']);
            }

            if ($instrument->is_synthetic === true && ! $isDemo) {
                throw ValidationException::withMessages([
                    'version' => 'Synthetic software fixtures cannot be approved for institution use.',
                ]);
            }

            if ($instrument->is_synthetic === true) {
                $syntheticMetadata = implode(' ', array_filter([
                    $version->display_name,
                    $version->publisher_name,
                    $version->form_code,
                    $version->edition,
                    $version->language,
                    $version->source_reference,
                    $version->permitted_use_reference,
                ]));

                if (preg_match('/\b(?:synthetic|demo)\b/i', $syntheticMetadata) !== 1) {
                    throw ValidationException::withMessages([
                        'version' => 'Synthetic instrument versions must identify themselves as synthetic or demo.',
                    ]);
                }
            }

            if (! $sourceReviewAttestation) {
                throw ValidationException::withMessages(['source_review_attestation' => 'Confirm that the official source and permitted-use evidence were reviewed.']);
            }

            $normalized = $this->validator->normalizeVersion($version);
            $this->validator->assertMetricInputContractsApprovable($normalized, $normalized['profile']);
            if ($instrument->is_synthetic !== true) {
                $this->validator->assertApprovable($normalized, $normalized['profile']);
            }
            $contentDigest = $this->validator->digest($normalized);

            $previous = $instrument->currentApprovedVersion()
                ->lockForUpdate()
                ->first();
            if ($previous !== null) {
                if ($previous->status !== TestInstrumentVersion::STATUS_APPROVED) {
                    throw ValidationException::withMessages(['version' => 'The current approved-version pointer is inconsistent. Refresh and contact the authorized administrator.']);
                }

                $previous->status = TestInstrumentVersion::STATUS_RETIRED;
                $previous->retired_by_user_id = $freshActor->id;
                $previous->retired_at = now();
                $previous->save();
            }

            $version->source_reviewed_by_user_id = $freshActor->id;
            $version->source_reviewed_at = now();
            $version->approved_by_user_id = $freshActor->id;
            $version->approved_at = now();
            $version->status = TestInstrumentVersion::STATUS_APPROVED;
            $version->lock_version++;
            $version->content_sha256 = $contentDigest;
            $version->save();

            $instrument->current_approved_version_id = $version->id;
            $instrument->save();

            $version = $version->fresh(['instrument']) ?? $version;
            $this->receipts->remember($receipt, $version);
            $this->audit->handle('assessment.test_instrument_version_approved', [
                'instrument_id' => (int) $instrument->id,
                'version_id' => (int) $version->id,
                'version_number' => (int) $version->version_number,
                'content_sha256' => $contentDigest,
            ], (string) $freshActor->id);

            return $version;
        });
    }

    public function approveDemoVersion(
        User $actor,
        int $instrumentId,
        int $versionId,
        int $expectedRevision,
        string $requestId,
    ): TestInstrumentVersion {
        return $this->handle(
            $actor,
            $instrumentId,
            $versionId,
            $expectedRevision,
            $requestId,
            true,
            true,
        );
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
