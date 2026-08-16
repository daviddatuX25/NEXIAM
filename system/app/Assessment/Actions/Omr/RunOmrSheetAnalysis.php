<?php

namespace App\Assessment\Actions\Omr;

use App\Assessment\Omr\OmrDetectionOutcome;
use App\Assessment\Omr\OmrDetectionPort;
use App\Assessment\Omr\OmrDetectionRequest;
use App\Assessment\Omr\OmrDetectionResult;
use App\Assessment\Omr\OmrDetectionResultValidator;
use App\Assessment\Omr\OmrDetectionSuccess;
use App\Assessment\Omr\OmrDetectedItem;
use App\Assessment\Omr\OmrInvalidDetectionOutput;
use App\Assessment\Omr\OmrItemGeometry;
use App\Assessment\Omr\OmrPinnedSnapshot;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\OmrCaptureSheet;
use App\Models\Assessment\OmrCaptureSheetPage;
use App\Models\Assessment\OmrProcessingRun;
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
        private readonly OmrDetectionPort $detector,
        private readonly OmrDetectionResultValidator $validator,
        private readonly CapabilityAuthorizer $authorizer,
        private readonly RecordAuditEvent $recordAuditEvent,
        private readonly SensitiveAssetStore $assetStore,
    ) {
    }

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
            $result = $this->detector->detect($request);
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

        $snapshot = OmrPinnedSnapshot::pin(
            $sheet->batch->templateVersion,
            $sheet->batch->bookletVersion,
        );

        return $this->persistSuccess(
            $run->id,
            $claimToken,
            $actor,
            $request,
            $result,
            $result->success,
            $snapshot,
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

            $run->update([
                'status' => OmrProcessingRun::STATUS_TERMINAL_FAILED,
                'diagnostic_code' => $safeCode,
                'claim_token' => null,
                'claim_expires_at' => null,
                'next_retry_at' => null,
            ]);

            if ($sheet !== null && $this->pageIsCurrent($run, $sheet, $page)) {
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
            }
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
                $run->update([
                    'status' => OmrProcessingRun::STATUS_TERMINAL_FAILED,
                    'diagnostic_code' => 'stale_run',
                    'claim_token' => null,
                    'claim_expires_at' => null,
                ]);

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
        $snapshot = OmrPinnedSnapshot::pin(
            $sheet->batch->templateVersion,
            $sheet->batch->bookletVersion,
        );
        $pageNo = (int) $run->page_no;
        $bytes = $this->assetStore->read((string) $page->asset_path, (string) $page->asset_hmac);
        $inputHash = hash('sha256', $bytes);
        [$imageWidth, $imageHeight] = $this->imageDimensions($bytes);

        if ($imageWidth === null || $imageHeight === null) {
            throw new RuntimeException('The uploaded OMR page image cannot be decoded.');
        }

        $expected = [
            'input_hash' => [$inputHash, (string) $run->input_hash],
            'native_bundle_hash' => [$snapshot->nativeBundleHash, (string) $run->native_bundle_hash],
            'manifest_hash' => [$snapshot->manifestHash, (string) $run->manifest_hash],
            'config_hash' => [$snapshot->configHash, (string) $run->config_hash],
            'booklet_hash' => [(string) $sheet->batch->bookletVersion->answer_key_hash, (string) $run->booklet_hash],
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

        return new OmrDetectionRequest(
            envelopeVersion: OmrDetectionRequest::ENVELOPE_VERSION,
            runId: (string) $run->run_uuid,
            correlationId: 'omr:run:'.$run->run_uuid.':page:'.$pageNo,
            imageBytes: $bytes,
            inputHash: $inputHash,
            imageMime: (string) $page->original_mime,
            imageWidth: $imageWidth,
            imageHeight: $imageHeight,
            areas: $snapshot->areas,
            items: $pageItems,
            identityRegions: $snapshot->pageIdentityRegions[$pageNo] ?? $snapshot->identityRegions,
            nativeBundleHash: $snapshot->nativeBundleHash,
            manifestHash: $snapshot->manifestHash,
            configHash: $snapshot->configHash,
            bookletHash: $snapshot->bookletHash,
            nativeTemplate: $snapshot->pageTemplates[$pageNo] ?? $snapshot->nativeTemplate,
            nativeConfig: $snapshot->nativeConfig,
            nativeBundleMetadata: $snapshot->nativeBundleMetadata,
            reviewManifest: $snapshot->reviewManifest,
            engineName: (string) $run->provider_name,
            engineCommit: $run->provider_commit !== null ? (string) $run->provider_commit : null,
            patchIdentity: $snapshot->patchIdentity,
            expectedFieldLabels: $snapshot->expectedFieldLabels,
            expectedOptionCodes: $snapshot->expectedOptionCodes,
            providerDeadline: (float) config('omr.provider.deadline_seconds', 30),
            pageNo: $pageNo,
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
    ): OmrProcessingRun {
        $proposal = $this->project($request, $success, $snapshot);
        $alignedFields = $this->alignedFields($success);
        $alignedPath = $alignedFields['aligned_asset_path'] ?? null;

        try {
            return DB::transaction(function () use ($runId, $claimToken, $actor, $request, $result, $success, $proposal, $alignedFields): OmrProcessingRun {
                $run = OmrProcessingRun::query()->lockForUpdate()->findOrFail($runId);
                $sheet = OmrCaptureSheet::query()->with($this->analysisRelations())->lockForUpdate()->findOrFail($run->sheet_id);
                $page = $this->pageFor($run, $sheet);

                if (! hash_equals((string) $run->claim_token, $claimToken)
                    || $run->status !== OmrProcessingRun::STATUS_CLAIMED
                    || ! $this->pageIsCurrent($run, $sheet, $page)) {
                    $run->update([
                        'status' => OmrProcessingRun::STATUS_TERMINAL_FAILED,
                        'diagnostic_code' => 'stale_result',
                        'claim_token' => null,
                        'claim_expires_at' => null,
                    ]);

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
                    ],
                ]);

                if ((int) $sheet->expected_page_count <= 1) {
                    $sheet->update([
                        'status' => OmrCaptureSheet::STATUS_NEEDS_REVIEW,
                        'version' => (int) $sheet->version + 1,
                        'sheet_confidence' => $success->sheetConfidence,
                        'proposal_json' => $proposal,
                        'detected_applicant_number' => $success->applicantNumberProposal,
                        'detected_applicant_confidence' => $success->applicantNumberProposal !== null
                            ? $success->applicantNumberConfidence
                            : null,
                        'detected_birthdate' => $success->birthdateProposal,
                    ]);
                    $this->flagInvalidBirthdate($sheet, $success->birthdateProposal);
                } else {
                    $this->maybeMerge($sheet);
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

                return $run;
            });
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
     * @param list<array<string, mixed>> $proposals
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
            $byArea[(string) ($item['area_code'] ?? '')][] = $item;
        }
        foreach ($byArea as $areaCode => $areaItems) {
            $perArea[(int) $areaItems[0]['area_id']] = [
                'score' => count(array_filter($areaItems, fn (array $item): bool => (int) ($item['score'] ?? 0) === 1)),
                'confidence' => $this->averageConfidence(array_map(
                    fn (array $item): OmrDetectedItem => new OmrDetectedItem(
                        position: (int) $item['position'],
                        areaCode: (string) $item['area_code'],
                        questionNo: (int) $item['question_no'],
                        selectedOption: $item['detected_answer'] ?? null,
                        state: (string) $item['state'],
                        confidence: $item['confidence'] ?? null,
                        region: \App\Assessment\Omr\OmrRegion::fromArray((array) $item['region']),
                    ),
                    $areaItems,
                )),
                'area_code' => $areaCode,
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

            $current = $this->pageIsCurrent($run, $sheet, $page);
            $exhausted = (int) $run->attempts >= (int) config('omr.retry.tries', 3);
            $willRetry = $retryable && ! $exhausted && $current;

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

    private function pageIsCurrent(OmrProcessingRun $run, OmrCaptureSheet $sheet, OmrCaptureSheetPage|OmrCaptureSheet $page): bool
    {
        return (int) $page->current_processing_run_id === (int) $run->id
            && (int) $sheet->version === (int) $run->expected_sheet_version;
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

    /** @return list<string> */
    private function analysisRelations(): array
    {
        return [
            'batch.gradingSession.examSession.academicCycle',
            'batch.templateVersion.areas.aptitudeArea',
            'batch.templateVersion.areas.questions.options',
            'batch.bookletVersion.questions.templateQuestion',
            'batch.bookletVersion.questions.templateQuestion.options',
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
    ): array {
        $items = array_map(function (OmrDetectedItem $item) use ($snapshot): array {
            $correctOption = $snapshot->correctOptionsByPosition[$item->position] ?? null;
            $score = $item->selectedOption !== null && $correctOption !== null && $item->selectedOption === $correctOption ? 1 : 0;

            return [
                'position' => $item->position,
                'area_code' => $item->areaCode,
                'area_id' => $snapshot->areaIdsByCode[$item->areaCode] ?? null,
                'question_no' => $item->questionNo,
                'detected_answer' => $item->selectedOption,
                'correct_option_code' => $correctOption,
                'state' => $item->state,
                'confidence' => $item->confidence,
                'region' => $item->region->toArray(),
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

    private function backoffFor(int $attempts): int
    {
        $backoff = array_map('intval', (array) config('omr.retry.backoff', [5, 20, 60]));

        return $backoff[($attempts - 1 + count($backoff)) % count($backoff)] ?? 60;
    }
}
