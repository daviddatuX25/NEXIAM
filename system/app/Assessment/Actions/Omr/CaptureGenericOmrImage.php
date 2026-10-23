<?php

namespace App\Assessment\Actions\Omr;

use App\Admission\Support\CommandIdempotency;
use App\Assessment\Omr\Canonical\OmrQrPayload;
use App\Assessment\Omr\Canonical\OmrQrPayloadException;
use App\Assessment\Omr\Canonical\OmrQrReader;
use App\Assessment\Omr\Diagnostics\CompiledVersionDiagnosticTarget;
use App\Assessment\Omr\OmrCaptureAnalysisPort;
use App\Assessment\Omr\OmrCaptureAnalysisRequest;
use App\Assessment\Omr\OmrCaptureInput;
use App\Assessment\Omr\OmrCapturePreflight;
use App\Assessment\Omr\OmrPendingCaptureAccess;
use App\Assessment\Omr\OmrScannerContextOutcome;
use App\Assessment\Omr\OmrScannerSessionResolver;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrBlankInstance;
use App\Models\Assessment\OmrBlankInstancePage;
use App\Models\Assessment\OmrPendingCapture;
use App\Models\Foundation\Operation;
use App\Models\User;
use App\Support\SensitiveAssetStore;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

use function array_filter;
use function array_values;
use function count;
use function is_array;
use function is_string;
use function min;
use function preg_match;
use function trim;

