<?php

namespace App\Assessment\Actions\Omr;

use App\Assessment\Omr\Diagnostics\OmrPinnedRequestDiagnosticTarget;
use App\Assessment\Omr\OmrCaptureAnalysisPort;
use App\Assessment\Omr\OmrCaptureAnalysisRequest;
use App\Assessment\Omr\OmrCaptureInput;
use App\Assessment\Omr\OmrCapturePolicy;
use App\Assessment\Omr\OmrCaptureResolver;
use App\Assessment\Omr\OmrClientQuad;
use App\Assessment\Omr\OmrDetectedItem;
use App\Assessment\Omr\OmrDetectionIssue;
use App\Assessment\Omr\OmrDetectionOutcome;
use App\Assessment\Omr\OmrDetectionPort;
use App\Assessment\Omr\OmrDetectionRequest;
use App\Assessment\Omr\OmrDetectionResult;
use App\Assessment\Omr\OmrDetectionResultValidator;
use App\Assessment\Omr\OmrDetectionSuccess;
use App\Assessment\Omr\OmrInvalidDetectionOutput;
use App\Assessment\Omr\OmrItemGeometry;
use App\Assessment\Omr\OmrPinnedSnapshot;
use App\Assessment\Omr\OmrPreparedCaptureEvidence;
use App\Assessment\Omr\OmrRegistrationMode;
use App\Assessment\Omr\OmrSourceImage;
use App\Assessment\Omr\OmrTemplateProjectionException;
use App\Assessment\Support\KitCompositionContext;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\OmrCaptureSheet;
use App\Models\Assessment\OmrCaptureSheetPage;
use App\Models\Assessment\OmrCaptureSheetPageRevision;
use App\Models\Assessment\OmrProcessingRun;
use App\Models\Assessment\TestKitVersion;
use App\Models\StaffProfile;
use App\Models\User;
use App\Support\SensitiveAssetStore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

use function array_filter;
use function array_map;
use function count;
use function getimagesizefromstring;
use function hash;
use function hash_equals;
use function is_array;
use function round;
use function strlen;

/**
 * Runs one durable, current, claimed OMR analysis attempt for ONE PAGE of a
 * logical sheet. Provider invocation happens outside database locks; only a
 * still-current claim may persist. After every page of a multi-page sheet
 * succeeds, the guarded merge step promotes the sheet to needs_review with
 * one combined proposal.
 */
final class RunOmrSheetAnalysis
{
    public function __construct(
        private readonly OmrCaptureAnalysisPort $analysis,
        private readonly OmrDetectionPort $detector,
        private readonly OmrDetectionResultValidator $validator,
        private readonly CapabilityAuthorizer $authorizer,
        private readonly RecordAuditEvent $recordAuditEvent,
        private readonly SensitiveAssetStore $assetStore,
        private readonly OmrCaptureResolver $captureResolver,
    ) {}

    public function handle(int $runId): OmrProcessingRun
    {
        $claimed = $this->claim($runId);

        if ($claimed === null) {
            return OmrProcessingRun::query()->findOrFail($runId);
        }

        [$run, $sheet, $page, $actor, $claimToken] = $claimed;

        try {
            $this->assertAuthorized($actor, $sheet);
            $request = $this->request($run, $sheet, $page);
            $prepared = null;
            if ($request->registrationMode === OmrRegistrationMode::FiducialAnchored) {
                $prepared = $this->analysis->analyze(new OmrCaptureAnalysisRequest(
                    capture: new OmrCaptureInput(
                        bytes: $request->imageBytes,
                        mime: $request->imageMime,
                        pageNo: $request->pageNo,
                        pageCount: 1,
                        clientQuad: $request->clientQuad,
                        cropPolicy: $request->cropPolicy,
                    ),
                    target: new OmrPinnedRequestDiagnosticTarget(
                        $request,
                        'grading:'.$run->run_uuid,
                        max(1, (int) $sheet->expected_page_count),
                    ),
                    pageNo: $request->pageNo,
                ));
                $result = $prepared->providerResult;
                $request = $prepared->detectionRequest ?? $request;
                if ($result === null) {
                    return $this->fail(
                        $run->id,
                        $claimToken,
                        true,
                        $prepared->code ?? 'capture_analysis_failed',
                    );
                }
            } elseif ($request->registrationMode === OmrRegistrationMode::DocumentBound) {
                $result = $this->detector->detect($request);
            } else {
                throw new RuntimeException('The pinned OMR version has no supported registration mode.');
            }
        } catch (OmrTemplateProjectionException $exception) {
            return $this->fail($run->id, $claimToken, false, $exception->errorCode);
        } catch (OmrInvalidDetectionOutput) {
            return $this->fail($run->id, $claimToken, false, 'provider_output_invalid');
        } catch (Throwable) {
            return $this->fail($run->id, $claimToken, true, 'provider_unavailable');
        }
        if ($result->outcome !== OmrDetectionOutcome::Succeeded || $result->success === null) {
            return $this->fail(
                $run->id,
                $claimToken,
                $result->outcome === OmrDetectionOutcome::RetryableFailure,
                $result->safeDiagnosticCode ?? 'provider_failed',
                $result,
            );
        }

        try {
            $snapshot = OmrPinnedSnapshot::pin(
                $sheet->batch->templateVersion,
                $sheet->batch->bookletVersion,
                $sheet->bookletSet,
                $this->kitContextForSheet($sheet),
            );
        } catch (OmrTemplateProjectionException $exception) {
            return $this->fail($run->id, $claimToken, true, $exception->errorCode);
        }

        try {
            $success = $this->validator->validate($request, $result) ?? $result->success;
        } catch (OmrInvalidDetectionOutput) {
            return $this->fail($run->id, $claimToken, false, 'provider_output_invalid');
        }

        return $this->persistSuccess(
            $run->id,
            $claimToken,
            $actor,
            $request,
            $result,
            $success,
            $snapshot,
            $prepared,
            max(1, (int) $run->page_revision),
        );
    }

