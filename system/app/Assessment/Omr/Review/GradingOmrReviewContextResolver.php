<?php

namespace App\Assessment\Omr\Review;

use App\Models\Assessment\OmrCaptureSheet;
use App\Models\Assessment\OmrItemReviewRevision;
use App\Models\Assessment\OmrTemplateItemMapping;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use RuntimeException;

/**
 * Resolves a grading proposal item against the locked capture sheet and its
 * pinned batch configuration. The caller supplies only the stable item key;
 * option identity and machine evidence are read from server-owned state.
 */
final class GradingOmrReviewContextResolver
{
    public function resolve(OmrCaptureSheet|int $sheet, string $itemKey): OmrReviewContext
    {
        $captureSheet = $sheet instanceof OmrCaptureSheet
            ? $sheet
            : OmrCaptureSheet::query()->find($sheet);

        if (! $captureSheet instanceof OmrCaptureSheet) {
            throw (new ModelNotFoundException)->setModel(OmrCaptureSheet::class, [(string) $sheet]);
        }

        $captureSheet->loadMissing([
            'batch.gradingSession.examSession.academicCycle',
            'batch.templateVersion',
            'batch.bookletVersion.questions.aptitudeArea',
            'batch.bookletVersion.questions.templateQuestion.options',
        ]);

        return $this->resolveLocked($captureSheet, $itemKey);
    }

    public function resolveLocked(OmrCaptureSheet $sheet, string $itemKey): OmrReviewContext
    {
        $requestedKey = trim($itemKey);
        if ($requestedKey === '') {
            throw new RuntimeException('omr_review_item_key_missing');
        }
        $sheet->loadMissing([
            'batch.templateVersion.itemMappings.subtest',
            'batch.templateVersion.itemMappings.item',
        ]);

        $proposal = $sheet->proposal_json;
        if (! is_array($proposal)) {
            throw new RuntimeException('omr_review_machine_evidence_missing');
        }

        $items = array_values(array_filter(
            (array) ($proposal['items'] ?? []),
            static fn (mixed $item): bool => is_array($item),
        ));
        $matches = [];
        foreach ($items as $itemIndex => $item) {
            $candidateKey = self::itemKeyFor($item);
            if ($candidateKey === $requestedKey) {
                $matches[] = ['item' => $item, 'item_index' => $itemIndex];
            }
        }
        if (count($matches) !== 1) {
            throw (new ModelNotFoundException)->setModel(OmrCaptureSheet::class, [(string) $sheet->getKey(), $requestedKey]);
        }

        $item = $matches[0]['item'];
        $itemKey = self::itemKeyFor($item);
        if ($itemKey === null) {
            throw new RuntimeException('omr_review_item_key_missing');
        }
        $mapping = $this->templateItemMapping($sheet, $item, $matches[0]['item_index']);
        if ($mapping?->subtest !== null) {
            $item['subtest_code'] = (string) $mapping->subtest->code;
            $item['subtest_name'] = (string) $mapping->subtest->name;
        }

        $machine = is_array($item['machine'] ?? null) ? $item['machine'] : [];
        $proposalOptions = self::optionCodes(
            $item['options']
                ?? ($machine['option_set']
                    ?? ($machine['option_observations'] ?? [])),
        );
        $pinnedOptions = $this->pinnedOptions($sheet, $item);
        if ($pinnedOptions !== [] && $proposalOptions !== [] && $pinnedOptions !== $proposalOptions) {
            throw new RuntimeException('omr_review_option_set_stale');
        }
        $optionSet = $pinnedOptions !== [] ? $pinnedOptions : $proposalOptions;
        if ($optionSet === []) {
            throw new RuntimeException('omr_review_option_set_missing');
        }

        $machine = $this->machineEnvelope($item, $optionSet, $sheet, $itemKey);
        $this->assertCardinality($machine, $optionSet);

        $evidenceHash = $this->evidenceHash($item, $proposal, $sheet, $itemKey, $machine, $optionSet);
        $latest = OmrItemReviewRevision::query()
            ->forGradingItem((int) $sheet->getKey(), $itemKey)
            ->orderByDesc('revision')
            ->first();

        $batch = $sheet->batch;
        $session = $batch?->gradingSession;
        $cycle = $session?->examSession?->academicCycle;
        $scope = [
            'office' => 'guidance',
            'grading_session_id' => $session?->getKey() === null ? null : (int) $session->getKey(),
            'capture_batch_id' => $batch?->getKey() === null ? null : (int) $batch->getKey(),
            'capture_sheet_id' => (int) $sheet->getKey(),
        ];
        if ($cycle !== null) {
            $scope['institution_id'] = (int) $cycle->institution_id;
            $scope['cycle'] = (string) $cycle->code;
        }
        $scope = array_filter($scope, static fn (mixed $value): bool => $value !== null);

        $reviewable = $batch?->status === 'open'
            && in_array($sheet->status, [
                OmrCaptureSheet::STATUS_NEEDS_REVIEW,
                OmrCaptureSheet::STATUS_REVIEWED,
            ], true);

        return new OmrReviewContext(
            type: OmrReviewContext::TYPE_GRADING,
            parentId: (int) $sheet->getKey(),
            itemKey: $itemKey,
            parentVersion: (int) ($sheet->version ?? 0),
            itemRevision: (int) ($latest?->revision ?? 0),
            optionSet: $optionSet,
            evidenceHash: $evidenceHash,
            machineState: $machine['state'],
            machineSelectedOption: $machine['selected_option'],
            observedOptions: $machine['observed_options'],
            lifecycle: $reviewable ? 'reviewable' : 'closed',
            reviewable: $reviewable,
            resourceScope: $scope,
            parent: $sheet,
            gradingItem: $item,
            machineIssueCodes: $machine['issue_codes'],
            machineEnvelope: $machine,
        );
    }

