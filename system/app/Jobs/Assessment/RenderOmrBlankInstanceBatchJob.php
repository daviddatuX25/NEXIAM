<?php

namespace App\Jobs\Assessment;

use App\Assessment\Omr\Canonical\OmrBlankInstanceService;
use App\Assessment\Omr\Canonical\OmrBlankPrintContract;
use App\Assessment\Omr\Canonical\OmrBlankPrintOrchestrator;
use App\Assessment\Omr\OmrQrMode;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrBlankInstance;
use App\Models\Assessment\OmrQrDisabledIssuance;
use App\Models\Assessment\OmrTemplateAsset;
use App\Models\Printing\PrintJob;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;
use ZipArchive;

final class RenderOmrBlankInstanceBatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 7200;

    public function __construct(public readonly int $printJobId, public readonly int $actorId) {}

    public function handle(
        OmrBlankInstanceService $instances,
        OmrBlankPrintOrchestrator $orchestrator,
        RecordAuditEvent $recordAuditEvent,
    ): void {
        $job = PrintJob::query()->findOrFail($this->printJobId);

        $versionId = (int) ($job->pinned_input_json['template_version_id'] ?? 0);
        $quantity = (int) ($job->pinned_input_json['quantity'] ?? 0);
        $renderCount = (int) ($job->pinned_input_json['render_count'] ?? $quantity);
        $copyPolicy = (string) ($job->pinned_input_json['copy_policy'] ?? OmrBlankInstance::COPY_POLICY_UNIQUE_INSTANCE);
        $chunkSize = (int) ($job->pinned_input_json['chunk_size'] ?? config('omr.canonical.limits.blank_print_chunk', 100));
        $institutionId = array_key_exists('institution_id', $job->pinned_input_json)
            ? ($job->pinned_input_json['institution_id'] === null ? null : (int) $job->pinned_input_json['institution_id'])
            : null;
        $academicCycleId = array_key_exists('academic_cycle_id', $job->pinned_input_json)
            ? ($job->pinned_input_json['academic_cycle_id'] === null ? null : (int) $job->pinned_input_json['academic_cycle_id'])
            : null;
        if (($institutionId === null) !== ($academicCycleId === null)) {
            throw new RuntimeException('omr_scope_unavailable');
        }
        $version = OmrAnswerSheetTemplateVersion::query()->findOrFail($versionId);
        $actor = User::query()->findOrFail($this->actorId);
        $contract = OmrBlankPrintContract::fromVersion($version);
        if ($contract->qrMode !== OmrQrMode::Disabled && ! $this->claimSignedJob($job->id)) {
            return;
        }
        $staticAssets = $version->canonicalAssets()
            ->where('kind', OmrTemplateAsset::KIND_PRINT_PDF)
            ->orderBy('id')
            ->get();
        $staticAsset = $staticAssets->first();
        if ($staticAsset === null) {
            throw new RuntimeException('canonical_print_pdf_missing');
        }
        if ($contract->qrMode === OmrQrMode::Disabled && $staticAssets->count() !== 1) {
            throw new RuntimeException('canonical_print_pdf_ambiguous');
        }
        $contract->assertPrintAsset($staticAsset);
        $staticPdf = Storage::disk($staticAsset->disk)->get($staticAsset->path);
        if (! is_string($staticPdf) || hash('sha256', $staticPdf) !== $staticAsset->sha256) {
            throw new RuntimeException('asset_integrity_failure');
        }
        $contract->assertPinnedInputs((array) $job->pinned_input_json, $staticAsset->sha256);
        if ($contract->qrMode === OmrQrMode::Disabled) {
            $this->renderQrDisabled($job, $version, $actor, $contract, $orchestrator, $recordAuditEvent, $staticPdf);

            return;
        }

        $pageCount = count($contract->compiledPages);
        $manifest = [];
        $stagedSets = [];
        $keyId = (string) config('omr.qr-keyring.current', 'dev-v1');
        $zipPath = tempnam(sys_get_temp_dir(), 'nexiam-blank-print-');
        if ($zipPath === false) {
            throw new RuntimeException('blank_print_zip_unavailable');
        }
        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::OVERWRITE) !== true) {
            @unlink($zipPath);
            throw new RuntimeException('blank_print_zip_unavailable');
        }
        $zipClosed = false;
        $downloadPath = 'omr-canonical/print-jobs/'.$job->id.'/blank-instances.zip';
        $jobDirectory = 'omr-canonical/print-jobs/'.$job->id;

        try {
            for ($index = 0; $index < $renderCount; $index++) {
                $chunkNo = intdiv($index, max(1, $chunkSize)) + 1;
                try {
                    $instanceCode = $instances->newInstanceCode();
                    $displayCode = $instances->displayCodeFor($instanceCode);
                    $overlay = $version->authoring_manifest['overlay'] ?? null;
                    $artifacts = $orchestrator->renderSet(
                        $staticPdf,
                        $instanceCode,
                        $displayCode,
                        $pageCount,
                        $keyId,
                        $overlay,
                    );
                } catch (Throwable $exception) {
                    $manifest[] = [
                        'status' => 'failed',
                        'chunk_no' => $chunkNo,
                        'error' => $exception->getMessage(),
                        'index' => $index,
                    ];
                    $job->forceFill([
                        'progress_done' => $index + 1,
                        'per_item_manifest' => $manifest,
                    ])->save();

                    continue;
                }

                if (! array_is_list($artifacts) || count($artifacts) !== $pageCount) {
                    throw new RuntimeException('print_artifact_page_count_mismatch');
                }

                $pageManifest = [];
                foreach ($artifacts as $artifactIndex => $artifact) {
                    $expectedPageNo = $contract->compiledPages[$artifactIndex]['page_no'];
                    if (! is_array($artifact)
                        || ($artifact['page_no'] ?? null) !== $expectedPageNo
                        || ! is_string($artifact['bytes'] ?? null)
                        || $artifact['bytes'] === ''
                        || ! is_string($artifact['sha256'] ?? null)
                        || ! is_string($artifact['payload'] ?? null)
                        || ! is_string($artifact['payload_hash'] ?? null)
                        || hash('sha256', $artifact['bytes']) !== $artifact['sha256']
                        || hash('sha256', $artifact['payload']) !== $artifact['payload_hash']) {
                        throw new RuntimeException('print_artifact_integrity_failure');
                    }

                    $path = sprintf(
                        'omr-canonical/print-jobs/%d/chunk-%04d/%s-page-%03d.pdf',
                        $job->id,
                        $chunkNo,
                        $instanceCode,
                        $expectedPageNo,
                    );
                    $this->putVerifiedArtifact($path, $artifact['bytes'], $artifact['sha256']);
                    $archiveName = sprintf('chunk-%04d/%s/page-%03d.pdf', $chunkNo, $instanceCode, $expectedPageNo);
                    if (! $zip->addFromString($archiveName, $artifact['bytes'])) {
                        throw new RuntimeException('blank_print_zip_write_failed');
                    }
                    $pageManifest[] = [
                        'page_no' => $expectedPageNo,
                        'path' => $path,
                        'archive_name' => $archiveName,
                        'sha256' => $artifact['sha256'],
                        'qr_payload_hash' => $artifact['payload_hash'],
                    ];
                }

                $manifest[] = [
                    'status' => 'staged',
                    'index' => $index,
                    'chunk_no' => $chunkNo,
                    'instance_code' => $instanceCode,
                    'display_code' => $displayCode,
                    'copy_policy' => $copyPolicy,
                    'intended_copy_count' => $copyPolicy === OmrBlankInstance::COPY_POLICY_REUSABLE_MASTER ? $quantity : 1,
                    'pages' => $pageManifest,
                ];
                $stagedSets[$index] = [
                    'instance_code' => $instanceCode,
                    'display_code' => $displayCode,
                    'page_artifact_hashes' => array_column($artifacts, 'sha256'),
                    'qr_payload_hashes' => array_column($artifacts, 'payload_hash'),
                ];
                $job->forceFill([
                    'progress_done' => $index + 1,
                    'per_item_manifest' => $manifest,
                ])->save();
            }

            if ($stagedSets === []) {
                throw new RuntimeException('blank_print_no_successful_items');
            }

            DB::transaction(function () use (
                $job,
                $version,
                $actor,
                $instances,
                $manifest,
                $stagedSets,
                $pageCount,
                $keyId,
                $quantity,
                $renderCount,
                $copyPolicy,
                $institutionId,
                $academicCycleId,
                $chunkSize,
                $zip,
                &$zipClosed,
                $zipPath,
                $downloadPath,
                $recordAuditEvent,
            ): void {
                $lockedJob = PrintJob::query()->lockForUpdate()->findOrFail($job->id);
                if ($lockedJob->status !== PrintJob::STATUS_RENDERING) {
                    throw new RuntimeException('blank_print_job_stale');
                }

                $outputManifest = [];
                $failedCount = 0;
                $printedCount = 0;
                foreach ($manifest as $item) {
                    if ($item['status'] === 'failed') {
                        $failedCount++;
                        $outputManifest[] = $item;

                        continue;
                    }

                    $index = (int) $item['index'];
                    $staged = $stagedSets[$index];
                    $instance = $instances->createPrintedSet(
                        $version,
                        $actor,
                        $pageCount,
                        $staged['page_artifact_hashes'],
                        $job->id,
                        $keyId,
                        $staged['instance_code'],
                        $staged['display_code'],
                        $copyPolicy,
                        $copyPolicy === OmrBlankInstance::COPY_POLICY_REUSABLE_MASTER ? $quantity : 1,
                        institutionId: $institutionId,
                        academicCycleId: $academicCycleId,
                    );
                    $persistedPayloadHashes = $instance->pages->pluck('qr_payload_hash')->all();
                    if ($persistedPayloadHashes !== $staged['qr_payload_hashes']) {
                        throw new RuntimeException('print_qr_payload_integrity_failure');
                    }

                    unset($item['index']);
                    $item['status'] = 'printed';
                    $item['instance_id'] = $instance->id;
                    $item['instance_code'] = $instance->instance_code;
                    $item['display_code'] = $instance->display_code;
                    $printedCount++;
                    $outputManifest[] = $item;
                }

                $manifestJson = json_encode($outputManifest, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
                if (! $zip->addFromString('manifest.json', $manifestJson)) {
                    throw new RuntimeException('blank_print_zip_write_failed');
                }
                if (! $zip->close()) {
                    throw new RuntimeException('blank_print_zip_write_failed');
                }
                $zipClosed = true;
                $this->verifySignedZipContents($zipPath, $outputManifest, $manifestJson);
                $zipBytes = file_get_contents($zipPath);
                if (! is_string($zipBytes) || $zipBytes === '') {
                    throw new RuntimeException('blank_print_zip_read_failed');
                }

                $downloadHash = hash('sha256', $zipBytes);
                $this->putVerifiedArtifact($downloadPath, $zipBytes, $downloadHash);
                $completedAt = now();
                $lockedJob->forceFill([
                    'status' => PrintJob::STATUS_READY,
                    'file_path' => $downloadPath,
                    'file_checksum' => $downloadHash,
                    'safe_error' => $failedCount > 0 ? 'partial_failure' : null,
                    'completed_at' => $completedAt,
                    'progress_done' => $renderCount,
                    'per_item_manifest' => $outputManifest,
                ])->save();
                $recordAuditEvent->handle('omr.canonical.blank_print_completed', [
                    'print_job_id' => $lockedJob->id,
                    'template_version_id' => $version->id,
                    'printed_count' => $printedCount,
                    'intended_copy_count' => $quantity,
                    'failed_count' => $failedCount,
                    'copy_policy' => $copyPolicy,
                    'chunk_size' => $chunkSize,
                    'download_sha256' => $downloadHash,
                ], (string) $this->actorId);
            }, attempts: 1);
        } catch (Throwable $exception) {
            if (! $zipClosed) {
                $zip->close();
                $zipClosed = true;
            }
            $this->cleanupSignedJobArtifacts($jobDirectory);

            throw $exception;
        } finally {
            if (! $zipClosed) {
                $zip->close();
            }
            @unlink($zipPath);
        }
    }

    private function claimSignedJob(int $printJobId): bool
    {
        return DB::transaction(function () use ($printJobId): bool {
            $job = PrintJob::query()->lockForUpdate()->findOrFail($printJobId);
            if (in_array($job->status, [PrintJob::STATUS_READY, PrintJob::STATUS_RENDERING], true)) {
                return false;
            }
            if ($job->status !== PrintJob::STATUS_QUEUED) {
                throw new RuntimeException('blank_print_job_not_renderable');
            }

            $job->forceFill(['status' => PrintJob::STATUS_RENDERING])->save();

            return true;
        });
    }

    private function putVerifiedArtifact(string $path, string $bytes, string $expectedHash): void
    {
        if (! hash_equals($expectedHash, hash('sha256', $bytes))) {
            throw new RuntimeException('blank_print_artifact_hash_mismatch');
        }

        $disk = Storage::disk('local');
        if (! $disk->put($path, $bytes) || ! $disk->exists($path)) {
            throw new RuntimeException('blank_print_storage_failed');
        }

        $storedBytes = $disk->get($path);
        if (! is_string($storedBytes) || ! hash_equals($expectedHash, hash('sha256', $storedBytes))) {
            throw new RuntimeException('blank_print_storage_integrity_failure');
        }
    }

    /**
     * @param  list<array<string, mixed>>  $manifest
     */
    private function verifySignedZipContents(string $zipPath, array $manifest, string $expectedManifest): void
    {
        $archive = new ZipArchive;
        if ($archive->open($zipPath, ZipArchive::RDONLY | ZipArchive::CHECKCONS) !== true) {
            throw new RuntimeException('blank_print_zip_integrity_failure');
        }

        $expectedFileCount = 1;
        try {
            foreach ($manifest as $item) {
                if (($item['status'] ?? null) !== 'printed') {
                    continue;
                }

                foreach (($item['pages'] ?? []) as $page) {
                    $archiveName = $page['archive_name'] ?? null;
                    $expectedHash = $page['sha256'] ?? null;
                    if (! is_string($archiveName) || ! is_string($expectedHash)) {
                        throw new RuntimeException('blank_print_zip_integrity_failure');
                    }

                    $stream = $archive->getStream($archiveName);
                    if ($stream === false) {
                        throw new RuntimeException('blank_print_zip_integrity_failure');
                    }

                    $context = hash_init('sha256');
                    $bytesRead = hash_update_stream($context, $stream);
                    fclose($stream);
                    if (! is_int($bytesRead) || ! hash_equals($expectedHash, hash_final($context))) {
                        throw new RuntimeException('blank_print_zip_integrity_failure');
                    }

                    $expectedFileCount++;
                }
            }

            $storedManifest = $archive->getFromName('manifest.json');
            if ($archive->numFiles !== $expectedFileCount
                || ! is_string($storedManifest)
                || ! hash_equals($expectedManifest, $storedManifest)) {
                throw new RuntimeException('blank_print_zip_integrity_failure');
            }
        } finally {
            $archive->close();
        }
    }

    private function cleanupSignedJobArtifacts(string $directory): void
    {
        try {
            if (! Storage::disk('local')->deleteDirectory($directory)) {
                report(new RuntimeException('blank_print_storage_cleanup_failed'));
            }
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function renderQrDisabled(
        PrintJob $job,
        OmrAnswerSheetTemplateVersion $version,
        User $actor,
        OmrBlankPrintContract $contract,
        OmrBlankPrintOrchestrator $orchestrator,
        RecordAuditEvent $recordAuditEvent,
        string $staticPdf,
    ): void {
        $pinnedInputs = (array) $job->pinned_input_json;
        $quantity = $pinnedInputs['quantity'] ?? null;
        $renderCount = $pinnedInputs['render_count'] ?? null;
        $copyPolicy = $pinnedInputs['copy_policy'] ?? null;
        $institutionId = $pinnedInputs['institution_id'] ?? null;
        $academicCycleId = $pinnedInputs['academic_cycle_id'] ?? null;
        $issuanceId = $pinnedInputs['qr_disabled_issuance_id'] ?? null;

        if (! is_int($quantity) || $quantity < 1 || $quantity > 10000
            || ! is_int($renderCount)
            || ! in_array($copyPolicy, [
                OmrBlankInstance::COPY_POLICY_UNIQUE_INSTANCE,
                OmrBlankInstance::COPY_POLICY_REUSABLE_MASTER,
            ], true)
            || ! is_int($institutionId) || $institutionId < 1
            || ! is_int($academicCycleId) || $academicCycleId < 1
            || ! is_int($issuanceId) || $issuanceId < 1
            || $job->artifact_type !== PrintJob::ARTIFACT_BLANK_SHEET
            || $job->scope_type !== 'template_version'
            || (int) $job->scope_id !== $version->id) {
            throw new RuntimeException('print_input_mismatch');
        }

        $expectedRenderCount = $copyPolicy === OmrBlankInstance::COPY_POLICY_REUSABLE_MASTER ? 1 : $quantity;
        if ($renderCount !== $expectedRenderCount || $job->progress_total !== $renderCount) {
            throw new RuntimeException('print_input_mismatch');
        }

        $issuance = DB::transaction(function () use (
            $job,
            $version,
            $actor,
            $contract,
            $quantity,
            $copyPolicy,
            $institutionId,
            $academicCycleId,
            $issuanceId,
        ): ?OmrQrDisabledIssuance {
            $lockedJob = PrintJob::query()->lockForUpdate()->findOrFail($job->id);
            $lockedIssuance = OmrQrDisabledIssuance::query()->lockForUpdate()->findOrFail($issuanceId);
            if ((int) $lockedIssuance->template_version_id !== $version->id
                || (int) $lockedIssuance->print_job_id !== $job->id
                || (int) $lockedIssuance->created_by_user_id !== (int) $actor->id
                || (int) $lockedIssuance->institution_id !== $institutionId
                || (int) $lockedIssuance->academic_cycle_id !== $academicCycleId
                || (int) $lockedIssuance->page_count !== count($contract->compiledPages)
                || $lockedIssuance->copy_policy !== $copyPolicy
                || (int) $lockedIssuance->intended_copy_count !== $quantity
                || preg_match('/^QDI-[0-9A-F-]{36}$/', (string) $lockedIssuance->issuance_code) !== 1) {
                throw new RuntimeException('qr_disabled_issuance_mismatch');
            }

            if ($lockedIssuance->status === OmrQrDisabledIssuance::STATUS_PRINTED
                && $lockedJob->status === PrintJob::STATUS_READY) {
                return null;
            }
            if ($lockedIssuance->status === OmrQrDisabledIssuance::STATUS_RENDERING
                && $lockedJob->status === PrintJob::STATUS_RENDERING) {
                return null;
            }
            if ($lockedIssuance->status !== OmrQrDisabledIssuance::STATUS_QUEUED
                || $lockedJob->status !== PrintJob::STATUS_QUEUED) {
                throw new RuntimeException('qr_disabled_issuance_not_printable');
            }

            $lockedIssuance->forceFill(['status' => OmrQrDisabledIssuance::STATUS_RENDERING])->save();
            $lockedJob->forceFill(['status' => PrintJob::STATUS_RENDERING])->save();

            return $lockedIssuance;
        });

        if ($issuance === null) {
            return;
        }

        $chunkSize = max(1, (int) ($pinnedInputs['chunk_size'] ?? config('omr.canonical.limits.blank_print_chunk', 100)));
        $zipPath = tempnam(sys_get_temp_dir(), 'nexiam-qr-disabled-print-');
        if ($zipPath === false) {
            throw new RuntimeException('blank_print_zip_unavailable');
        }

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::OVERWRITE) !== true) {
            @unlink($zipPath);
            throw new RuntimeException('blank_print_zip_unavailable');
        }

        $zipClosed = false;
        $storedPaths = [];
        $downloadPath = 'omr-canonical/print-jobs/'.$job->id.'/qr-disabled-issuance.zip';

        try {
            $copies = [];
            for ($index = 0; $index < $renderCount; $index++) {
                $copyNo = $index + 1;
                $chunkNo = intdiv($index, $chunkSize) + 1;
                $artifacts = $orchestrator->renderQrDisabledSet($staticPdf, $contract->compiledPages);
                if (count($artifacts) !== count($contract->compiledPages)) {
                    throw new RuntimeException('blank_static_page_count_mismatch');
                }

                $pages = [];
                foreach ($artifacts as $artifactIndex => $artifact) {
                    $pageNo = $contract->compiledPages[$artifactIndex]['page_no'];
                    if ($artifact['page_no'] !== $pageNo
                        || ! is_string($artifact['bytes'])
                        || ! is_string($artifact['sha256'])
                        || hash('sha256', $artifact['bytes']) !== $artifact['sha256']) {
                        throw new RuntimeException('print_artifact_hash_mismatch');
                    }

                    $path = sprintf(
                        'omr-canonical/print-jobs/%d/qr-disabled/%s/copy-%05d-page-%03d.pdf',
                        $job->id,
                        $issuance->issuance_code,
                        $copyNo,
                        $pageNo,
                    );
                    if (! Storage::disk('local')->put($path, $artifact['bytes'])) {
                        throw new RuntimeException('blank_print_storage_failed');
                    }
                    $storedPaths[] = $path;
                    $archiveName = sprintf('chunk-%04d/copy-%05d/page-%03d.pdf', $chunkNo, $copyNo, $pageNo);
                    if (! $zip->addFromString($archiveName, $artifact['bytes'])) {
                        throw new RuntimeException('blank_print_zip_write_failed');
                    }
                    $pages[] = [
                        'page_no' => $pageNo,
                        'path' => $path,
                        'archive_name' => $archiveName,
                        'sha256' => $artifact['sha256'],
                    ];
                }

                $copies[] = [
                    'copy_no' => $copyNo,
                    'status' => 'printed',
                    'pages' => $pages,
                ];
                $job->forceFill([
                    'progress_done' => $copyNo,
                    'per_item_manifest' => $copies,
                ])->save();
            }

            $manifestBody = [
                'schema' => 'nexiam.omr.qr-disabled-print.v1',
                'issuance_id' => $issuance->id,
                'issuance_code' => $issuance->issuance_code,
                'template_version_id' => $version->id,
                'registration_mode' => $contract->registrationMode->value,
                'qr_mode' => $contract->qrMode->value,
                'compiled_pages' => $contract->compiledPages,
                'page_count' => count($contract->compiledPages),
                'native_bundle_hash' => $contract->nativeBundleHash,
                'artifact_hash' => $contract->artifactHash,
                'print_pdf_sha256' => (string) ($pinnedInputs['print_pdf_sha256'] ?? ''),
                'copy_policy' => $copyPolicy,
                'intended_copy_count' => $quantity,
                'rendered_copy_count' => $renderCount,
                'copies' => $copies,
            ];
            $artifactManifestHash = hash(
                'sha256',
                json_encode($manifestBody, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
            );
            $manifest = [...$manifestBody, 'artifact_manifest_hash' => $artifactManifestHash];
            if (! $zip->addFromString(
                'manifest.json',
                json_encode($manifest, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
            )) {
                throw new RuntimeException('blank_print_zip_write_failed');
            }
            if (! $zip->close()) {
                throw new RuntimeException('blank_print_zip_write_failed');
            }
            $zipClosed = true;

            $zipBytes = file_get_contents($zipPath);
            if (! is_string($zipBytes) || ! Storage::disk('local')->put($downloadPath, $zipBytes)) {
                throw new RuntimeException('blank_print_storage_failed');
            }
            $downloadHash = hash('sha256', $zipBytes);
            $printedAt = now();

            DB::transaction(function () use (
                $job,
                $issuance,
                $manifestBody,
                $manifest,
                $artifactManifestHash,
                $downloadPath,
                $downloadHash,
                $printedAt,
                $recordAuditEvent,
                $contract,
                $version,
                $quantity,
                $renderCount,
                $copyPolicy,
                $chunkSize,
            ): void {
                $lockedJob = PrintJob::query()->lockForUpdate()->findOrFail($job->id);
                $lockedIssuance = OmrQrDisabledIssuance::query()->lockForUpdate()->findOrFail($issuance->id);
                if ($lockedJob->status !== PrintJob::STATUS_RENDERING
                    || $lockedIssuance->status !== OmrQrDisabledIssuance::STATUS_RENDERING) {
                    throw new RuntimeException('qr_disabled_issuance_stale');
                }

                $lockedIssuance->forceFill([
                    'status' => OmrQrDisabledIssuance::STATUS_PRINTED,
                    'artifact_manifest_hash' => $artifactManifestHash,
                    'printed_at' => $printedAt,
                    'failed_at' => null,
                    'failure_code' => null,
                ])->save();
                $lockedJob->forceFill([
                    'status' => PrintJob::STATUS_READY,
                    'file_path' => $downloadPath,
                    'file_checksum' => $downloadHash,
                    'safe_error' => null,
                    'completed_at' => $printedAt,
                    'per_item_manifest' => $manifestBody['copies'],
                    'output_manifest' => $manifest,
                ])->save();
                $recordAuditEvent->handle('omr.canonical.qr_disabled_issuance_printed', [
                    'print_job_id' => $lockedJob->id,
                    'issuance_id' => $lockedIssuance->id,
                    'issuance_code' => $lockedIssuance->issuance_code,
                    'template_version_id' => $version->id,
                    'registration_mode' => $contract->registrationMode->value,
                    'qr_mode' => $contract->qrMode->value,
                    'compiled_pages' => $contract->compiledPages,
                    'native_bundle_hash' => $contract->nativeBundleHash,
                    'artifact_hash' => $contract->artifactHash,
                    'print_pdf_sha256' => $manifestBody['print_pdf_sha256'],
                    'artifact_manifest_hash' => $artifactManifestHash,
                    'copy_policy' => $copyPolicy,
                    'intended_copy_count' => $quantity,
                    'rendered_copy_count' => $renderCount,
                    'chunk_size' => $chunkSize,
                    'download_sha256' => $downloadHash,
                ], (string) $this->actorId);
            });
        } catch (Throwable $exception) {
            foreach ($storedPaths as $path) {
                Storage::disk('local')->delete($path);
            }
            Storage::disk('local')->delete($downloadPath);

            throw $exception;
        } finally {
            if (! $zipClosed) {
                $zip->close();
            }
            @unlink($zipPath);
        }
    }

    public function failed(?Throwable $exception): void
    {
        $job = PrintJob::query()->find($this->printJobId);
        if ($job === null) {
            return;
        }
        if (($job->pinned_input_json['qr_mode'] ?? null) === OmrQrMode::Disabled->value) {
            DB::transaction(function () use ($exception): void {
                $lockedJob = PrintJob::query()->lockForUpdate()->find($this->printJobId);
                if ($lockedJob === null || $lockedJob->status === PrintJob::STATUS_READY) {
                    return;
                }

                $issuance = OmrQrDisabledIssuance::query()
                    ->where('print_job_id', $lockedJob->id)
                    ->lockForUpdate()
                    ->first();
                $issuanceFailed = $issuance !== null
                    && in_array($issuance->status, [
                        OmrQrDisabledIssuance::STATUS_QUEUED,
                        OmrQrDisabledIssuance::STATUS_RENDERING,
                    ], true);
                if ($issuanceFailed) {
                    $issuance->forceFill([
                        'status' => OmrQrDisabledIssuance::STATUS_FAILED,
                        'failed_at' => now(),
                        'failure_code' => 'blank_print_failed',
                    ])->save();
                }

                if ($lockedJob->status !== PrintJob::STATUS_FAILED) {
                    $lockedJob->forceFill([
                        'status' => PrintJob::STATUS_FAILED,
                        'safe_error' => 'blank_print_failed',
                    ])->save();
                }

                if ($issuanceFailed || $lockedJob->wasChanged()) {
                    app(RecordAuditEvent::class)->handle('omr.canonical.qr_disabled_issuance_failed', [
                        'print_job_id' => $lockedJob->id,
                        'issuance_id' => $issuance?->id,
                        'template_version_id' => $issuance?->template_version_id,
                        'failure_code' => 'blank_print_failed',
                        'error' => $exception?->getMessage() ?? 'unknown',
                    ], (string) $this->actorId);
                }
            });

            return;
        }

        $statusChanged = DB::transaction(function (): ?bool {
            $lockedJob = PrintJob::query()->lockForUpdate()->find($this->printJobId);
            if ($lockedJob === null || $lockedJob->status === PrintJob::STATUS_READY) {
                return null;
            }

            $changed = $lockedJob->status !== PrintJob::STATUS_FAILED;
            if ($changed) {
                $lockedJob->forceFill([
                    'status' => PrintJob::STATUS_FAILED,
                    'safe_error' => 'blank_print_failed',
                ])->save();
            }

            return $changed;
        });
        if ($statusChanged === null) {
            return;
        }

        $this->cleanupSignedJobArtifacts('omr-canonical/print-jobs/'.$this->printJobId);
        if ($statusChanged) {
            app(RecordAuditEvent::class)->handle('omr.canonical.blank_print_failed', [
                'print_job_id' => $this->printJobId,
                'error' => $exception?->getMessage() ?? 'unknown',
            ], (string) $this->actorId);
        }
    }
}