    public function markJobFailure(int $runId, string $safeCode = 'analysis_job_failed'): void
    {
        DB::transaction(function () use ($runId, $safeCode): void {
            $run = OmrProcessingRun::query()->lockForUpdate()->find($runId);
            if ($run === null || ! $run->isActive()) {
                return;
            }

            $sheet = OmrCaptureSheet::query()->lockForUpdate()->find($run->sheet_id);
            $page = $this->pageFor($run, $sheet);

            if (! $this->pageIsCurrent($run, $sheet, $page)) {
                $run->forceFill([
                    'status' => OmrProcessingRun::STATUS_SUPERSEDED,
                    'diagnostic_code' => 'stale_page_revision',
                    'claim_token' => null,
                    'claim_expires_at' => null,
                    'next_retry_at' => null,
                ])->save();

                return;
            }

            $run->update([
                'status' => OmrProcessingRun::STATUS_TERMINAL_FAILED,
                'diagnostic_code' => $safeCode,
                'claim_token' => null,
                'claim_expires_at' => null,
                'next_retry_at' => null,
            ]);
            $page->update(['status' => OmrCaptureSheetPage::STATUS_FAILED_TERMINAL]);
            $sheet->update([
                'status' => OmrCaptureSheet::STATUS_FAILED_TERMINAL,
                'version' => (int) $sheet->version + 1,
                'proposal_json' => [
                    'envelope' => OmrDetectionRequest::ENVELOPE_VERSION,
                    'abstained' => true,
                    'reason' => 'provider_failed',
                    'safe_error' => $safeCode,
                ],
            ]);
            $this->appendPageRevisionSnapshot($sheet, $page, $run->actor_id === null ? null : (int) $run->actor_id);
        });
    }

    /**
     * @return array{0: OmrProcessingRun, 1: OmrCaptureSheet, 2: OmrCaptureSheetPage|OmrCaptureSheet, 3: User, 4: string}|null
     */
    private function claim(int $runId): ?array
    {
        return DB::transaction(function () use ($runId): ?array {
            $run = OmrProcessingRun::query()->lockForUpdate()->findOrFail($runId);

            if (! $run->isActive()) {
                return null;
            }

            if ($run->status === OmrProcessingRun::STATUS_CLAIMED
                && $run->claim_expires_at !== null
                && $run->claim_expires_at->isFuture()) {
                return null;
            }

            if ($run->status === OmrProcessingRun::STATUS_RETRYABLE_FAILED
                && $run->next_retry_at !== null
                && $run->next_retry_at->isFuture()) {
                return null;
            }

            $sheet = OmrCaptureSheet::query()
                ->with($this->analysisRelations())
                ->lockForUpdate()
                ->findOrFail($run->sheet_id);
            $page = $this->pageFor($run, $sheet);

            if (! $this->pageIsCurrent($run, $sheet, $page)
                || ! in_array($sheet->status, [OmrCaptureSheet::STATUS_QUEUED, OmrCaptureSheet::STATUS_PROCESSING], true)
                || ! in_array($page->status, [OmrCaptureSheetPage::STATUS_QUEUED, OmrCaptureSheetPage::STATUS_PROCESSING], true)) {
                $run->forceFill([
                    'status' => OmrProcessingRun::STATUS_SUPERSEDED,
                    'diagnostic_code' => 'stale_page_revision',
                    'claim_token' => null,
                    'claim_expires_at' => null,
                    'next_retry_at' => null,
                ])->save();

                return null;
            }

            $actor = User::query()->with('staffProfile')->find($run->actor_id);
            if ($actor === null) {
                $this->terminalizeUnauthorized($run, $sheet, $page, 'actor_missing');

                return null;
            }

            try {
                $this->assertAuthorized($actor, $sheet);
            } catch (RuntimeException) {
                $this->terminalizeUnauthorized($run, $sheet, $page, 'actor_revoked');

                return null;
            }

            $claimToken = (string) Str::uuid();
            $run->update([
                'status' => OmrProcessingRun::STATUS_CLAIMED,
                'claim_token' => $claimToken,
                'claimed_at' => now(),
                'claim_expires_at' => now()->addSeconds((int) config('omr.retry.claim_lease_seconds', 50)),
                'attempts' => (int) $run->attempts + 1,
                'next_retry_at' => null,
                'diagnostic_code' => null,
            ]);
            $sheet->update(['status' => OmrCaptureSheet::STATUS_PROCESSING]);
            $this->setPageProcessing($page, $run);

            return [$run->fresh() ?? $run, $sheet, $page, $actor, $claimToken];
        });
    }

