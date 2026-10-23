<?php

namespace App\Assessment\Support;

use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use RuntimeException;

use function hash;
use function json_encode;

final class PhysicalSheetInventory
{
    /**
     * @return array{
     *   template_version_id:int,
     *   inventory_digest:string,
     *   groups:list<array{printed_section_uid:string,section_name:string,page_no:int,expected_item_count:int,items:list<array<string,mixed>>}>,
     *   items:list<array{physical_item_uid:string,printed_section_uid:string,section_name:string,sheet_item_id:int,page_no:int,native_label:string,global_position:int,option_tokens:list<string>}>
     * }
     */
    public function forVersion(OmrAnswerSheetTemplateVersion $version): array
    {
        $version->loadMissing('areas.questions.options');
        $sectionDeclarations = data_get($version->authoring_manifest, 'physical_inventory.sections');
        if (! is_array($sectionDeclarations) || ! array_is_list($sectionDeclarations) || $sectionDeclarations === []) {
            throw new RuntimeException('Compiled Answer Sheet is missing its declared Printed section inventory.');
        }

        $sectionsByUid = [];
        $declarationsByUid = [];
        $declaredLabels = [];
        foreach ($sectionDeclarations as $section) {
            if (! is_array($section)) {
                throw new RuntimeException('Compiled Printed section inventory contains malformed section metadata.');
            }
            $sectionUid = $section['printed_section_uid'] ?? null;
            $sectionName = $section['name'] ?? null;
            $pageNo = $section['page_no'] ?? null;
            $expectedCount = $section['expected_item_count'] ?? null;
            $declaredItems = $section['items'] ?? null;
            if (! is_string($sectionUid) || $sectionUid === '' || trim($sectionUid) !== $sectionUid
                || isset($sectionsByUid[$sectionUid])
                || ! is_string($sectionName) || $sectionName === '' || trim($sectionName) !== $sectionName
                || ! is_int($pageNo) || $pageNo < 1 || $pageNo > (int) $version->page_count
                || ! is_int($expectedCount) || $expectedCount < 1
                || ! is_array($declaredItems) || ! array_is_list($declaredItems)
                || count($declaredItems) !== $expectedCount) {
                throw new RuntimeException('Compiled Printed section inventory has inconsistent exact section identity or item count.');
            }

            $sectionsByUid[$sectionUid] = [
                'printed_section_uid' => $sectionUid,
                'section_name' => $sectionName,
                'page_no' => $pageNo,
                'expected_item_count' => $expectedCount,
                'items' => [],
            ];
            foreach ($declaredItems as $declaredItem) {
                if (! is_array($declaredItem)) {
                    throw new RuntimeException('Compiled Printed section inventory contains a malformed physical item declaration.');
                }
                $physicalUid = $declaredItem['physical_item_uid'] ?? null;
                $nativeLabel = $declaredItem['native_label'] ?? null;
                $declaredPageNo = $declaredItem['page_no'] ?? null;
                $optionTokens = $declaredItem['option_tokens'] ?? null;
                if (! is_string($physicalUid) || $physicalUid === '' || trim($physicalUid) !== $physicalUid
                    || isset($declarationsByUid[$physicalUid])
                    || ! is_string($nativeLabel) || $nativeLabel === '' || trim($nativeLabel) !== $nativeLabel
                    || isset($declaredLabels[$nativeLabel])
                    || ! is_int($declaredPageNo) || $declaredPageNo !== $pageNo
                    || ! is_array($optionTokens) || ! array_is_list($optionTokens) || $optionTokens === []
                    || array_filter($optionTokens, static fn (mixed $token): bool => ! is_string($token) || $token === '' || trim($token) !== $token) !== []
                    || count(array_unique($optionTokens, SORT_STRING)) !== count($optionTokens)) {
                    throw new RuntimeException('Compiled Printed section inventory has incomplete or duplicate physical item identity.');
                }
                $declaredLabels[$nativeLabel] = true;
                $declarationsByUid[$physicalUid] = [
                    'printed_section_uid' => $sectionUid,
                    'native_label' => $nativeLabel,
                    'page_no' => $declaredPageNo,
                    'option_tokens' => $optionTokens,
                ];
            }
        }

        $items = [];
        $physicalUids = [];
        $nativeLabels = [];
        $positions = [];
        $questions = $version->areas->flatMap(fn ($area) => $area->questions);
        if ($questions->isEmpty()) {
            throw new RuntimeException('Compiled Answer Sheet has no persisted physical response locations.');
        }
        foreach ($questions as $question) {
            $physicalUid = $question->physical_item_uid;
            $sectionUid = $question->printed_section_uid;
            $nativeLabel = $question->native_label;
            $pageNo = $question->page_no;
            $globalPosition = $question->global_position;
            $sheetItemId = (int) $question->getKey();
            $questionVersionId = $question->template_version_id;
            $declared = is_string($physicalUid) ? ($declarationsByUid[$physicalUid] ?? null) : null;
            if (! is_string($physicalUid) || $physicalUid === '' || trim($physicalUid) !== $physicalUid
                || isset($physicalUids[$physicalUid])
                || ! is_string($sectionUid) || $sectionUid === '' || trim($sectionUid) !== $sectionUid
                || ! is_string($nativeLabel) || $nativeLabel === '' || trim($nativeLabel) !== $nativeLabel
                || isset($nativeLabels[$nativeLabel])
                || ! is_int($pageNo) || $pageNo < 1 || $pageNo > (int) $version->page_count
                || ! is_int($globalPosition) || $globalPosition < 1 || isset($positions[$globalPosition])
                || $sheetItemId < 1
                || $questionVersionId === null || (int) $questionVersionId !== (int) $version->getKey()
                || ! isset($sectionsByUid[$sectionUid])
                || ! is_array($declared)
                || $declared['printed_section_uid'] !== $sectionUid
                || $declared['native_label'] !== $nativeLabel
                || $declared['page_no'] !== $pageNo) {
                throw new RuntimeException('Compiled Answer Sheet is missing or contradicts its persisted physical inventory identity.');
            }
            $optionTokens = $question->options
                ->sortBy('display_order')
                ->pluck('code')
                ->map(static fn (mixed $token): string => (string) $token)
                ->values()
                ->all();
            if ($optionTokens === []
                || $optionTokens !== $declared['option_tokens']
                || count(array_unique($optionTokens, SORT_STRING)) !== count($optionTokens)
                || array_filter($optionTokens, static fn (string $token): bool => $token === '') !== []) {
                throw new RuntimeException('Compiled Answer Sheet physical options do not match the declared literal response tokens.');
            }

            $physicalUids[$physicalUid] = true;
            $nativeLabels[$nativeLabel] = true;
            $positions[$globalPosition] = true;
            $item = [
                'physical_item_uid' => $physicalUid,
                'printed_section_uid' => $sectionUid,
                'section_name' => $sectionsByUid[$sectionUid]['section_name'],
                'sheet_item_id' => $sheetItemId,
                'page_no' => $pageNo,
                'native_label' => $nativeLabel,
                'global_position' => $globalPosition,
                'option_tokens' => $optionTokens,
            ];
            $items[] = $item;
            $sectionsByUid[$sectionUid]['items'][] = $item;
        }

        $positions = array_keys($positions);
        sort($positions, SORT_NUMERIC);
        if ($positions !== range(1, count($items)) || count($physicalUids) !== count($declarationsByUid)) {
            throw new RuntimeException('Persisted physical locations do not cover the declared inventory exactly once.');
        }
        usort($items, static fn (array $left, array $right): int => $left['global_position'] <=> $right['global_position']);
        $groups = [];
        foreach ($sectionsByUid as $section) {
            if (count($section['items']) !== $section['expected_item_count']) {
                throw new RuntimeException('Persisted physical locations do not match a Printed section expected item count.');
            }
            usort($section['items'], static fn (array $left, array $right): int => $left['global_position'] <=> $right['global_position']);
            $groups[] = $section;
        }

        $canonical = ['groups' => $groups, 'items' => $items];

        return [
            'template_version_id' => (int) $version->getKey(),
            'inventory_digest' => hash('sha256', json_encode($canonical, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)),
            'groups' => $groups,
            'items' => $items,
        ];
    }
}