final class CaptureGenericOmrImage
{
    public function __construct(
        private readonly CapabilityAuthorizer $authorizer,
        private readonly CommandIdempotency $idempotency,
        private readonly OmrCapturePreflight $preflight,
        private readonly OmrQrReader $qrReader,
        private readonly OmrQrPayload $qrPayload,
        private readonly OmrCaptureAnalysisPort $analysis,
        private readonly OmrScannerSessionResolver $sessionResolver,
        private readonly OmrPendingCaptureAccess $pendingAccess,
        private readonly SensitiveAssetStore $assetStore,
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {}

    /**
     * Store one immutable image and perform the template-bound diagnostic once.
     * No roster binding or score mutation happens in this action.
     *
     * @throws RuntimeException when the request is invalid or its idempotency
     *                          key is already processing another capture
     */
    public function handle(
        User $actor,
        string $imageBytes,
        string $mime,
        string $clientCaptureId,
        string $idempotencyKey,
    ): OmrPendingCapture {
        $this->assertCapability($actor);
        $scope = $this->pendingAccess->actorScope($actor);
        $clientCaptureId = trim($clientCaptureId);
        $idempotencyKey = trim($idempotencyKey);
        if ($clientCaptureId === '' || $idempotencyKey === '') {
            throw new RuntimeException('omr_scanner_idempotency_required');
        }

        $input = new OmrCaptureInput(
            bytes: $imageBytes,
            mime: strtolower(trim($mime)),
            pageNo: 1,
            pageCount: 1,
        );
        $preflight = $this->preflight->validate($input);
        if (! $preflight->succeeded()) {
            throw new RuntimeException(($preflight->code ?? 'capture_preflight_failed').': '.($preflight->detail ?? 'The capture could not be validated.'));
        }
        if ($input->isPdf()) {
            throw new RuntimeException('generic_scanner_image_required: Upload a single image of the answer sheet.');
        }

        $operation = $this->idempotency->claim(
            $actor,
            'grading.omr.generic_capture',
            $clientCaptureId,
            [
                'client_capture_id' => $clientCaptureId,
                'input_hash' => $input->sourceHash(),
                'mime' => $input->mime,
            ],
            $idempotencyKey,
        );

        $existingId = is_array($operation->payload['result'] ?? null)
            ? (int) ($operation->payload['result']['pending_capture_id'] ?? 0)
            : 0;
        if (! $operation->wasRecentlyCreated && $existingId > 0) {
            $existing = OmrPendingCapture::query()->findOrFail($existingId);
            $this->pendingAccess->assertPending($actor, $existing);

            return $existing;
        }
        $operation = $this->resumeOperation($operation);

        $existing = OmrPendingCapture::query()
            ->where('client_capture_id', $clientCaptureId)
            ->first();
        if ($existing !== null) {
            $this->pendingAccess->assertPending($actor, $existing);
            if ((string) $existing->input_hash !== $input->sourceHash()
                || (string) $existing->original_mime !== $input->mime) {
                $this->failOperation($operation, 'client_capture_id_reused');
                throw new RuntimeException('client_capture_id_reused');
            }
            $this->completeOperation($operation, $existing->id);

            return $existing;
        }

        $storedPaths = [];
        $pendingId = null;
        try {
            $capturePath = $this->assetStore->store(
                $imageBytes,
                'omr/pending/'.Str::uuid()->toString().'/captured.bin',
            );
            $storedPaths[] = $capturePath;

            $qr = $this->resolveQr($imageBytes, $input->mime);
            $templateVersion = $qr['template_version'];
            $blankInstance = $qr['blank_instance'];
            $blankPage = $qr['blank_page'];
            $pageCount = $templateVersion?->pageCount() ?? 1;

            $pending = DB::transaction(function () use (
                $actor,
                $scope,
                $clientCaptureId,
                $capturePath,
                $imageBytes,
                $input,
                $qr,
                $templateVersion,
                $blankInstance,
                $blankPage,
                $pageCount,
            ): OmrPendingCapture {
                return OmrPendingCapture::query()->create([
                    'client_capture_id' => $clientCaptureId,
                    'status' => OmrPendingCapture::STATUS_ANALYZING,
                    'capture_mode' => 'auto',
                    'page_no' => 1,
                    'page_count' => $pageCount,
                    'qr_state' => $qr['state'],
                    'qr_payload' => $qr['payload'],
                    'qr_payload_hash' => $qr['payload_hash'],
                    'institution_id' => $scope['institution_id'],
                    'academic_cycle_id' => $scope['academic_cycle_id'],
                    'template_version_id' => $templateVersion?->id,
                    'blank_instance_id' => $blankInstance?->id,
                    'blank_instance_page_id' => $blankPage?->id,
                    'asset_path' => $capturePath,
                    'asset_hmac' => $this->assetStore->hmac($imageBytes),
                    'asset_hmac_key_id' => (string) config('omr.keyring.active_key_id'),
                    'asset_key_id' => (string) config('omr.keyring.active_key_id'),
                    'asset_format_version' => 'v1',
                    'asset_state' => OmrPendingCapture::ASSET_LIVE,
                    'original_mime' => $input->mime,
                    'original_bytes' => $input->byteCount,
                    'input_hash' => $input->sourceHash(),
                    'uploaded_by_user_id' => $actor->id,
                    'version' => 0,
                    'captured_at' => now(),
                    'expires_at' => now()->addHours(24),
                ]);
            });
            $pendingId = (int) $pending->id;

            $this->recordAuditEvent->handle('grading.omr_pending_capture_received', [
                'pending_capture_id' => $pending->id,
                'client_capture_id' => $clientCaptureId,
                'input_hash' => $input->sourceHash(),
                'mime' => $input->mime,
                'width' => $preflight->width,
                'height' => $preflight->height,
                'qr_state' => $qr['state'],
                'template_version_id' => $templateVersion?->id,
            ], (string) $actor->id);

            if ($qr['code'] !== null || $templateVersion === null || $pageCount !== 1) {
                $result = $this->finishWithoutAnalysis($pending, $actor, $qr, $pageCount, 0);
            } else {
                $result = $this->analyzeAndResolve($pending, $actor, $input, $templateVersion, $storedPaths, 0);
            }
            $this->completeOperation($operation, $result->id);

            return $result;
        } catch (Throwable $exception) {
            if ($pendingId !== null) {
                $this->persistCaptureFailure($pendingId, $actor, $exception);
            }
            $pending = $pendingId === null ? null : OmrPendingCapture::query()->find($pendingId);
            $this->cleanupStoredPaths($storedPaths, $pending);
            $this->failOperation($operation, $exception->getMessage());

            throw $exception;
        }
    }

    /**
     * Re-run analysis against the same stored bytes after an operator has
     * repaired a QR/template prerequisite. The original capture is never
     * uploaded again or replaced.
     */
    public function retry(
        OmrPendingCapture $pending,
        User $actor,
        int $expectedVersion,
        string $idempotencyKey,
    ): OmrPendingCapture {
        $this->assertCapability($actor);
        $idempotencyKey = trim($idempotencyKey);
        if ($expectedVersion < 0 || $idempotencyKey === '') {
            throw new RuntimeException('omr_pending_capture_version_required');
        }

        $this->pendingAccess->assertPending($actor, $pending);
        $operation = $this->idempotency->claim(
            $actor,
            'grading.omr.retry_capture',
            (string) $pending->id,
            [
                'pending_capture_id' => (int) $pending->id,
                'expected_version' => $expectedVersion,
            ],
            $idempotencyKey,
        );
        $existingId = is_array($operation->payload['result'] ?? null)
            ? (int) ($operation->payload['result']['pending_capture_id'] ?? 0)
            : 0;
        if (! $operation->wasRecentlyCreated && $existingId > 0) {
            $existing = OmrPendingCapture::query()->findOrFail($existingId);
            $this->pendingAccess->assertPending($actor, $existing);

            return $existing;
        }
        $operation = $this->resumeOperation($operation);
        $storedPaths = [];
        $locked = null;

        try {
            $locked = DB::transaction(function () use ($pending, $actor, $expectedVersion): OmrPendingCapture {
                $locked = OmrPendingCapture::query()->lockForUpdate()->findOrFail($pending->id);
                $this->pendingAccess->assertPending($actor, $locked);
                if ((int) $locked->version !== $expectedVersion) {
                    throw new RuntimeException('omr_pending_capture_stale_version');
                }
                if ($locked->expires_at !== null && $locked->expires_at->isPast()) {
                    throw new RuntimeException('omr_pending_capture_expired');
                }
                if (! in_array($locked->status, [
                    OmrPendingCapture::STATUS_NEEDS_RECOVERY,
                    OmrPendingCapture::STATUS_RESOLUTION_REQUIRED,
                    OmrPendingCapture::STATUS_ANALYZING,
                ], true)) {
                    throw new RuntimeException('omr_pending_capture_not_retryable');
                }
                $locked->forceFill([
                    'status' => OmrPendingCapture::STATUS_ANALYZING,
                    'analysis_code' => null,
                    'analysis_detail' => null,
                    'version' => (int) $locked->version + 1,
                ])->save();

                return $locked->fresh(['templateVersion.template']);
            });

            $bytes = null;
            $qr = null;
            try {
                $bytes = $this->assetStore->read((string) $locked->asset_path, (string) $locked->asset_hmac);
            } catch (Throwable) {
                throw new RuntimeException('omr_pending_capture_asset_missing');
            }
            $input = new OmrCaptureInput(
                bytes: $bytes,
                mime: (string) $locked->original_mime,
                pageNo: 1,
                pageCount: 1,
                expectedPageCount: 1,
                originalHash: (string) $locked->input_hash,
            );
            $preflight = $this->preflight->validate($input);
            if (! $preflight->succeeded()) {
                throw new RuntimeException(($preflight->code ?? 'capture_preflight_failed').': '.($preflight->detail ?? 'The stored capture failed validation.'));
            }

            if ($this->shouldRefreshQr($locked)) {
                $qr = $this->resolveQr($bytes, $input->mime);
                $locked = $this->persistQrResolution($locked, $actor, $qr);
            }

            $templateVersion = $locked->templateVersion;
            $pageCount = $templateVersion?->pageCount() ?? max(1, (int) $locked->page_count);
            if ($qr !== null && ($qr['code'] !== null || $templateVersion === null || $pageCount !== 1)) {
                $result = $this->finishWithoutAnalysis($locked, $actor, $qr, $pageCount, (int) $locked->version);
            } elseif (! $templateVersion instanceof OmrAnswerSheetTemplateVersion) {
                throw new RuntimeException('omr_pending_capture_template_missing');
            } elseif ((int) $locked->page_count !== 1) {
                throw new RuntimeException('multi_page_capture: Capture the complete set from the session workbench.');
            } else {
                $result = $this->analyzeAndResolve(
                    $locked,
                    $actor,
                    $input,
                    $templateVersion,
                    $storedPaths,
                    (int) $locked->version,
                );
            }
            $this->completeOperation($operation, $result->id);

            return $result;
        } catch (Throwable $exception) {
            if ($locked !== null) {
                $this->persistCaptureFailure((int) $locked->id, $actor, $exception);
                $locked = OmrPendingCapture::query()->find((int) $locked->id);
            }
            $this->cleanupStoredPaths($storedPaths, $locked);
            $this->failOperation($operation, $exception->getMessage());

            throw $exception;
        }
    }

    private function shouldRefreshQr(OmrPendingCapture $pending): bool
    {
        if ($pending->qr_state === 'manual_resolved') {
            return false;
        }

        return (int) ($pending->template_version_id ?? 0) < 1
            || in_array($pending->qr_state, ['absent', 'undecodable'], true)
            || in_array($pending->analysis_code, [
                'qr_missing',
                'qr_reader_unavailable',
                'qr_reader_response_invalid',
                'qr_instance_missing',
                'qr_page_invalid',
            ], true);
    }

    /**
     * @param  array{
     *     state: string,
     *     code: ?string,
     *     payload: ?string,
     *     payload_hash: ?string,
     *     template_version: ?OmrAnswerSheetTemplateVersion,
     *     blank_instance: ?OmrBlankInstance,
     *     blank_page: ?OmrBlankInstancePage
     * }  $qr
     */
    private function persistQrResolution(
        OmrPendingCapture $pending,
        User $actor,
        array $qr,
    ): OmrPendingCapture {
        return DB::transaction(function () use ($pending, $actor, $qr): OmrPendingCapture {
            $locked = OmrPendingCapture::query()->lockForUpdate()->findOrFail($pending->id);
            $this->pendingAccess->assertPending($actor, $locked);
            if ((int) $locked->version !== (int) $pending->version
                || $locked->status !== OmrPendingCapture::STATUS_ANALYZING) {
                throw new RuntimeException('omr_pending_capture_stale_version');
            }

            $templateVersion = $qr['template_version'];
            $locked->forceFill([
                'qr_state' => $qr['state'],
                'qr_payload' => $qr['payload'],
                'qr_payload_hash' => $qr['payload_hash'],
                'template_version_id' => $templateVersion?->id,
                'blank_instance_id' => $qr['blank_instance']?->id,
                'blank_instance_page_id' => $qr['blank_page']?->id,
                'page_count' => $templateVersion?->pageCount() ?? 1,
            ])->save();

            $this->recordAuditEvent->handle('grading.omr_pending_capture_qr_re_resolved', [
                'pending_capture_id' => $locked->id,
                'qr_state' => $qr['state'],
                'qr_code' => $qr['code'],
                'template_version_id' => $templateVersion?->id,
            ], (string) $actor->id);

            return $locked->fresh(['templateVersion.template']);
        });
    }

    /**
     * @return array{
     *     state: string,
     *     code: ?string,
     *     payload: ?string,
     *     payload_hash: ?string,
     *     template_version: ?OmrAnswerSheetTemplateVersion,
     *     blank_instance: ?OmrBlankInstance,
     *     blank_page: ?OmrBlankInstancePage
     * }
     */
    private function resolveQr(string $imageBytes, string $mime): array
    {
        $state = 'absent';
        $code = 'qr_missing';
        $payload = null;
        $payloadHash = null;
        $templateVersion = null;
        $blankInstance = null;
        $blankPage = null;

        try {
            $observation = $this->qrReader->inspect($imageBytes, $mime);
            if ($observation->found()) {
                $payload = trim((string) $observation->payload);
                $state = 'verified';
                $code = null;
                $payloadHash = $this->qrPayload->payloadHash($payload);

                try {
                    $verified = $this->qrPayload->verify($payload);
                } catch (OmrQrPayloadException) {
                    return [
                        'state' => 'present_invalid',
                        'code' => 'qr_invalid',
                        'payload' => $payload,
                        'payload_hash' => $payloadHash,
                        'template_version' => null,
                        'blank_instance' => null,
                        'blank_page' => null,
                    ];
                }

                $blankInstance = OmrBlankInstance::query()
                    ->with(['templateVersion.template', 'pages'])
                    ->where('instance_code', $verified['instance_code'])
                    ->first();
                if ($blankInstance === null) {
                    return [
                        'state' => 'verified',
                        'code' => 'qr_instance_missing',
                        'payload' => $payload,
                        'payload_hash' => $payloadHash,
                        'template_version' => null,
                        'blank_instance' => null,
                        'blank_page' => null,
                    ];
                }

                $templateVersion = $blankInstance->templateVersion;
                if (! $templateVersion instanceof OmrAnswerSheetTemplateVersion) {
                    return [
                        'state' => 'verified',
                        'code' => 'qr_instance_missing',
                        'payload' => $payload,
                        'payload_hash' => $payloadHash,
                        'template_version' => null,
                        'blank_instance' => $blankInstance,
                        'blank_page' => null,
                    ];
                }
                if ((int) $verified['page_count'] !== (int) $blankInstance->page_count
                    || (int) $verified['page_no'] !== 1) {
                    return [
                        'state' => 'verified',
                        'code' => 'qr_page_invalid',
                        'payload' => $payload,
                        'payload_hash' => $payloadHash,
                        'template_version' => $templateVersion,
                        'blank_instance' => $blankInstance,
                        'blank_page' => null,
                    ];
                }

                $blankPage = $blankInstance->pages->firstWhere('page_no', 1);
                if (! $blankPage instanceof OmrBlankInstancePage) {
                    return [
                        'state' => 'verified',
                        'code' => 'qr_page_invalid',
                        'payload' => $payload,
                        'payload_hash' => $payloadHash,
                        'template_version' => $templateVersion,
                        'blank_instance' => $blankInstance,
                        'blank_page' => null,
                    ];
                }
                if ($blankInstance->isTerminal() && ! $blankInstance->isReusableMaster()) {
                    return [
                        'state' => 'verified',
                        'code' => 'qr_lifecycle_invalid',
                        'payload' => $payload,
                        'payload_hash' => $payloadHash,
                        'template_version' => $templateVersion,
                        'blank_instance' => $blankInstance,
                        'blank_page' => $blankPage,
                    ];
                }
                if ($blankPage->status === OmrBlankInstancePage::STATUS_VOID) {
                    return [
                        'state' => 'verified',
                        'code' => 'qr_lifecycle_invalid',
                        'payload' => $payload,
                        'payload_hash' => $payloadHash,
                        'template_version' => $templateVersion,
                        'blank_instance' => $blankInstance,
                        'blank_page' => $blankPage,
                    ];
                }
                if (! $blankInstance->isReusableMaster()
                    && ($blankPage->status === OmrBlankInstancePage::STATUS_CAPTURED || $blankPage->claims()->exists())) {
                    return [
                        'state' => 'verified',
                        'code' => 'qr_duplicate_page',
                        'payload' => $payload,
                        'payload_hash' => $payloadHash,
                        'template_version' => $templateVersion,
                        'blank_instance' => $blankInstance,
                        'blank_page' => $blankPage,
                    ];
                }
            }
        } catch (Throwable $exception) {
            $message = $exception->getMessage();
            $code = str_starts_with($message, 'qr_reader_response_invalid')
                ? 'qr_reader_response_invalid'
                : 'qr_reader_unavailable';
            $state = 'undecodable';
        }

        return [
            'state' => $state,
            'code' => $code,
            'payload' => $payload,
            'payload_hash' => $payloadHash,
            'template_version' => $templateVersion,
            'blank_instance' => $blankInstance,
            'blank_page' => $blankPage,
        ];
    }

    /** @param array<string, mixed> $qr */
    private function finishWithoutAnalysis(
        OmrPendingCapture $pending,
        User $actor,
        array $qr,
        int $pageCount,
        int $expectedVersion,
    ): OmrPendingCapture {
        $code = (string) ($qr['code'] ?? ($pageCount !== 1 ? 'multi_page_capture' : 'qr_missing'));
        $status = $code === 'qr_invalid' ? OmrPendingCapture::STATUS_QUARANTINED : OmrPendingCapture::STATUS_NEEDS_RECOVERY;
        $detail = match ($code) {
            'qr_invalid' => 'The signed QR is invalid. This capture cannot use display-code recovery.',
            'qr_instance_missing' => 'The signed Answer Sheet is not registered for trusted template resolution.',
            'qr_duplicate_page' => 'This issued Answer Sheet page is already attached to another capture.',
            'qr_lifecycle_invalid' => 'This issued Answer Sheet is void or superseded.',
            'qr_page_invalid' => 'The signed QR page metadata does not match the issued Answer Sheet.',
            'multi_page_capture' => 'Capture the complete multi-page set from the selected Exam Monitoring session.',
            'qr_reader_unavailable', 'qr_reader_response_invalid' => 'The QR reader is unavailable; retry this same capture or use the printed display code.',
            default => 'The QR code could not be trusted; retry the scan or use the printed display code.',
        };

        return DB::transaction(function () use ($pending, $actor, $status, $code, $detail, $expectedVersion): OmrPendingCapture {
            $locked = OmrPendingCapture::query()->lockForUpdate()->findOrFail($pending->id);
            if ((int) $locked->version !== $expectedVersion || $locked->status !== OmrPendingCapture::STATUS_ANALYZING) {
                return $locked->fresh();
            }
            $locked->forceFill([
                'status' => $status,
                'analysis_code' => $code,
                'analysis_detail' => $detail,
                'analyzed_at' => now(),
                'version' => (int) $locked->version + 1,
            ])->save();
            $this->recordAuditEvent->handle('grading.omr_pending_capture_recovery_required', [
                'pending_capture_id' => $locked->id,
                'code' => $code,
                'status' => $status,
            ], (string) $actor->id);

            return $locked->fresh();
        });
    }

    /** @param list<string> $storedPaths */
    private function analyzeAndResolve(
        OmrPendingCapture $pending,
        User $actor,
        OmrCaptureInput $input,
        OmrAnswerSheetTemplateVersion $templateVersion,
        array &$storedPaths,
        int $expectedVersion,
    ): OmrPendingCapture {
        $analysis = null;
        try {
            $templateVersion->loadMissing('template');
            $analysis = $this->analysis->analyze(new OmrCaptureAnalysisRequest(
                capture: $input,
                target: new CompiledVersionDiagnosticTarget($templateVersion),
                pageNo: 1,
            ));
        } catch (Throwable $exception) {
            return $this->saveAnalysisFailure($pending, $actor, 'analysis_failed', $exception->getMessage(), $expectedVersion);
        }

        try {
            $evidence = $analysis->normalize();
        } catch (Throwable) {
            return $this->saveAnalysisFailure($pending, $actor, 'analysis_evidence_invalid', 'The scanner returned evidence that could not be stored safely.', $expectedVersion);
        }

        $success = $analysis->providerResult?->success;
        $applicantNumber = $this->applicantNumber($success?->applicantNumberProposal, $analysis->identity);
        $confidence = $success?->applicantNumberConfidence ?? $this->identityConfidence($analysis->identity);
        $birthdate = $success?->birthdateProposal;
        $aligned = is_array($analysis->assets['aligned'] ?? null) ? $analysis->assets['aligned'] : null;
        $alignedBytes = is_string($aligned['bytes'] ?? null) ? $aligned['bytes'] : null;
        $alignedPath = null;
        if ($alignedBytes !== null && $alignedBytes !== '') {
            $alignedPath = $this->assetStore->store(
                $alignedBytes,
                'omr/pending/'.$pending->client_capture_id.'/aligned.bin',
            );
            $storedPaths[] = $alignedPath;
        }

        $code = $analysis->succeeded() ? null : ($analysis->code ?? 'analysis_failed');
        $status = $analysis->succeeded()
            ? OmrPendingCapture::STATUS_RESOLUTION_REQUIRED
            : OmrPendingCapture::STATUS_NEEDS_RECOVERY;
        $resolutionCode = $code;
        $resolution = [
            'outcome' => 'selection_required',
            'code' => $code,
            'candidates' => [],
        ];
        $gradingSessionId = null;

        if ($analysis->succeeded()) {
            if ($applicantNumber === null || $confidence === null || $confidence < self::IDENTITY_MIN_CONFIDENCE) {
                $resolutionCode = 'applicant_unresolved';
            } elseif (preg_match('/^[A-Za-z0-9._-]+$/', $applicantNumber) !== 1) {
                $resolutionCode = 'applicant_unresolved';
                $applicantNumber = null;
            } else {
                try {
                    $context = $this->sessionResolver->discover($applicantNumber, $actor);
                    $resolution = [
                        'outcome' => $context->outcome->value,
                        'code' => $context->resolutionCode,
                        'candidates' => $context->candidates,
                    ];
                    $resolutionCode = $context->resolutionCode;
                    if ($context->gradingSession !== null && $context->outcome === OmrScannerContextOutcome::AutoResolved) {
                        $status = OmrPendingCapture::STATUS_READY;
                        $gradingSessionId = (int) $context->gradingSession->id;
                        $resolution['grading_session_id'] = $gradingSessionId;
                        $resolutionCode = null;
                    }
                } catch (Throwable) {
                    $resolutionCode = 'session_unresolved';
                    $resolution = [
                        'outcome' => 'selection_required',
                        'code' => $resolutionCode,
                        'candidates' => [],
                    ];
                }
            }
        }

        return DB::transaction(function () use (
            $pending,
            $actor,
            $analysis,
            $evidence,
            $applicantNumber,
            $confidence,
            $birthdate,
            $aligned,
            $alignedBytes,
            $alignedPath,
            $code,
            $status,
            $resolutionCode,
            $resolution,
            $gradingSessionId,
            $expectedVersion,
        ): OmrPendingCapture {
            $locked = OmrPendingCapture::query()->lockForUpdate()->findOrFail($pending->id);
            if ((int) $locked->version !== $expectedVersion || $locked->status !== OmrPendingCapture::STATUS_ANALYZING) {
                return $locked->fresh();
            }
            $locked->forceFill([
                'status' => $status,
                'analysis_code' => $code,
                'analysis_detail' => $analysis->detail,
                'evidence_json' => $evidence,
                'resolution_json' => [
                    ...$resolution,
                    'resolution_code' => $resolutionCode,
                ],
                'detected_applicant_number' => $applicantNumber,
                'detected_applicant_confidence' => $confidence,
                'detected_birthdate' => $birthdate,
                'grading_session_id' => $gradingSessionId,
                'aligned_mime' => is_string($aligned['mime'] ?? null) ? $aligned['mime'] : null,
                'aligned_bytes' => $alignedBytes === null ? null : strlen($alignedBytes),
                'aligned_width' => is_numeric($aligned['width'] ?? null) ? (int) $aligned['width'] : null,
                'aligned_height' => is_numeric($aligned['height'] ?? null) ? (int) $aligned['height'] : null,
                'aligned_hash' => is_string($aligned['sha256'] ?? null)
                    ? $aligned['sha256']
                    : ($alignedBytes === null ? null : hash('sha256', $alignedBytes)),
                'aligned_asset_path' => $alignedPath,
                'aligned_asset_hmac' => $alignedBytes === null ? null : $this->assetStore->hmac($alignedBytes),
                'aligned_asset_hmac_key_id' => $alignedBytes === null ? null : (string) config('omr.keyring.active_key_id'),
                'aligned_asset_key_id' => $alignedBytes === null ? null : (string) config('omr.keyring.active_key_id'),
                'analyzed_at' => now(),
                'version' => (int) $locked->version + 1,
            ])->save();

            $this->recordAuditEvent->handle('grading.omr_pending_capture_analyzed', [
                'pending_capture_id' => $locked->id,
                'status' => $status,
                'analysis_code' => $code,
                'resolution_code' => $resolutionCode,
                'detected_identity' => $applicantNumber !== null,
                'evidence_hash' => $evidence['evidence_hash'] ?? null,
            ], (string) $actor->id);

            return $locked->fresh();
        });
    }

    private function saveAnalysisFailure(
        OmrPendingCapture $pending,
        User $actor,
        string $code,
        ?string $detail,
        int $expectedVersion,
    ): OmrPendingCapture {
        return DB::transaction(function () use ($pending, $actor, $code, $detail, $expectedVersion): OmrPendingCapture {
            $locked = OmrPendingCapture::query()->lockForUpdate()->findOrFail($pending->id);
            if ((int) $locked->version !== $expectedVersion || $locked->status !== OmrPendingCapture::STATUS_ANALYZING) {
                return $locked->fresh();
            }
            $locked->forceFill([
                'status' => OmrPendingCapture::STATUS_NEEDS_RECOVERY,
                'analysis_code' => $code,
                'analysis_detail' => $detail !== null && trim($detail) !== '' ? trim($detail) : 'The scanner could not analyze this capture.',
                'analyzed_at' => now(),
                'version' => (int) $locked->version + 1,
            ])->save();
            $this->recordAuditEvent->handle('grading.omr_pending_capture_analysis_failed', [
                'pending_capture_id' => $locked->id,
                'code' => $code,
            ], (string) $actor->id);

            return $locked->fresh();
        });
    }

    private function resumeOperation(Operation $operation): Operation
    {
        if (! $operation->wasRecentlyCreated
            && $operation->status === Operation::STATUS_PROCESSING
            && $operation->updated_at !== null
            && $operation->updated_at->isAfter(now()->subMinutes(5))) {
            throw new RuntimeException('omr_scanner_operation_in_progress');
        }

        $operation->forceFill([
            'status' => Operation::STATUS_PROCESSING,
            'attempts' => (int) $operation->attempts + 1,
            'finished_at' => null,
            'last_error' => null,
        ])->save();

        return $operation;
    }

    private function completeOperation(Operation $operation, int $pendingId): void
    {
        $operation->forceFill([
            'status' => Operation::STATUS_SUCCEEDED,
            'payload' => [
                ...(array) $operation->payload,
                'result' => ['pending_capture_id' => $pendingId],
            ],
            'finished_at' => now(),
            'last_error' => null,
        ])->save();
    }

    private function failOperation(Operation $operation, string $message): void
    {
        $operation->forceFill([
            'status' => Operation::STATUS_FAILED,
            'last_error' => trim(substr($message, 0, 500)),
            'finished_at' => now(),
        ])->save();
    }

    private function persistCaptureFailure(int $pendingId, User $actor, Throwable $exception): void
    {
        try {
            DB::transaction(function () use ($pendingId, $actor, $exception): void {
                $pending = OmrPendingCapture::query()->lockForUpdate()->find($pendingId);
                if ($pending === null || $pending->isTerminal()) {
                    return;
                }
                $pending->forceFill([
                    'status' => OmrPendingCapture::STATUS_NEEDS_RECOVERY,
                    'analysis_code' => 'capture_persistence_failed',
                    'analysis_detail' => 'The capture was preserved, but processing failed. Retry the preserved capture.',
                    'version' => (int) $pending->version + 1,
                ])->save();
                $this->recordAuditEvent->handle('grading.omr_pending_capture_persistence_failed', [
                    'pending_capture_id' => $pending->id,
                    'exception' => get_class($exception),
                ], (string) $actor->id);
            });
        } catch (Throwable) {
            // Keep the source asset when a failure transition itself cannot commit.
        }
    }

    /** @param list<string> $paths */
    private function cleanupStoredPaths(array $paths, ?OmrPendingCapture $pending): void
    {
        $protected = $pending === null
            ? []
            : array_filter([
                $pending->asset_path,
                $pending->aligned_asset_path,
            ], static fn (mixed $path): bool => is_string($path) && $path !== '');
        foreach (array_values(array_unique($paths)) as $path) {
            if (in_array($path, $protected, true)) {
                continue;
            }
            try {
                $this->assetStore->delete($path);
            } catch (Throwable) {
                // A later cleanup pass may remove an unreferenced failed asset.
            }
        }
    }

    private function assertCapability(User $actor): void
    {
        if (! $this->authorizer->allows($actor, CapabilityCatalog::GUIDANCE_SCORING_OMR)) {
            throw new AuthorizationException('OMR scanner is not available.');
        }
    }

    private function applicantNumber(?string $proposal, array $identity): ?string
    {
        $proposal = $proposal !== null ? trim($proposal) : '';
        if ($proposal !== '') {
            return $proposal;
        }

        $values = [];
        foreach ($identity as $field) {
            if (! is_array($field) || ($field['code'] ?? null) !== 'applicant_number') {
                continue;
            }
            $value = $field['detected_value'] ?? null;
            if (is_scalar($value) && trim((string) $value) !== '') {
                $values[(int) ($field['position'] ?? count($values) + 1)] = trim((string) $value);
            }
        }
        if ($values === []) {
            return null;
        }
        ksort($values);

        return trim(implode('', $values));
    }

    /** @param list<array<string, mixed>> $identity */
    private function identityConfidence(array $identity): ?float
    {
        $confidences = [];
        foreach ($identity as $field) {
            if (! is_array($field) || ($field['code'] ?? null) !== 'applicant_number') {
                continue;
            }
            if (is_numeric($field['confidence'] ?? null)) {
                $confidences[] = (float) $field['confidence'];
            }
        }

        return $confidences === [] ? null : min($confidences);
    }
}