    private function request(OmrProcessingRun $run, OmrCaptureSheet $sheet, OmrCaptureSheetPage|OmrCaptureSheet $page): OmrDetectionRequest
    {
        $version = $sheet->batch->templateVersion;
        $registrationMode = $version->registration_mode;
        $versionId = (int) $version->getKey();
        $pageNo = (int) $run->page_no;
        if (! $registrationMode instanceof OmrRegistrationMode
            || $versionId < 1
            || $pageNo < 1
            || $pageNo > 4
            || $pageNo > (int) $version->page_count) {
            throw new OmrTemplateProjectionException('registration_policy_mismatch', 'The pinned OMR version has no valid immutable registration/page identity.');
        }

        $nativeBundle = is_array($version->native_bundle_json) ? $version->native_bundle_json : [];
        $nativeTemplate = is_array($nativeBundle['template'] ?? null) ? $nativeBundle['template'] : [];
        $nativeManifest = is_array($nativeBundle['manifest'] ?? null) ? $nativeBundle['manifest'] : [];
        $manifestMode = $nativeManifest['registration_mode'] ?? null;
        if (($manifestMode !== null && $manifestMode !== $registrationMode->value)
            || ($manifestMode === null && $registrationMode !== OmrRegistrationMode::FiducialAnchored)) {
            throw new OmrTemplateProjectionException('registration_policy_mismatch', 'The pinned OMR version registration policy does not match its native manifest.');
        }
        $markerMetadata = OmrCapturePolicy::markerMetadata($nativeTemplate);
        if (($registrationMode === OmrRegistrationMode::FiducialAnchored && $markerMetadata === null)
            || ($registrationMode === OmrRegistrationMode::DocumentBound && $markerMetadata !== null)) {
            throw new OmrTemplateProjectionException('registration_policy_mismatch', 'The pinned OMR version marker assets do not match its explicit registration mode.');
        }

        $snapshot = OmrPinnedSnapshot::pin(
            $version,
            $sheet->batch->bookletVersion,
            $sheet->bookletSet,
            $this->kitContextForSheet($sheet),
        );
        $pageTemplate = $snapshot->pageTemplates[$pageNo] ?? null;
        if (! is_array($pageTemplate)
            || ($registrationMode === OmrRegistrationMode::DocumentBound
                && (in_array('CropOnMarkers', (array) ($pageTemplate['preProcessors'] ?? []), true)
                    || $snapshot->nativeAssets !== []))) {
            throw new OmrTemplateProjectionException('registration_policy_mismatch', 'The pinned OMR page preprocessing contract conflicts with its registration mode.');
        }

        $bytes = $this->assetStore->read((string) $page->asset_path, (string) $page->asset_hmac);
        $sourceImage = OmrSourceImage::fromBytes($bytes);
        $inputHash = $sourceImage->inputHash;

        $expected = [
            'input_hash' => [$inputHash, (string) $run->input_hash],
            'native_bundle_hash' => [$snapshot->nativeBundleHash, (string) $run->native_bundle_hash],
            'manifest_hash' => [$snapshot->manifestHash, (string) $run->manifest_hash],
            'booklet_hash' => [$snapshot->bookletHash, (string) $run->booklet_hash],
            'patch_identity' => [$snapshot->patchIdentity, (string) $run->patch_identity],
        ];
        foreach ($expected as $name => [$actual, $pinned]) {
            if ($actual === '' || $actual !== $pinned) {
                throw new OmrInvalidDetectionOutput("Pinned {$name} changed before provider invocation.");
            }
        }

        $pageItems = array_values(array_filter(
            $snapshot->items,
            static fn (OmrItemGeometry $item): bool => $item->pageNo === $pageNo,
        ));
        $clientQuad = null;
        if ($page->client_quad_json !== null) {
            if ($registrationMode !== OmrRegistrationMode::DocumentBound || ! is_array($page->client_quad_json)) {
                throw new RuntimeException('A client quad is allowed only for document-bound inference.');
            }
            $clientQuad = OmrClientQuad::fromArray($page->client_quad_json);
            if (! $clientQuad->isBoundTo($sourceImage, $pageNo)) {
                throw new RuntimeException('The stored client quad no longer matches its source page.');
            }
        }
        $cropPolicy = $this->cropPolicy($page->crop_policy);
        if ($cropPolicy === 'keep_original' && $registrationMode !== OmrRegistrationMode::DocumentBound) {
            throw new RuntimeException('Keeping the original image is supported only for document-bound capture.');
        }

        return new OmrDetectionRequest(
            envelopeVersion: OmrDetectionRequest::ENVELOPE_VERSION,
            runId: (string) $run->run_uuid,
            correlationId: 'omr:run:'.$run->run_uuid.':page:'.$pageNo,
            imageBytes: $bytes,
            inputHash: $inputHash,
            imageMime: (string) $page->original_mime,
            imageWidth: $sourceImage->width,
            imageHeight: $sourceImage->height,
            areas: $snapshot->areas,
            items: $pageItems,
            identityRegions: $snapshot->pageIdentityRegions[$pageNo] ?? $snapshot->identityRegions,
            nativeBundleHash: $snapshot->nativeBundleHash,
            manifestHash: $snapshot->manifestHash,
            configHash: $snapshot->configHash,
            bookletHash: $snapshot->bookletHash,
            nativeTemplate: $pageTemplate,
            nativeConfigJson: $snapshot->nativeConfigJson,
            nativeBundleMetadata: [
                ...$snapshot->nativeBundleMetadata,
                'registration_mode' => $registrationMode->value,
                'qr_mode' => $version->qr_mode->value,
                'compiled_pages' => array_map(
                    static fn (int $compiledPageNo): array => ['page_no' => $compiledPageNo],
                    array_keys($snapshot->pageTemplates),
                ),
            ],
            reviewManifest: $snapshot->reviewManifest,
            engineName: (string) $run->provider_name,
            engineCommit: $run->provider_commit !== null ? (string) $run->provider_commit : null,
            patchIdentity: $snapshot->patchIdentity,
            expectedFieldLabels: $snapshot->expectedFieldLabels,
            expectedOptionCodes: $snapshot->expectedOptionCodes,
            registrationMode: $registrationMode,
            templateVersionId: $versionId,
            candidateTargetId: null,
            sourceImage: $sourceImage,
            pageNo: $pageNo,
            providerDeadline: (float) config('omr.provider.deadline_seconds', 30),
            clientQuad: $clientQuad,
            nativeAssets: $snapshot->nativeAssets,
            cropPolicy: $cropPolicy,
        );
    }

