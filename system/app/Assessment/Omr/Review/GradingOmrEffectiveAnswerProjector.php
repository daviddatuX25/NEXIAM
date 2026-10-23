<?php

namespace App\Assessment\Omr\Review;

use App\Assessment\Omr\OmrCaptureResolution;
use App\Assessment\Omr\OmrCaptureResolver;
use App\Assessment\Omr\OmrPinnedSnapshot;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrBookletSet;
use App\Models\Assessment\OmrCaptureSheet;
use App\Models\Assessment\OmrItemReviewRevision;
use App\Models\Assessment\OmrQuestionBookletVersion;
use App\Models\Foundation\AptitudeArea;
use App\Models\User;
use RuntimeException;

/**
 * Server-only projector for grading effective answers, issue reconciliation,
 * derived per-area scores, and immutable score provenance (OpenSpec Section 9).
 */
final class GradingOmrEffectiveAnswerProjector
{
    public function __construct(private readonly OmrCaptureResolver $captureResolver) {}

    /**
     * Projects effective answers and derived per-area scores for a capture sheet.
     *
     * @param  array<string, mixed>|null  $kitContext
     * @return array{
     *     per_area_scores: array<int, array{score: int, confidence: float|null, area_code: string, max_items: int}>,
     *     is_complete: bool,
     *     has_unresolved: bool,
     *     has_stale: bool,
     *     unresolved_item_keys: list<string>,
     *     stale_item_keys: list<string>,
     *     items: list<array<string, mixed>>,
     *     provenance: array<string, mixed>
     * }
     */
    public function project(OmrCaptureSheet $sheet, ?array $kitContext = null): array
    {
        $sheet->loadMissing([
            'batch.templateVersion',
            'batch.bookletVersion.questions.aptitudeArea',
            'batch.bookletVersion.questions.templateQuestion',
            'bookletSet.items.aptitudeArea',
        ]);

        $proposal = $sheet->proposal_json;
        if (! is_array($proposal)) {
            throw new RuntimeException('Sheet proposal machine evidence is missing.');
        }

        $items = array_values(array_filter(
            (array) ($proposal['items'] ?? []),
            static fn (mixed $i): bool => is_array($i),
        ));

        if ($items === []) {
            throw new RuntimeException('Sheet proposal contains no scorable items.');
        }

        $resolution = $this->captureResolver->resolveSheet($sheet, $kitContext);
        if (! $resolution->isResolved()) {
            throw new RuntimeException($resolution->failure?->message ?? 'The capture material could not be resolved.');
        }
        $correctOptionsByPosition = $this->resolveCorrectOptionsByPosition($resolution);
        $kitBindingsByPosition = $this->kitBindingsByPosition($kitContext);
        $geometryByPosition = [];
        if ($kitContext !== null) {
            $template = $kitContext['template'] ?? null;
            $booklet = $kitContext['booklet'] ?? null;
            $set = $kitContext['set'] ?? null;
            if (! $template instanceof OmrAnswerSheetTemplateVersion
                || ! $booklet instanceof OmrQuestionBookletVersion
                || ! $set instanceof OmrBookletSet) {
                throw new RuntimeException('The exact Test Kit OMR components are unavailable.');
            }
            $pinned = OmrPinnedSnapshot::pin($template, $booklet, $set, $kitContext);
            foreach ($pinned->items as $geometryItem) {
                $geometryByPosition[$geometryItem->position] = $geometryItem;
            }
        }
        $areas = AptitudeArea::query()->where('is_active', true)->get()->keyBy('id');
        $areasByCode = AptitudeArea::query()->where('is_active', true)->get()->keyBy(static fn (AptitudeArea $a): string => strtoupper($a->code));

        $itemKeys = [];
        foreach ($items as $item) {
            $key = GradingOmrReviewContextResolver::itemKeyFor($item);
            if ($key !== null) {
                $itemKeys[] = $key;
            }
        }

        $reviews = OmrItemReviewRevision::latestForGradingItems((int) $sheet->getKey(), $itemKeys);

        $perAreaCounts = [];
        $perAreaScores = [];
        $perAreaConfidences = [];
        $projectedItems = [];
        $unresolvedKeys = [];
        $staleKeys = [];
        $humanRevisionsCount = 0;
        $machineItemsCount = 0;

        foreach ($items as $item) {
            $itemKey = GradingOmrReviewContextResolver::itemKeyFor($item) ?? 'POSITION_'.($item['position'] ?? 0);
            $position = (int) ($item['position'] ?? 0);
            $areaCode = strtoupper(trim((string) ($item['area_code'] ?? '')));
            $questionNo = (int) ($item['question_no'] ?? 0);
            $kitBinding = $kitBindingsByPosition[$position] ?? null;
            $geometryItem = $geometryByPosition[$position] ?? null;
            if ($kitContext !== null && ($kitBinding === null
                || $geometryItem === null
                || (int) $kitBinding['page_no'] !== (int) $geometryItem->pageNo
                || (string) $kitBinding['native_label'] !== $geometryItem->nativeLabel
                || ! is_string($geometryItem->physicalItemUid)
                || $kitBinding['physical_item_uid'] !== $geometryItem->physicalItemUid)) {
                throw new RuntimeException('The reviewed observations do not match the exact Test Kit item map.');
            }

            $area = null;
            if (isset($item['area_id']) && is_numeric($item['area_id'])) {
                $area = $areas->get((int) $item['area_id']);
            }
            if ($area === null && $areaCode !== '') {
                $area = $areasByCode->get($areaCode);
            }
            $areaId = $area ? (int) $area->id : (int) ($item['area_id'] ?? 0);

            if (! isset($perAreaCounts[$areaId])) {
                $perAreaCounts[$areaId] = 0;
                $perAreaScores[$areaId] = 0;
                $perAreaConfidences[$areaId] = [];
            }
            $perAreaCounts[$areaId]++;

            $machine = is_array($item['machine'] ?? null) ? $item['machine'] : [];
            $machineState = strtolower(trim((string) ($machine['state'] ?? ($item['state'] ?? ''))));
            $machineSelected = $machine['selected_option'] ?? ($item['selected_option'] ?? ($item['detected_answer'] ?? null));
            $machineSelected = is_string($machineSelected) || is_numeric($machineSelected) ? trim((string) $machineSelected) : null;
            $machineSelected = $machineSelected === '' ? null : $machineSelected;

            $machineHash = $item['evidence_hash'] ?? ($machine['evidence_hash'] ?? null);
            $machineHash = is_string($machineHash) ? strtolower(trim($machineHash)) : null;

            $review = $reviews->get($itemKey);
            $isStale = false;
            $reviewRevisionNo = null;
            $reviewDecision = null;
            $reviewDecisionEnum = null;
            $reviewOption = null;

            if ($review !== null) {
                $reviewRevisionNo = (int) $review->revision;
                $reviewDecisionEnum = $review->decision instanceof OmrReviewDecision
                    ? $review->decision
                    : OmrReviewDecision::tryFrom((string) $review->decision);
                $reviewDecision = $reviewDecisionEnum?->value ?? (string) $review->decision;
                $reviewOption = is_string($review->selected_option)
                    ? trim($review->selected_option)
                    : null;
                $reviewOption = $reviewOption === '' ? null : $reviewOption;
                $reviewHash = strtolower(trim((string) $review->reviewed_machine_evidence_hash));
                $isStale = $machineHash === null || ! hash_equals($machineHash, $reviewHash);
            }

            $effective = OmrEffectiveAnswerDecision::resolve(
                $machineState,
                $machineSelected,
                $reviewDecisionEnum,
                $reviewOption,
                $isStale,
            );
            $effectiveState = $effective['state'];
            $effectiveOption = $effective['option'];
            $effectiveSource = $effective['source'];

            if ($effective['stale']) {
                $staleKeys[] = $itemKey;
            }
            if ($effective['reviewed']) {
                $humanRevisionsCount++;
            }
            if ($effective['machine']) {
                $machineItemsCount++;
            }

            if ($effectiveState === 'unresolved') {
                $unresolvedKeys[] = $itemKey;
            }

            $correctOption = $correctOptionsByPosition[$position] ?? ($item['correct_option_code'] ?? null);
            $correctOption = is_string($correctOption) ? trim($correctOption) : null;
            $isCorrect = $effectiveState === 'selected'
                && $effectiveOption !== null
                && $correctOption !== null
                && $effectiveOption === $correctOption;

            if ($isCorrect) {
                $perAreaScores[$areaId]++;
            }

            $confidence = is_numeric($machine['confidence'] ?? ($item['confidence'] ?? null))
                ? (float) ($machine['confidence'] ?? $item['confidence'])
                : null;
            if ($confidence !== null) {
                $perAreaConfidences[$areaId][] = $confidence;
            }

            $projectedItems[] = [
                'item_key' => $itemKey,
                'position' => $position,
                'area_code' => $areaCode,
                'area_id' => $areaId,
                'question_no' => $questionNo,
                'page_no' => $geometryItem?->pageNo,
                'native_label' => $geometryItem?->nativeLabel,
                'physical_item_uid' => $kitBinding['physical_item_uid'] ?? null,
                'test_instrument_subtest_id' => $kitBinding['subtest_id'] ?? null,
                'test_instrument_item_id' => $kitBinding['item_id'] ?? null,
                'effective_state' => $effectiveState,
                'effective_option' => $effectiveOption,
                'effective_source' => $effectiveSource,
                'is_correct' => $isCorrect,
                'correct_option' => $correctOption,
                'machine_state' => $machineState,
                'machine_selected' => $machineSelected,
                'is_stale' => $isStale,
                'review_revision' => $reviewRevisionNo,
                'review_decision' => $reviewDecision,
                'evidence_hash' => $machineHash,
            ];
        }

        $perAreaSummary = [];
        foreach ($perAreaCounts as $areaId => $totalItems) {
            $confList = $perAreaConfidences[$areaId] ?? [];
            $avgConfidence = $confList !== [] ? round(array_sum($confList) / count($confList), 3) : null;
            $area = $areas->get($areaId);
            $areaCode = $area ? $area->code : (string) ($projectedItems[0]['area_code'] ?? '');

            $perAreaSummary[$areaId] = [
                'score' => (int) ($perAreaScores[$areaId] ?? 0),
                'confidence' => $avgConfidence,
                'area_code' => $areaCode,
                'max_items' => $area ? (int) $area->max_items : $totalItems,
            ];
        }

        $hasUnresolved = $unresolvedKeys !== [];
        $hasStale = $staleKeys !== [];
        $isComplete = ! $hasUnresolved && ! $hasStale;

        $provenance = [
            'capture_sheet_id' => (int) $sheet->getKey(),
            'sheet_version' => (int) ($sheet->version ?? 0),
            'booklet_version_id' => $resolution->snapshot?->booklet_version_id,
            'answer_key_hash' => $resolution->materialSet?->answer_key_hash,
            'material_snapshot_id' => $resolution->snapshot?->id,
            'material_snapshot_revision' => $resolution->snapshot?->revision,
            'seat_allocation_id' => $resolution->seatAllocation?->id,
            'total_items' => count($items),
            'human_revisions_count' => $humanRevisionsCount,
            'machine_items_count' => $machineItemsCount,
            'is_complete' => $isComplete,
            'has_unresolved' => $hasUnresolved,
            'admission_attempt_id' => $resolution->attempt?->id,
            'booklet_set_id' => $resolution->materialSet?->booklet_set_id,
            'has_stale' => $hasStale,
        ];

        return [
            'per_area_scores' => $perAreaSummary,
            'is_complete' => $isComplete,
            'has_unresolved' => $hasUnresolved,
            'has_stale' => $hasStale,
            'unresolved_item_keys' => array_values(array_unique($unresolvedKeys)),
            'stale_item_keys' => array_values(array_unique($staleKeys)),
            'items' => $projectedItems,
            'provenance' => $provenance,
        ];
    }

