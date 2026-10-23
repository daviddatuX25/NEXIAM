<?php

namespace App\Assessment\Support;

use App\Assessment\Omr\OmrTemplateGeometryProjector;
use App\Assessment\Omr\Queries\BookletContractQuery;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrBookletSet;
use App\Models\Assessment\OmrQuestionBookletVersion;
use App\Models\Assessment\TestInstrumentItem;
use App\Models\Assessment\TestInstrumentMetric;
use App\Models\Assessment\TestInstrumentVersion;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * Builds the unchanged scalar evaluator contract from explicit Kit-owned
 * physical-to-intrinsic-to-formal rows. It never reads a Sheet formal map or
 * infers item identity from area counts, names, local numbers, or ordinals.
 */
final class ExactTestKitItemMap
{
    public function __construct(
        private readonly PhysicalSheetInventory $physicalInventory = new PhysicalSheetInventory,
        private readonly BookletContractQuery $bookletContract = new BookletContractQuery(new OmrTemplateGeometryProjector),
    ) {}

    /**
     * Snapshot the approved profile without claiming a physical mapping.
     *
     * @return array{
     *     profile_version_id:int,
     *     content_sha256:string,
     *     profile_items:list<array{item_id:int,subtest_id:int,is_scored:bool,option_tokens:list<string>}>,
     *     metrics:list<array<string, mixed>>
     * }
     */
    public function buildProfileContract(TestInstrumentVersion $profile, array $componentVector): array
    {
        $profile->loadMissing(['instrument', 'subtests.items', 'metrics.inputs', 'metrics.subtest.items']);
        $profileId = $this->positiveId($profile->getKey(), 'Test Setup version');
        if ($profile->status !== TestInstrumentVersion::STATUS_APPROVED
            || (int) ($componentVector['test_instrument_version_id'] ?? 0) !== $profileId
            || (int) ($componentVector['test_kit_id'] ?? 0) < 1
            || (int) ($componentVector['institution_id'] ?? 0) < 1
            || ! is_bool($componentVector['is_synthetic'] ?? null)) {
            throw new RuntimeException('The approved Test Setup and exact Kit owner vector are required for a profile-only contract.');
        }

        $owner = $profile->instrument;
        if ($owner === null
            || $owner->getAttribute('institution_id') === null
            || (int) $owner->getAttribute('institution_id') !== (int) $componentVector['institution_id']
            || $owner->getAttribute('is_synthetic') === null
            || (bool) $owner->getAttribute('is_synthetic') !== $componentVector['is_synthetic']
            || (int) $profile->test_instrument_id !== (int) $owner->getKey()) {
            throw new RuntimeException('The approved Test Setup owner does not match the exact Kit institution and provenance.');
        }

        $profileItems = [];
        foreach ($profile->subtests->sortBy([['display_order', 'asc'], ['id', 'asc']])->values() as $subtest) {
            $subtestId = $this->positiveId($subtest->getKey(), 'Formal subtest');
            if ((int) $subtest->test_instrument_version_id !== $profileId) {
                throw new RuntimeException('Test Setup contains a cross-version subtest.');
            }
            foreach ($subtest->items->sortBy([['display_order', 'asc'], ['id', 'asc']])->values() as $item) {
                $itemId = $this->positiveId($item->getKey(), 'Formal Test Setup item');
                if ((int) $item->test_instrument_version_id !== $profileId
                    || (int) $item->test_instrument_subtest_id !== $subtestId) {
                    throw new RuntimeException('Test Setup contains a cross-version or mismatched formal item.');
                }
                $tokens = is_array($item->option_tokens) && $item->option_tokens !== []
                    ? $this->tokens($item->option_tokens, 'Formal item option tokens')
                    : [];
                $profileItems[] = [
                    'item_id' => $itemId,
                    'subtest_id' => $subtestId,
                    'is_scored' => (bool) $item->is_scored,
                    'option_tokens' => $tokens,
                ];
            }
        }

        $profileValidator = new TestInstrumentProfileValidator;
        $normalizedProfile = $profileValidator->normalizeVersion($profile);
        $contentHash = $this->sha256($profile->content_sha256, 'Approved profile content hash');
        if (! hash_equals($profileValidator->digest($normalizedProfile), $contentHash)) {
            throw new RuntimeException('The approved profile content hash does not match its exact profile and scoring rules.');
        }

        return [
            'profile_version_id' => $profileId,
            'content_sha256' => $contentHash,
            'profile_items' => $profileItems,
            'metrics' => $this->metrics($profile, $profileId),
            'normalized_metrics' => $normalizedProfile['metrics'],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $compositionRows
     * @param  array<string, mixed>  $componentVector
     * @return array{
     *     profile_version_id:int,
     *     content_sha256:string,
     *     items:list<array{item_id:int,subtest_id:int,is_scored:bool,option_tokens:list<string>}>,
     *     profile_items:list<array{item_id:int,subtest_id:int,is_scored:bool,option_tokens:list<string>}>,
     *     bindings:list<array{page_no:int,native_label:string,global_position:int,sheet_item_id:int,item_id:int,subtest_id:int,option_tokens:list<string>,correct_token:?string}>,
     *     composition_rows:list<array<string, mixed>>,
     *     composition_sha256:string,
     *     geometry:array{projection_rows:list<array<string,mixed>>,native_fields:list<array<string,mixed>>},
     *     metrics:list<array{metric_id:int,code:string,rule_kind:string,subtest_id:?int,unit:?string,min_raw_value:string,max_raw_value:string,precision_scale:int,is_required:bool,input_metric_ids:list<int>,procedure_reference:?string}>
     * }
     */
    public function build(
        OmrAnswerSheetTemplateVersion $template,
        OmrQuestionBookletVersion $booklet,
        OmrBookletSet $set,
        TestInstrumentVersion $profile,
        array $compositionRows,
        array $componentVector,
    ): array {
        $template->loadMissing(['template', 'pages', 'areas.questions.options']);
        $booklet->loadMissing(['booklet', 'items.section', 'sets.items.bookletItem']);
        $set->loadMissing('items.bookletItem');
        $profile->loadMissing(['instrument', 'subtests.items', 'metrics.inputs']);

        $templateId = $this->positiveId($template->getKey(), 'Answer Sheet version');
        $bookletId = $this->positiveId($booklet->getKey(), 'Booklet version');
        $setId = $this->positiveId($set->getKey(), 'Booklet Set A');
        $profileId = $this->positiveId($profile->getKey(), 'Test Setup version');
        if ($componentVector === []) {
            throw new RuntimeException('A Test Kit item map requires the saved exact component vector.');
        }
        if ((int) ($componentVector['template_version_id'] ?? 0) !== $templateId
            || (int) ($componentVector['booklet_version_id'] ?? 0) !== $bookletId
            || (int) ($componentVector['booklet_set_id'] ?? 0) !== $setId
            || (int) ($componentVector['test_instrument_version_id'] ?? 0) !== $profileId) {
            throw new RuntimeException('The saved Kit component vector does not match its exact selected versions.');
        }
        if (! in_array($template->status, [OmrAnswerSheetTemplateVersion::STATUS_COMPILED, OmrAnswerSheetTemplateVersion::STATUS_ACTIVE], true)
            || $booklet->status !== OmrQuestionBookletVersion::STATUS_FINALIZED
            || ! $booklet->isFinalized()
            || $set->booklet_version_id === null
            || (int) $set->booklet_version_id !== $bookletId
            || $set->set_code !== 'A'
            || $set->status !== OmrBookletSet::STATUS_FINALIZED
            || $set->finalized_at === null
            || $profile->status !== TestInstrumentVersion::STATUS_APPROVED) {
            throw new RuntimeException('The exact selected Answer Sheet, finalized Booklet Set A, and approved Test Setup are not usable.');
        }
        $owners = [$template->template, $booklet->booklet, $profile->instrument];
        $institutionId = $owners[0]?->getAttribute('institution_id');
        $synthetic = $owners[0]?->getAttribute('is_synthetic');
        if ($institutionId === null || $synthetic === null) {
            throw new RuntimeException('The selected Kit components have incomplete owner provenance.');
        }
        foreach ($owners as $owner) {
            if ($owner === null
                || (int) $owner->getAttribute('institution_id') !== (int) $institutionId
                || $owner->getAttribute('is_synthetic') === null
                || (bool) $owner->getAttribute('is_synthetic') !== (bool) $synthetic) {
                throw new RuntimeException('The selected Kit components do not share exact institution and provenance ownership.');
            }
        }
        if ((int) ($componentVector['test_kit_id'] ?? 0) < 1
            || (int) ($componentVector['institution_id'] ?? 0) !== (int) $institutionId
            || ! is_bool($componentVector['is_synthetic'] ?? null)
            || $componentVector['is_synthetic'] !== (bool) $synthetic) {
            throw new RuntimeException('The saved Kit component vector does not match actual Test Kit ownership and provenance.');
        }

        $this->assertTemplateProjection($template, $templateId);
        $physical = $this->physicalInventory->forVersion($template);
        if ($compositionRows === []) {
            throw new RuntimeException('A Test Kit item map requires saved exact composition rows.');
        }
        $this->assertComponentVector($componentVector, $template, $booklet, $set, $profile, $physical);
        $physicalByUid = [];
        $physicalById = [];
        $physicalPositions = [];
        foreach ($physical['items'] as $location) {
            $uid = (string) ($location['physical_item_uid'] ?? '');
            $sheetItemId = $this->positiveId($location['sheet_item_id'] ?? null, 'Physical Answer Sheet item');
            $globalPosition = $this->positiveId($location['global_position'] ?? null, 'Physical transport position');
            $pageNo = $this->positiveId($location['page_no'] ?? null, 'Physical page number');
            $nativeLabel = (string) ($location['native_label'] ?? '');
            if ($uid === '' || $nativeLabel === '' || isset($physicalByUid[$uid])
                || isset($physicalById[$sheetItemId]) || isset($physicalPositions[$globalPosition])
                || $pageNo > (int) $template->page_count) {
                throw new RuntimeException('The compiled physical inventory contains an invalid or duplicate identity.');
            }
            $optionTokens = $this->tokens($location['option_tokens'] ?? null, 'Physical response option tokens');
            $this->assertNativeField($template, $pageNo, $nativeLabel, $optionTokens);
            $location['option_tokens'] = $optionTokens;
            $physicalByUid[$uid] = $location;
            $physicalById[$sheetItemId] = $location;
            $physicalPositions[$globalPosition] = true;
        }

        $profileItemsById = [];
        $profileItems = [];
        $subtestsById = [];
        foreach ($profile->subtests->sortBy([['display_order', 'asc'], ['id', 'asc']])->values() as $subtest) {
            $subtestId = $this->positiveId($subtest->getKey(), 'Formal subtest');
            if ((int) $subtest->test_instrument_version_id !== $profileId || isset($subtestsById[$subtestId])) {
                throw new RuntimeException('Test Setup contains an invalid subtest version identity.');
            }
            $subtestsById[$subtestId] = $subtest;
            foreach ($subtest->items->sortBy([['display_order', 'asc'], ['id', 'asc']])->values() as $item) {
                $itemId = $this->positiveId($item->getKey(), 'Formal Test Setup item');
                if ((int) $item->test_instrument_version_id !== $profileId
                    || (int) $item->test_instrument_subtest_id !== $subtestId
                    || isset($profileItemsById[$itemId])) {
                    throw new RuntimeException('Test Setup contains a duplicate or cross-version formal item.');
                }
                $tokens = is_array($item->option_tokens) && $item->option_tokens !== []
                    ? $this->tokens($item->option_tokens, 'Formal item option tokens')
                    : [];
                $profileItemsById[$itemId] = [
                    'model' => $item,
                    'subtest' => $subtest,
                    'tokens' => $tokens,
                ];
                $profileItems[] = [
                    'item_id' => $itemId,
                    'subtest_id' => $subtestId,
                    'is_scored' => (bool) $item->is_scored,
                    'option_tokens' => $tokens,
                ];
            }
        }

        $bookletContract = $this->bookletContract->forBooklet($booklet);
        $this->bookletContract->assertSetIntegrity($booklet, $set);
        $bookletItems = $booklet->items;
        $bookletItemsById = [];
        foreach ($bookletItems as $bookletItem) {
            $itemId = $this->positiveId($bookletItem->getKey(), 'Intrinsic Booklet item');
            $uid = (string) ($bookletItem->booklet_item_uid ?? '');
            $sectionUid = (string) ($bookletItem->booklet_section_uid ?? '');
            $tokens = is_array($bookletItem->option_tokens) && $bookletItem->option_tokens !== []
                ? $this->tokens($bookletItem->option_tokens, 'Intrinsic Booklet option tokens')
                : [];
            if ((int) ($bookletItem->booklet_version_id ?? 0) !== $bookletId
                || $uid === '' || $sectionUid === '' || $tokens === []
                || isset($bookletItemsById[$itemId])) {
                throw new RuntimeException('The Booklet inventory contains a missing, duplicate, or cross-version intrinsic item identity.');
            }
            $bookletItemsById[$itemId] = [
                'model' => $bookletItem,
                'uid' => $uid,
                'section_uid' => $sectionUid,
                'tokens' => $tokens,
            ];
        }
        $setRowsById = [];
        $setRowsByItemId = [];
        foreach ($set->items->sortBy([['global_position', 'asc'], ['id', 'asc']])->values() as $setItem) {
            $setItemId = $this->positiveId($setItem->getKey(), 'Set A occurrence');
            $bookletItemId = $this->positiveId($setItem->booklet_item_id ?? null, 'Set A intrinsic Booklet item');
            if ((int) $setItem->booklet_set_id !== $setId
                || ! isset($bookletItemsById[$bookletItemId])
                || isset($setRowsById[$setItemId])
                || isset($setRowsByItemId[$bookletItemId])) {
                throw new RuntimeException('Set A contains an invalid or duplicate intrinsic item occurrence.');
            }
            $setRowsById[$setItemId] = $setItem;
            $setRowsByItemId[$bookletItemId] = $setItemId;
        }
        if (count($setRowsByItemId) !== count($bookletItemsById)) {
            throw new RuntimeException('Set A must include each exact intrinsic Booklet item once.');
        }
        $identityLabels = array_fill_keys(array_values(array_filter(
            (array) ($template->review_manifest_json['identity']['labels'] ?? []),
            'is_string',
        )), true);
        $mappedPhysical = [];
        $mappedPhysicalFormal = [];
        $mappedBooklet = [];
        $mappedFormal = [];
        $physicalMapRows = [];
        $nonphysicalRows = [];
        foreach ($compositionRows as $row) {
            if ($row instanceof Model) {
                $row = $row->getAttributes();
            }
            if (! is_array($row) || ! in_array($row['disposition'] ?? null, ['physical', 'nonphysical_practice'], true)) {
                throw new RuntimeException('A saved Kit composition row has an unknown disposition.');
            }
            foreach ([
                'template_version_id' => $templateId,
                'booklet_version_id' => $bookletId,
                'booklet_set_id' => $setId,
                'test_instrument_version_id' => $profileId,
            ] as $field => $expectedId) {
                if ((int) ($row[$field] ?? 0) !== $expectedId) {
                    throw new RuntimeException('A saved Kit composition row references a foreign or missing component version.');
                }
            }
            $formalItemId = $this->positiveId($row['test_instrument_item_id'] ?? $row['formal_item_id'] ?? null, 'Mapped formal item');
            $formalSubtestId = $this->positiveId($row['test_instrument_subtest_id'] ?? $row['formal_subtest_id'] ?? null, 'Mapped formal subtest');
            $formal = $profileItemsById[$formalItemId] ?? null;
            if ($formal === null || (int) $formal['model']->test_instrument_subtest_id !== $formalSubtestId
                || ! isset($subtestsById[$formalSubtestId]) || isset($mappedFormal[$formalItemId])) {
                throw new RuntimeException('A Kit map references an unknown, duplicated, or mismatched formal item.');
            }
            $mappedFormal[$formalItemId] = true;

            if ($row['disposition'] === 'nonphysical_practice') {
                if ((bool) $formal['model']->is_scored
                    || ($row['physical_item_uid'] ?? null) !== null
                    || ($row['sheet_item_id'] ?? null) !== null
                    || ($row['booklet_set_item_id'] ?? null) !== null
                    || ($row['booklet_item_id'] ?? null) !== null
                    || ($row['booklet_item_uid'] ?? null) !== null
                    || ($row['booklet_section_uid'] ?? null) !== null) {
                    throw new RuntimeException('Only explicitly unscored practice may use a nonphysical disposition.');
                }
                $nonphysicalRows[] = [
                    'sort_position' => (int) $formal['model']->display_order,
                    'physical_item_uid' => null,
                    'sheet_item_id' => null,
                    'page_no' => null,
                    'physical_section_uid' => null,
                    'native_label' => null,
                    'global_position' => null,
                    'booklet_set_item_id' => null,
                    'booklet_item_id' => null,
                    'booklet_item_uid' => null,
                    'booklet_section_uid' => null,
                    'booklet_global_position' => null,
                    'formal_item_id' => $formalItemId,
                    'formal_subtest_id' => $formalSubtestId,
                    'physical_option_tokens' => [],
                    'booklet_option_tokens' => [],
                    'formal_option_tokens' => $formal['tokens'],
                    'is_scored' => false,
                    'key_required' => false,
                    'disposition' => 'nonphysical_practice',
                ];

                continue;
            }

            $isScored = (bool) $formal['model']->is_scored;
            $physicalUid = (string) ($row['physical_item_uid'] ?? '');
            $sheetItemId = $this->positiveId($row['sheet_item_id'] ?? null, 'Mapped physical item');
            $physicalLocation = $physicalByUid[$physicalUid] ?? null;
            if ($physicalUid === '' || $physicalLocation === null
                || (int) $physicalLocation['sheet_item_id'] !== $sheetItemId
                || isset($mappedPhysical[$physicalUid])) {
                throw new RuntimeException('A physical Kit row does not identify one exact Sheet location and Set A intrinsic item.');
            }
            $mappedPhysical[$physicalUid] = true;
            $mappedPhysicalFormal[$formalItemId] = true;

            $bookletItemId = isset($row['booklet_item_id']) && $row['booklet_item_id'] !== null
                ? $this->positiveId($row['booklet_item_id'], 'Mapped Booklet item')
                : null;
            $bookletSetItemId = isset($row['booklet_set_item_id']) && $row['booklet_set_item_id'] !== null
                ? $this->positiveId($row['booklet_set_item_id'], 'Mapped Set A occurrence')
                : null;
            $bookletItem = $bookletItemId !== null ? ($bookletItemsById[$bookletItemId] ?? null) : null;
            $setItem = $bookletSetItemId !== null ? ($setRowsById[$bookletSetItemId] ?? null) : null;

            if ($isScored) {
                if ($bookletItem === null
                    || (string) ($row['booklet_item_uid'] ?? '') !== $bookletItem['uid']
                    || (string) ($row['booklet_section_uid'] ?? '') !== $bookletItem['section_uid']
                    || $setItem === null
                    || (int) $setItem->booklet_item_id !== $bookletItemId
                    || isset($mappedBooklet[$bookletItemId])) {
                    throw new RuntimeException('A physical Kit row does not identify one exact Sheet location and Set A intrinsic item.');
                }
                $mappedBooklet[$bookletItemId] = true;
            } elseif ($bookletItem === null
                || $bookletSetItemId === null
                || (int) $setItem?->booklet_item_id !== $bookletItemId
                || (string) ($row['booklet_item_uid'] ?? '') !== $bookletItem['uid']
                || (string) ($row['booklet_section_uid'] ?? '') !== $bookletItem['section_uid']
                || isset($mappedBooklet[$bookletItemId])) {
                throw new RuntimeException('An unscored physical practice row must retain its exact intrinsic Booklet and Set A occurrence identity.');
            } else {
                $mappedBooklet[$bookletItemId] = true;
            }

            $pageNo = $this->positiveId($physicalLocation['page_no'], 'Physical page number');
            $nativeLabel = (string) $physicalLocation['native_label'];
            if (isset($identityLabels[$nativeLabel])
                || str_starts_with($nativeLabel, 'APPLICANT_NUMBER_')
                || str_starts_with($nativeLabel, 'BIRTHDATE_')
                || str_starts_with($nativeLabel, 'EXAM_DATE_')) {
                throw new RuntimeException('Identity and write-in locations cannot be composed as response items.');
            }
            $physicalTokens = $physicalLocation['option_tokens'];
            $formalTokens = $formal['tokens'];
            $bookletTokens = $bookletItem !== null ? $bookletItem['tokens'] : $formalTokens;
            if ($physicalTokens === [] || ($bookletItem !== null && $physicalTokens !== $bookletTokens) || $physicalTokens !== $formalTokens) {
                throw new RuntimeException('A physical, intrinsic, and formal item must have identical ordered literal option tokens.');
            }
            $keyRequired = $bookletItem !== null ? (bool) $bookletItem['model']->key_required : false;
            if ($isScored && ! $keyRequired) {
                throw new RuntimeException('Booklet key-required intent conflicts with the formal scored/practice classification.');
            }
            $correctToken = $setItem?->correct_option_code;
            if ($keyRequired) {
                if (! is_string($correctToken) || ! in_array($correctToken, $physicalTokens, true)) {
                    throw new RuntimeException('Set A requires one exact literal key token for each scored mapped item.');
                }
            } else {
                if ($isScored || ($correctToken !== null && $correctToken !== '')) {
                    throw new RuntimeException('An unscored physical practice item must not carry an answer key.');
                }
                $correctToken = null;
            }

            $physicalMapRows[] = [
                'sort_position' => $this->positiveId($physicalLocation['global_position'], 'Physical transport position'),
                'physical_item_uid' => $physicalUid,
                'sheet_item_id' => $sheetItemId,
                'physical_section_uid' => (string) $physicalLocation['printed_section_uid'],
                'page_no' => $pageNo,
                'native_label' => $nativeLabel,
                'global_position' => (int) $physicalLocation['global_position'],
                'booklet_set_item_id' => $bookletSetItemId,
                'booklet_item_id' => $bookletItemId,
                'booklet_item_uid' => $bookletItem ? $bookletItem['uid'] : null,
                'booklet_section_uid' => $bookletItem ? $bookletItem['section_uid'] : null,
                'booklet_global_position' => $setItem ? (int) $setItem->global_position : null,
                'formal_item_id' => $formalItemId,
                'formal_subtest_id' => $formalSubtestId,
                'physical_option_tokens' => $physicalTokens,
                'booklet_option_tokens' => $bookletTokens,
                'formal_option_tokens' => $formalTokens,
                'is_scored' => $isScored,
                'key_required' => $keyRequired,
                'correct_token' => $correctToken,
                'disposition' => 'physical',
            ];
        }

        if (count($mappedPhysical) !== count($physicalByUid)
            || count($mappedBooklet) !== count($setRowsById)
            || count($mappedFormal) !== count($profileItemsById)) {
            throw new RuntimeException('The Kit composition must explicitly account for every physical location, Set A occurrence, and formal item disposition.');
        }

        usort($physicalMapRows, static fn (array $left, array $right): int => $left['sort_position'] <=> $right['sort_position']);
        $lastBookletPosition = 0;
        $formalIdsBySubtest = [];
        foreach ($physicalMapRows as $mapRow) {
            if ($mapRow['booklet_global_position'] !== null) {
                if ($mapRow['booklet_global_position'] <= $lastBookletPosition) {
                    throw new RuntimeException('The explicit physical mapping reverses the exact finalized Set A order.');
                }
                $lastBookletPosition = (int) $mapRow['booklet_global_position'];
            }
            $formalIdsBySubtest[$mapRow['formal_subtest_id']][] = $mapRow['formal_item_id'];
        }
        foreach ($formalIdsBySubtest as $subtestId => $itemIds) {
            $subtest = $subtestsById[$subtestId];
            $policy = (string) $subtest->item_order_policy;
            if (! in_array($policy, ['fixed', 'within_subtest_permutable'], true)) {
                throw new RuntimeException('A formal subtest has an unsupported item-order policy.');
            }
            if ($policy === 'fixed') {
                $expected = collect($subtest->items)
                    ->filter(fn ($item): bool => isset($mappedPhysicalFormal[(int) $item->id]))
                    ->sortBy([['display_order', 'asc'], ['id', 'asc']])
                    ->map(fn ($item): int => (int) $item->id)
                    ->values()->all();
                if ($expected !== $itemIds) {
                    throw new RuntimeException('The explicit Kit mapping changes a fixed formal subtest order.');
                }
            }
        }

        usort($nonphysicalRows, static fn (array $left, array $right): int => $left['sort_position'] <=> $right['sort_position']);
        $compositionRows = array_map(
            static fn (array $row): array => array_diff_key($row, ['sort_position' => true, 'correct_token' => true]),
            [...$physicalMapRows, ...$nonphysicalRows],
        );
        $bindings = [];
        $items = [];
        foreach ($physicalMapRows as $row) {
            $bindings[] = [
                'page_no' => $row['page_no'],
                'native_label' => $row['native_label'],
                'global_position' => $row['global_position'],
                'sheet_item_id' => $row['sheet_item_id'],
                'item_id' => $row['formal_item_id'],
                'subtest_id' => $row['formal_subtest_id'],
                'option_tokens' => $row['physical_option_tokens'],
                'correct_token' => $row['correct_token'],
            ];
            $items[] = [
                'item_id' => $row['formal_item_id'],
                'subtest_id' => $row['formal_subtest_id'],
                'is_scored' => $row['is_scored'],
                'option_tokens' => $row['physical_option_tokens'],
            ];
        }

        return [
            'profile_version_id' => $profileId,
            'content_sha256' => $this->sha256($profile->content_sha256, 'Approved profile content hash'),
            'items' => $items,
            'profile_items' => $profileItems,
            'bindings' => $bindings,
            'composition_rows' => $compositionRows,
            'composition_sha256' => $this->compositionDigest($componentVector, $compositionRows),
            'component_vector' => $componentVector,
            'geometry' => $this->liveGeometry($template),
            'metrics' => $this->metrics($profile, $profileId),
        ];
    }

    /**
     * @param  array<string, mixed>  $componentVector
     * @param  list<array<string, mixed>>  $rows
     */
    private function compositionDigest(array $componentVector, array $rows): string
    {
        $digestRows = array_map(static function (array $row): array {
            unset(
                $row['template_version_id'],
                $row['booklet_version_id'],
                $row['booklet_set_id'],
                $row['test_instrument_version_id'],
            );

            return $row;
        }, $rows);
        $identity = [
            'schema' => 'nexiam.test-kit.composition.v1',
            'component_vector' => $this->sortAssociativeKeys($componentVector),
            'rows' => $this->sortAssociativeKeys($digestRows),
        ];

        return hash('sha256', json_encode(
            $identity,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        ));
    }

    /**
     * @param  array<array-key, mixed>  $value
     * @return array<array-key, mixed>
     */
    private function sortAssociativeKeys(array $value): array
    {
        if (! array_is_list($value)) {
            ksort($value, SORT_STRING);
        }

        foreach ($value as $key => $entry) {
            if (is_array($entry)) {
                $value[$key] = $this->sortAssociativeKeys($entry);
            }
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $vector
     * @param  array<string, mixed>  $physicalInventory
     */
    private function assertComponentVector(
        array $vector,
        OmrAnswerSheetTemplateVersion $template,
        OmrQuestionBookletVersion $booklet,
        OmrBookletSet $set,
        TestInstrumentVersion $profile,
        array $physicalInventory,
    ): void {
        $sheet = $vector['sheet'] ?? null;
        $bookletVector = $vector['booklet'] ?? null;
        $profileVector = $vector['profile'] ?? null;
        if (! is_array($sheet) || ! is_array($bookletVector) || ! is_array($profileVector)
            || (int) ($sheet['template_id'] ?? 0) !== (int) $template->template_id
            || (int) ($sheet['version'] ?? 0) !== (int) $template->version
            || ($sheet['native_bundle_hash'] ?? null) !== $template->native_bundle_hash
            || ($sheet['template_json_hash'] ?? null) !== $template->template_json_hash
            || ($sheet['config_hash'] ?? null) !== $template->config_hash
            || ($sheet['review_manifest_hash'] ?? null) !== $template->review_manifest_hash
            || ($sheet['layout_blueprint_hash'] ?? null) !== $template->layout_blueprint_hash
            || ($sheet['physical_inventory_sha256'] ?? null) !== ($physicalInventory['inventory_digest'] ?? null)
            || (int) ($bookletVector['booklet_id'] ?? 0) !== (int) $booklet->booklet_id
            || (int) ($bookletVector['version'] ?? 0) !== (int) $booklet->version
            || ($bookletVector['content_hash'] ?? null) !== $booklet->content_hash
            || ($bookletVector['answer_key_hash'] ?? null) !== $booklet->answer_key_hash
            || ($bookletVector['authoring_mode'] ?? null) !== (string) $booklet->authoring_mode
            || ($bookletVector['set_code'] ?? null) !== (string) $set->set_code
            || ($bookletVector['set_order_hash'] ?? null) !== $set->order_hash
            || ($bookletVector['set_answer_key_hash'] ?? null) !== $set->answer_key_hash
            || (int) ($profileVector['instrument_id'] ?? 0) !== (int) $profile->test_instrument_id
            || (int) ($profileVector['version_number'] ?? 0) !== (int) $profile->version_number
            || ($profileVector['content_sha256'] ?? null) !== $profile->content_sha256) {
            throw new RuntimeException('The Kit component vector does not match the exact immutable component material.');
        }
    }

    private function liveGeometry(OmrAnswerSheetTemplateVersion $template): array
    {
        $geometry = [];
        foreach ($template->areas->sortBy([['display_order', 'asc'], ['id', 'asc']])->values() as $area) {
            $questions = [];
            foreach ($area->questions->sortBy([['question_no', 'asc'], ['id', 'asc']])->values() as $question) {
                $options = [];
                foreach ($question->options->sortBy([['display_order', 'asc'], ['id', 'asc']])->values() as $option) {
                    $options[] = [
                        'option_id' => (int) $option->id,
                        'token' => (string) $option->code,
                        'display_order' => (int) $option->display_order,
                        'region' => [
                            'x' => (float) $option->region_x,
                            'y' => (float) $option->region_y,
                            'w' => (float) $option->region_w,
                            'h' => (float) $option->region_h,
                        ],
                    ];
                }
                $questions[] = [
                    'question_id' => (int) $question->id,
                    'page_no' => (int) $question->page_no,
                    'question_no' => (int) $question->question_no,
                    'region' => [
                        'x' => (float) $question->region_x,
                        'y' => (float) $question->region_y,
                        'w' => (float) $question->region_w,
                        'h' => (float) $question->region_h,
                    ],
                    'options' => $options,
                ];
            }
            $geometry[] = [
                'area_id' => (int) $area->id,
                'display_order' => (int) $area->display_order,
                'max_items' => (int) $area->max_items,
                'region' => [
                    'x' => (float) $area->region_x,
                    'y' => (float) $area->region_y,
                    'w' => (float) $area->region_w,
                    'h' => (float) $area->region_h,
                ],
                'questions' => $questions,
            ];
        }

        $manifestFields = $template->review_manifest_json['fields'] ?? null;
        if (! is_array($manifestFields) || ! array_is_list($manifestFields)) {
            throw new RuntimeException('The compiled Answer Sheet has no exact native field geometry.');
        }
        $nativeFields = [];
        foreach ($manifestFields as $field) {
            if (! is_array($field)
                || ! is_string($field['label'] ?? null)
                || ! is_string($field['option'] ?? null)
                || ! is_array($field['pixel'] ?? null)) {
                throw new RuntimeException('A native Answer Sheet field is missing exact label, token, or pixel geometry.');
            }
            $pixel = $field['pixel'];
            foreach (['x', 'y', 'w', 'h'] as $coordinate) {
                if (! is_numeric($pixel[$coordinate] ?? null)
                    || (in_array($coordinate, ['w', 'h'], true) && (float) $pixel[$coordinate] <= 0)) {
                    throw new RuntimeException('A native Answer Sheet field contains invalid pixel geometry.');
                }
            }
            $nativeFields[] = [
                'page_no' => $this->positiveId($field['page'] ?? null, 'Native field page number'),
                'label' => $field['label'],
                'token' => $field['option'],
                'pixel' => $pixel,
                'region' => is_array($field['region'] ?? null) ? $field['region'] : null,
            ];
        }

        return [
            'projection_rows' => $geometry,
            'native_fields' => $nativeFields,
        ];
    }

    private function assertTemplateProjection(OmrAnswerSheetTemplateVersion $template, int $templateId): void
    {
        $pageCount = $this->positiveId($template->page_count, 'Answer Sheet page count');
        $reviewHash = $this->sha256($template->review_manifest_hash, 'Answer Sheet review manifest hash');
        $manifest = $template->getAttribute('review_manifest_json');
        if (! is_array($manifest) || ! is_array($manifest['fields'] ?? null) || ! is_array($manifest['pages'] ?? null)) {
            throw new RuntimeException('The compiled Answer Sheet has no complete native review manifest.');
        }

        $pages = $template->pages->sortBy([['page_no', 'asc'], ['id', 'asc']])->values();
        if ($pages->count() !== $pageCount) {
            throw new RuntimeException('The Answer Sheet page rows do not match its declared page count.');
        }
        $pageRows = [];
        foreach ($pages as $index => $page) {
            $pageNo = $this->positiveId($page->page_no, 'Answer Sheet page number');
            $pageManifest = $page->getAttribute('page_manifest_json');
            $pageWidth = $page->getAttribute('width');
            $pageHeight = $page->getAttribute('height');
            if ((int) $page->template_version_id !== $templateId
                || $pageNo !== $index + 1
                || $pageWidth === null || (int) $pageWidth < 1
                || $pageHeight === null || (int) $pageHeight < 1
                || ! is_array($pageManifest)
                || preg_match('/^[a-f0-9]{64}$/D', (string) $page->page_manifest_hash) !== 1
                || ($pageManifest['template_code'] ?? null) !== ($manifest['template_code'] ?? null)
                || (int) ($pageManifest['template_version'] ?? 0) !== (int) ($manifest['template_version'] ?? 0)
                || ($pageManifest['fields'] ?? null) !== $manifest['fields']
                || ($pageManifest['pages'] ?? null) !== $manifest['pages']) {
                throw new RuntimeException('The Answer Sheet contains a stale or mismatched native page manifest.');
            }
            $pageRows[$pageNo] = $page;
        }

        foreach ($manifest['pages'] as $page) {
            if (! is_array($page)) {
                throw new RuntimeException('The native review manifest contains an invalid page entry.');
            }
            $pageNo = $this->positiveId($page['page_no'] ?? null, 'Native manifest page number');
            $dimensions = $page['page_dimensions'] ?? null;
            $pageRow = $pageRows[$pageNo] ?? null;
            if (! is_array($dimensions) || $pageRow === null
                || (int) ($dimensions['width'] ?? 0) !== (int) $pageRow->width
                || (int) ($dimensions['height'] ?? 0) !== (int) $pageRow->height) {
                throw new RuntimeException('The native page dimensions do not match the compiled Answer Sheet pages.');
            }
        }
        if (count($pageRows) !== count($manifest['pages'])) {
            throw new RuntimeException('The Answer Sheet manifest has missing or duplicate page identities.');
        }

        $projectionHash = (string) ($pages->first()?->page_manifest_hash ?? '');
        if (preg_match('/^[a-f0-9]{64}$/D', $projectionHash) !== 1) {
            throw new RuntimeException('The Answer Sheet contains an invalid projection manifest hash.');
        }

        foreach ($template->areas as $area) {
            if ((int) $area->template_version_id !== $templateId
                || ! hash_equals($projectionHash, (string) $area->projection_manifest_hash)) {
                throw new RuntimeException('The Answer Sheet area projection hash does not match its native manifest.');
            }
            foreach ($area->questions as $question) {
                if (! hash_equals($projectionHash, (string) $question->projection_manifest_hash)) {
                    throw new RuntimeException('The Answer Sheet question projection hash does not match its native manifest.');
                }
                foreach ($question->options as $option) {
                    if (! hash_equals($projectionHash, (string) $option->projection_manifest_hash)) {
                        throw new RuntimeException('The Answer Sheet option projection hash does not match its native manifest.');
                    }
                }
            }
        }
    }

    /**
     * @param  list<string>  $expectedTokens
     */
    private function assertNativeField(
        OmrAnswerSheetTemplateVersion $template,
        int $pageNo,
        string $nativeLabel,
        array $expectedTokens,
    ): void {
        $fields = (array) ($template->review_manifest_json['fields'] ?? []);
        $actualTokens = [];
        foreach ($fields as $field) {
            if (! is_array($field)
                || (int) ($field['page'] ?? 0) !== $pageNo
                || (string) ($field['label'] ?? '') !== $nativeLabel) {
                continue;
            }
            if (! is_string($field['option'] ?? null)) {
                throw new RuntimeException('A native Answer Sheet item has an invalid option token.');
            }
            $actualTokens[] = $field['option'];
        }
        if ($actualTokens === [] || $actualTokens !== $expectedTokens) {
            throw new RuntimeException('The native page label or ordered option tokens do not match the profile mapping.');
        }
    }

    /**
     * @return list<array{metric_id:int,code:string,rule_kind:string,subtest_id:?int,unit:?string,min_raw_value:string,max_raw_value:string,precision_scale:int,is_required:bool,input_metric_ids:list<int>,procedure_reference:?string}>
     */
    private function metrics(TestInstrumentVersion $profile, int $profileId): array
    {
        $metrics = [];
        $metricIds = [];
        foreach ($profile->metrics->sortBy([['code', 'asc'], ['id', 'asc']])->values() as $metric) {
            if ((int) $metric->test_instrument_version_id !== $profileId) {
                throw new RuntimeException('The profile contains a raw metric owned by another version.');
            }
            $metricId = $this->positiveId($metric->getKey(), 'Profile metric');
            if (isset($metricIds[$metricId])) {
                throw new RuntimeException('The profile contains a duplicate metric identity.');
            }
            $metricIds[$metricId] = true;
            $subtestId = $metric->test_instrument_subtest_id === null
                ? null
                : $this->positiveId($metric->test_instrument_subtest_id, 'Metric subtest');
            $inputs = [];
            foreach ($metric->inputs->sortBy([['position', 'asc'], ['id', 'asc']])->values() as $input) {
                if ((int) $input->test_instrument_version_id !== $profileId
                    || (int) $input->metric_id !== $metricId) {
                    throw new RuntimeException('The profile contains a metric input owned by another metric or version.');
                }
                $inputId = $this->positiveId($input->input_metric_id, 'Metric input');
                if (! isset($metricIds[$inputId]) && ! $profile->metrics->contains('id', $inputId)) {
                    throw new RuntimeException('A raw metric references an input outside its profile version.');
                }
                $inputs[] = $inputId;
            }
            if (count($inputs) !== count(array_unique($inputs))) {
                throw new RuntimeException('A raw metric contains duplicate input identities.');
            }
            $unit = $metric->unit;
            if ((int) $metric->precision_scale > 6) {
                throw new RuntimeException('A raw metric requires an exact supported precision scale.');
            }
            $finalOnly = $metric->rule_kind === TestInstrumentMetric::RULE_EXTERNAL_FINAL;
            $metricContract = [
                'metric_id' => $metricId,
                'code' => (string) $metric->code,
                'rule_kind' => (string) $metric->rule_kind,
                'subtest_id' => $subtestId,
                'unit' => $unit === null ? null : (string) $unit,
                'min_raw_value' => $finalOnly ? null : $this->decimal($metric->min_raw_value, 'Metric minimum'),
                'max_raw_value' => $finalOnly ? null : $this->decimal($metric->max_raw_value, 'Metric maximum'),
                'precision_scale' => (int) $metric->precision_scale,
                'is_required' => (bool) $metric->is_required,
                'input_metric_ids' => $inputs,
                'procedure_reference' => $metric->procedure_reference === null ? null : (string) $metric->procedure_reference,
            ];
            if ($finalOnly) {
                $metricContract['construct_aptitude_area_id'] = $this->positiveId($metric->construct_aptitude_area_id, 'Final aptitude construct');
                $metricContract['required_output_scales'] = $metric->required_output_scales;
            }
            if ($metric->formula_expression !== null) {
                $metricContract['formula_expression'] = (string) $metric->formula_expression;
                $metricContract['formula_ast_json'] = $metric->formula_ast_json;
                $metricContract['formula_algorithm_identity'] = TestInstrumentMetric::FORMULA_ALGORITHM_IDENTITY;
            }
            if ($metric->item_codes_json !== null) {
                $itemCodes = is_array($metric->item_codes_json) ? $metric->item_codes_json : [];
                $itemIdsByCode = $metric->subtest?->items
                    ->filter(static fn (TestInstrumentItem $item): bool => in_array($item->item_code, $itemCodes, true))
                    ->mapWithKeys(static fn (TestInstrumentItem $item): array => [$item->item_code => (int) $item->getKey()])
                    ->all() ?? [];
                if (count($itemIdsByCode) !== count($itemCodes)) {
                    throw new RuntimeException('A metric item scope contains an item outside its approved subtest.');
                }
                $metricContract['item_codes'] = $itemCodes;
                $metricContract['item_ids'] = array_values($itemIdsByCode);
            }
            $metrics[] = $metricContract;
        }

        return $metrics;
    }

    /** @return list<string> */
    private function tokens(mixed $tokens, string $field): array
    {
        if (! is_array($tokens) || ! array_is_list($tokens) || $tokens === []) {
            throw new RuntimeException($field.' must be a non-empty ordered token list.');
        }
        $normalized = [];
        foreach ($tokens as $token) {
            if (! is_string($token) || $token === '' || trim($token) === '' || in_array($token, $normalized, true)) {
                throw new RuntimeException($field.' must contain exact, non-empty, unique string tokens.');
            }
            $normalized[] = $token;
        }

        return $normalized;
    }

    private function positiveId(mixed $value, string $field): int
    {
        if (! is_numeric($value) || (int) $value < 1 || (string) (int) $value !== (string) $value) {
            throw new RuntimeException($field.' must be a persisted positive integer.');
        }

        return (int) $value;
    }

    private function sha256(mixed $value, string $field): string
    {
        if (! is_string($value) || preg_match('/\A[a-f0-9]{64}\z/i', $value) !== 1) {
            throw new RuntimeException($field.' must be a SHA-256 digest.');
        }

        return strtolower($value);
    }

    private function decimal(mixed $value, string $field): string
    {
        if (! is_string($value) && ! is_int($value)) {
            throw new RuntimeException($field.' must be an exact decimal string.');
        }
        $decimal = (string) $value;
        if (preg_match('/\A-?\d+(?:\.\d+)?\z/', $decimal) !== 1) {
            throw new RuntimeException($field.' must be an exact decimal string.');
        }

        return $decimal;
    }
}