    private function persistSuccess(
        int $runId,
        string $claimToken,
        User $actor,
        OmrDetectionRequest $request,
        OmrDetectionResult $result,
        OmrDetectionSuccess $success,
        OmrPinnedSnapshot $snapshot,
        ?OmrPreparedCaptureEvidence $prepared = null,
        int $pageRevision = 1,
    ): OmrProcessingRun {
        $proposal = $this->project($request, $success, $snapshot, max(1, $pageRevision));
        $alignedFields = $this->alignedFields($success);
        $alignedPath = $alignedFields['aligned_asset_path'] ?? null;
        $discardAlignedAsset = false;

        try {
            $runResult = DB::transaction(function () use ($runId, $claimToken, $actor, $request, $result, $success, $proposal, $alignedFields, $prepared, &$discardAlignedAsset): OmrProcessingRun {
                $run = OmrProcessingRun::query()->lockForUpdate()->findOrFail($runId);
                $sheet = OmrCaptureSheet::query()->with($this->analysisRelations())->lockForUpdate()->findOrFail($run->sheet_id);
                $page = $this->pageFor($run, $sheet);

                if ($run->status !== OmrProcessingRun::STATUS_CLAIMED
                    || ! hash_equals((string) $run->claim_token, $claimToken)) {
                    $discardAlignedAsset = true;

                    return $run;
                }
                if (! $this->pageIsCurrent($run, $sheet, $page)) {
                    $run->forceFill([
                        'status' => OmrProcessingRun::STATUS_SUPERSEDED,
                        'diagnostic_code' => 'stale_page_revision',
                        'claim_token' => null,
                        'claim_expires_at' => null,
                        'next_retry_at' => null,
                    ])->save();
                    $discardAlignedAsset = true;

                    return $run;
                }

                try {
                    $this->assertAuthorized($actor->fresh(['staffProfile']) ?? $actor, $sheet);
                } catch (RuntimeException) {
                    $this->terminalizeUnauthorized($run, $sheet, $page, 'actor_revoked');

                    return $run;
                }

                $sheet->issues()->where('page_no', (int) $run->page_no)->delete();
                $sheet->issues()->createMany(array_map(fn ($issue): array => [
                    'page_no' => (int) $run->page_no,
                    'area_code' => $issue->areaCode,
                    'question_no' => $issue->questionNo,
                    'issue_code' => $issue->code,
                    'severity' => $issue->severity,
                    'state' => 'open',
                    'evidence_json' => [
                        'provider' => $success->engineName,
                        'input_hash' => $request->inputHash,
                    ],
                    'version' => 1,
                ], $success->issues));

                $page->update([
                    ...$alignedFields,
                    'status' => OmrCaptureSheetPage::STATUS_SUCCEEDED,
                    'proposal_json' => $proposal,
                ]);

                $run->update([
                    'status' => OmrProcessingRun::STATUS_SUCCEEDED,
                    'response_version' => $success->responseEnvelopeVersion,
                    'claim_token' => null,
                    'claim_expires_at' => null,
                    'next_retry_at' => null,
                    'diagnostic_code' => null,
                    'duration_ms' => $result->timings !== null ? (int) round($result->timings->detectionMs) : null,
                    'timings_json' => $result->timings !== null ? [
                        'detection_ms' => $result->timings->detectionMs,
                        'preprocess_ms' => $result->timings->preprocessMs,
                    ] : null,
                    'output_json' => [
                        'outcome' => $result->outcome->value,
                        'issue_count' => count($success->issues),
                        'calibration_status' => $success->calibrationStatus,
                        'aligned_image_hash' => $success->alignedImage['hash'] ?? null,
                        'prepared_capture' => $prepared?->normalize(),
                    ],
                ]);

                if ((int) $sheet->expected_page_count <= 1) {
                    $sheet->update([
                        'status' => OmrCaptureSheet::STATUS_NEEDS_REVIEW,
                        'version' => (int) $sheet->version + 1,
                        'sheet_confidence' => $proposal['sheet_confidence'],
                        'proposal_json' => $proposal,
                        'detected_applicant_number' => $proposal['applicant_number_proposal'],
                        'detected_applicant_confidence' => $proposal['applicant_number_confidence'],
                        'detected_birthdate' => $proposal['birthdate_proposal'],
                    ]);
                    $this->flagInvalidBirthdate($sheet, $proposal['birthdate_proposal']);
                } else {
                    $this->maybeMerge($sheet);
                }
                if ($sheet->status === OmrCaptureSheet::STATUS_NEEDS_REVIEW) {
                    $this->applyMaterialResolution($sheet);
                }

                $this->recordAuditEvent->handle('grading.omr_sheet_analyzed', [
                    'sheet_id' => $sheet->id,
                    'batch_id' => $sheet->batch_id,
                    'processing_run_id' => $run->id,
                    'page_no' => (int) $run->page_no,
                    'status' => $sheet->status,
                    'outcome' => $result->outcome->value,
                    'issue_count' => count($success->issues),
                ], (string) $actor->id);

                $this->appendPageRevisionSnapshot($sheet, $page, (int) $actor->getAuthIdentifier());

                return $run;
            });
            if ($discardAlignedAsset && $alignedPath !== null) {
                try {
                    $this->assetStore->delete($alignedPath);
                } catch (Throwable) {
                    // Best-effort cleanup of a derivative no longer referenced by the page.
                }
            }

            return $runResult;
        } catch (Throwable $exception) {
            if ($alignedPath !== null) {
                try {
                    $this->assetStore->delete($alignedPath);
                } catch (Throwable) {
                    // Best-effort orphan cleanup; retention policy governs survivors.
                }
            }

            throw $exception;
        }
    }

    private function maybeMerge(OmrCaptureSheet $sheet): void
    {
        $pages = $sheet->pages()->orderBy('page_no')->lockForUpdate()->get();

        if ($pages->count() < (int) $sheet->expected_page_count
            || $pages->contains(fn (OmrCaptureSheetPage $page): bool => $page->status !== OmrCaptureSheetPage::STATUS_SUCCEEDED)) {
            return;
        }

        $proposals = $pages->map(fn (OmrCaptureSheetPage $page): array => is_array($page->proposal_json) ? $page->proposal_json : [])->values()->all();
        $merged = $this->mergeProposals($proposals);
        $sheet->update([
            'status' => OmrCaptureSheet::STATUS_NEEDS_REVIEW,
            'version' => (int) $sheet->version + 1,
            'sheet_confidence' => $merged['sheet_confidence'],
            'proposal_json' => $merged['proposal'],
            'detected_applicant_number' => $merged['applicant_number'],
            'detected_applicant_confidence' => $merged['applicant_number_confidence'],
            'detected_birthdate' => $merged['birthdate'],
        ]);

        if ($merged['identity_conflict']) {
            $sheet->issues()->create([
                'page_no' => 0,
                'issue_code' => 'identity_unresolved',
                'severity' => 'required',
                'state' => 'open',
                'evidence_json' => [
                    'provider' => 'nexiam.merge',
                    'reason' => 'page identity conflict',
                    'per_page_applicant_numbers' => $merged['per_page_applicant_numbers'],
                    'per_page_birthdates' => $merged['per_page_birthdates'],
                ],
                'version' => 1,
            ]);
        }

        $this->flagInvalidBirthdate($sheet, $merged['birthdate']);
    }

    private function applyMaterialResolution(OmrCaptureSheet $sheet): void
    {
        $freshSheet = $sheet->fresh();
        $resolution = $this->captureResolver->resolveSheet($freshSheet, $this->kitContextForSheet($freshSheet));
        if ($resolution->isResolved()) {
            $resolvedAttemptId = $resolution->attempt?->id === null ? null : (int) $resolution->attempt->id;
            $resolvedBookletSetId = $resolution->materialSet?->booklet_set_id === null
                ? null
                : (int) $resolution->materialSet->booklet_set_id;
            if (($sheet->admission_attempt_id !== null
                    && (int) $sheet->admission_attempt_id !== $resolvedAttemptId)
                || ($sheet->booklet_set_id !== null
                    && (int) $sheet->booklet_set_id !== $resolvedBookletSetId)) {
                $sheet->issues()->firstOrCreate(
                    [
                        'page_no' => 0,
                        'question_no' => null,
                        'issue_code' => 'replacement_material_identity_changed',
                        'state' => 'open',
                    ],
                    [
                        'severity' => 'required',
                        'evidence_json' => [
                            'prior_admission_attempt_id' => $sheet->admission_attempt_id,
                            'resolved_admission_attempt_id' => $resolvedAttemptId,
                            'prior_booklet_set_id' => $sheet->booklet_set_id,
                            'resolved_booklet_set_id' => $resolvedBookletSetId,
                        ],
                        'version' => 1,
                    ],
                );

                return;
            }
            $sheet->update([
                'admission_attempt_id' => $resolution->attempt?->id,
                'booklet_set_id' => $resolution->materialSet?->booklet_set_id,
                'set_resolution_reason' => 'submission_frozen_seat',
            ]);

            return;
        }

        $failure = $resolution->failure;
        $sheet->issues()->firstOrCreate(
            [
                'page_no' => 0,
                'question_no' => null,
                'issue_code' => $failure?->code ?? 'material_unresolved',
                'state' => 'open',
            ],
            [
                'severity' => 'required',
                'evidence_json' => $failure?->context ?? [],
                'version' => 1,
            ],
        );
    }

