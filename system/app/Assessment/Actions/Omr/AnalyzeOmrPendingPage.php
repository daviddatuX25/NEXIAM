<?php

namespace App\Assessment\Actions\Omr;

use App\Assessment\Omr\Diagnostics\CompiledVersionDiagnosticTarget;
use App\Assessment\Omr\OmrCaptureAnalysisPort;
use App\Assessment\Omr\OmrCaptureAnalysisRequest;
use App\Assessment\Omr\OmrCaptureInput;
use App\Assessment\Omr\OmrClientQuad;
use App\Assessment\Omr\OmrPendingCaptureAccess;
use App\Assessment\Omr\OmrRegistrationMode;
use App\Assessment\Omr\OmrSourceImage;
use App\Assessment\Omr\Operational\OmrDiagnosticRunStatusSnapshot;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\GradingSession;
use App\Models\Assessment\OmrCaptureBatch;
use App\Models\Assessment\OmrDiagnosticRun;
use App\Models\Assessment\OmrPendingCapturePageAnalysis;
use App\Models\Assessment\OmrPendingCapturePageEvidence;
use App\Models\Assessment\OmrPendingCaptureSet;
use App\Models\User;
use App\Support\SensitiveAssetStore;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

final class AnalyzeOmrPendingPage
{
    public function __construct(
        private readonly OmrPendingCaptureAccess $access,
        private readonly OmrCaptureAnalysisPort $analysis,
        private readonly SensitiveAssetStore $assets,
        private readonly RecordAuditEvent $audit,
        private readonly OmrDiagnosticRunStatusSnapshot $diagnosticSnapshot,
    ) {}