    /**
     * Atomically reconciles item-level issues for a newly saved item review revision
     * and regenerates the review_draft_json and reviewed_json on the sheet.
     */
    public function reconcileAndRegenerate(
        OmrCaptureSheet $sheet,
        string $itemKey,
        OmrReviewDecision $decision,
        User $actor,
    ): void {
        $projection = $this->project($sheet);

        $matchedItem = null;
        foreach ($projection['items'] as $item) {
            if ($item['item_key'] === $itemKey) {
                $matchedItem = $item;
                break;
            }
        }

        if ($matchedItem !== null) {
            $areaCode = $matchedItem['area_code'];
            $questionNo = $matchedItem['question_no'];

            $itemIssues = $sheet->issues()
                ->where('question_no', $questionNo)
                ->where(static function ($q) use ($areaCode): void {
                    if ($areaCode !== '') {
                        $q->where('area_code', $areaCode)->orWhereNull('area_code');
                    }
                })
                ->get();

            if ($decision === OmrReviewDecision::ConfirmMachine
                || $decision === OmrReviewDecision::SetOption
                || $decision === OmrReviewDecision::Blank) {
                foreach ($itemIssues as $issue) {
                    if ($issue->state === 'open') {
                        $issue->update([
                            'state' => 'resolved',
                            'resolution_code' => 'manual_review',
                            'resolution_reason' => 'Resolved by item review decision: '.$decision->value,
                            'resolved_by_user_id' => $actor->id,
                            'resolved_at' => now(),
                            'version' => (int) $issue->version + 1,
                        ]);
                    }
                }
            } elseif ($decision === OmrReviewDecision::Unresolved) {
                foreach ($itemIssues as $issue) {
                    if ($issue->state === 'resolved') {
                        $issue->update([
                            'state' => 'open',
                            'resolution_code' => null,
                            'resolution_reason' => null,
                            'resolved_by_user_id' => null,
                            'resolved_at' => null,
                            'version' => (int) $issue->version + 1,
                        ]);
                    }
                }
            }
        }

        $draftJson = [
            'is_complete' => $projection['is_complete'],
            'has_unresolved' => $projection['has_unresolved'],
            'has_stale' => $projection['has_stale'],
            'per_area_scores' => $projection['per_area_scores'],
            'provenance' => $projection['provenance'],
            'unresolved_item_keys' => $projection['unresolved_item_keys'],
            'stale_item_keys' => $projection['stale_item_keys'],
            'regenerated_at' => now()->toIso8601String(),
        ];

        $updates = ['review_draft_json' => $draftJson];

        if ($sheet->status === OmrCaptureSheet::STATUS_REVIEWED) {
            $existingReviewed = is_array($sheet->reviewed_json) ? $sheet->reviewed_json : [];
            $wasAbstained = (bool) ($existingReviewed['abstained'] ?? false);

            if (! $wasAbstained) {
                $updates['reviewed_json'] = [
                    'abstained' => false,
                    'reason' => null,
                    'per_area_scores' => $projection['per_area_scores'],
                    'provenance' => $projection['provenance'],
                    'is_complete' => $projection['is_complete'],
                    'unresolved_item_keys' => $projection['unresolved_item_keys'],
                    'stale_item_keys' => $projection['stale_item_keys'],
                    'regenerated_at' => now()->toIso8601String(),
                ];
            }
        }

        $sheet->update($updates);
    }