    /**
     * Stable item identity is derived from the persisted area/question pair;
     * only when that pair is absent do we use a server-persisted item label or
     * position.
     *
     * @param  array<string, mixed>  $item
     */
    public static function itemKeyFor(array $item): ?string
    {
        $area = trim((string) ($item['area_code'] ?? ''));
        $questionNo = (int) ($item['question_no'] ?? 0);
        if ($area !== '' && $questionNo > 0) {
            return strtoupper($area).'_Q_'.$questionNo;
        }

        foreach (['item_key', 'item_id', 'native_label'] as $field) {
            $value = trim((string) ($item[$field] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        $position = (int) ($item['position'] ?? 0);

        return $position > 0 ? 'POSITION_'.$position : null;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function templateItemMapping(
        OmrCaptureSheet $sheet,
        array $item,
        int $itemIndex,
    ): ?OmrTemplateItemMapping {
        $mappings = $sheet->batch?->templateVersion?->itemMappings;
        if ($mappings === null || $mappings->isEmpty()) {
            return null;
        }

        $position = is_numeric($item['position'] ?? null) ? (int) $item['position'] : 0;
        $mapping = $position > 0 ? $mappings->firstWhere('global_position', $position) : null;
        if ($mapping instanceof OmrTemplateItemMapping) {
            return $mapping;
        }

        $mapping = $mappings->sortBy('global_position')->values()->get($itemIndex);

        return $mapping instanceof OmrTemplateItemMapping ? $mapping : null;
    }

    /**
     * @return list<string>
     */
    private static function optionCodes(mixed $raw): array
    {
        $codes = [];
        foreach ((array) $raw as $entry) {
            $code = is_array($entry) ? ($entry['code'] ?? null) : $entry;
            if (! is_string($code) && ! is_numeric($code)) {
                continue;
            }
            $code = trim((string) $code);
            if ($code !== '' && ! in_array($code, $codes, true)) {
                $codes[] = $code;
            }
        }

        return $codes;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return list<string>
     */
    private function pinnedOptions(OmrCaptureSheet $sheet, array $item): array
    {
        $batch = $sheet->batch;
        $booklet = $batch?->bookletVersion;
        if ($booklet === null) {
            return [];
        }

        $area = strtoupper(trim((string) ($item['area_code'] ?? '')));
        $questionNo = (int) ($item['question_no'] ?? 0);
        foreach ($booklet->questions as $question) {
            $questionArea = strtoupper(trim((string) ($question->aptitudeArea?->code ?? '')));
            $templateQuestionNo = (int) ($question->templateQuestion?->question_no ?? 0);
            if ($area === '' || $questionNo < 1 || $questionArea !== $area || $templateQuestionNo !== $questionNo) {
                continue;
            }

            return self::optionCodes($question->templateQuestion?->options?->all() ?? []);
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  list<string>  $optionSet
     * @return array{state:string,selected_option:string|null,observed_options:list<string>,option_observations:list<array<string,mixed>>,confidence:float|null,issue_codes:list<string>}
     */
    private function machineEnvelope(array $item, array $optionSet, OmrCaptureSheet $sheet, string $itemKey): array
    {
        $machine = is_array($item['machine'] ?? null) ? $item['machine'] : [];
        $selectedRaw = $machine['selected_option'] ?? ($item['selected_option'] ?? ($item['detected_answer'] ?? null));
        $selected = is_string($selectedRaw) || is_numeric($selectedRaw) ? trim((string) $selectedRaw) : null;
        $selected = $selected === '' ? null : $selected;

        $rawState = strtolower(trim((string) ($machine['state'] ?? ($item['state'] ?? ''))));
        $state = match ($rawState) {
            'marked' => 'marked',
            'blank', 'missing' => 'blank',
            'ambiguous', 'multiple' => 'ambiguous',
            'unreadable' => 'unreadable',
            default => throw new RuntimeException('omr_review_machine_state_missing'),
        };

        $observed = self::optionCodes($machine['observed_options'] ?? ($item['observed_options'] ?? []));
        if ($observed === [] && $selected !== null) {
            $observed = [$selected];
        }
        $rawObservations = $machine['option_observations'] ?? ($item['option_observations'] ?? []);
        $observationByCode = [];
        foreach ((array) $rawObservations as $observation) {
            if (! is_array($observation)) {
                continue;
            }
            $code = trim((string) ($observation['code'] ?? ''));
            if ($code !== '') {
                $observationByCode[$code] = $observation;
            }
        }
        $means = is_array($item['means'] ?? null) ? $item['means'] : [];
        $observations = [];
        foreach ($optionSet as $code) {
            $observation = $observationByCode[$code] ?? [];
            $mean = $observation['mean'] ?? ($means[$code] ?? null);
            $observations[] = [
                'code' => $code,
                'mean' => is_numeric($mean) ? (float) $mean : null,
                'classification' => (string) ($observation['classification'] ?? (in_array($code, $observed, true) ? 'observed' : 'not_observed')),
            ];
        }

        $issues = $machine['issue_codes'] ?? ($item['issue_codes'] ?? []);
        $issueCodes = is_array($issues)
            ? array_values(array_unique(array_filter(array_map('strval', $issues))))
            : [];

        return [
            'state' => $state,
            'selected_option' => $selected,
            'observed_options' => $observed,
            'option_observations' => $observations,
            'confidence' => is_numeric($machine['confidence'] ?? ($item['confidence'] ?? null))
                ? (float) ($machine['confidence'] ?? $item['confidence'])
                : null,
            'issue_codes' => $issueCodes,
            'parent_asset_hash' => (string) ($sheet->aligned_hash ?? ''),
            'item_key' => $itemKey,
        ];
    }

    /**
     * @param  array<string, mixed>  $machine
     * @param  list<string>  $optionSet
     */
    private function assertCardinality(array $machine, array $optionSet): void
    {
        $observed = $machine['observed_options'];
        foreach ($observed as $option) {
            if (! in_array($option, $optionSet, true)) {
                throw new RuntimeException('omr_review_machine_option_invalid');
            }
        }

        $state = $machine['state'];
        $selected = $machine['selected_option'];
        if ($state === 'marked' && (count($observed) !== 1 || $selected === null || $selected !== $observed[0])) {
            throw new RuntimeException('omr_review_machine_cardinality_invalid');
        }
        if ($state === 'blank' && ($observed !== [] || $selected !== null)) {
            throw new RuntimeException('omr_review_machine_cardinality_invalid');
        }
        if ($state === 'ambiguous' && (count($observed) < 2 || $selected !== null)) {
            throw new RuntimeException('omr_review_machine_cardinality_invalid');
        }
        if ($state === 'unreadable' && $selected !== null) {
            throw new RuntimeException('omr_review_machine_cardinality_invalid');
        }
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<string, mixed>  $proposal
     * @param  array<string, mixed>  $machine
     * @param  list<string>  $optionSet
     */
    private function evidenceHash(
        array $item,
        array $proposal,
        OmrCaptureSheet $sheet,
        string $itemKey,
        array $machine,
        array $optionSet,
    ): string {
        $provided = $item['evidence_hash'] ?? ($item['machine']['evidence_hash'] ?? null);
        if ($provided !== null) {
            $provided = trim((string) $provided);
            if (preg_match('/^[a-fA-F0-9]{64}$/', $provided) !== 1) {
                throw new RuntimeException('omr_review_evidence_hash_invalid');
            }

            return strtolower($provided);
        }

        $identity = [
            'item_key' => $itemKey,
            'machine' => [
                'state' => $machine['state'],
                'selected_option' => $machine['selected_option'],
                'observed_options' => $machine['observed_options'],
                'option_observations' => $machine['option_observations'],
                'confidence' => $machine['confidence'],
                'issue_codes' => $machine['issue_codes'],
            ],
            'option_set' => $optionSet,
            'sheet' => [
                'sheet_id' => (int) $sheet->getKey(),
                'version' => (int) ($sheet->version ?? 0),
                'aligned_hash' => (string) ($sheet->aligned_hash ?? ''),
                'proposal_input_hash' => (string) ($proposal['input_hash'] ?? ''),
                'proposal_native_bundle_hash' => (string) ($proposal['native_bundle_hash'] ?? ''),
                'proposal_manifest_hash' => (string) ($proposal['manifest_hash'] ?? ''),
                'proposal_config_hash' => (string) ($proposal['config_hash'] ?? ''),
                'envelope' => $proposal['envelope'] ?? null,
            ],
        ];

        return hash('sha256', json_encode($identity, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }
}
