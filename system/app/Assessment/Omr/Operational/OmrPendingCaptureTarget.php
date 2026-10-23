<?php

namespace App\Assessment\Omr\Operational;

use App\Assessment\Omr\OmrRecoveryCatalog;
use App\Assessment\Support\TestKitMockSession;
use App\Models\Assessment\OmrCaptureBatch;
use App\Models\Assessment\OmrDiagnosticRun;
use App\Models\Assessment\OmrPendingCapturePageAnalysis;
use App\Models\Assessment\OmrPendingCapturePageEvidence;
use App\Models\Assessment\OmrPendingCaptureSet;
use App\Models\User;

final class OmrPendingCaptureTarget
{
    public function __construct(private readonly OmrRecoveryCatalog $recoveryCatalog) {}

    /** @return array<string, mixed>|null */
    public function forProduction(OmrCaptureBatch $batch, User $actor): ?array
    {
        return $this->project(OmrPendingCaptureSet::query()
            ->whereNull('adopted_sheet_id')
            ->where('production_batch_id', $batch->id)
            ->where('created_by_user_id', $actor->id)
            ->whereIn('status', [OmrPendingCaptureSet::STATUS_PENDING, OmrPendingCaptureSet::STATUS_CAPTURED, OmrPendingCaptureSet::STATUS_READY])
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first());
    }

    /** @return array<string, mixed>|null */
    public function forDiagnostic(OmrDiagnosticRun $run, User $actor): ?array
    {
        $query = OmrPendingCaptureSet::query()
            ->where('diagnostic_run_id', $run->id)
            ->where('created_by_user_id', $actor->id)
            ->whereIn('status', [OmrPendingCaptureSet::STATUS_PENDING, OmrPendingCaptureSet::STATUS_CAPTURED, OmrPendingCaptureSet::STATUS_READY])
            ->where('expires_at', '>', now());
        if ($run->hasMockContext()) {
            $target = app(TestKitMockSession::class)->captureTarget($run);
            if (! is_array($target) || ! is_string($target['instance_id'] ?? null) || ! is_string($target['pending_capture_id'] ?? null)) {
                return null;
            }
            $query->where('mock_instance_key', $target['instance_id'])
                ->where('public_id', $target['pending_capture_id']);
        } else {
            $query->latest('id');
        }

        return $this->project($query->first());
    }

    public function withStartedSet(OmrWorkbenchProjection $projection, OmrPendingCaptureSet $set): OmrWorkbenchProjection
    {
        $payload = $projection->toSafeArray();
        $payload['context']['pending_capture'] = $this->project($set);

        return OmrWorkbenchProjection::fromSafeArray($payload);
    }

    /** @return array<string, mixed>|null */
    private function project(?OmrPendingCaptureSet $set): ?array
    {
        if ($set === null) {
            return null;
        }

        $evidenceByPage = OmrPendingCapturePageEvidence::query()
            ->where('set_id', $set->id)
            ->orderBy('id')
            ->get(['id', 'page_no'])
            ->groupBy('page_no');
        $selectedIds = $evidenceByPage->map(static fn ($captures): ?int => $captures->last()?->id)->filter()->values()->all();
        $analyses = OmrPendingCapturePageAnalysis::query()->whereIn('evidence_id', $selectedIds)->get()->keyBy('evidence_id');

        $version = $set->templateVersion()->first();
        $manifest = is_array($version?->authoring_manifest) ? $version->authoring_manifest : [];
        $identityMethod = is_array($manifest['identity_method'] ?? null) ? $manifest['identity_method'] : [];
        $regionsByPage = [];
        foreach ((array) ($identityMethod['regions'] ?? []) as $region) {
            if (is_array($region) && isset($region['page_no'])) {
                $regionsByPage[(int) $region['page_no']] = $region;
            }
        }
        $manualByPage = [];
        if (is_array($set->manual_identity_json['pages'] ?? null)) {
            foreach ($set->manual_identity_json['pages'] as $entry) {
                if (is_array($entry) && isset($entry['page_no'])) {
                    $manualByPage[(int) $entry['page_no']] = $entry;
                }
            }
        }

        return [
            'id' => (string) $set->public_id,
            'revision' => (int) $set->revision,
            'status' => (string) $set->status,
            'identity_mode' => (string) ($set->identity_mode ?? ($identityMethod['method'] ?? 'shaded_grid')),
            'pairing_code' => $set->pairing_code,
            'resolution_code' => $set->resolution_code,
            'adopted_sheet_id' => $set->adopted_sheet_id === null ? null : (int) $set->adopted_sheet_id,
            'supplier_stock_allocation_id' => $set->supplier_stock_allocation_id === null ? null : (int) $set->supplier_stock_allocation_id,
            'manual_identity' => $set->manual_identity_json,
            'recovery' => $set->resolution_code !== null
                ? $this->recoveryCatalog->for($set->resolution_code)
                : ($set->pairing_code === 'identity_review_required'
                    ? $this->recoveryCatalog->for('identity_review_required')
                    : null),
            'active_page_no' => (int) $set->active_page_no,
            'page_count' => (int) $set->page_count,
            'pages' => array_map(static function (array $page) use ($evidenceByPage, $analyses, $set, $regionsByPage, $manualByPage): array {
                $pageNo = (int) $page['page_no'];
                $captures = $evidenceByPage->get($pageNo);
                $analysis = $captures === null ? null : $analyses->get($captures->last()?->id);
                $manualEntry = $manualByPage[$pageNo] ?? null;

                return [
                    'page_no' => $pageNo,
                    'active' => $set->status === OmrPendingCaptureSet::STATUS_PENDING
                        && $pageNo === (int) $set->active_page_no,
                    'capture_count' => $captures?->count() ?? 0,
                    'capture_id' => $captures?->last()?->id,
                    'detected_applicant_number' => $analysis?->detected_applicant_number
                        ?? (is_array($manualEntry) ? ($manualEntry['applicant_number'] ?? null) : null),
                    'manual_applicant_number' => is_array($manualEntry) ? ($manualEntry['applicant_number'] ?? null) : null,
                    'write_in_region' => $regionsByPage[$pageNo] ?? null,
                    'recognition_code' => $analysis?->recognition_code,
                ];
            }, $set->expected_pages_json),
            'template_version_id' => (int) $set->template_version_id,
            'registration_mode' => (string) $set->registration_mode,
            'copy_policy' => (string) $set->copy_policy,
            'expires_at' => $set->expires_at->toIso8601String(),
        ];
    }
}