    private function flagInvalidBirthdate(OmrCaptureSheet $sheet, ?string $birthdate): void
    {
        if ($birthdate === null || $this->isValidMmDdYyyy($birthdate)) {
            return;
        }

        $sheet->issues()->create([
            'page_no' => 0,
            'issue_code' => 'birthdate_invalid',
            'severity' => 'required',
            'state' => 'open',
            'evidence_json' => [
                'provider' => 'nexiam.merge',
                'reason' => 'Detected birthdate is not a valid MMDDYYYY date.',
                'detected_birthdate' => $birthdate,
            ],
            'version' => 1,
        ]);
    }

    private function isValidMmDdYyyy(string $value): bool
    {
        if (preg_match('/^(\d{2})(\d{2})(\d{4})$/', $value, $matches) !== 1) {
            return false;
        }

        [$month, $day, $year] = [(int) $matches[1], (int) $matches[2], (int) $matches[3]];

        return $month >= 1 && $month <= 12
            && $day >= 1 && $day <= 31
            && $year >= 1900 && $year <= (int) now()->format('Y');
    }

    /**
     * @param  list<array<string, mixed>>  $proposals
     * @return array{proposal: array<string, mixed>, applicant_number: ?string, applicant_number_confidence: ?float, birthdate: ?string, sheet_confidence: ?float, identity_conflict: bool, per_page_applicant_numbers: array<string, mixed>, per_page_birthdates: array<string, mixed>}
     */
    private function mergeProposals(array $proposals): array
    {
        $items = [];
        $perArea = [];
        $applicantNumbers = [];
        $birthdates = [];
        $applicantConfidences = [];
        $confidences = [];
        $identityConflict = false;
        $perPageApplicantNumbers = [];
        $perPageBirthdates = [];
        $envelope = OmrDetectionRequest::ENVELOPE_VERSION;
        $provider = null;

        foreach ($proposals as $index => $proposal) {
            $envelope = (string) ($proposal['envelope'] ?? $envelope);
            $provider ??= $proposal['provider'] ?? null;
            $number = $proposal['applicant_number_proposal'] ?? null;
            if ($number !== null) {
                $applicantNumbers[] = (string) $number;
                $applicantConfidences[] = $proposal['applicant_number_confidence'] ?? null;
            }
            $perPageApplicantNumbers['page_'.($index + 1)] = $number;
            $birthdate = $proposal['birthdate_proposal'] ?? null;
            if ($birthdate !== null) {
                $birthdates[] = (string) $birthdate;
            }
            $perPageBirthdates['page_'.($index + 1)] = $birthdate;
            $confidences[] = $proposal['sheet_confidence'] ?? null;

            foreach ((array) ($proposal['items'] ?? []) as $item) {
                $items[(int) $item['position']] = $item;
            }
        }

        ksort($items);
        $items = array_values($items);

        $uniqueNumbers = array_values(array_unique($applicantNumbers));
        $uniqueBirthdates = array_values(array_unique($birthdates));
        if (count($uniqueNumbers) > 1 || count($uniqueBirthdates) > 1) {
            $identityConflict = true;
        }

        $byArea = [];
        foreach ($items as $item) {
            $byArea[(int) ($item['area_id'] ?? 0)][] = $item;
        }
        foreach ($byArea as $areaId => $areaItems) {
            $perArea[$areaId] = [
                'score' => count(array_filter($areaItems, fn (array $item): bool => (int) ($item['score'] ?? 0) === 1)),
                'confidence' => $this->averageConfidenceValues(array_map(
                    static fn (array $item): ?float => isset($item['confidence'])
                        ? (float) $item['confidence']
                        : null,
                    $areaItems,
                )),
                'area_code' => (string) ($areaItems[0]['area_code'] ?? ''),
            ];
        }

        $merged = [
            'envelope' => $envelope,
            'provider' => $provider,
            'input_hash' => null,
            'native_bundle_hash' => $proposals[0]['native_bundle_hash'] ?? null,
            'manifest_hash' => $proposals[0]['manifest_hash'] ?? null,
            'config_hash' => $proposals[0]['config_hash'] ?? null,
            'patch_identity' => $proposals[0]['patch_identity'] ?? null,
            'image' => null,
            'field_block_shifts' => [],
            'aligned_image' => null,
            'applicant_number_proposal' => $uniqueNumbers === [] ? null : $uniqueNumbers[0],
            'applicant_number_confidence' => $uniqueNumbers === [] ? 0.0 : $this->minConfidence($applicantConfidences),
            'birthdate_proposal' => $uniqueBirthdates === [] ? null : $uniqueBirthdates[0],
            'sheet_confidence' => $this->minConfidence($confidences),
            'per_area_scores' => $perArea,
            'items' => $items,
            'pages' => count($proposals),
        ];

        return [
            'proposal' => $merged,
            'applicant_number' => $uniqueNumbers === [] ? null : $uniqueNumbers[0],
            'applicant_number_confidence' => $uniqueNumbers === [] ? null : $this->minConfidence($applicantConfidences),
            'birthdate' => $uniqueBirthdates === [] ? null : $uniqueBirthdates[0],
            'sheet_confidence' => $merged['sheet_confidence'],
            'identity_conflict' => $identityConflict,
            'per_page_applicant_numbers' => $perPageApplicantNumbers,
            'per_page_birthdates' => $perPageBirthdates,
        ];
    }