    public function handle(OmrPendingCaptureSet $set, User $actor, string $clientCaptureId): OmrPendingCaptureSet
    {
        $scope = $this->access->actorScope($actor);
        if ((int) $set->created_by_user_id !== (int) $actor->id
            || (int) $set->institution_id !== $scope['institution_id']
            || (int) $set->academic_cycle_id !== $scope['academic_cycle_id']) {
            throw new RuntimeException('omr_capture_set_scope_mismatch');
        }

        $page = OmrPendingCapturePageEvidence::query()
            ->where('set_id', $set->id)
            ->where('client_capture_id', $clientCaptureId)
            ->firstOrFail();
        $existing = OmrPendingCapturePageAnalysis::query()->where('evidence_id', $page->id)->first();
        $number = null;
        $code = 'analysis_failed';
        $normalized = ['input_hash' => $page->input_hash, 'page_no' => $page->page_no, 'code' => $code];
        if ($existing === null) {
            try {
                $bytes = $this->assets->read($page->asset_path);
                if (! hash_equals($page->input_hash, hash('sha256', $bytes))) {
                    throw new RuntimeException('omr_capture_set_input_integrity_failed');
                }
                $clientQuad = null;
                if ($page->client_quad_json !== null) {
                    if ($set->registration_mode !== OmrRegistrationMode::DocumentBound->value
                        || ! is_array($page->client_quad_json)) {
                        throw new RuntimeException('omr_capture_set_client_quad_registration_mismatch');
                    }
                    $clientQuad = OmrClientQuad::fromArray($page->client_quad_json);
                    if (! $clientQuad->isBoundTo(OmrSourceImage::fromBytes($bytes), (int) $page->page_no)) {
                        throw new RuntimeException('omr_capture_set_client_quad_source_mismatch');
                    }
                }
                $version = $set->templateVersion()->with('template')->firstOrFail();
                $prepared = $this->analysis->analyze(new OmrCaptureAnalysisRequest(
                    capture: new OmrCaptureInput(
                        bytes: $bytes,
                        mime: $page->mime_type,
                        pageNo: (int) $page->page_no,
                        pageCount: 1,
                        expectedPageCount: (int) $set->page_count,
                        clientQuad: $clientQuad,
                        cropPolicy: $page->crop_policy ?? 'auto_document_quad',
                    ),
                    target: new CompiledVersionDiagnosticTarget($version),
                    pageNo: (int) $page->page_no,
                ));
                if (! hash_equals($page->input_hash, $prepared->inputHash)
                    || (int) $page->page_no !== $prepared->pageNo) {
                    throw new RuntimeException('omr_capture_set_analysis_target_mismatch');
                }
                $normalized = $prepared->normalize();
                $success = $prepared->succeeded() ? $prepared->providerResult?->success : null;
                $proposal = $success?->applicantNumberProposal;
                $manifest = is_array($version->authoring_manifest) ? $version->authoring_manifest : [];
                $identityMethod = is_array($manifest['identity_method'] ?? null) ? ($manifest['identity_method']['method'] ?? null) : null;
                $isWriteInManual = $identityMethod === 'write_in_manual' || $set->identity_mode === 'write_in_manual';
                if ($success !== null && hash_equals($page->input_hash, $success->inputHash)
                    && $success->registrationMode === $set->registration_mode
                    && $success->templateVersionId === (int) $set->template_version_id
                    && $success->expectedPageNo === (int) $page->page_no
                    && is_string($proposal) && preg_match('/^[A-Za-z0-9._-]+$/', trim($proposal)) === 1) {
                    $number = trim($proposal);
                    $code = null;
                } elseif ($isWriteInManual && $prepared->succeeded()) {
                    $number = null;
                    $code = null;
                } else {
                    $code = $prepared->succeeded() ? 'applicant_unresolved' : ($prepared->code ?? 'analysis_failed');
                }
            } catch (Throwable) {
                $code = 'analysis_failed';
                $normalized = ['input_hash' => $page->input_hash, 'page_no' => $page->page_no, 'code' => $code];
            }
        }

        return DB::transaction(function () use ($set, $actor, $page, $existing, $number, $code, $normalized): OmrPendingCaptureSet {
            $currentActor = $actor->fresh(['staffProfile']) ?? $actor;
            $scope = $this->access->actorScope($currentActor);
            if ((int) $set->created_by_user_id !== (int) $currentActor->id
                || (int) $set->institution_id !== $scope['institution_id']
                || (int) $set->academic_cycle_id !== $scope['academic_cycle_id']) {
                throw new RuntimeException('omr_capture_set_scope_mismatch');
            }
            if ($set->production_batch_id !== null) {
                $batch = OmrCaptureBatch::query()->lockForUpdate()->findOrFail($set->production_batch_id);
                $session = GradingSession::query()->findOrFail($batch->grading_session_id);
                if ($batch->status !== OmrCaptureBatch::STATUS_OPEN
                    || $session->status === GradingSession::STATUS_FINALIZED
                    || (int) $batch->template_version_id !== (int) $set->template_version_id) {
                    throw new RuntimeException('omr_capture_set_context_closed');
                }
            } else {
                $run = OmrDiagnosticRun::query()->lockForUpdate()->findOrFail($set->diagnostic_run_id);
                $this->diagnosticSnapshot->assertCanProject($run, $currentActor);
                if ($run->status !== OmrDiagnosticRun::STATUS_OPEN
                    || $run->phase !== OmrDiagnosticRun::PHASE_CAPTURE
                    || (int) $run->template_version_id !== (int) $set->template_version_id) {
                    throw new RuntimeException('omr_capture_set_context_closed');
                }
            }
            $locked = OmrPendingCaptureSet::query()->lockForUpdate()->findOrFail($set->id);
            if ($locked->isExpired() || ! in_array($locked->status, [OmrPendingCaptureSet::STATUS_PENDING, OmrPendingCaptureSet::STATUS_CAPTURED, OmrPendingCaptureSet::STATUS_READY], true)) {
                throw new RuntimeException('omr_capture_set_context_closed');
            }
            if ($existing === null && ! OmrPendingCapturePageAnalysis::query()->where('evidence_id', $page->id)->exists()) {
                OmrPendingCapturePageAnalysis::query()->create([
                    'evidence_id' => $page->id,
                    'detected_applicant_number' => $number,
                    'recognition_code' => $code,
                    'evidence_json' => $normalized,
                ]);
                $this->audit->handle('omr.capture_page_analyzed', [
                    'capture_set_id' => $locked->id,
                    'evidence_id' => $page->id,
                    'page_no' => $page->page_no,
                    'recognition_code' => $code,
                    'detected_identity' => $number !== null,
                ], (string) $actor->id);
            }
            if ($locked->status === OmrPendingCaptureSet::STATUS_PENDING) {
                return $locked;
            }

            $version = $locked->templateVersion()->first();
            $manifest = is_array($version?->authoring_manifest) ? $version->authoring_manifest : [];
            $identityMethod = is_array($manifest['identity_method'] ?? null) ? ($manifest['identity_method']['method'] ?? null) : null;
            $isWriteInManual = $identityMethod === 'write_in_manual' || $locked->identity_mode === 'write_in_manual';

            $numbers = [];
            $selectedEvidenceIds = [];
            $missing = false;
            $manualPages = [];
            if ($isWriteInManual && is_array($locked->manual_identity_json['pages'] ?? null)) {
                foreach ($locked->manual_identity_json['pages'] as $manualEntry) {
                    if (is_array($manualEntry) && isset($manualEntry['page_no'])) {
                        $manualPages[(int) $manualEntry['page_no']] = $manualEntry;
                    }
                }
            }
            $manualConfirmed = $isWriteInManual && count($manualPages) === count($locked->expected_pages_json);
            foreach ($locked->expected_pages_json as $expectedPage) {
                $pageNo = (int) $expectedPage['page_no'];
                $selected = OmrPendingCapturePageEvidence::query()
                    ->where('set_id', $locked->id)
                    ->where('page_no', $pageNo)
                    ->orderByDesc('id')
                    ->first();
                $selectedEvidenceIds[] = $selected?->id;
                $analysis = $selected === null ? null : OmrPendingCapturePageAnalysis::query()
                    ->where('evidence_id', $selected->id)->first();
                if ($isWriteInManual) {
                    if ($analysis === null || $analysis->recognition_code !== null) {
                        $missing = true;
                    } elseif ($manualConfirmed) {
                        $entry = $manualPages[$pageNo] ?? null;
                        if (! is_array($entry)
                            || (int) ($entry['evidence_id'] ?? 0) !== (int) $selected->id
                            || (string) ($entry['input_hash'] ?? '') !== (string) $selected->input_hash
                            || trim((string) ($entry['applicant_number'] ?? '')) === '') {
                            $manualConfirmed = false;
                        } else {
                            $numbers[] = trim((string) $entry['applicant_number']);
                        }
                    }
                } elseif ($analysis === null || $analysis->detected_applicant_number === null) {
                    $missing = true;
                } else {
                    $numbers[] = $analysis->detected_applicant_number;
                }
            }
            $distinct = array_unique($numbers);
            if ($isWriteInManual) {
                $pairingCode = $missing
                    ? 'pairing_missing'
                    : (! $manualConfirmed ? 'identity_review_required' : (count($distinct) > 1 ? 'pairing_conflict' : null));
            } else {
                $pairingCode = $missing ? 'pairing_missing' : (count($distinct) > 1 ? 'pairing_conflict' : null);
            }
            $status = $pairingCode === null ? OmrPendingCaptureSet::STATUS_READY : OmrPendingCaptureSet::STATUS_CAPTURED;
            if ($locked->pairing_code !== $pairingCode || $locked->status !== $status) {
                $locked->pairing_code = $pairingCode;
                $locked->status = $status;
                $locked->revision++;
                $locked->save();
                $this->audit->handle('omr.capture_set_pairing_evaluated', [
                    'capture_set_id' => $locked->id,
                    'status' => $status,
                    'pairing_code' => $pairingCode,
                    'selected_evidence_ids' => $selectedEvidenceIds,
                ], (string) $actor->id);
            }

            return $locked;
        });
    }
}
