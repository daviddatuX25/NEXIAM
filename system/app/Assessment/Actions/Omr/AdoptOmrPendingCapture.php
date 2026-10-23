<?php

namespace App\Assessment\Actions\Omr;

use App\Admission\Support\CommandIdempotency;
use App\Assessment\Omr\Canonical\OmrBlankInstanceService;
use App\Assessment\Omr\OmrCaptureResolver;
use App\Assessment\Omr\OmrPendingCaptureAccess;
use App\Assessment\Omr\OmrPinnedSnapshot;
use App\Assessment\Omr\OmrResolutionRequest;
use App\Assessment\Support\KitCompositionContext;
use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\GradingSession;
use App\Models\Assessment\OmrBlankInstance;
use App\Models\Assessment\OmrCaptureBatch;
use App\Models\Assessment\OmrCaptureSheet;
use App\Models\Assessment\OmrCaptureSheetPage;
use App\Models\Assessment\OmrPendingCapture;
use App\Models\Assessment\OmrProcessingRun;
use App\Models\Assessment\TestKitVersion;
use App\Models\Foundation\Operation;
use App\Models\User;
use App\Support\SensitiveAssetStore;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

use function array_values;
use function hash;
use function is_array;
use function is_numeric;
use function is_string;
use function json_encode;
use function trim;