    private function fail(
        int $runId,
        string $claimToken,
        bool $retryable,
        string $safeCode,
        ?OmrDetectionResult $result = null,
    ): OmrProcessingRun {
        return DB::transaction(function () use ($runId, $claimToken, $retryable, $safeCode, $result): OmrProcessingRun {
            $run = OmrProcessingRun::query()->lockForUpdate()->findOrFail($runId);
            $sheet = OmrCaptureSheet::query()->lockForUpdate()->findOrFail($run->sheet_id);
            $page = $this->pageFor($run, $sheet);

            if ($run->status !== OmrProcessingRun::STATUS_CLAIMED
                || ! hash_equals((string) $run->claim_token, $claimToken)) {
                return $run;
            }

            if (! $this->pageIsCurrent($run, $sheet, $page)) {
                $run->forceFill([
                    'status' => OmrProcessingRun::STATUS_SUPERSEDED,
                    'diagnostic_code' => 'stale_page_revision',
                    'claim_token' => null,
                    'claim_expires_at' => null,
                    'next_retry_at' => null,
                    'duration_ms' => $result?->timings !== null ? (int) round($result->timings->detectionMs) : null,
                    'timings_json' => $result?->timings !== null ? [
                        'detection_ms' => $result->timings->detectionMs,
                        'preprocess_ms' => $result->timings->preprocessMs,
                    ] : null,
                    'output_json' => [
                        'outcome' => $result?->outcome->value ?? 'superseded',
                        'superseded' => true,
                    ],
                ])->save();

                return $run;
            }

            $current = true;

            $exhausted = (int) $run->attempts >= (int) config('omr.retry.tries', 3);
            $willRetry = $retryable && ! $exhausted;

            $run->update([
                'status' => $willRetry
                    ? OmrProcessingRun::STATUS_RETRYABLE_FAILED
                    : OmrProcessingRun::STATUS_TERMINAL_FAILED,
                'claim_token' => null,
                'claim_expires_at' => null,
                'next_retry_at' => $willRetry ? now()->addSeconds($this->backoffFor((int) $run->attempts)) : null,
                'diagnostic_code' => $safeCode,
                'duration_ms' => $result?->timings !== null ? (int) round($result->timings->detectionMs) : null,
                'timings_json' => $result?->timings !== null ? [
                    'detection_ms' => $result->timings->detectionMs,
                    'preprocess_ms' => $result->timings->preprocessMs,
                ] : null,
                'output_json' => ['outcome' => $result?->outcome->value ?? 'retryable_failure'],
            ]);

            if ($current) {
                if ($willRetry) {
                    $page->update(['status' => OmrCaptureSheetPage::STATUS_QUEUED]);
                    $sheet->update(['status' => OmrCaptureSheet::STATUS_QUEUED]);
                } else {
                    $page->update(['status' => OmrCaptureSheetPage::STATUS_FAILED_TERMINAL]);
                    $sheet->update([
                        'status' => OmrCaptureSheet::STATUS_FAILED_TERMINAL,
                        'version' => (int) $sheet->version + 1,
                        'proposal_json' => [
                            'envelope' => OmrDetectionRequest::ENVELOPE_VERSION,
                            'abstained' => true,
                            'reason' => 'provider_failed',
                            'safe_error' => $safeCode,
                            'page_no' => (int) $run->page_no,
                        ],
                    ]);
                }
            }
            $this->appendPageRevisionSnapshot($sheet, $page, $run->actor_id === null ? null : (int) $run->actor_id);

            $this->recordAuditEvent->handle('grading.omr_sheet_analysis_failed', [
                'sheet_id' => $sheet->id,
                'batch_id' => $sheet->batch_id,
                'processing_run_id' => $run->id,
                'page_no' => (int) $run->page_no,
                'safe_error' => $safeCode,
                'retrying' => $willRetry,
            ], (string) ($run->actor_id ?? 'system'));

            return $run;
        });
    }

    private function terminalizeUnauthorized(
        OmrProcessingRun $run,
        OmrCaptureSheet $sheet,
        OmrCaptureSheetPage|OmrCaptureSheet $page,
        string $code,
    ): void {
        $run->update([
            'status' => OmrProcessingRun::STATUS_TERMINAL_FAILED,
            'diagnostic_code' => $code,
            'claim_token' => null,
            'claim_expires_at' => null,
            'next_retry_at' => null,
        ]);
        $page->update(['status' => OmrCaptureSheetPage::STATUS_FAILED_TERMINAL]);
        $sheet->update([
            'status' => OmrCaptureSheet::STATUS_FAILED_TERMINAL,
            'version' => (int) $sheet->version + 1,
            'proposal_json' => [
                'envelope' => OmrDetectionRequest::ENVELOPE_VERSION,
                'abstained' => true,
                'reason' => 'authorization_failed',
                'safe_error' => $code,
            ],
        ]);
        $this->appendPageRevisionSnapshot(
            $sheet,
            $page,
            $run->actor_id === null ? null : (int) $run->actor_id,
        );
    }

    /** @param array<string, mixed> $snapshotReviewManifest */
    private function pageManifest(OmrPinnedSnapshot $snapshot, int $pageNo): array
    {
        $manifest = $snapshot->reviewManifest;
        $fields = array_values(array_filter(
            (array) ($manifest['fields'] ?? []),
            static fn (array $field): bool => (int) ($field['page'] ?? 1) === $pageNo,
        ));
        $manifest['fields'] = $fields;

        return $manifest;
    }

    private function pageFor(OmrProcessingRun $run, OmrCaptureSheet $sheet): OmrCaptureSheetPage|OmrCaptureSheet
    {
        if ((int) $sheet->expected_page_count <= 1 && $sheet->pages()->doesntExist()) {
            return $sheet;
        }

        $page = $sheet->pages()->where('page_no', (int) $run->page_no)->first();

        if ($page === null) {
            throw new RuntimeException("OMR page {$run->page_no} is missing for sheet {$sheet->id}.");
        }

        return $page;
    }

    private function appendPageRevisionSnapshot(
        OmrCaptureSheet $sheet,
        OmrCaptureSheetPage|OmrCaptureSheet $page,
        ?int $actorUserId,
    ): void {
        if (! $page instanceof OmrCaptureSheetPage) {
            return;
        }

        $page->setRelation('sheet', $sheet);
        $sourceRevisionId = $page->current_revision_id === null
            ? null
            : (int) $page->current_revision_id;
        if ($sourceRevisionId === null && (int) $page->current_revision !== 1) {
            throw new RuntimeException('omr_page_revision_history_missing');
        }

        $revision = OmrCaptureSheetPageRevision::capture(
            $page,
            $sourceRevisionId === null
                ? OmrCaptureSheetPageRevision::KIND_SOURCE
                : OmrCaptureSheetPageRevision::KIND_ANALYSIS,
            sourceRevisionId: $sourceRevisionId,
            actorUserId: $actorUserId,
        );
        $page->forceFill(['current_revision_id' => $revision->getKey()])->save();
    }

