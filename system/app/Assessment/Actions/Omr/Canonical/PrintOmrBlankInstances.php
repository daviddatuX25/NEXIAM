<?php

namespace App\Assessment\Actions\Omr\Canonical;

use App\Admission\Support\CommandIdempotency;
use App\Assessment\Omr\Canonical\OmrBlankPrintContract;
use App\Assessment\Omr\Canonical\OmrSourcePolicy;
use App\Assessment\Omr\OmrQrMode;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Foundation\Actions\RecordAuditEvent;
use App\Jobs\Assessment\RenderOmrBlankInstanceBatchJob;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrBlankInstance;
use App\Models\Assessment\OmrQrDisabledIssuance;
use App\Models\Assessment\OmrTemplateAsset;
use App\Models\Foundation\AcademicCycle;
use App\Models\Foundation\Operation;
use App\Models\Printing\PrintJob;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

use function config;

final class PrintOmrBlankInstances
{
    public function __construct(
        private readonly CommandIdempotency $idempotency,
        private readonly CapabilityAuthorizer $authorizer,
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {}

    public function handle(
        OmrAnswerSheetTemplateVersion $version,
        User $actor,
        int $quantity,
        string $idempotencyKey,
        string $copyPolicy = OmrBlankInstance::COPY_POLICY_UNIQUE_INSTANCE,
    ): Operation {
        if ($quantity < 1 || $quantity > 10000) {
            throw new \RuntimeException('quantity_invalid');
        }
        if (! in_array($copyPolicy, [
            OmrBlankInstance::COPY_POLICY_UNIQUE_INSTANCE,
            OmrBlankInstance::COPY_POLICY_REUSABLE_MASTER,
        ], true)) {
            throw new \RuntimeException('copy_policy_invalid');
        }

        $scope = $this->issuerScope($actor);

        return DB::transaction(function () use ($version, $actor, $quantity, $idempotencyKey, $copyPolicy, $scope): Operation {
            $lockedVersion = OmrAnswerSheetTemplateVersion::query()
                ->lockForUpdate()
                ->findOrFail($version->id);
            if ($lockedVersion->status !== OmrAnswerSheetTemplateVersion::STATUS_COMPILED
                && $lockedVersion->status !== OmrAnswerSheetTemplateVersion::STATUS_ACTIVE) {
                throw new \RuntimeException('template_not_active');
            }
            OmrSourcePolicy::assertSystemDesignedVersion($lockedVersion);

            $contract = OmrBlankPrintContract::fromVersion($lockedVersion);
            $printAssets = $lockedVersion->canonicalAssets()
                ->where('kind', OmrTemplateAsset::KIND_PRINT_PDF)
                ->orderBy('id')
                ->get();
            $printAsset = $printAssets->first();
            if ($printAsset === null) {
                throw new \RuntimeException('canonical_print_pdf_missing');
            }
            if ($contract->qrMode === OmrQrMode::Disabled && $printAssets->count() !== 1) {
                throw new \RuntimeException('canonical_print_pdf_ambiguous');
            }
            $contract->assertPrintAsset($printAsset);

            $renderCount = $copyPolicy === OmrBlankInstance::COPY_POLICY_REUSABLE_MASTER ? 1 : $quantity;
            $pinnedInputs = [
                ...$contract->pinnedInputs($printAsset->sha256),
                'quantity' => $quantity,
                'render_count' => $renderCount,
                'copy_policy' => $copyPolicy,
                'chunk_size' => (int) config('omr.canonical.limits.blank_print_chunk', 100),
                ...$scope,
            ];

            $operation = $this->idempotency->claim($actor, 'omr.canonical.blank_print', (string) $lockedVersion->id, [
                ...$pinnedInputs,
            ], $idempotencyKey);
            if (! $operation->wasRecentlyCreated) {
                return $operation;
            }

            $job = PrintJob::query()->create([
                'artifact_type' => PrintJob::ARTIFACT_BLANK_SHEET,
                'scope_type' => 'template_version',
                'scope_id' => $lockedVersion->id,
                'status' => PrintJob::STATUS_QUEUED,
                'requested_by_user_id' => $actor->id,
                'progress_total' => $renderCount,
                'progress_done' => 0,
                'pinned_input_json' => $pinnedInputs,
            ]);

            $issuance = null;
            if ($contract->qrMode === OmrQrMode::Disabled) {
                $issuance = OmrQrDisabledIssuance::query()->create([
                    'template_version_id' => $lockedVersion->id,
                    'institution_id' => $scope['institution_id'],
                    'academic_cycle_id' => $scope['academic_cycle_id'],
                    'print_job_id' => $job->id,
                    'issuance_code' => 'QDI-'.Str::upper((string) Str::uuid()),
                    'page_count' => count($contract->compiledPages),
                    'copy_policy' => $copyPolicy,
                    'intended_copy_count' => $quantity,
                    'status' => OmrQrDisabledIssuance::STATUS_QUEUED,
                    'created_by_user_id' => $actor->id,
                ]);
                $pinnedInputs['qr_disabled_issuance_id'] = $issuance->id;
                $job->forceFill(['pinned_input_json' => $pinnedInputs])->save();
            }
            $operation->forceFill([
                'payload' => [
                    ...($operation->payload ?? []),
                    'print_job_id' => $job->id,
                ],
            ])->save();

            RenderOmrBlankInstanceBatchJob::dispatch($job->id, (int) $actor->id)->afterCommit();
            $this->recordAuditEvent->handle(
                $contract->qrMode === OmrQrMode::Disabled
                    ? 'omr.canonical.qr_disabled_issuance_queued'
                    : 'omr.canonical.blank_print_queued',
                [
                    'print_job_id' => $job->id,
                    'template_version_id' => $lockedVersion->id,
                    'registration_mode' => $contract->registrationMode->value,
                    'qr_mode' => $contract->qrMode->value,
                    'compiled_pages' => $contract->compiledPages,
                    'native_bundle_hash' => $contract->nativeBundleHash,
                    'artifact_hash' => $contract->artifactHash,
                    'print_pdf_sha256' => $printAsset->sha256,
                    'issuance_id' => $issuance?->id,
                    'issuance_code' => $issuance?->issuance_code,
                    'quantity' => $quantity,
                    'render_count' => $renderCount,
                    'copy_policy' => $copyPolicy,
                    ...$scope,
                ],
                (string) $actor->id,
            );

            return $operation;
        });
    }

    /**
     * @return array{institution_id: int, academic_cycle_id: int}
     */
    private function issuerScope(User $actor): array
    {
        $scope = $actor->actorScope();
        if ($scope->office !== 'guidance'
            || $scope->institutionId === null
            || trim((string) $scope->activeCycleCode) === '') {
            throw new AuthorizationException('A complete guidance institution and academic-cycle scope is required.');
        }

        $cycle = AcademicCycle::query()
            ->where('institution_id', $scope->institutionId)
            ->where('code', trim((string) $scope->activeCycleCode))
            ->first();
        if ($cycle === null) {
            throw new AuthorizationException('The active guidance academic cycle is unavailable.');
        }

        $decision = $this->authorizer->decide($actor, CapabilityCatalog::GUIDANCE_SCORING_OMR, [
            'office' => 'guidance',
            'institution_id' => (int) $cycle->institution_id,
            'cycle' => (string) $cycle->code,
        ]);
        if (! $decision->allowed) {
            throw new AuthorizationException('The OMR print capability is unavailable for this scope.');
        }

        return [
            'institution_id' => (int) $cycle->institution_id,
            'academic_cycle_id' => (int) $cycle->id,
        ];
    }
}
