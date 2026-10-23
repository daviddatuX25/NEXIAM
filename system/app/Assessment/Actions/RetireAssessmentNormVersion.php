<?php

namespace App\Assessment\Actions;

use App\Assessment\Support\AssessmentNormAccess;
use App\Assessment\Support\AssessmentNormCommandReceipt;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\AssessmentNormPackage;
use App\Models\Assessment\AssessmentNormPackageVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RetireAssessmentNormVersion
{
    public function __construct(
        private readonly AssessmentNormAccess $access,
        private readonly AssessmentNormCommandReceipt $receipts,
        private readonly RecordAuditEvent $audit,
    ) {}

    public function handle(
        User $actor,
        int $packageId,
        int $versionId,
        int $expectedRevision,
        string $requestId,
        string $reason,
    ): AssessmentNormPackageVersion {
        return DB::transaction(function () use ($actor, $packageId, $versionId, $expectedRevision, $requestId, $reason): AssessmentNormPackageVersion {
            $institutionId = $this->access->institutionId($actor);
            $freshActor = User::query()->findOrFail($actor->getKey());
            $package = AssessmentNormPackage::query()
                ->where('institution_id', $institutionId)
                ->whereKey($packageId)
                ->lockForUpdate()
                ->firstOrFail();
            $version = AssessmentNormPackageVersion::query()
                ->where('norm_package_id', $package->id)
                ->whereKey($versionId)
                ->lockForUpdate()
                ->firstOrFail();

            $reason = trim($reason);
            $receipt = $this->receipts->claim(
                $freshActor,
                'assessment.norm-package.retire-version',
                "norm-package:{$package->id}:version:{$version->id}",
                [
                    'institution_id' => $institutionId,
                    'package_id' => (int) $package->id,
                    'version_id' => (int) $version->id,
                    'expected_revision' => $expectedRevision,
                    'reason_sha256' => hash('sha256', $reason),
                ],
                $requestId,
            );

            $replay = $this->receipts->result($receipt);
            if ($replay !== null) {
                return $this->versionForInstitution($institutionId, $replay['package_id'], $replay['version_id']);
            }

            if ($version->status !== AssessmentNormPackageVersion::STATUS_APPROVED
                || (int) $package->current_approved_version_id !== (int) $version->id) {
                throw ValidationException::withMessages([
                    'version' => 'Only the current approved version can be retired.',
                ]);
            }

            if ((int) $version->lock_version !== $expectedRevision) {
                throw ValidationException::withMessages([
                    'expected_revision' => 'The approved version changed. Reload it before retirement.',
                ]);
            }
            if ($reason === '') {
                throw ValidationException::withMessages([
                    'reason' => 'A reason is required to retire an approved version.',
                ]);
            }

            $version->status = AssessmentNormPackageVersion::STATUS_RETIRED;
            $version->retired_by_user_id = $freshActor->id;
            $version->retired_at = now();
            $version->lock_version++;
            $version->save();

            $package->current_approved_version_id = null;
            $package->save();

            $version = $version->fresh(['package', 'tables']) ?? $version;
            $this->receipts->remember($receipt, $version);
            $this->audit->handle('assessment.norm_package_version_retired', [
                'package_id' => (int) $package->id,
                'version_id' => (int) $version->id,
                'version_number' => (int) $version->version_number,
                'reason_sha256' => hash('sha256', $reason),
            ], (string) $freshActor->id);

            return $version;
        });
    }

    private function versionForInstitution(int $institutionId, int $packageId, int $versionId): AssessmentNormPackageVersion
    {
        return AssessmentNormPackageVersion::query()
            ->whereKey($versionId)
            ->whereHas('package', static fn ($query) => $query
                ->whereKey($packageId)
                ->where('institution_id', $institutionId))
            ->firstOrFail();
    }
}