    private function pageIsCurrent(OmrProcessingRun $run, OmrCaptureSheet $sheet, OmrCaptureSheetPage|OmrCaptureSheet $page): bool
    {
        if ((int) $page->current_processing_run_id !== (int) $run->id
            || (int) $sheet->version !== (int) $run->expected_sheet_version) {
            return false;
        }

        return $page instanceof OmrCaptureSheet
            ? (int) $run->page_revision === 1
            : (int) $run->page_revision === (int) $page->current_revision;
    }

    private function setPageProcessing(OmrCaptureSheetPage|OmrCaptureSheet $page, OmrProcessingRun $run): void
    {
        if ($page instanceof OmrCaptureSheet) {
            $page->forceFill(['status' => OmrCaptureSheet::STATUS_PROCESSING, 'current_processing_run_id' => $run->id])->save();

            return;
        }

        $page->update(['status' => OmrCaptureSheetPage::STATUS_PROCESSING, 'current_processing_run_id' => $run->id]);
    }

    private function assertAuthorized(User $actor, OmrCaptureSheet $sheet): void
    {
        $profile = $actor->staffProfile;
        if (! $profile instanceof StaffProfile || ! $profile->isActive()) {
            throw new RuntimeException('The OMR processing actor is no longer active.');
        }

        $cycle = $sheet->batch->gradingSession?->examSession?->academicCycle;
        if ($cycle === null) {
            throw new RuntimeException('The OMR processing scope is unavailable.');
        }

        $decision = $this->authorizer->decide($actor, CapabilityCatalog::GUIDANCE_SCORING_OMR, [
            'office' => 'guidance',
            'institution_id' => (int) $cycle->institution_id,
            'cycle' => (string) $cycle->code,
        ]);

        if (! $decision->allowed) {
            throw new RuntimeException('The OMR processing capability or scope was revoked.');
        }
    }

    /** @return array<string, mixed>|null */
    private function kitContextForSheet(OmrCaptureSheet $sheet): ?array
    {
        $sheet->loadMissing('batch.gradingSession.examSession.currentMaterialSnapshot.testKitVersion');
        $materialSnapshot = $sheet->batch->gradingSession?->examSession?->currentMaterialSnapshot;
        if ($materialSnapshot === null || $materialSnapshot->test_kit_version_id === null) {
            return null;
        }

        $kitVersion = $materialSnapshot->testKitVersion;
        if (! $kitVersion instanceof TestKitVersion
            || (int) $kitVersion->id !== (int) $materialSnapshot->test_kit_version_id) {
            throw new OmrTemplateProjectionException(
                'pair_geometry_coverage_mismatch',
                'The frozen Test Kit version for this OMR sitting is unavailable.',
            );
        }
        $context = app(KitCompositionContext::class)->resolveVersion($kitVersion);
        if ((int) $context['test_kit_version_id'] !== (int) $materialSnapshot->test_kit_version_id
            || (string) $context['manifest_sha256'] !== (string) $materialSnapshot->manifest_sha256
            || (int) $context['template_version_id'] !== (int) $materialSnapshot->template_version_id
            || (int) $context['booklet_version_id'] !== (int) $materialSnapshot->booklet_version_id
            || (int) $context['test_instrument_version_id'] !== (int) $materialSnapshot->test_instrument_version_id
            || (int) ($context['version']->selected_acceptance_receipt_id ?? 0) !== (int) $materialSnapshot->acceptance_receipt_id) {
            throw new OmrTemplateProjectionException(
                'pair_geometry_coverage_mismatch',
                'The frozen Test Kit composition does not match the material snapshot.',
            );
        }

        return $context;
    }

    /** @return list<string> */
    private function analysisRelations(): array
    {
        return [
            'batch.gradingSession.examSession.academicCycle',
            'batch.templateVersion.areas.aptitudeArea',
            'batch.bookletVersion.questions.aptitudeArea',
            'admissionAttempt',
            'pages',
        ];
    }

    /** @return array<string, mixed> */
    private function project(
        OmrDetectionRequest $request,
        OmrDetectionSuccess $success,
        OmrPinnedSnapshot $snapshot,
        int $pageRevision,
    ): array {
        if ($this->hasRequiredMisalignment($success)) {
            return $this->misalignedProposal($request, $success);
        }

        $items = array_map(function (OmrDetectedItem $item) use ($snapshot, $request, $pageRevision): array {
            $correctOption = $snapshot->correctOptionsByPosition[$item->position] ?? null;
            $score = $item->selectedOption !== null && $correctOption !== null && $item->selectedOption === $correctOption ? 1 : 0;

            return [
                'position' => $item->position,
                'area_code' => $item->areaCode,
                'area_id' => $snapshot->formalSubtestIdsByPosition[$item->position] ?? ($snapshot->areaIdsByCode[$item->areaCode] ?? null),
                'question_no' => $item->questionNo,
                'detected_answer' => $item->selectedOption,
                'correct_option_code' => $correctOption,
                'state' => $item->state,
                'confidence' => $item->confidence,
                'region' => $item->region->toArray(),
                'observed_options' => $item->observedOptions,
                'option_observations' => array_map(
                    static fn ($observation): array => $observation->toArray(),
                    $item->optionObservations,
                ),
                'issue_codes' => $item->issueCodes,
                'legacy_evidence_limited' => $item->legacyEvidenceLimited,
                'evidence_hash' => hash('sha256', $item->evidenceHash."\0".$request->inputHash."\0".$pageRevision),
                'score' => $score,
            ];
        }, $success->items);

        $perArea = [];
        $byArea = [];
        foreach ($success->items as $item) {
            $byArea[$item->areaCode][] = $item;
        }

        foreach ($byArea as $areaCode => $areaItems) {
            $areaId = $snapshot->areaIdsByCode[$areaCode] ?? null;
            if ($areaId === null) {
                continue;
            }

            $score = count(array_filter($areaItems, function (OmrDetectedItem $item) use ($snapshot): bool {
                $correctOption = $snapshot->correctOptionsByPosition[$item->position] ?? null;

                return $item->selectedOption !== null && $correctOption !== null && $item->selectedOption === $correctOption;
            }));

            $perArea[$areaId] = [
                'score' => $score,
                'confidence' => $this->averageConfidence($areaItems),
                'area_code' => $areaCode,
            ];
        }

        return [
            'envelope' => $request->envelopeVersion,
            'provider' => [
                'name' => $success->engineName,
                'version' => $success->engineVersion,
                'commit' => $success->engineCommit,
                'calibration_status' => $success->calibrationStatus,
            ],
            'input_hash' => $success->inputHash,
            'native_bundle_hash' => $request->nativeBundleHash,
            'manifest_hash' => $request->manifestHash,
            'config_hash' => $success->configHash,
            'provider_diagnostics' => $success->providerDiagnostics,
            'patch_identity' => $request->patchIdentity,
            'image' => [
                'mime' => $success->imageMime,
                'width' => $success->imageWidth,
                'height' => $success->imageHeight,
            ],
            'field_block_shifts' => $success->fieldBlockShifts,
            'aligned_image' => $success->alignedImage === null ? null : [
                'mime' => $success->alignedImage['mime'] ?? null,
                'width' => $success->alignedImage['width'] ?? null,
                'height' => $success->alignedImage['height'] ?? null,
                'hash' => $success->alignedImage['hash'] ?? null,
            ],
            'applicant_number_proposal' => $success->applicantNumberProposal,
            'applicant_number_confidence' => $success->applicantNumberConfidence,
            'birthdate_proposal' => $success->birthdateProposal,
            'sheet_confidence' => $success->sheetConfidence,
            'per_area_scores' => $perArea,
            'items' => $items,
        ];
    }

