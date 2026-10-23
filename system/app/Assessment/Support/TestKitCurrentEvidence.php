<?php

declare(strict_types=1);

namespace App\Assessment\Support;

use App\Models\Assessment\OmrDiagnosticCapture;
use App\Models\Assessment\OmrDiagnosticItemEvidence;
use App\Models\Assessment\OmrDiagnosticReviewRevision;
use App\Models\Assessment\OmrDiagnosticRun;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;

/**
 * Resolves the immutable current case/page selection for a frozen kit rehearsal.
 *
 * The vector deliberately follows page-selection pointers, never capture creation
 * order or input-asset identity. It is safe to use as the shared freshness seam
 * for comparison, sealing, receipts, and status projections.
 */
final class TestKitCurrentEvidence
{
    /**
     * @return array{
     *   run: OmrDiagnosticRun,
     *   captures: EloquentCollection<int, OmrDiagnosticCapture>,
     *   attempts: list<array<string, mixed>>,
     *   evidence_vector: array{schema_version: string, run_id: int, plan_sha256: string, entries: list<array<string, mixed>>},
     *   evidence_vector_sha256: string,
     *   complete: bool,
     *   errors: list<array{code: string, case_id?: string, page_no?: int}>
     * }
     */
    public function forRun(OmrDiagnosticRun $run, bool $lock = false): array
    {
        $resolved = OmrDiagnosticRun::query()
            ->whereKey((int) $run->getKey())
            ->when($lock, static fn ($query) => $query->lockForUpdate())
            ->firstOrFail();

        $errors = [];
        $plan = $resolved->getAttribute('acceptance_plan_json');
        $planHash = (string) ($resolved->getAttribute('acceptance_plan_sha256') ?? '');
        if (! is_array($plan) || $plan === [] || preg_match('/^[a-f0-9]{64}$/D', $planHash) !== 1) {
            return $this->result($resolved, [], [], [], $planHash, [['code' => 'acceptance_plan_missing']]);
        }
        $actualPlanHash = hash(
            'sha256',
            json_encode($this->canonicalize($plan), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        );
        if (! hash_equals($planHash, $actualPlanHash)) {
            $errors[] = ['code' => 'acceptance_plan_hash_mismatch'];
        }
        foreach ([
            'test_kit_version_id',
            'booklet_set_id',
            'test_instrument_version_id',
            'acceptance_plan_schema_version',
            'acceptance_protocol_schema_version',
            'scoring_kernel_identity',
        ] as $field) {
            if ($resolved->getAttribute($field) === null || $resolved->getAttribute($field) === '') {
                $errors[] = ['code' => 'acceptance_context_missing'];
                break;
            }
        }
        foreach (['manifest_sha256', 'profile_content_sha256', 'acceptance_protocol_sha256', 'acceptance_plan_sha256'] as $field) {
            if (preg_match('/^[a-f0-9]{64}$/D', (string) $resolved->getAttribute($field)) !== 1) {
                $errors[] = ['code' => 'acceptance_context_hash_invalid'];
                break;
            }
        }
        if (! is_array($resolved->getAttribute('expected_worker_identity_json'))) {
            $errors[] = ['code' => 'acceptance_worker_identity_missing'];
        }

        $inventory = $this->plannedInventory($plan, $errors);
        $bindingInventory = $this->plannedBindings($plan, $errors);
        $this->assertPlanBindings($inventory, $bindingInventory, $errors);
        $selectionQuery = DB::table('test_kit_diagnostic_page_selections')
            ->where('diagnostic_run_id', (int) $resolved->getKey())
            ->orderBy('case_id')
            ->orderBy('page_no')
            ->orderBy('id');
        if ($lock) {
            $selectionQuery->lockForUpdate();
        }
        $selections = $selectionQuery->get();
        if ($lock) {
            $this->lockSelectedLineage((int) $resolved->getKey(), $selections);
        }

        $expectedSelectionKeys = array_fill_keys(array_keys($inventory), true);
        foreach ($selections as $selection) {
            $key = $this->pageKey((string) $selection->case_id, (int) $selection->page_no);
            if (! isset($expectedSelectionKeys[$key])) {
                $errors[] = ['code' => 'selected_page_extra', 'case_id' => (string) $selection->case_id, 'page_no' => (int) $selection->page_no];
            }
        }

        $selectionByKey = [];
        foreach ($selections as $selection) {
            $selectionByKey[$this->pageKey((string) $selection->case_id, (int) $selection->page_no)] = $selection;
        }

        $selectedRows = [];
        foreach ($inventory as $key => $plannedPage) {
            $selection = $selectionByKey[$key] ?? null;
            if ($selection === null) {
                $errors[] = ['code' => 'selected_page_missing', 'case_id' => $plannedPage['case_id'], 'page_no' => $plannedPage['page_no']];

                continue;
            }
            $attemptQuery = DB::table('test_kit_diagnostic_page_attempts')
                ->where('id', (int) $selection->selected_attempt_id)
                ->where('diagnostic_run_id', (int) $resolved->getKey());
            $attempt = $attemptQuery->first();
            if ($attempt === null
                || (string) $attempt->case_id !== $plannedPage['case_id']
                || (int) $attempt->page_no !== $plannedPage['page_no']) {
                $errors[] = ['code' => 'selected_attempt_lineage_mismatch', 'case_id' => $plannedPage['case_id'], 'page_no' => $plannedPage['page_no']];

                continue;
            }
            $pendingLineage = $this->pendingLineage($resolved, $attempt, $plannedPage, false, $errors);
            if ($lock) {
                $attempt = DB::table('test_kit_diagnostic_page_attempts')
                    ->where('id', (int) $selection->selected_attempt_id)
                    ->where('diagnostic_run_id', (int) $resolved->getKey())
                    ->lockForUpdate()
                    ->first();
            }
            if ($attempt === null
                || (string) $attempt->case_id !== $plannedPage['case_id']
                || (int) $attempt->page_no !== $plannedPage['page_no']) {
                $errors[] = ['code' => 'selected_attempt_lineage_changed', 'case_id' => $plannedPage['case_id'], 'page_no' => $plannedPage['page_no']];

                continue;
            }

            if ($attempt->predecessor_attempt_id !== null) {
                $predecessorQuery = DB::table('test_kit_diagnostic_page_attempts')
                    ->where('id', (int) $attempt->predecessor_attempt_id)
                    ->where('diagnostic_run_id', (int) $resolved->getKey());
                if ($lock) {
                    $predecessorQuery->lockForUpdate();
                }
                $predecessor = $predecessorQuery->first();
                if ($predecessor === null
                    || (string) $predecessor->case_id !== $plannedPage['case_id']
                    || (int) $predecessor->page_no !== $plannedPage['page_no']) {
                    $errors[] = ['code' => 'predecessor_lineage_mismatch', 'case_id' => $plannedPage['case_id'], 'page_no' => $plannedPage['page_no']];
                }
            }

            $captureQuery = OmrDiagnosticCapture::query()
                ->whereKey((int) $attempt->diagnostic_capture_id)
                ->where('diagnostic_run_id', (int) $resolved->getKey());
            if ($lock) {
                $captureQuery->lockForUpdate();
            }
            $capture = $captureQuery->first();
            if (! $capture instanceof OmrDiagnosticCapture) {
                $errors[] = ['code' => 'capture_lineage_mismatch', 'case_id' => $plannedPage['case_id'], 'page_no' => $plannedPage['page_no']];

                continue;
            }
            if ($capture->failure_code === 'discarded_by_operator') {
                $errors[] = [
                    'code' => 'capture_discarded_by_operator',
                    'case_id' => $plannedPage['case_id'],
                    'page_no' => $plannedPage['page_no'],
                ];
            }

            $attemptInputHash = (string) $attempt->input_sha256;
            if (preg_match('/^[a-f0-9]{64}$/D', $attemptInputHash) !== 1
                || ! hash_equals($attemptInputHash, (string) $capture->input_asset_hash)) {
                $errors[] = ['code' => 'capture_input_hash_mismatch', 'case_id' => $plannedPage['case_id'], 'page_no' => $plannedPage['page_no']];
            }
            $inputAsset = DB::table('omr_template_assets')
                ->where('id', (int) $capture->input_asset_id)
                ->first();
            if ($inputAsset === null
                || (int) $inputAsset->template_version_id !== (int) $resolved->template_version_id
                || ! hash_equals((string) $capture->input_asset_hash, (string) $inputAsset->sha256)) {
                $errors[] = ['code' => 'capture_input_asset_link_mismatch', 'case_id' => $plannedPage['case_id'], 'page_no' => $plannedPage['page_no']];
            }
            if (preg_match('/^[a-f0-9]{64}$/D', (string) $capture->evidence_hash) !== 1) {
                $errors[] = ['code' => 'capture_evidence_hash_missing', 'case_id' => $plannedPage['case_id'], 'page_no' => $plannedPage['page_no']];
            }

            if ($pendingLineage !== null) {
                $evidencePayload = is_array($capture->evidence_json) ? $capture->evidence_json : [];
                $link = is_array($evidencePayload['pending_page_link'] ?? null) ? $evidencePayload['pending_page_link'] : null;
                $preLinkHash = (string) ($evidencePayload['evidence_hash'] ?? '');

                if ($link === null
                    || (int) ($link['capture_set_id'] ?? 0) !== (int) $attempt->pending_set_id
                    || (int) ($link['pending_page_evidence_id'] ?? 0) !== (int) $attempt->pending_page_evidence_id
                    || (int) ($link['page_no'] ?? 0) !== $plannedPage['page_no']
                    || ! is_string($link['input_hash'] ?? null)
                    || ! hash_equals((string) $attempt->input_sha256, (string) $link['input_hash'])
                    || ! is_string($link['diagnostic_evidence_hash'] ?? null)
                    || preg_match('/^[a-f0-9]{64}$/D', (string) $link['diagnostic_evidence_hash']) !== 1
                    || ! hash_equals($preLinkHash, (string) $link['diagnostic_evidence_hash'])) {
                    $errors[] = ['code' => 'capture_pending_source_link_mismatch', 'case_id' => $plannedPage['case_id'], 'page_no' => $plannedPage['page_no']];
                }
            } elseif (is_array($capture->evidence_json) && array_key_exists('pending_page_link', $capture->evidence_json)) {
                $errors[] = ['code' => 'capture_pending_source_link_unbound', 'case_id' => $plannedPage['case_id'], 'page_no' => $plannedPage['page_no']];
            }
            $rawIdentities = $attempt->item_bindings_json ?? null;
            $mappedIdentities = $this->mappedIdentities($rawIdentities, $plannedPage, $bindingInventory, $errors);
            $items = $this->itemVector($capture, $plannedPage, $mappedIdentities, $lock, $errors);
            if (! in_array($capture->status, [OmrDiagnosticCapture::STATUS_NEEDS_REVIEW, OmrDiagnosticCapture::STATUS_REVIEWED, OmrDiagnosticCapture::STATUS_COMPLETED], true)) {
                $errors[] = ['code' => 'capture_not_reviewed', 'case_id' => $plannedPage['case_id'], 'page_no' => $plannedPage['page_no']];
            }

            $row = [
                'case_id' => $plannedPage['case_id'],
                'page_no' => $plannedPage['page_no'],
                'selection_id' => (int) $selection->id,
                'selection_revision' => (int) $selection->revision,
                'attempt_id' => (int) $attempt->id,
                'predecessor_attempt_id' => $attempt->predecessor_attempt_id === null ? null : (int) $attempt->predecessor_attempt_id,
                'capture_id' => (int) $capture->getKey(),
                'capture_version' => (int) $capture->version,
                'capture_evidence_sha256' => (string) ($capture->evidence_hash ?? ''),
                'input_sha256' => $attemptInputHash,
                'input_asset_id' => (int) $capture->input_asset_id,
                'input_asset_sha256' => (string) $capture->input_asset_hash,
                'pending_set_id' => $attempt->pending_set_id === null ? null : (int) $attempt->pending_set_id,
                'pending_page_evidence_id' => $attempt->pending_page_evidence_id === null ? null : (int) $attempt->pending_page_evidence_id,
                'pending_page' => $pendingLineage,
                'items' => $items,
            ];
            $selectedRows[] = ['attempt' => (array) $attempt, 'capture' => $capture, 'vector' => $row];
        }

        $entries = array_map(static fn (array $row): array => $row['vector'], $selectedRows);
        $vector = $this->canonicalize([
            'schema_version' => 'nexiam.test-kit-current-evidence.v1',
            'run_id' => (int) $resolved->getKey(),
            'run_revision' => (int) $resolved->revision,
            'plan_sha256' => $planHash,
            'entries' => $entries,
        ]);
        $vectorHash = hash('sha256', json_encode($vector, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $this->assertSealCurrent($resolved, $vectorHash, $errors);

        return $this->result(
            $resolved,
            array_map(static fn (array $row): OmrDiagnosticCapture => $row['capture'], $selectedRows),
            array_map(static fn (array $row): array => $row['attempt'], $selectedRows),
            $vector,
            $vectorHash,
            $errors,
        );
    }

    /** @return array<string, array{case_id: string, page_no: int, responses: list<array{native_label: string, token: ?string}>}> */
    private function plannedInventory(array $plan, array &$errors): array
    {
        $cases = $plan['cases'] ?? null;
        if (! is_array($cases) || ! array_is_list($cases) || $cases === []) {
            $errors[] = ['code' => 'acceptance_plan_inventory_invalid'];

            return [];
        }

        $inventory = [];
        foreach ($cases as $case) {
            if (! is_array($case) || ! is_string($case['case_id'] ?? null) || $case['case_id'] === '' || ! is_array($case['pages'] ?? null) || ! array_is_list($case['pages']) || $case['pages'] === []) {
                $errors[] = ['code' => 'acceptance_plan_case_invalid'];

                continue;
            }
            foreach ($case['pages'] as $page) {
                $caseId = (string) $case['case_id'];
                $pageNo = is_array($page) ? (int) ($page['page_no'] ?? 0) : 0;
                $responses = is_array($page) ? ($page['responses'] ?? null) : null;
                if ($pageNo < 1 || ! is_array($responses) || ! array_is_list($responses)) {
                    $errors[] = ['code' => 'acceptance_plan_page_invalid', 'case_id' => $caseId, 'page_no' => $pageNo];

                    continue;
                }
                $labels = [];
                foreach ($responses as $response) {
                    $label = is_array($response) ? ($response['native_label'] ?? null) : null;
                    if (! is_string($label) || $label === '' || isset($labels[$label]) || ! array_key_exists('token', (array) $response)) {
                        $errors[] = ['code' => 'acceptance_plan_response_inventory_invalid', 'case_id' => $caseId, 'page_no' => $pageNo];

                        continue;
                    }
                    $labels[$label] = true;
                }
                $key = $this->pageKey($caseId, $pageNo);
                if (isset($inventory[$key])) {
                    $errors[] = ['code' => 'acceptance_plan_page_duplicate', 'case_id' => $caseId, 'page_no' => $pageNo];

                    continue;
                }
                $inventory[$key] = ['case_id' => $caseId, 'page_no' => $pageNo, 'responses' => $responses];
            }
        }
        ksort($inventory, SORT_STRING);

        return $inventory;
    }

    private function assertPlanBindings(array $inventory, array $bindings, array &$errors): void
    {
        $expected = [];
        foreach ($inventory as $page) {
            $labels = array_values(array_map(
                static fn (array $response): string => (string) $response['native_label'],
                $page['responses'],
            ));
            sort($labels, SORT_STRING);
            $expected[$this->pageKey('', (int) $page['page_no'])] = $labels;
        }

        foreach ($bindings as $pageKey => $pageBindings) {
            $expectedLabels = $expected[$pageKey] ?? null;
            $actualLabels = array_keys($pageBindings);
            sort($actualLabels, SORT_STRING);
            if ($expectedLabels === null || $actualLabels !== $expectedLabels) {
                $errors[] = ['code' => 'acceptance_plan_binding_inventory_mismatch'];
            }
            unset($expected[$pageKey]);
        }
        if ($expected !== []) {
            $errors[] = ['code' => 'acceptance_plan_binding_inventory_mismatch'];
        }
    }

    /** @return array<string, array<string, array<string, mixed>>> */
    private function plannedBindings(array $plan, array &$errors): array
    {
        $bindings = $plan['item_bindings'] ?? null;
        if (! is_array($bindings) || ! array_is_list($bindings) || $bindings === []) {
            $errors[] = ['code' => 'acceptance_plan_item_bindings_missing'];

            return [];
        }
        $indexed = [];
        foreach ($bindings as $binding) {
            if (! is_array($binding) || (int) ($binding['page_no'] ?? 0) < 1 || ! is_string($binding['native_label'] ?? null) || $binding['native_label'] === '') {
                $errors[] = ['code' => 'acceptance_plan_item_binding_invalid'];

                continue;
            }
            $key = $this->pageKey('', (int) $binding['page_no']);
            $label = (string) $binding['native_label'];
            if (isset($indexed[$key][$label])) {
                $errors[] = ['code' => 'acceptance_plan_item_binding_duplicate', 'page_no' => (int) $binding['page_no']];

                continue;
            }
            $indexed[$key][$label] = $binding;
        }

        return $indexed;
    }

    private function lockSelectedLineage(int $runId, iterable $selections): void
    {
        $selectedAttemptIds = [];
        foreach ($selections as $selection) {
            $selectedAttemptIds[] = (int) $selection->selected_attempt_id;
        }
        $selectedAttemptIds = array_values(array_unique($selectedAttemptIds));
        sort($selectedAttemptIds, SORT_NUMERIC);
        if ($selectedAttemptIds === []) {
            return;
        }

        $attempts = DB::table('test_kit_diagnostic_page_attempts')
            ->where('diagnostic_run_id', $runId)
            ->whereIn('id', $selectedAttemptIds)
            ->orderBy('id')
            ->get();
        $pendingSetIds = $attempts->pluck('pending_set_id')->filter()->map(static fn ($id): int => (int) $id)->unique()->sort()->values()->all();
        $pendingEvidenceIds = $attempts->pluck('pending_page_evidence_id')->filter()->map(static fn ($id): int => (int) $id)->unique()->sort()->values()->all();
        if ($pendingSetIds !== []) {
            DB::table('omr_pending_capture_sets')->whereIn('id', $pendingSetIds)->orderBy('id')->lockForUpdate()->get();
        }
        if ($pendingEvidenceIds !== []) {
            DB::table('omr_pending_capture_page_evidence')->whereIn('id', $pendingEvidenceIds)->orderBy('id')->lockForUpdate()->get();
        }

        $lineageAttemptIds = $selectedAttemptIds;
        foreach ($attempts as $attempt) {
            if ($attempt->predecessor_attempt_id !== null) {
                $lineageAttemptIds[] = (int) $attempt->predecessor_attempt_id;
            }
        }
        $lineageAttemptIds = array_values(array_unique($lineageAttemptIds));
        sort($lineageAttemptIds, SORT_NUMERIC);
        DB::table('test_kit_diagnostic_page_attempts')
            ->where('diagnostic_run_id', $runId)
            ->whereIn('id', $lineageAttemptIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
        $captureIds = $attempts->pluck('diagnostic_capture_id')->map(static fn ($id): int => (int) $id)->unique()->sort()->values()->all();
        if ($captureIds !== []) {
            OmrDiagnosticCapture::query()->whereIn('id', $captureIds)->orderBy('id')->lockForUpdate()->get();
        }
        $itemIds = OmrDiagnosticItemEvidence::query()
            ->whereIn('diagnostic_capture_id', $captureIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
        if ($itemIds !== []) {
            DB::table('omr_diagnostic_review_revisions as revisions')
                ->whereIn('revisions.diagnostic_item_evidence_id', $itemIds)
                ->whereRaw('revisions.revision = (SELECT MAX(current_revision.revision) FROM omr_diagnostic_review_revisions AS current_revision WHERE current_revision.diagnostic_item_evidence_id = revisions.diagnostic_item_evidence_id)')
                ->orderBy('revisions.id')
                ->lockForUpdate()
                ->get();
        }
    }

    private function pendingLineage(OmrDiagnosticRun $run, object $attempt, array $plannedPage, bool $lock, array &$errors): ?array
    {
        if ($attempt->pending_set_id === null && $attempt->pending_page_evidence_id === null) {
            return null;
        }
        if ($attempt->pending_set_id === null || $attempt->pending_page_evidence_id === null) {
            $errors[] = ['code' => 'pending_page_lineage_incomplete', 'case_id' => $plannedPage['case_id'], 'page_no' => $plannedPage['page_no']];

            return null;
        }
        $setQuery = DB::table('omr_pending_capture_sets')->where('id', (int) $attempt->pending_set_id);
        if ($lock) {
            $setQuery->lockForUpdate();
        }
        $set = $setQuery->first();
        $evidenceQuery = DB::table('omr_pending_capture_page_evidence')->where('id', (int) $attempt->pending_page_evidence_id);
        if ($lock) {
            $evidenceQuery->lockForUpdate();
        }
        $evidence = $evidenceQuery->first();
        if ($set === null || $evidence === null
            || (int) $set->diagnostic_run_id !== (int) $run->getKey()
            || (int) $evidence->set_id !== (int) $set->id
            || (int) $set->id !== (int) $attempt->pending_set_id
            || (int) $evidence->page_no !== $plannedPage['page_no']
            || $set->production_batch_id !== null
            || (string) $set->copy_policy !== 'diagnostic_only'
            || ! hash_equals((string) $attempt->input_sha256, (string) $evidence->input_hash)) {
            $errors[] = ['code' => 'pending_page_lineage_mismatch', 'case_id' => $plannedPage['case_id'], 'page_no' => $plannedPage['page_no']];
        }
        $successorQuery = DB::table('omr_pending_capture_page_evidence')
            ->where('supersedes_id', (int) $evidence->id)
            ->orderBy('id');
        if ($lock) {
            $successorQuery->lockForUpdate();
        }
        if ($evidence !== null && $successorQuery->exists()) {
            $errors[] = ['code' => 'pending_page_evidence_superseded', 'case_id' => $plannedPage['case_id'], 'page_no' => $plannedPage['page_no']];
        }

        return $evidence === null ? null : [
            'set_id' => (int) $evidence->set_id,
            'page_evidence_id' => (int) $evidence->id,
            'page_no' => (int) $evidence->page_no,
            'input_sha256' => (string) $evidence->input_hash,
            'supersedes_id' => $evidence->supersedes_id === null ? null : (int) $evidence->supersedes_id,
        ];
    }

    /** @return list<array<string, mixed>> */
    private function mappedIdentities(mixed $raw, array $plannedPage, array $bindings, array &$errors): array
    {
        $identities = is_array($raw) ? $raw : json_decode((string) $raw, true);
        if (! is_array($identities) || ! array_is_list($identities)) {
            $errors[] = ['code' => 'mapped_item_identity_missing', 'case_id' => $plannedPage['case_id'], 'page_no' => $plannedPage['page_no']];

            return [];
        }
        $pageBindings = $bindings[$this->pageKey('', $plannedPage['page_no'])] ?? [];
        $expectedLabels = array_values(array_map(static fn (array $response): string => (string) $response['native_label'], $plannedPage['responses']));
        $identityLabels = [];
        $normalized = [];
        foreach ($identities as $identity) {
            if (! is_array($identity) || ! is_string($identity['native_label'] ?? null)) {
                $errors[] = ['code' => 'mapped_item_identity_invalid', 'case_id' => $plannedPage['case_id'], 'page_no' => $plannedPage['page_no']];

                continue;
            }
            $label = (string) $identity['native_label'];
            $binding = $pageBindings[$label] ?? null;
            if ($binding === null || in_array($label, $identityLabels, true)) {
                $errors[] = ['code' => 'mapped_item_identity_mismatch', 'case_id' => $plannedPage['case_id'], 'page_no' => $plannedPage['page_no']];

                continue;
            }
            foreach (['page_no', 'native_label', 'global_position', 'sheet_item_id', 'item_id', 'subtest_id', 'option_tokens'] as $field) {
                if (array_key_exists($field, $binding) && ($identity[$field] ?? null) !== $binding[$field]) {
                    $errors[] = ['code' => 'mapped_item_identity_mismatch', 'case_id' => $plannedPage['case_id'], 'page_no' => $plannedPage['page_no']];
                }
            }
            $identityLabels[] = $label;
            $normalized[] = $identity;
        }
        sort($expectedLabels, SORT_STRING);
        sort($identityLabels, SORT_STRING);
        if ($expectedLabels !== $identityLabels) {
            $errors[] = ['code' => 'mapped_item_inventory_incomplete', 'case_id' => $plannedPage['case_id'], 'page_no' => $plannedPage['page_no']];
        }
        usort($normalized, static fn (array $left, array $right): int => strcmp((string) $left['native_label'], (string) $right['native_label']));

        return $normalized;
    }

    /** @return list<array<string, mixed>> */
    private function itemVector(OmrDiagnosticCapture $capture, array $plannedPage, array $identities, bool $lock, array &$errors): array
    {
        if (OmrDiagnosticItemEvidence::query()
            ->where('diagnostic_capture_id', (int) $capture->getKey())
            ->where('page_no', '!=', $plannedPage['page_no'])
            ->exists()) {
            $errors[] = ['code' => 'capture_has_unplanned_item_evidence', 'case_id' => $plannedPage['case_id'], 'page_no' => $plannedPage['page_no']];
        }
        $itemsQuery = OmrDiagnosticItemEvidence::query()
            ->where('diagnostic_capture_id', (int) $capture->getKey())
            ->where('page_no', $plannedPage['page_no'])
            ->orderBy('item_key');
        if ($lock) {
            $itemsQuery->lockForUpdate();
        }
        $items = $itemsQuery->get();
        if ($items->count() !== count($plannedPage['responses']) || $items->count() !== count($identities)) {
            $errors[] = ['code' => 'mapped_item_evidence_incomplete', 'case_id' => $plannedPage['case_id'], 'page_no' => $plannedPage['page_no']];
        }

        $byKey = $identities;
        $itemVectors = [];
        foreach ($items as $item) {
            $identity = collect($byKey)->first(static fn (array $entry): bool => isset($entry['item_key']) && (string) $entry['item_key'] === (string) $item->item_key);
            if ($identity === null || (int) $item->page_no !== $plannedPage['page_no'] || preg_match('/^[a-f0-9]{64}$/D', (string) $item->evidence_hash) !== 1) {
                $errors[] = ['code' => 'mapped_item_evidence_mismatch', 'case_id' => $plannedPage['case_id'], 'page_no' => $plannedPage['page_no']];

                continue;
            }
            $reviewQuery = OmrDiagnosticReviewRevision::query()
                ->where('diagnostic_item_evidence_id', (int) $item->getKey())
                ->orderByDesc('revision')
                ->orderByDesc('id');
            if ($lock) {
                $reviewQuery->lockForUpdate();
            }
            $review = $reviewQuery->first();
            if ($review === null) {
                $errors[] = ['code' => 'item_review_missing', 'case_id' => $plannedPage['case_id'], 'page_no' => $plannedPage['page_no']];
            } elseif (! hash_equals((string) $item->evidence_hash, (string) $review->reviewed_evidence_hash)
                || (int) $review->parent_revision > (int) $capture->version) {
                $errors[] = ['code' => 'item_review_stale', 'case_id' => $plannedPage['case_id'], 'page_no' => $plannedPage['page_no']];
            }
            $itemVectors[] = [
                'item_key' => (string) $item->item_key,
                'item_identity' => $identity,
                'evidence_id' => (int) $item->getKey(),
                'evidence_version' => (string) $item->evidence_version,
                'evidence_sha256' => (string) $item->evidence_hash,
                'review_id' => $review === null ? null : (int) $review->getKey(),
                'review_revision' => $review === null ? null : (int) $review->revision,
                'review_decision' => $review?->decision?->value,
            ];
        }
        usort($itemVectors, static fn (array $left, array $right): int => strcmp($left['item_key'], $right['item_key']));

        return $itemVectors;
    }

    private function assertSealCurrent(OmrDiagnosticRun $run, string $vectorHash, array &$errors): void
    {
        $sealedHash = (string) ($run->getAttribute('sealed_evidence_vector_sha256') ?? '');
        $sealedRevision = $run->getAttribute('sealed_evidence_revision');
        $sealedAt = $run->getAttribute('sealed_at');
        if ($sealedAt !== null
            && (
                $sealedRevision === null
                || (int) $sealedRevision !== (int) $run->revision
                || $sealedHash === ''
                || ! hash_equals($sealedHash, $vectorHash)
            )) {
            $errors[] = ['code' => 'sealed_evidence_stale'];
        }
    }

    /** @param list<array<string, mixed>> $attempts @param list<array<string, mixed>> $captures @param list<array<string, mixed>> $errors */
    private function result(OmrDiagnosticRun $run, array $captures, array $attempts, array $vector, string $vectorHash, array $errors): array
    {
        $evidenceVector = $vector === [] ? [
            'schema_version' => 'nexiam.test-kit-current-evidence.v1',
            'run_id' => (int) $run->getKey(),
            'run_revision' => (int) $run->revision,
            'plan_sha256' => (string) ($run->getAttribute('acceptance_plan_sha256') ?? ''),
            'entries' => [],
        ] : $vector;
        $evidenceVectorHash = $vectorHash === ''
            ? hash('sha256', json_encode($this->canonicalize($evidenceVector), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE))
            : $vectorHash;

        return [
            'run' => $run,
            'captures' => new EloquentCollection($captures),
            'attempts' => $attempts,
            'evidence_vector' => $evidenceVector,
            'evidence_vector_sha256' => $evidenceVectorHash,
            'complete' => $errors === [],
            'errors' => $this->uniqueErrors($errors),
        ];
    }

    /** @param list<array<string, mixed>> $errors @return list<array<string, mixed>> */
    private function uniqueErrors(array $errors): array
    {
        $unique = [];
        foreach ($errors as $error) {
            $unique[json_encode($error, JSON_THROW_ON_ERROR)] = $error;
        }

        return array_values($unique);
    }

    public function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(fn (mixed $item): mixed => $this->canonicalize($item), $value);
        }

        ksort($value, SORT_STRING);

        foreach ($value as $key => $item) {
            $value[$key] = $this->canonicalize($item);
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $vector
     */
    public function computeVectorHash(array $vector): string
    {
        $canonical = $this->canonicalize($vector);

        return hash('sha256', json_encode($canonical, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    private function pageKey(string $caseId, int $pageNo): string
    {
        return $caseId.'#'.$pageNo;
    }
}