    /**
     * @param  array<string, mixed>|null  $kitContext
     * @return array<int, array{page_no:int,native_label:string,global_position:int,item_id:int,subtest_id:int,physical_item_uid:string}>
     */
    private function kitBindingsByPosition(?array $kitContext): array
    {
        if ($kitContext === null) {
            return [];
        }

        $itemMap = $kitContext['item_map'] ?? null;
        if (! is_array($itemMap)
            || ! is_array($itemMap['bindings'] ?? null)
            || ! is_array($itemMap['composition_rows'] ?? null)) {
            throw new RuntimeException('The exact Test Kit item map is unavailable.');
        }

        $rowsByPosition = [];
        foreach ($itemMap['composition_rows'] as $row) {
            if (! is_array($row) || ($row['disposition'] ?? null) !== 'physical') {
                continue;
            }
            $position = (int) ($row['global_position'] ?? 0);
            if ($position < 1 || isset($rowsByPosition[$position])) {
                throw new RuntimeException('The exact Test Kit item map contains duplicate physical addresses.');
            }
            $rowsByPosition[$position] = $row;
        }

        $bindingsByPosition = [];
        foreach ($itemMap['bindings'] as $binding) {
            if (! is_array($binding)) {
                throw new RuntimeException('The exact Test Kit item map contains an invalid binding.');
            }
            $position = (int) ($binding['global_position'] ?? 0);
            $row = $rowsByPosition[$position] ?? null;
            $uid = $row['physical_item_uid'] ?? null;
            if ($position < 1 || isset($bindingsByPosition[$position])
                || ! is_string($uid) || $uid === ''
                || (int) ($binding['sheet_item_id'] ?? 0) !== (int) ($row['sheet_item_id'] ?? 0)
                || (int) ($binding['item_id'] ?? 0) !== (int) ($row['formal_item_id'] ?? 0)
                || (int) ($binding['subtest_id'] ?? 0) !== (int) ($row['formal_subtest_id'] ?? 0)
                || (int) ($binding['page_no'] ?? 0) !== (int) ($row['page_no'] ?? 0)
                || (string) ($binding['native_label'] ?? '') !== (string) ($row['native_label'] ?? '')) {
                throw new RuntimeException('The exact Test Kit item map does not match its saved physical and formal identities.');
            }
            $bindingsByPosition[$position] = [
                'page_no' => (int) $binding['page_no'],
                'native_label' => (string) $binding['native_label'],
                'global_position' => $position,
                'item_id' => (int) $binding['item_id'],
                'subtest_id' => (int) $binding['subtest_id'],
                'physical_item_uid' => $uid,
            ];
        }

        if (count($bindingsByPosition) !== count($rowsByPosition)) {
            throw new RuntimeException('The exact Test Kit item map omits a physical response address.');
        }

        return $bindingsByPosition;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array<int, string>
     */
    private function resolveCorrectOptionsByPosition(OmrCaptureResolution $resolution): array
    {
        $answerKey = $resolution->materialSet?->answer_key_json;
        if (! is_array($answerKey) || $answerKey === []) {
            throw new RuntimeException('The submission-frozen Set answer key could not be resolved.');
        }

        return collect($answerKey)
            ->mapWithKeys(fn (mixed $option, mixed $position): array => [(int) $position => (string) $option])
            ->all();
    }
}