    private function hasRequiredMisalignment(OmrDetectionSuccess $success): bool
    {
        foreach ($success->issues as $issue) {
            if ($issue->code === OmrDetectionIssue::CODE_SHEET_MISALIGNED
                && $issue->severity === OmrDetectionIssue::SEVERITY_REQUIRED) {
                return true;
            }
        }

        return false;
    }

    /**
     * Fail-closed proposal for unsafe alignment: keep the envelope, provider
     * identity, evidence hashes, and observed field-block shifts, but never
     * persist answer or score proposals built from shifted bubble reads.
     * Confidence stays unqualified and identity proposals are withheld so
     * review resolves them instead of binding a misread number.
     *
     * @return array<string, mixed>
     */
    private function misalignedProposal(OmrDetectionRequest $request, OmrDetectionSuccess $success): array
    {
        return [
            'envelope' => $request->envelopeVersion,
            'provider' => [
                'name' => $success->engineName,
                'version' => $success->engineVersion,
                'commit' => $success->engineCommit,
                'calibration_status' => $success->calibrationStatus,
            ],
            'input_hash' => $success->inputHash,
            'native_bundle_hash' => $request->nativeBundleHash,
            'manifest_hash' => $request->manifestHash,
            'config_hash' => $success->configHash,
            'provider_diagnostics' => $success->providerDiagnostics,
            'patch_identity' => $request->patchIdentity,
            'image' => [
                'mime' => $success->imageMime,
                'width' => $success->imageWidth,
                'height' => $success->imageHeight,
            ],
            'field_block_shifts' => $success->fieldBlockShifts,
            'aligned_image' => $success->alignedImage === null ? null : [
                'mime' => $success->alignedImage['mime'] ?? null,
                'width' => $success->alignedImage['width'] ?? null,
                'height' => $success->alignedImage['height'] ?? null,
                'hash' => $success->alignedImage['hash'] ?? null,
            ],
            'applicant_number_proposal' => null,
            'applicant_number_confidence' => null,
            'birthdate_proposal' => null,
            'sheet_confidence' => null,
            'per_area_scores' => [],
            'items' => [],
            'abstained' => true,
            'reason' => 'sheet_misaligned',
        ];
    }

    /** @return array<string, mixed> */
    private function alignedFields(OmrDetectionSuccess $success): array
    {
        if (! is_array($success->alignedImage)) {
            return [];
        }

        $alignedBytes = (string) $success->alignedImage['bytes'];
        $alignedPath = $this->assetStore->store(
            $alignedBytes,
            'omr/aligned/'.Str::uuid()->toString().'.bin',
        );

        return [
            'aligned_mime' => (string) $success->alignedImage['mime'],
            'aligned_bytes' => strlen($alignedBytes),
            'aligned_width' => (int) $success->alignedImage['width'],
            'aligned_height' => (int) $success->alignedImage['height'],
            'aligned_hash' => (string) $success->alignedImage['hash'],
            'aligned_asset_path' => $alignedPath,
            'aligned_asset_hmac' => $this->assetStore->hmac($alignedBytes),
            'aligned_asset_hmac_key_id' => (string) config('omr.keyring.active_key_id'),
            'aligned_asset_key_id' => (string) config('omr.keyring.active_key_id'),
        ];
    }

    /** @param list<OmrDetectedItem> $items */
    private function averageConfidence(array $items): ?float
    {
        $sum = 0.0;
        foreach ($items as $item) {
            if ($item->confidence === null) {
                return null;
            }
            $sum += $item->confidence;
        }

        return $items === [] ? null : round($sum / count($items), 4);
    }

    /** @param list<?float> $confidences */
    private function averageConfidenceValues(array $confidences): ?float
    {
        $sum = 0.0;
        foreach ($confidences as $confidence) {
            if ($confidence === null) {
                return null;
            }
            $sum += $confidence;
        }

        return $confidences === [] ? null : round($sum / count($confidences), 4);
    }

    /** @param list<?float> $confidences */
    private function minConfidence(array $confidences): ?float
    {
        $values = array_values(array_filter($confidences, fn ($value): bool => $value !== null));

        return $values === [] ? null : round(min($values), 4);
    }

    /** @return array{0: ?int, 1: ?int} */
    private function imageDimensions(string $bytes): array
    {
        $info = getimagesizefromstring($bytes);

        if ($info === false) {
            return [null, null];
        }

        return [(int) $info[0], (int) $info[1]];
    }

    private function cropPolicy(?string $cropPolicy): string
    {
        $cropPolicy ??= 'auto_document_quad';
        if (! in_array($cropPolicy, ['auto_document_quad', 'keep_original'], true)) {
            throw new RuntimeException('The stored OMR crop policy is unsupported.');
        }

        return $cropPolicy;
    }

    private function backoffFor(int $attempts): int
    {
        $backoff = array_map('intval', (array) config('omr.retry.backoff', [5, 20, 60]));

        return $backoff[($attempts - 1 + count($backoff)) % count($backoff)] ?? 60;
    }
}
