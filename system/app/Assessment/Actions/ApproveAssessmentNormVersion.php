<?php

namespace App\Assessment\Actions;

use App\Assessment\Support\AssessmentNormAccess;
use App\Assessment\Support\AssessmentNormCommandReceipt;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\AssessmentNormPackage;
use App\Models\Assessment\AssessmentNormPackageVersion;
use App\Models\Assessment\AssessmentNormTable;
use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\Exception\NumberFormatException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ApproveAssessmentNormVersion
{
    public function __construct(
        private readonly AssessmentNormAccess $access,
        private readonly AssessmentNormCommandReceipt $receipts,
        private readonly RecordAuditEvent $audit,
    ) {}

    public function handle(
        User $actor,
        int|AssessmentNormPackage $package,
        int|AssessmentNormPackageVersion $version,
        ?int $expectedRevision,
        ?string $requestId,
        mixed $attestationOrPayload = null,
    ): AssessmentNormPackageVersion {
        $packageId = $package instanceof AssessmentNormPackage ? (int) $package->id : (int) $package;
        $versionId = $version instanceof AssessmentNormPackageVersion ? (int) $version->id : (int) $version;
        $requestId = ! empty($requestId) ? (string) $requestId : (string) Str::uuid();
        $sourceReviewAttestation = $attestationOrPayload;

        return DB::transaction(function () use (
            $actor,
            $packageId,
            $versionId,
            $expectedRevision,
            $requestId,
            $sourceReviewAttestation,
        ): AssessmentNormPackageVersion {
            $institutionId = $this->access->institutionId($actor);
            $freshActor = User::query()->findOrFail($actor->getKey());

            $normPackage = AssessmentNormPackage::query()
                ->where('institution_id', $institutionId)
                ->whereKey($packageId)
                ->lockForUpdate()
                ->firstOrFail();

            $normVersion = AssessmentNormPackageVersion::query()
                ->where('norm_package_id', $normPackage->id)
                ->whereKey($versionId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($expectedRevision === null) {
                throw ValidationException::withMessages([
                    'expected_revision' => 'Expected revision is required for norm version approval.',
                ]);
            }

            if ($sourceReviewAttestation !== true) {
                throw ValidationException::withMessages([
                    'source_review_attestation' => 'Explicit source review attestation is required to approve a norm package version.',
                ]);
            }

            $receipt = $this->receipts->claim(
                $freshActor,
                'assessment.norm-package.approve-version',
                "norm-package:{$normPackage->id}:version:{$normVersion->id}",
                [
                    'institution_id' => $institutionId,
                    'package_id' => $normPackage->id,
                    'version_id' => $normVersion->id,
                    'expected_revision' => $expectedRevision,
                    'source_review_attestation' => $sourceReviewAttestation,
                ],
                $requestId,
            );

            $replay = $this->receipts->result($receipt);
            if ($replay !== null) {
                return $this->versionForInstitution($institutionId, $replay['package_id'], $replay['version_id']);
            }

            if ($normVersion->status !== AssessmentNormPackageVersion::STATUS_DRAFT) {
                throw ValidationException::withMessages([
                    'version' => 'Only a draft version can be approved. Create a successor draft for changes.',
                ]);
            }

            if ((int) $normVersion->lock_version !== $expectedRevision) {
                throw ValidationException::withMessages([
                    'expected_revision' => 'This draft changed. Reload it and review the current version before approval.',
                ]);
            }

            if (empty(trim((string) $normVersion->display_name))) {
                throw ValidationException::withMessages([
                    'display_name' => 'A display name is required before approval.',
                ]);
            }

            if ($normPackage->is_synthetic === true) {
                $syntheticMetadata = implode(' ', array_filter([
                    $normVersion->display_name,
                    $normVersion->publisher_name,
                    $normVersion->form_code,
                    $normVersion->edition,
                    $normVersion->language,
                    $normVersion->applicable_population,
                    $normVersion->source_reference,
                    $normVersion->permitted_use_reference,
                ]));

                if (preg_match('/\b(?:synthetic|demo)\b/i', $syntheticMetadata) !== 1) {
                    throw ValidationException::withMessages([
                        'display_name' => 'Synthetic norm package versions must identify themselves as synthetic or demo.',
                    ]);
                }
            } else {
                if (empty(trim((string) $normVersion->source_reference))) {
                    throw ValidationException::withMessages([
                        'source_reference' => 'Source citation reference is required before approval.',
                    ]);
                }

                if (empty(trim((string) $normVersion->permitted_use_reference))) {
                    throw ValidationException::withMessages([
                        'permitted_use_reference' => 'Permitted use reference is required before approval.',
                    ]);
                }
            }

            $tables = $normVersion->tables()->get();
            if ($tables->isEmpty()) {
                throw ValidationException::withMessages([
                    'tables' => 'A norm package version must define at least one conversion table before approval.',
                ]);
            }

            foreach ($tables as $table) {
                $intervals = $table->intervals_json;
                if (! is_array($intervals) || $intervals === []) {
                    throw ValidationException::withMessages([
                        'tables' => "Table for metric {$table->metric_code} ({$table->output_scale}) has no interval rows.",
                    ]);
                }

                if (! in_array($table->output_scale, [AssessmentNormTable::SCALE_PERCENTILE_RANK, AssessmentNormTable::SCALE_STANINE], true)) {
                    throw ValidationException::withMessages([
                        'tables' => "Unsupported output scale '{$table->output_scale}' in table for {$table->metric_code}.",
                    ]);
                }

                $sortedIntervals = $intervals;
                usort($sortedIntervals, fn (array $a, array $b): int => $this->decimalBound($a['min_score'] ?? null)
                    ->compareTo($this->decimalBound($b['min_score'] ?? null)));
                $gridScale = 0;
                foreach ($sortedIntervals as $row) {
                    $gridScale = max(
                        $gridScale,
                        $this->decimalBound($row['min_score'] ?? null)->getScale(),
                        $this->decimalBound($row['max_score'] ?? null)->getScale(),
                    );
                }
                $gridStep = BigDecimal::of($gridScale === 0 ? '1' : '0.'.str_repeat('0', $gridScale - 1).'1');
                $previousMax = null;
                foreach ($sortedIntervals as $row) {
                    $min = $this->decimalBound($row['min_score'] ?? null);
                    $max = $this->decimalBound($row['max_score'] ?? null);
                    $out = trim((string) ($row['output_value'] ?? ''));
                    if ($min->compareTo($max) > 0) {
                        throw ValidationException::withMessages([
                            'tables' => "Invalid decimal range in table for {$table->metric_code} ({$table->output_scale}).",
                        ]);
                    }
                    if ($out === '') {
                        throw ValidationException::withMessages([
                            'tables' => "Missing output value in table for {$table->metric_code} ({$table->output_scale}).",
                        ]);
                    }
                    if ($previousMax !== null) {
                        if ($min->compareTo($previousMax) <= 0) {
                            throw ValidationException::withMessages([
                                'tables' => "Conversion table ranges must not overlap in {$table->metric_code} ({$table->output_scale}).",
                            ]);
                        }
                        if ($min->compareTo($previousMax->plus($gridStep)) !== 0) {
                            throw ValidationException::withMessages([
                                'tables' => "Conversion table has an uncovered decimal-grid value between intervals in {$table->metric_code} ({$table->output_scale}).",
                            ]);
                        }
                    }
                    if ($table->output_scale === AssessmentNormTable::SCALE_STANINE) {
                        $stanine = is_numeric($out) ? (int) $out : 0;
                        if ($stanine < 1 || $stanine > 9 || (string) $stanine !== $out) {
                            throw ValidationException::withMessages([
                                'tables' => "Stanine output '{$out}' in table {$table->metric_code} is outside valid 1..9 scale.",
                            ]);
                        }
                    }
                    $previousMax = $max;
                }
            }

            $contentDigest = $this->calculateDigest($normVersion, $tables->toArray());

            // Retire previous approved version if present
            $previous = $normPackage->currentApprovedVersion()
                ->lockForUpdate()
                ->first();

            if ($previous !== null) {
                if ($previous->status === AssessmentNormPackageVersion::STATUS_APPROVED) {
                    $previous->status = AssessmentNormPackageVersion::STATUS_RETIRED;
                    $previous->retired_by_user_id = $freshActor->id;
                    $previous->retired_at = now();
                    $previous->save();
                }
            }

            $normVersion->source_reviewed_by_user_id = $freshActor->id;
            $normVersion->source_reviewed_at = now();
            $normVersion->approved_by_user_id = $freshActor->id;
            $normVersion->approved_at = now();
            $normVersion->status = AssessmentNormPackageVersion::STATUS_APPROVED;
            $normVersion->lock_version++;
            $normVersion->content_sha256 = $contentDigest;
            $normVersion->save();

            $normPackage->current_approved_version_id = $normVersion->id;
            $normPackage->save();

            $version = $normVersion->fresh(['package', 'tables']) ?? $normVersion;
            $this->receipts->remember($receipt, $version);
            $this->audit->handle('assessment.norm_package_version_approved', [
                'package_id' => (int) $normPackage->id,
                'version_id' => (int) $version->id,
                'version_number' => (int) $version->version_number,
                'content_sha256' => $contentDigest,
            ], (string) $freshActor->id);

            return $version;
        });
    }

    /**
     * @param  list<array<string, mixed>>  $tables
     */
    private function calculateDigest(AssessmentNormPackageVersion $version, array $tables): string
    {
        $payload = [
            'display_name' => $version->display_name,
            'publisher_name' => $version->publisher_name,
            'form_code' => $version->form_code,
            'edition' => $version->edition,
            'language' => $version->language,
            'applicable_population' => $version->applicable_population,
            'source_reference' => $version->source_reference,
            'permitted_use_reference' => $version->permitted_use_reference,
            'tables' => $tables,
        ];

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }

    private function decimalBound(mixed $value): BigDecimal
    {
        $text = is_string($value) || is_int($value) ? (string) $value : '';
        if (! preg_match('/^-?(?:0|[1-9][0-9]{0,11})(?:\\.[0-9]{1,6})?$/D', $text)) {
            throw ValidationException::withMessages([
                'tables' => 'Norm conversion boundaries must be finite decimal values on a grid of at most six places.',
            ]);
        }
        try {
            return BigDecimal::of($text);
        } catch (NumberFormatException) {
            throw ValidationException::withMessages([
                'tables' => 'Norm conversion boundary is not a supported decimal value.',
            ]);
        }
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