final class AdoptOmrPendingCapture
{
    public function __construct(
        private readonly CapabilityAuthorizer $authorizer,
        private readonly CommandIdempotency $idempotency,
        private readonly OmrPendingCaptureAccess $pendingAccess,
        private readonly OmrCaptureResolver $captureResolver,
        private readonly OmrBlankInstanceService $instances,
        private readonly SensitiveAssetStore $assetStore,
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {}

    public function handle(
        OmrPendingCapture $pending,
        User $actor,
        int $expectedVersion,
        string $idempotencyKey,
    ): OmrCaptureSheet {
        $this->assertCapability($actor);
        $idempotencyKey = trim($idempotencyKey);
        if ($expectedVersion < 0 || $idempotencyKey === '') {
            throw new RuntimeException('omr_pending_capture_version_required');
        }

        $pending = OmrPendingCapture::query()
            ->with([
                'templateVersion.areas.aptitudeArea',
                'blankInstance.pages',
                'blankInstancePage.instance',
                'gradingSession.examSession.academicCycle',
            ])
            ->findOrFail($pending->id);
        $this->pendingAccess->assertPending($actor, $pending, $pending->gradingSession);
        if ($pending->status !== OmrPendingCapture::STATUS_READY) {
            throw new RuntimeException('omr_pending_capture_not_ready');
        }
        if ((int) $pending->version !== $expectedVersion) {
            throw new RuntimeException('omr_pending_capture_stale_version');
        }
        if ($pending->expires_at !== null && $pending->expires_at->isPast()) {
            throw new RuntimeException('omr_pending_capture_expired');
        }
        if ($pending->grading_session_id === null || $pending->template_version_id === null) {
            throw new RuntimeException('omr_pending_capture_resolution_incomplete');
        }

        $operation = $this->idempotency->claim(
            $actor,
            'grading.omr.adopt_pending_capture',
            (string) $pending->id,
            [
                'pending_capture_id' => (int) $pending->id,
                'pending_version' => $expectedVersion,
                'grading_session_id' => (int) $pending->grading_session_id,
            ],
            $idempotencyKey,
        );
        $existingSheetId = is_array($operation->payload['result'] ?? null)
            ? (int) ($operation->payload['result']['sheet_id'] ?? 0)
            : 0;
        if (! $operation->wasRecentlyCreated && $existingSheetId > 0) {
            $this->pendingAccess->assertPending($actor, $pending);

            return OmrCaptureSheet::query()->findOrFail($existingSheetId);
        }
        if (! $operation->wasRecentlyCreated
            && $operation->status === Operation::STATUS_PROCESSING
            && $operation->updated_at !== null
            && $operation->updated_at->isAfter(now()->subMinutes(5))) {
            throw new RuntimeException('omr_scanner_adoption_in_progress');
        }
        $operation->forceFill([
            'status' => Operation::STATUS_PROCESSING,
            'attempts' => (int) $operation->attempts + 1,
            'finished_at' => null,
            'last_error' => null,
        ])->save();

        $sheet = null;
        try {
            $sheet = DB::transaction(function () use ($pending, $actor, $expectedVersion): OmrCaptureSheet {
                $lockedPending = OmrPendingCapture::query()
                    ->lockForUpdate()
                    ->with([
                        'templateVersion.areas.aptitudeArea',
                        'blankInstance.pages',
                        'blankInstancePage.instance',
                        'gradingSession.examSession.academicCycle',
                    ])
                    ->findOrFail($pending->id);
                $this->pendingAccess->assertPending($actor, $lockedPending, $lockedPending->gradingSession);
                if ((int) $lockedPending->version !== $expectedVersion
                    || $lockedPending->status !== OmrPendingCapture::STATUS_READY) {
                    if ($lockedPending->status === OmrPendingCapture::STATUS_ADOPTED && $lockedPending->adopted_sheet_id !== null) {
                        return OmrCaptureSheet::query()->findOrFail($lockedPending->adopted_sheet_id);
                    }
                    throw new RuntimeException('omr_pending_capture_stale_version');
                }
                if ($lockedPending->expires_at !== null && $lockedPending->expires_at->isPast()) {
                    throw new RuntimeException('omr_pending_capture_expired');
                }
                if (! $lockedPending->blankInstance instanceof OmrBlankInstance) {
                    throw new RuntimeException('qr_missing: Issued Answer Sheet provenance is required before adoption.');
                }
                try {
                    $this->assetStore->read((string) $lockedPending->asset_path, (string) $lockedPending->asset_hmac);
                    if ($lockedPending->aligned_asset_path !== null && $lockedPending->aligned_asset_hmac !== null) {
                        $this->assetStore->read((string) $lockedPending->aligned_asset_path, (string) $lockedPending->aligned_asset_hmac);
                    }
                } catch (Throwable) {
                    throw new RuntimeException('omr_pending_capture_asset_integrity_failed');
                }

                $session = GradingSession::query()
                    ->with('examSession.academicCycle')
                    ->lockForUpdate()
                    ->findOrFail($lockedPending->grading_session_id);
                $this->pendingAccess->assertPending($actor, $lockedPending, $session);
                $examSession = $session->examSession;
                if ($examSession === null) {
                    throw new RuntimeException('session_missing');
                }
                $this->assertBlankInstanceScope($lockedPending->blankInstance, $session);

                $batch = OmrCaptureBatch::query()
                    ->where('grading_session_id', $session->id)
                    ->whereIn('status', OmrCaptureBatch::ACTIVE_STATUSES)
                    ->with([
                        'gradingSession.examSession.currentMaterialSnapshot',
                        'templateVersion.areas.aptitudeArea',
                        'bookletVersion.questions.aptitudeArea',
                        'bookletVersion.questions.templateQuestion.options',
                    ])
                    ->lockForUpdate()
                    ->first();
                if ($batch === null) {
                    throw new RuntimeException('session_missing: The active OMR capture batch is no longer available.');
                }
                if ((int) $batch->template_version_id !== (int) $lockedPending->template_version_id) {
                    throw new RuntimeException('template_mismatch: The active batch uses another immutable template version.');
                }
                if ($batch->templateVersion === null || $batch->bookletVersion === null) {
                    throw new RuntimeException('template_mismatch: The active batch is missing its immutable material pins.');
                }

                $resolution = $this->captureResolver->resolve(new OmrResolutionRequest(
                    session: $session,
                    applicantNumber: $lockedPending->detected_applicant_number,
                    pageNo: (int) $lockedPending->page_no,
                    qrPayload: $lockedPending->qr_payload,
                    blankInstance: $lockedPending->blankInstance,
                    blankPage: $lockedPending->blankInstancePage,
                ));
                if (! $resolution->isResolved()) {
                    $code = $resolution->failureCode() ?? 'session_unresolved';
                    throw new RuntimeException($code.': '.($resolution->failure?->message ?? 'The capture could not be resolved.'));
                }
                if ($resolution->materialSet === null || $resolution->snapshot === null || $resolution->attempt === null) {
                    throw new RuntimeException('session_unresolved: The frozen material chain is incomplete.');
                }

                $duplicate = OmrCaptureSheet::query()
                    ->where('batch_id', $batch->id)
                    ->whereNotIn('status', [OmrCaptureSheet::STATUS_FAILED_TERMINAL])
                    ->where(function ($query) use ($resolution, $lockedPending): void {
                        $query->where('admission_attempt_id', (int) $resolution->attempt->id)
                            ->orWhere('detected_applicant_number', (string) $lockedPending->detected_applicant_number);
                    })
                    ->first();
                if ($duplicate !== null) {
                    throw new RuntimeException('applicant_duplicate: Another capture in this batch already uses this applicant.');
                }

                $set = $resolution->materialSet->bookletSet()->first();
                if ($set === null) {
                    throw new RuntimeException('key_missing: The frozen booklet Set is unavailable.');
                }
                $kitContext = null;
                if ($resolution->snapshot->test_kit_version_id !== null) {
                    $kitVersion = TestKitVersion::query()->findOrFail((int) $resolution->snapshot->test_kit_version_id);
                    $kitContext = app(KitCompositionContext::class)->resolveVersion($kitVersion);
                    if ((int) $kitContext['test_kit_version_id'] !== (int) $resolution->snapshot->test_kit_version_id
                        || (string) $kitContext['manifest_sha256'] !== (string) $resolution->snapshot->manifest_sha256
                        || (int) $kitContext['template_version_id'] !== (int) $batch->template_version_id
                        || (int) $kitContext['booklet_version_id'] !== (int) $batch->booklet_version_id
                        || (int) $kitContext['booklet_set_id'] !== (int) $set->id
                        || (int) $kitContext['test_instrument_version_id'] !== (int) $resolution->snapshot->test_instrument_version_id
                        || (int) ($kitContext['version']->selected_acceptance_receipt_id ?? 0) !== (int) $resolution->snapshot->acceptance_receipt_id
                        || (int) $resolution->materialSet->booklet_set_id !== (int) $set->id) {
                        throw new RuntimeException('session_unresolved: The frozen Test Kit composition does not match the resolved sitting.');
                    }
                }
                $snapshot = OmrPinnedSnapshot::pin($batch->templateVersion, $batch->bookletVersion, $set, $kitContext);
                $proposal = $this->proposal($lockedPending, $snapshot);
                $sheet = OmrCaptureSheet::query()->create([
                    'batch_id' => $batch->id,
                    'admission_attempt_id' => $resolution->attempt->id,
                    'booklet_set_id' => $resolution->materialSet->booklet_set_id,
                    'observed_set_code' => null,
                    'set_resolution_reason' => 'submission_frozen_seat',
                    'set_resolved_by_user_id' => $actor->id,
                    'set_resolved_at' => now(),
                    'status' => OmrCaptureSheet::STATUS_NEEDS_REVIEW,
                    'expected_page_count' => 1,
                    'capture_mode' => OmrCaptureSheet::MODE_AUTO,
                    'version' => 0,
                    'client_capture_id' => $lockedPending->client_capture_id,
                    'detected_applicant_number' => $lockedPending->detected_applicant_number,
                    'detected_applicant_confidence' => $lockedPending->detected_applicant_confidence,
                    'detected_birthdate' => $lockedPending->detected_birthdate,
                    'sheet_confidence' => null,
                    'proposal_json' => $proposal,
                    'original_mime' => $lockedPending->original_mime,
                    'original_bytes' => $lockedPending->original_bytes,
                    'asset_path' => $lockedPending->asset_path,
                    'asset_hmac' => $lockedPending->asset_hmac,
                    'asset_hmac_key_id' => $lockedPending->asset_hmac_key_id,
                    'asset_format_version' => $lockedPending->asset_format_version,
                    'asset_key_id' => $lockedPending->asset_key_id,
                    'asset_state' => OmrCaptureSheet::ASSET_LIVE,
                    'captured_by_user_id' => $lockedPending->uploaded_by_user_id,
                    'captured_at' => $lockedPending->captured_at ?? now(),
                ]);

                $page = $sheet->pages()->create([
                    'page_no' => (int) $lockedPending->page_no,
                    'status' => OmrCaptureSheetPage::STATUS_SUCCEEDED,
                    'asset_path' => $lockedPending->asset_path,
                    'asset_hmac' => $lockedPending->asset_hmac,
                    'asset_hmac_key_id' => $lockedPending->asset_hmac_key_id,
                    'asset_key_id' => $lockedPending->asset_key_id,
                    'asset_format_version' => $lockedPending->asset_format_version,
                    'asset_state' => OmrCaptureSheet::ASSET_LIVE,
                    'original_mime' => $lockedPending->original_mime,
                    'original_bytes' => $lockedPending->original_bytes,
                    'aligned_mime' => $lockedPending->aligned_mime,
                    'aligned_bytes' => $lockedPending->aligned_bytes,
                    'aligned_width' => $lockedPending->aligned_width,
                    'aligned_height' => $lockedPending->aligned_height,
                    'aligned_hash' => $lockedPending->aligned_hash,
                    'aligned_asset_path' => $lockedPending->aligned_asset_path,
                    'aligned_asset_hmac' => $lockedPending->aligned_asset_hmac,
                    'aligned_asset_hmac_key_id' => $lockedPending->aligned_asset_hmac_key_id,
                    'aligned_asset_key_id' => $lockedPending->aligned_asset_key_id,
                    'proposal_json' => $proposal,
                ]);

                $run = $sheet->processingRuns()->create([
                    'actor_id' => $actor->id,
                    'run_no' => 1,
                    'run_uuid' => Str::uuid()->toString(),
                    'status' => OmrProcessingRun::STATUS_SUCCEEDED,
                    'page_no' => (int) $page->page_no,
                    'provider_name' => (string) ($proposal['provider']['name'] ?? 'generic-scanner'),
                    'provider_version' => (string) ($proposal['provider']['version'] ?? '1'),
                    'provider_commit' => $proposal['provider']['commit'] ?? null,
                    'request_version' => (string) ($proposal['envelope'] ?? 'nexiam.omr.detection.v1'),
                    'expected_sheet_version' => (int) $sheet->version,
                    'input_hash' => (string) $lockedPending->input_hash,
                    'template_hash' => (string) $batch->templateVersion->native_bundle_hash,
                    'booklet_hash' => (string) $resolution->materialSet->answer_key_hash,
                    'native_bundle_hash' => (string) $batch->templateVersion->native_bundle_hash,
                    'manifest_hash' => (string) $batch->templateVersion->review_manifest_hash,
                    'config_hash' => (string) $batch->templateVersion->config_hash,
                    'patch_identity' => (string) $batch->templateVersion->patch_identity,
                    'response_version' => (string) ($proposal['envelope'] ?? 'nexiam.omr.detection.v1'),
                    'attempts' => 1,
                    'duration_ms' => null,
                    'timings_json' => $lockedPending->evidence_json['timings'] ?? null,
                    'output_json' => [
                        'source' => 'generic_pending_capture',
                        'pending_capture_id' => $lockedPending->id,
                        'evidence_hash' => $lockedPending->evidence_json['evidence_hash'] ?? null,
                    ],
                ]);
                $page->forceFill(['current_processing_run_id' => $run->id])->save();
                $sheet->forceFill(['current_processing_run_id' => $run->id])->save();

                $this->instances->registerCapture(
                    $lockedPending->blankInstance,
                    (int) $page->page_no,
                    $page,
                    $actor,
                    (int) $examSession->id,
                );
                $this->persistIssues($sheet, $lockedPending->evidence_json);

                $lockedPending->forceFill([
                    'status' => OmrPendingCapture::STATUS_ADOPTED,
                    'adopted_sheet_id' => $sheet->id,
                    'adopted_by_user_id' => $actor->id,
                    'adopted_at' => now(),
                    'version' => (int) $lockedPending->version + 1,
                ])->save();
                $this->recordAuditEvent->handle('grading.omr_pending_capture_adopted', [
                    'pending_capture_id' => $lockedPending->id,
                    'sheet_id' => $sheet->id,
                    'batch_id' => $batch->id,
                    'grading_session_id' => $session->id,
                    'admission_attempt_id' => $resolution->attempt->id,
                    'input_hash' => $lockedPending->input_hash,
                ], (string) $actor->id);

                return $sheet;
            });
        } catch (Throwable $exception) {
            $this->markResolutionFailure(
                $pending,
                $actor,
                $expectedVersion,
                'adoption_failed',
                $exception->getMessage(),
            );
            $operation->forceFill([
                'status' => Operation::STATUS_FAILED,
                'last_error' => trim(substr($exception->getMessage(), 0, 500)),
                'finished_at' => now(),
            ])->save();

            throw $exception;
        }

        $operation->forceFill([
            'status' => Operation::STATUS_SUCCEEDED,
            'payload' => [
                ...(array) $operation->payload,
                'result' => ['sheet_id' => (int) $sheet->id],
            ],
            'finished_at' => now(),
            'last_error' => null,
        ])->save();

        return $sheet->fresh(['batch', 'pages']);
    }

    private function assertBlankInstanceScope(OmrBlankInstance $blankInstance, GradingSession $session): void
    {
        $examSession = $session->examSession;
        $cycle = $examSession?->academicCycle;
        if ($cycle === null
            || $blankInstance->institution_id === null
            || $blankInstance->academic_cycle_id === null
            || (int) $blankInstance->institution_id !== (int) $cycle->institution_id
            || (int) $blankInstance->academic_cycle_id !== (int) $cycle->id) {
            throw new RuntimeException('qr_scope_mismatch: Issued Answer Sheet scope does not match the selected grading session.');
        }
    }

    private function assertCapability(User $actor): void
    {
        if (! $this->authorizer->allows($actor, CapabilityCatalog::GUIDANCE_SCORING_OMR)) {
            throw new AuthorizationException('OMR scanner is not available.');
        }
    }

    private function markResolutionFailure(
        OmrPendingCapture $pending,
        User $actor,
        int $expectedVersion,
        string $code,
        ?string $detail,
    ): void {
        DB::transaction(function () use ($pending, $actor, $expectedVersion, $code, $detail): void {
            $locked = OmrPendingCapture::query()
                ->lockForUpdate()
                ->with('gradingSession')
                ->find($pending->id);
            if ($locked === null) {
                return;
            }

            $this->pendingAccess->assertPending($actor, $locked, $locked->gradingSession);
            if ($locked->status !== OmrPendingCapture::STATUS_READY
                || (int) $locked->version !== $expectedVersion
                || $locked->adopted_sheet_id !== null
                || $locked->isTerminal()) {
                return;
            }

            $locked->forceFill([
                'status' => OmrPendingCapture::STATUS_RESOLUTION_REQUIRED,
                'resolution_json' => [
                    'outcome' => 'selection_required',
                    'code' => $code,
                    'resolution_code' => $code,
                    'candidates' => [],
                ],
                'analysis_code' => $code,
                'analysis_detail' => $detail,
                'resolved_by_user_id' => $actor->id,
                'version' => $expectedVersion + 1,
            ])->save();
        });
    }

    /** @return array<string, mixed> */
    private function proposal(OmrPendingCapture $pending, OmrPinnedSnapshot $snapshot): array
    {
        $evidence = is_array($pending->evidence_json) ? $pending->evidence_json : [];
        $diagnosticItems = is_array($evidence['items'] ?? null) ? array_values($evidence['items']) : [];
        $geometryByPosition = [];
        foreach ($snapshot->items as $geometry) {
            $geometryByPosition[(int) $geometry->position] = $geometry;
        }

        $items = [];
        foreach ($diagnosticItems as $diagnostic) {
            if (! is_array($diagnostic)) {
                continue;
            }
            $position = is_numeric($diagnostic['position'] ?? null) ? (int) $diagnostic['position'] : 0;
            $geometry = $geometryByPosition[$position] ?? null;
            if ($geometry === null) {
                continue;
            }
            $detected = is_array($diagnostic['detected'] ?? null) ? $diagnostic['detected'] : [];
            $selected = is_string($detected['selected_option'] ?? null) && trim($detected['selected_option']) !== ''
                ? trim($detected['selected_option'])
                : null;
            $correct = $snapshot->correctOptionsByPosition[$position] ?? null;
            $issueCodes = is_array($evidence['issues'] ?? null) ? $evidence['issues'] : [];
            $items[] = [
                'position' => $position,
                'area_code' => $geometry->areaCode,
                'area_id' => $snapshot->areaIdsByCode[$geometry->areaCode] ?? null,
                'question_no' => $geometry->questionNo,
                'detected_answer' => $selected,
                'correct_option_code' => $correct,
                'state' => is_string($detected['state'] ?? null) ? $detected['state'] : 'unreadable',
                'confidence' => is_numeric($detected['confidence'] ?? null) ? (float) $detected['confidence'] : null,
                'region' => is_array($diagnostic['region'] ?? null) ? $diagnostic['region'] : $geometry->region->toArray(),
                'observed_options' => $selected === null ? [] : [$selected],
                'option_observations' => [],
                'issue_codes' => $issueCodes,
                'legacy_evidence_limited' => false,
                'evidence_hash' => hash('sha256', json_encode($diagnostic, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)),
                'score' => $selected !== null && $correct !== null && $selected === $correct ? 1 : 0,
            ];
        }

        $perArea = [];
        foreach ($items as $item) {
            $areaCode = (string) $item['area_code'];
            $areaId = $item['area_id'];
            if ($areaId === null) {
                continue;
            }
            $perArea[$areaId] ??= ['score' => 0, 'confidence' => null, 'area_code' => $areaCode];
            $perArea[$areaId]['score'] += (int) $item['score'];
        }

        $target = is_array($evidence['target'] ?? null) ? $evidence['target'] : [];
        $engine = is_array($target['engine'] ?? null) ? $target['engine'] : [];
        $hashes = is_array($evidence['hashes'] ?? null) ? $evidence['hashes'] : [];
        $captured = is_array($evidence['assets']['captured'] ?? null) ? $evidence['assets']['captured'] : [];

        return [
            'envelope' => 'nexiam.omr.detection.v1',
            'provider' => [
                'name' => (string) ($engine['name'] ?? 'generic-scanner'),
                'version' => (string) ($engine['version'] ?? '1'),
                'commit' => $engine['commit'] ?? null,
                'calibration_status' => 'unqualified',
            ],
            'input_hash' => $pending->input_hash,
            'native_bundle_hash' => $pending->templateVersion?->native_bundle_hash,
            'manifest_hash' => $pending->templateVersion?->review_manifest_hash,
            'config_hash' => $pending->templateVersion?->config_hash,
            'patch_identity' => $pending->templateVersion?->patch_identity,
            'image' => [
                'mime' => $captured['mime'] ?? $pending->original_mime,
                'width' => $captured['width'] ?? null,
                'height' => $captured['height'] ?? null,
            ],
            'field_block_shifts' => $evidence['field_block_shifts'] ?? [],
            'aligned_image' => $pending->aligned_asset_path === null ? null : [
                'mime' => $pending->aligned_mime,
                'width' => $pending->aligned_width,
                'height' => $pending->aligned_height,
                'hash' => $pending->aligned_hash,
            ],
            'applicant_number_proposal' => $pending->detected_applicant_number,
            'applicant_number_confidence' => $pending->detected_applicant_confidence,
            'birthdate_proposal' => $pending->detected_birthdate,
            'sheet_confidence' => null,
            'per_area_scores' => $perArea,
            'items' => $items,
            'generic_capture' => [
                'pending_capture_id' => $pending->id,
                'evidence_hash' => $evidence['evidence_hash'] ?? null,
                'input_hash' => $pending->input_hash,
                'evidence_hashes' => $hashes,
            ],
        ];
    }

    /** @param array<string, mixed>|null $evidence */
    private function persistIssues(OmrCaptureSheet $sheet, ?array $evidence): void
    {
        $issues = is_array($evidence['issues'] ?? null) ? $evidence['issues'] : [];
        foreach ($issues as $issue) {
            if (! is_array($issue) || ! is_string($issue['code'] ?? null) || trim($issue['code']) === '') {
                continue;
            }
            $severity = is_string($issue['severity'] ?? null) && in_array($issue['severity'], ['warning', 'required', 'terminal'], true)
                ? $issue['severity']
                : 'warning';
            $sheet->issues()->create([
                'page_no' => 1,
                'area_code' => is_string($issue['area_code'] ?? null) ? $issue['area_code'] : null,
                'question_no' => is_numeric($issue['question_no'] ?? null) ? (int) $issue['question_no'] : null,
                'issue_code' => $issue['code'],
                'severity' => $severity,
                'state' => 'open',
                'evidence_json' => ['source' => 'generic_pending_capture'],
                'version' => 1,
            ]);
        }
    }
}
