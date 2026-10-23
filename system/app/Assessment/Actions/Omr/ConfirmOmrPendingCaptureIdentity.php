<?php

namespace App\Assessment\Actions\Omr;

use App\Assessment\Omr\OmrCaptureResolver;
use App\Assessment\Omr\OmrPendingCaptureAccess;
use App\Assessment\Omr\OmrResolutionRequest;
use App\Assessment\Omr\Operational\OmrDiagnosticRunStatusSnapshot;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\AdmissionAttempt;
use App\Models\Assessment\GradingSession;
use App\Models\Assessment\OmrCaptureBatch;
use App\Models\Assessment\OmrDiagnosticRun;
use App\Models\Assessment\OmrPendingCapturePageAnalysis;
use App\Models\Assessment\OmrPendingCapturePageEvidence;
use App\Models\Assessment\OmrPendingCaptureSet;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

final class ConfirmOmrPendingCaptureIdentity
{
    public function __construct(
        private readonly OmrPendingCaptureAccess $access,
        private readonly OmrCaptureResolver $resolver,
        private readonly OmrDiagnosticRunStatusSnapshot $diagnosticSnapshot,
        private readonly RecordAuditEvent $audit,
    ) {}

    /**
     * @param  list<array{page_no:int,applicant_number?:?string,admission_attempt_id?:?int}>  $selections
     */
    public function handle(
        OmrPendingCaptureSet $set,
        User $actor,
        int $expectedRevision,
        array $selections,
        string $idempotencyKey,
    ): OmrPendingCaptureSet {
        if (! Str::isUuid($idempotencyKey)) {
            throw new InvalidArgumentException('omr_capture_set_idempotency_key_invalid');
        }

        $scope = $this->access->actorScope($actor);

        return DB::transaction(function () use ($set, $actor, $expectedRevision, $selections, $idempotencyKey, $scope): OmrPendingCaptureSet {
            $session = null;
            if ($set->production_batch_id !== null) {
                $batch = OmrCaptureBatch::query()->lockForUpdate()->findOrFail($set->production_batch_id);
                $session = GradingSession::query()->lockForUpdate()->findOrFail($batch->grading_session_id);
                if ($batch->status !== OmrCaptureBatch::STATUS_OPEN
                    || $session->status === GradingSession::STATUS_FINALIZED
                    || (int) $batch->template_version_id !== (int) $set->template_version_id) {
                    throw new RuntimeException('omr_capture_set_context_closed');
                }
            } else {
                $run = OmrDiagnosticRun::query()->lockForUpdate()->findOrFail($set->diagnostic_run_id);
                $this->diagnosticSnapshot->assertCanProject($run, $actor);
                if ($run->status !== OmrDiagnosticRun::STATUS_OPEN
                    || $run->phase !== OmrDiagnosticRun::PHASE_CAPTURE
                    || (int) $run->template_version_id !== (int) $set->template_version_id) {
                    throw new RuntimeException('omr_capture_set_context_closed');
                }
            }

            $locked = OmrPendingCaptureSet::query()->lockForUpdate()->findOrFail($set->id);
            if ((int) $locked->created_by_user_id !== (int) $actor->id
                || (int) $locked->institution_id !== $scope['institution_id']
                || (int) $locked->academic_cycle_id !== $scope['academic_cycle_id']) {
                throw new RuntimeException('omr_capture_set_scope_mismatch');
            }
            if ($locked->isExpired() || ! in_array($locked->status, [OmrPendingCaptureSet::STATUS_CAPTURED, OmrPendingCaptureSet::STATUS_READY], true)) {
                throw new RuntimeException('omr_capture_set_context_closed');
            }

            $existingManual = is_array($locked->manual_identity_json) ? $locked->manual_identity_json : null;
            if ($existingManual !== null && ($existingManual['idempotency_key'] ?? null) === $idempotencyKey) {
                return $locked;
            }

            if ((int) $locked->revision !== $expectedRevision) {
                throw new RuntimeException('omr_capture_set_revision_stale');
            }

            $version = $locked->templateVersion()->firstOrFail();
            $manifest = is_array($version->authoring_manifest) ? $version->authoring_manifest : [];
            $identityMethod = is_array($manifest['identity_method'] ?? null) ? $manifest['identity_method'] : [];
            $hasManualIdentity = ($identityMethod['method'] ?? null) === 'write_in_manual'
                || in_array(($identityMethod['strategy'] ?? null), ['write_in_only', 'bubbles_and_write_in'], true)
                || ! empty($identityMethod['has_write_in'])
                || ! empty($identityMethod['write_in_boxes']);
            if (! $hasManualIdentity) {
                throw new RuntimeException('omr_capture_set_manual_identity_not_allowed');
            }

            $regionsByPage = [];
            foreach ((array) ($identityMethod['regions'] ?? []) as $region) {
                if (is_array($region) && isset($region['page_no'])) {
                    $regionsByPage[(int) $region['page_no']] = [
                        'page_no' => (int) $region['page_no'],
                        'x' => (int) ($region['x'] ?? 0),
                        'y' => (int) ($region['y'] ?? 0),
                        'w' => (int) ($region['w'] ?? 0),
                        'h' => (int) ($region['h'] ?? 0),
                    ];
                }
            }
            if ($regionsByPage === []) {
                $paperWidthMm = 210.0;
                $paperHeightMm = 297.0;
                $pageWidthPx = 2480;
                $pageHeightPx = 3508;
                foreach ((array) ($identityMethod['write_in_boxes'] ?? []) as $box) {
                    if (is_array($box) && isset($box['page_no'])) {
                        $pNo = (int) $box['page_no'];
                        $xMm = (float) ($box['x_mm'] ?? 0);
                        $yMm = (float) ($box['y_mm'] ?? 0);
                        $wMm = (float) ($box['w_mm'] ?? 0);
                        $hMm = (float) ($box['h_mm'] ?? 0);
                        $regionsByPage[$pNo] = [
                            'page_no' => $pNo,
                            'x' => (int) round(($xMm / $paperWidthMm) * $pageWidthPx),
                            'y' => (int) round(($yMm / $paperHeightMm) * $pageHeightPx),
                            'w' => (int) round(($wMm / $paperWidthMm) * $pageWidthPx),
                            'h' => (int) round(($hMm / $paperHeightMm) * $pageHeightPx),
                        ];
                    }
                }
            }
            if ($regionsByPage === []) {
                $manifestJson = is_array($version->manifest_json) ? $version->manifest_json : [];
                $bundleJson = is_array($version->native_bundle_json) ? $version->native_bundle_json : [];
                $identityRegions = is_array($manifestJson['identity_regions'] ?? null)
                    ? $manifestJson['identity_regions']
                    : (is_array($bundleJson['identity_regions'] ?? null)
                        ? $bundleJson['identity_regions']
                        : []);
                foreach ($identityRegions as $pNo => $pageRegions) {
                    if (is_array($pageRegions) && isset($pageRegions['applicant_number_write_in'])) {
                        $box = $pageRegions['applicant_number_write_in'];
                        $regionsByPage[(int) $pNo] = [
                            'page_no' => (int) $pNo,
                            'x' => (int) round(((float) ($box['x'] ?? 0)) * 2480),
                            'y' => (int) round(((float) ($box['y'] ?? 0)) * 3508),
                            'w' => (int) round(((float) ($box['w'] ?? 0)) * 2480),
                            'h' => (int) round(((float) ($box['h'] ?? 0)) * 3508),
                        ];
                    }
                }
            }
            $selectionsByPage = [];
            foreach ($selections as $selection) {
                if (! is_array($selection) || ! isset($selection['page_no'])) {
                    throw new InvalidArgumentException('omr_capture_set_manual_identity_invalid');
                }
                $pageNo = (int) $selection['page_no'];
                if (isset($selectionsByPage[$pageNo])) {
                    throw new InvalidArgumentException('omr_capture_set_manual_identity_invalid');
                }
                $selectionsByPage[$pageNo] = $selection;
            }

            if (count($selectionsByPage) !== (int) $locked->page_count) {
                throw new InvalidArgumentException('omr_capture_set_manual_identity_incomplete');
            }

            $pageEntries = [];
            $numbers = [];
            foreach ($locked->expected_pages_json as $expectedPage) {
                $pageNo = (int) $expectedPage['page_no'];
                $selection = $selectionsByPage[$pageNo] ?? null;
                $region = $regionsByPage[$pageNo] ?? null;
                if ($selection === null || $region === null) {
                    throw new RuntimeException('omr_capture_set_manual_identity_incomplete');
                }

                $evidence = OmrPendingCapturePageEvidence::query()
                    ->where('set_id', $locked->id)
                    ->where('page_no', $pageNo)
                    ->orderByDesc('id')
                    ->first();
                $analysis = $evidence === null
                    ? null
                    : OmrPendingCapturePageAnalysis::query()->where('evidence_id', $evidence->id)->first();
                $payload = $analysis?->evidence_json;
                if ($evidence === null
                    || $analysis === null
                    || ! is_array($payload)
                    || ($payload['outcome'] ?? null) !== 'succeeded'
                    || ! hash_equals((string) $evidence->input_hash, (string) ($payload['input_hash'] ?? ''))) {
                    throw new RuntimeException('omr_capture_set_pairing_required');
                }

                $applicantNumber = trim((string) ($selection['applicant_number'] ?? ''));
                $attemptId = isset($selection['admission_attempt_id']) && $selection['admission_attempt_id'] !== null
                    ? (int) $selection['admission_attempt_id']
                    : null;
                if ($applicantNumber === '' && $attemptId !== null) {
                    $attempt = AdmissionAttempt::query()->find($attemptId);
                    $applicantNumber = trim((string) ($attempt?->reference_code ?? ''));
                }
                if ($applicantNumber === '') {
                    throw new InvalidArgumentException('omr_capture_set_manual_identity_incomplete');
                }

                if ($session !== null) {
                    $resolution = $this->resolver->resolve(new OmrResolutionRequest($session, $applicantNumber));
                    if (! $resolution->isResolved() || $resolution->attempt === null) {
                        throw new RuntimeException($resolution->failureCode() ?? 'applicant_unresolved');
                    }
                    if ($attemptId !== null && (int) $resolution->attempt->id !== $attemptId) {
                        throw new RuntimeException('applicant_conflict');
                    }
                    $attemptId = (int) $resolution->attempt->id;
                    $applicantNumber = (string) ($resolution->attempt->reference_code ?? $applicantNumber);
                }

                $numbers[] = $applicantNumber;
                $pageEntries[$pageNo] = [
                    'page_no' => $pageNo,
                    'evidence_id' => (int) $evidence->id,
                    'input_hash' => (string) $evidence->input_hash,
                    'region' => $region,
                    'applicant_number' => $applicantNumber,
                    'admission_attempt_id' => $attemptId,
                ];
            }

            $distinctNumbers = array_values(array_unique($numbers));
            $pairingCode = count($distinctNumbers) === 1 ? null : 'pairing_conflict';
            $nextStatus = $pairingCode === null
                ? OmrPendingCaptureSet::STATUS_READY
                : OmrPendingCaptureSet::STATUS_CAPTURED;

            $locked->manual_identity_json = [
                'method' => 'write_in_manual',
                'idempotency_key' => $idempotencyKey,
                'confirmed_by_user_id' => (int) $actor->id,
                'confirmed_at' => now()->toIso8601String(),
                'applicant_number' => count($distinctNumbers) === 1 ? $distinctNumbers[0] : null,
                'pages' => array_values($pageEntries),
            ];
            $locked->pairing_code = $pairingCode;
            $locked->status = $nextStatus;
            $locked->resolution_code = null;
            $locked->revision = (int) $locked->revision + 1;
            $locked->save();

            $this->audit->handle('omr.capture_set_manual_identity_confirmed', [
                'capture_set_id' => $locked->id,
                'production_batch_id' => $locked->production_batch_id,
                'diagnostic_run_id' => $locked->diagnostic_run_id,
                'status' => $locked->status,
                'pairing_code' => $locked->pairing_code,
                'pages' => array_values($pageEntries),
            ], (string) $actor->id);

            return $locked;
        }, 3);
    }
}
