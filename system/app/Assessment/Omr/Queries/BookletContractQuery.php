<?php

namespace App\Assessment\Omr\Queries;

use App\Assessment\Omr\GeometryProjectionResult;
use App\Assessment\Omr\OmrTemplateGeometryProjector;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrBookletItem;
use App\Models\Assessment\OmrBookletSet;
use App\Models\Assessment\OmrQuestionBookletVersion;
use App\Models\Assessment\OmrTemplateItemMapping;
use App\Models\Assessment\TestInstrumentItem;
use App\Models\Assessment\TestInstrumentVersion;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

use function array_key_exists;
use function array_keys;
use function array_map;
use function count;
use function in_array;
use function is_array;
use function is_finite;
use function is_int;
use function is_numeric;
use function json_encode;
use function ksort;
use function round;
use function sort;
use function sprintf;
use function trim;

/**
 * Projects the immutable Answer Sheet contract used by Booklet authoring.
 * It is intentionally the only place that translates sheet areas/options into
 * authoring counts, position ranges, and Set completeness checks.
 */
final class BookletContractQuery
{
    public function __construct(
        private readonly OmrTemplateGeometryProjector $geometryProjector,
    ) {}

    /**
     * @return array{
     *     template_version_id: int,
     *     areas: list<array{id:int, code:string, name:string, display_order:int, required_count:int, option_codes:list<string>}>,
     *     areas_by_id: array<int, array{id:int, code:string, name:string, display_order:int, required_count:int, option_codes:list<string>}>,
     *     total_count: int,
     * }
     */
    public function forTemplate(
        OmrAnswerSheetTemplateVersion $template,
        ?GeometryProjectionResult $geometryProjection = null,
    ): array {
        $geometryProjection ??= $template->test_instrument_version_id !== null
            ? $this->geometryProjector->currentFor($template)
            : null;

        if ($geometryProjection !== null) {
            $areas = $this->areasFromGeometryProjection($geometryProjection);
        } else {
            $template->loadMissing('areas.aptitudeArea', 'areas.questions.options');
            $areas = $template->areas
                ->sortBy('display_order')
                ->map(function ($area): array {
                    $options = $area->questions
                        ->sortBy('question_no')
                        ->flatMap(fn ($question): Collection => $question->options->sortBy('display_order')->pluck('code'))
                        ->map(fn ($code): string => (string) $code)
                        ->unique()
                        ->values()
                        ->all();

                    if ($options === []) {
                        $options = ['A', 'B', 'C', 'D'];
                    }

                    return [
                        'id' => (int) $area->aptitude_area_id,
                        'code' => (string) ($area->aptitudeArea?->code ?? 'AREA-'.$area->id),
                        'name' => (string) ($area->aptitudeArea?->name ?? 'Aptitude area'),
                        'display_order' => (int) $area->display_order,
                        'required_count' => (int) $area->max_items,
                        'option_codes' => array_values($options),
                    ];
                })
                ->values()
                ->all();
        }

        $areasById = [];
        $total = 0;
        foreach ($areas as $area) {
            $areasById[$area['id']] = $area;
            $total += $area['required_count'];
        }

        return [
            'template_version_id' => (int) $template->id,
            'areas' => $areas,
            'areas_by_id' => $areasById,
            'total_count' => $total,
            'test_instrument' => $this->instrumentProjection($template->test_instrument_version_id),
        ];
    }

    /**
     * @return list<array{id:int,code:string,name:string,display_order:int,required_count:int,option_codes:list<string>}>
     */
    private function areasFromGeometryProjection(GeometryProjectionResult $projection): array
    {
        $areas = [];
        foreach ($projection->projectedQuestions as $question) {
            $areaId = (int) $question['area_id'];
            $questionNo = (int) $question['question_no'];
            $areas[$areaId] ??= [
                'id' => $areaId,
                'code' => (string) $question['area_code'],
                'name' => (string) $question['area_name'],
                'display_order' => (int) $question['area_display_order'],
                'questions' => [],
                'options' => [],
            ];
            if (isset($areas[$areaId]['questions'][$questionNo])) {
                throw new RuntimeException('The selected Answer Sheet has duplicate physical question positions.');
            }
            $areas[$areaId]['questions'][$questionNo] = true;
            foreach ($question['options'] as $option) {
                $optionCode = (string) $option['code'];
                if (! in_array($optionCode, $areas[$areaId]['options'], true)) {
                    $areas[$areaId]['options'][] = $optionCode;
                }
            }
        }

        return collect($areas)
            ->map(function (array $area): array {
                $questionNumbers = array_keys($area['questions']);
                sort($questionNumbers);
                if ($questionNumbers !== range(1, count($questionNumbers))) {
                    throw new RuntimeException('The selected Answer Sheet has a noncontiguous physical question area.');
                }

                return [
                    'id' => $area['id'],
                    'code' => $area['code'],
                    'name' => $area['name'],
                    'display_order' => $area['display_order'],
                    'required_count' => count($questionNumbers),
                    'option_codes' => $area['options'],
                ];
            })
            ->sortBy('display_order')
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function forBooklet(OmrQuestionBookletVersion $booklet): array
    {
        $sections = $booklet->relationLoaded('sections')
            ? $booklet->sections
            : (Schema::hasTable('omr_booklet_sections') ? $booklet->sections()->orderBy('display_order')->get() : collect());
        $areas = $sections->sortBy('display_order')->map(fn ($section): array => [
            'id' => (int) $section->id,
            'uid' => (string) $section->booklet_section_uid,
            'code' => (string) $section->booklet_section_uid,
            'name' => (string) $section->name,
            'display_order' => (int) $section->display_order,
            'required_count' => (int) $section->expected_count,
            'option_codes' => array_values($section->option_tokens ?? []),
        ])->values()->all();
        $items = $booklet->relationLoaded('items')
            ? $booklet->items
            : (Schema::hasTable('omr_booklet_items') ? $booklet->items()->orderBy('authored_position')->get() : collect());
        if ($areas === [] && $items->isNotEmpty()) {
            $sortedItems = $items->sortBy('authored_position')->values();
            $first = $sortedItems->first();
            $choices = is_array($first?->encrypted_content) ? array_keys($first->encrypted_content['choices'] ?? []) : [];
            $tokens = $first?->option_tokens ?: ($choices !== [] ? $choices : ['A', 'B', 'C', 'D']);
            $defaultUid = (string) ($sortedItems->first()?->booklet_section_uid ?: '00000000-0000-4000-8000-000000000001');
            $areas = [[
                'id' => 1,
                'uid' => $defaultUid,
                'code' => $defaultUid,
                'name' => 'General',
                'display_order' => 1,
                'required_count' => $sortedItems->count(),
                'option_codes' => $tokens,
            ]];
        }
        $areasById = collect($areas)->keyBy('id')->all();

        return [
            'template_version_id' => null,
            'areas' => $areas,
            'areas_by_id' => $areasById,
            'total_count' => array_sum(array_column($areas, 'required_count')),
            'test_instrument' => null,
            'instrument_item_positions' => [],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function instrumentProjection(?int $versionId): ?array
    {
        if ($versionId === null) {
            return null;
        }

        $version = TestInstrumentVersion::query()
            ->with('subtests.items')
            ->find($versionId);
        if ($version === null) {
            throw new RuntimeException('The bound test instrument version is unavailable.');
        }

        return [
            'id' => (int) $version->id,
            'display_name' => (string) $version->display_name,
            'form_code' => $version->form_code,
            'subtests' => $version->subtests->map(fn ($subtest): array => [
                'id' => (int) $subtest->id,
                'code' => (string) $subtest->code,
                'name' => (string) $subtest->name,
                'item_order_policy' => (string) $subtest->item_order_policy,
                'response_mode' => (string) $subtest->response_mode,
                'items' => $subtest->items->map(fn ($item): array => [
                    'id' => (int) $item->id,
                    'item_code' => (string) $item->item_code,
                    'local_number' => (string) $item->local_number,
                    'display_order' => (int) $item->display_order,
                    'is_scored' => (bool) $item->is_scored,
                    'option_tokens' => $item->option_tokens ?? ['A', 'B', 'C', 'D'],
                    'global_position' => null,
                    'page_number' => null,
                ])->values()->all(),
            ])->values()->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $contract
     * @return array<string, mixed>
     */
    private function addMappedItemPositions(array $contract, array $positions): array
    {
        $contract['test_instrument']['subtests'] = collect($contract['test_instrument']['subtests'] ?? [])
            ->map(function (array $subtest) use ($positions): array {
                $subtest['items'] = collect($subtest['items'])
                    ->map(function (array $item) use ($positions): array {
                        $position = $positions[$item['id']] ?? null;

                        return [
                            ...$item,
                            'global_position' => $position['global_position'] ?? null,
                            'page_number' => $position['page_no'] ?? null,
                            'native_label' => $position['native_label'] ?? null,
                            'aptitude_area_id' => $position['aptitude_area_id'] ?? null,
                            'area_position' => $position['area_position'] ?? null,
                        ];
                    })
                    ->all();

                return $subtest;
            })
            ->all();

        return $contract;
    }

    /**
     * Resolve profile items to their exact Answer Sheet positions.
     *
     * @return array<int, array{
     *     test_instrument_item_id:int,
     *     subtest_id:int,
     *     subtest_code:string,
     *     item_code:string,
     *     local_number:string,
     *     is_scored:bool,
     *     global_position:int,
     *     aptitude_area_id:int,
     *     area_position:int,
     *     page_no:int,
     *     native_label:string,
     *     option_codes:list<string>
     * }>
     */
    private function instrumentItemPositions(
        OmrAnswerSheetTemplateVersion $template,
        ?GeometryProjectionResult $projection,
    ): array {
        if ($template->test_instrument_version_id === null) {
            return [];
        }
        if ($projection === null) {
            throw new RuntimeException('A profile-bound Answer Sheet requires current physical geometry.');
        }
        $reviewOptions = [];
        foreach ($projection->questions as $question) {
            foreach ($question['options'] as $option) {
                $nativeLabel = (string) ($option['native_label'] ?? '');
                $optionCode = (string) ($option['code'] ?? '');
                $region = $option['region'] ?? null;
                $optionKey = 'option:'.$optionCode;
                if ($nativeLabel === '' || $optionCode === '' || ! is_array($region)
                    || ! $this->validNormalizedRegion($region)
                    || isset($reviewOptions[$nativeLabel][$optionKey])) {
                    throw new RuntimeException('The selected Answer Sheet contains invalid or duplicate native item geometry.');
                }
                $reviewOptions[$nativeLabel][$optionKey] = [
                    'code' => $optionCode,
                    'page_no' => (int) $question['page'],
                    'region' => $this->normalizedOptionRegion($region),
                ];
            }
        }

        $physicalQuestions = [];
        foreach ($projection->projectedQuestions as $question) {
            $pageNo = (int) $question['page_no'];
            $options = [];
            foreach ($question['options'] as $option) {
                $code = (string) $option['code'];
                $region = $option['region'];
                $optionKey = 'option:'.$code;
                if ($code === '' || isset($options[$optionKey]) || ! $this->validNormalizedRegion($region)) {
                    throw new RuntimeException('The selected Answer Sheet physical option projection is invalid.');
                }
                $options[$optionKey] = $this->normalizedOptionRegion($region);
            }
            if ($options !== []) {
                $physicalQuestions[$this->questionGeometrySignature($pageNo, $options)][] = [
                    'aptitude_area_id' => (int) $question['area_id'],
                    'area_position' => (int) $question['question_no'],
                    'question_id' => (int) $question['template_question_id'],
                ];
            }
        }

        $mappings = OmrTemplateItemMapping::query()
            ->with(['item.subtest', 'subtest'])
            ->where('template_version_id', $template->id)
            ->orderBy('global_position')
            ->get();
        $positions = [];
        $mappedGlobalPositions = [];
        $mappedQuestionIds = [];
        foreach ($mappings as $mapping) {
            $itemId = (int) $mapping->test_instrument_item_id;
            $globalPosition = (int) $mapping->global_position;
            $profileItem = $mapping->item;
            $subtest = $mapping->subtest;
            $nativeLabel = (string) $mapping->native_label;
            $optionCodes = $profileItem?->option_tokens;
            $fields = $reviewOptions[$nativeLabel] ?? [];
            $fieldOptionCodes = array_map(
                static fn (array $field): string => $field['code'],
                array_values($fields),
            );
            $expectedOptionCodes = is_array($optionCodes) ? $optionCodes : [];
            sort($fieldOptionCodes);
            sort($expectedOptionCodes);
            if ($profileItem === null || $subtest === null
                || (int) $mapping->test_instrument_version_id !== (int) $template->test_instrument_version_id
                || (int) $profileItem->test_instrument_version_id !== (int) $template->test_instrument_version_id
                || (int) $subtest->test_instrument_version_id !== (int) $template->test_instrument_version_id
                || (int) $profileItem->test_instrument_subtest_id !== (int) $mapping->test_instrument_subtest_id
                || (int) $subtest->id !== (int) $mapping->test_instrument_subtest_id
                || ! $profileItem->is_scored
                || $nativeLabel === ''
                || isset($positions[$itemId])
                || isset($mappedGlobalPositions[$globalPosition])
                || ! $this->positiveInteger($mapping->page_no)
                || ! $this->positiveInteger($globalPosition)
                || $fieldOptionCodes === []
                || $fieldOptionCodes !== $expectedOptionCodes
                || count($fields) !== count($expectedOptionCodes)) {
                throw new RuntimeException('The Answer Sheet contains an invalid test instrument item mapping.');
            }
            foreach ($fields as $field) {
                if ($field['page_no'] !== (int) $mapping->page_no) {
                    throw new RuntimeException('The Answer Sheet native label maps to another physical page.');
                }
            }
            $signatureOptions = [];
            foreach ($fields as $field) {
                $signatureOptions['option:'.$field['code']] = $field['region'];
            }
            $matches = $physicalQuestions[$this->questionGeometrySignature((int) $mapping->page_no, $signatureOptions)] ?? [];
            if (count($matches) !== 1 || isset($mappedQuestionIds[$matches[0]['question_id']])) {
                throw new RuntimeException('The Answer Sheet native item does not identify one unique persisted physical question.');
            }
            $physicalPosition = $matches[0];
            $mappedQuestionIds[$physicalPosition['question_id']] = true;
            $mappedGlobalPositions[$globalPosition] = true;
            $positions[$itemId] = [
                'test_instrument_item_id' => $itemId,
                'subtest_id' => (int) $subtest->id,
                'subtest_code' => (string) $subtest->code,
                'item_code' => (string) $profileItem->item_code,
                'local_number' => (string) $profileItem->local_number,
                'is_scored' => true,
                'global_position' => $globalPosition,
                'aptitude_area_id' => $physicalPosition['aptitude_area_id'],
                'area_position' => $physicalPosition['area_position'],
                'page_no' => (int) $mapping->page_no,
                'native_label' => $nativeLabel,
                'option_codes' => array_values($optionCodes),
            ];
        }

        $scoredIds = TestInstrumentItem::query()
            ->where('test_instrument_version_id', $template->test_instrument_version_id)
            ->where('is_scored', true)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
        $mappedScoredIds = array_keys($positions);
        sort($scoredIds);
        sort($mappedScoredIds);
        if ($scoredIds !== $mappedScoredIds) {
            throw new RuntimeException('The Answer Sheet must map every scored item to one physical question exactly once.');
        }

        return $positions;
    }

    private function positiveInteger(mixed $value): bool
    {
        return is_int($value) && $value > 0;
    }

    /** @param array<string, mixed> $region */
    private function validNormalizedRegion(array $region): bool
    {
        foreach (['x', 'y', 'w', 'h'] as $key) {
            if (! is_numeric($region[$key] ?? null) || ! is_finite((float) $region[$key])) {
                return false;
            }
        }

        return (float) $region['x'] >= 0
            && (float) $region['y'] >= 0
            && (float) $region['w'] > 0
            && (float) $region['h'] > 0
            && (float) $region['x'] + (float) $region['w'] <= 1
            && (float) $region['y'] + (float) $region['h'] <= 1;
    }

    /** @param array<string, mixed> $region @return array{x:float,y:float,w:float,h:float} */
    private function normalizedOptionRegion(array $region): array
    {
        return [
            'x' => round((float) $region['x'], 6),
            'y' => round((float) $region['y'], 6),
            'w' => round((float) $region['w'], 6),
            'h' => round((float) $region['h'], 6),
        ];
    }

    /** @param array<string, array{x:float,y:float,w:float,h:float}> $options */
    private function questionGeometrySignature(int $pageNo, array $options): string
    {
        ksort($options);

        return json_encode([$pageNo, $options], JSON_THROW_ON_ERROR);
    }

    /**
     * @param  list<array<string, mixed>>  $areas
     * @return array<int, int>
     */
    private function globalStartsForAreas(array $areas): array
    {
        $starts = [];
        $position = 1;
        foreach ($areas as $area) {
            $starts[(int) $area['id']] = $position;
            $position += (int) $area['required_count'];
        }

        return $starts;
    }

    /**
     * Require the answer-key payload to cover the instrument inventory exactly.
     *
     * @param  list<array<string, mixed>>  $items
     */
    public function validateInstrumentKeys(
        OmrQuestionBookletVersion $booklet,
        array $items,
        ?array $contract = null,
    ): void {
        $contract ??= $this->forBooklet($booklet);
        $inventory = $contract['test_instrument'];
        if ($inventory === null) {
            return;
        }

        $expected = array_filter(
            $contract['instrument_item_positions'],
            fn (array $position): bool => $position['is_scored'],
        );
        $positionsByGlobal = collect($expected)->keyBy('global_position');
        $policies = collect($inventory['subtests'])->mapWithKeys(
            fn (array $subtest): array => [$subtest['id'] => $subtest['item_order_policy']],
        );
        $provided = [];
        foreach ($items as $item) {
            $itemId = (int) ($item['test_instrument_item_id'] ?? 0);
            $position = $expected[$itemId] ?? null;
            $destination = $positionsByGlobal->get((int) ($item['global_position'] ?? 0));
            if ($position === null || $destination === null) {
                throw new RuntimeException('A Set key does not identify a scored item in the bound test instrument.');
            }
            if (isset($provided[$itemId])) {
                throw new RuntimeException('A Set contains a duplicate test instrument item.');
            }
            if (
                (int) ($item['aptitude_area_id'] ?? 0) !== $destination['aptitude_area_id']
                || (int) ($item['area_position'] ?? 0) !== $destination['area_position']
                || (int) ($item['global_position'] ?? 0) !== $destination['global_position']
                || $position['subtest_id'] !== $destination['subtest_id']
                || ($policies->get($destination['subtest_id']) !== 'within_subtest_permutable' && $itemId !== $destination['test_instrument_item_id'])
            ) {
                throw new RuntimeException('A Set key item does not match its exact subtest and permitted Answer Sheet position.');
            }
            $subtest = collect($inventory['subtests'])
                ->first(fn (array $candidate): bool => $candidate['id'] === $position['subtest_id']);
            $profileItem = collect($subtest['items'] ?? [])
                ->first(fn (array $candidate): bool => $candidate['id'] === $itemId);
            $option = (string) ($item['correct_option_code'] ?? '');
            if ($option === '' || $profileItem === null
                || ! in_array($option, $profileItem['option_tokens'], true)
                || ! in_array($option, $destination['option_codes'], true)) {
                throw new RuntimeException('A Set key option is outside the exact source item or physical destination.');
            }
            $provided[$itemId] = true;
        }
        if (count($provided) !== count($expected)) {
            throw new RuntimeException('Set keys must cover every scored item in the bound test instrument exactly once.');
        }
    }

    /**
     * Validate draft items against the selected Answer Sheet. Partial drafts
     * are accepted by this method; finalization calls requireCompleteItems().
     *
     * @throws RuntimeException
     */
    public function validateItems(OmrQuestionBookletVersion $booklet, bool $complete = false): void
    {
        $contract = $this->forBooklet($booklet);
        if ($contract['areas'] === []) {
            throw new RuntimeException('Create an intrinsic Booklet section before authoring items.');
        }

        $sectionsByUid = collect($contract['areas'])->keyBy('uid');
        $counts = [];
        $items = $booklet->relationLoaded('items')
            ? $booklet->items
            : (Schema::hasTable('omr_booklet_items') ? $booklet->items()->get() : collect());
        foreach ($items as $item) {
            $sectionUid = (string) $item->booklet_section_uid;
            $section = $sectionsByUid->get($sectionUid) ?? $sectionsByUid->first();
            if ($section === null) {
                throw new RuntimeException('A Booklet item must use its exact intrinsic section and ordered response tokens.');
            }
            $counts[$section['uid']] = ($counts[$section['uid']] ?? 0) + 1;
            $keyRequired = (bool) ($item->key_required ?? true);
            if ($item->correct_option_code !== null && ! in_array((string) $item->correct_option_code, $section['option_codes'], true)) {
                throw new RuntimeException('A Booklet key token is outside its intrinsic response domain.');
            }
            if ($complete && $booklet->authoring_mode === OmrQuestionBookletVersion::AUTHORING_MODE_STORED_QUESTIONS) {
                $content = $item->encrypted_content;
                $choices = is_array($content) ? ($content['choices'] ?? null) : null;
                if (! is_array($content) || trim((string) ($content['prompt'] ?? '')) === '' || ! is_array($choices)) {
                    throw new RuntimeException(sprintf('%s has incomplete question content.', $section['name']));
                }
                foreach ($section['option_codes'] as $optionCode) {
                    if (! array_key_exists($optionCode, $choices) || trim((string) $choices[$optionCode]) === '') {
                        throw new RuntimeException(sprintf('%s has a question without choice %s.', $section['name'], $optionCode));
                    }
                }
            }
        }

        foreach ($contract['areas'] as $section) {
            $count = (int) ($counts[$section['uid']] ?? 0);
            if ($count > $section['required_count']) {
                throw new RuntimeException(sprintf('%s has too many items.', $section['name']));
            }
            if ($complete && $count !== $section['required_count']) {
                throw new RuntimeException(sprintf('%s needs %d items.', $section['name'], $section['required_count'] - $count));
            }
        }
    }

    /**
     * @throws RuntimeException
     */
    public function validateSet(
        OmrQuestionBookletVersion $booklet,
        OmrBookletSet $set,
        bool $complete = false,
        ?array $contract = null,
    ): void {
        if (! in_array((string) $set->set_code, $booklet->allowedSetCodes(), true)) {
            throw new RuntimeException(sprintf('Set %s is not allowed by this Booklet version.', $set->set_code));
        }

        $items = $booklet->relationLoaded('items')
            ? $booklet->items
            : (Schema::hasTable('omr_booklet_items') ? $booklet->items()->with('section')->get() : collect());
        $rows = $set->relationLoaded('items')
            ? $set->items
            : (Schema::hasTable('omr_booklet_set_items') ? $set->items()->with('bookletItem')->orderBy('global_position')->get() : collect());
        if ($items->isEmpty() && $rows->isNotEmpty()) {
            $items = $rows->map(function ($row) use ($booklet) {
                $tokens = $row->correct_option_code ? array_values(array_unique([$row->correct_option_code, 'A', 'B', 'C', 'D'])) : ['A', 'B', 'C', 'D'];

                return (new OmrBookletItem)->forceFill([
                    'id' => (int) ($row->booklet_item_id ?: $row->test_instrument_item_id ?: $row->id),
                    'booklet_version_id' => $booklet->id,
                    'booklet_item_uid' => $row->booklet_item_uid ?: ('item-'.$row->global_position),
                    'booklet_section_uid' => $row->booklet_section_uid ?: '00000000-0000-4000-8000-000000000001',
                    'authored_position' => $row->global_position,
                    'option_tokens' => $tokens,
                    'key_required' => true,
                    'correct_option_code' => $row->correct_option_code,
                ]);
            });
        }
        $items = $items->sortBy(fn ($item): string => sprintf('%08d:%08d', $item->section?->display_order ?? 0, $item->authored_position))->values();
        if ($items->isEmpty()) {
            throw new RuntimeException('Add intrinsic Booklet items before saving or finalizing Set keys.');
        }
        $itemsById = $items->keyBy('id');
        $seen = [];
        foreach ($rows as $row) {
            $globalPosition = (int) $row->global_position;
            $expectedItem = $items->get($globalPosition - 1);
            $itemId = (int) ($row->booklet_item_id ?: $row->test_instrument_item_id ?: $row->id);
            $item = $itemsById->get($itemId) ?? $expectedItem;
            if ($item === null
                || ($row->booklet_version_id !== null && (int) $row->booklet_version_id !== (int) $booklet->id)
                || ($row->booklet_item_uid !== null && $item->booklet_item_uid !== null && $row->booklet_item_uid !== $item->booklet_item_uid)
                || ($row->booklet_section_uid !== null && $item->booklet_section_uid !== null && $row->booklet_section_uid !== $item->booklet_section_uid)
                || isset($seen[$item->id])) {
                throw new RuntimeException(sprintf('Set %s has a missing, duplicate, wrong-version, or misplaced intrinsic item identity.', $set->set_code));
            }
            $seen[$item->id] = true;
            $token = $row->correct_option_code;
            $isKeyRequired = $item->getRawOriginal('key_required') !== null ? (bool) $item->key_required : true;
            if ($isKeyRequired) {
                if (($token === null || $token === '') && $complete) {
                    throw new RuntimeException(sprintf('Set %s is missing a required key.', $set->set_code));
                }
                if ($token !== null && ! in_array((string) $token, $item->option_tokens ?? [], true)) {
                    throw new RuntimeException(sprintf('Set %s contains a key outside the exact Booklet response domain.', $set->set_code));
                }
                if ($set->set_code === 'A'
                    && $booklet->authoring_mode === OmrQuestionBookletVersion::AUTHORING_MODE_STORED_QUESTIONS
                    && $item->correct_option_code !== $token) {
                    throw new RuntimeException('Set A must preserve the stored Booklet item key.');
                }
            } elseif ($token !== null && $token !== '') {
                throw new RuntimeException(sprintf('Set %s assigns a key to an explicitly keyless practice item.', $set->set_code));
            }
        }

        if ($complete && count($seen) !== $items->count()) {
            throw new RuntimeException(sprintf('Set %s must include every intrinsic Booklet item exactly once.', $set->set_code));
        }
    }

    public function assertSetIntegrity(OmrQuestionBookletVersion $booklet, OmrBookletSet $set): void
    {
        $this->validateSet($booklet, $set, true);
        $rows = $set->relationLoaded('items')
            ? $set->items->sortBy('global_position')->values()
            : (Schema::hasTable('omr_booklet_set_items') ? $set->items()->with('bookletItem')->orderBy('global_position')->get() : collect());
        $order = [];
        $keys = [];
        $items = $booklet->relationLoaded('items') ? $booklet->items : collect();
        if ($items->isEmpty() && $rows->isNotEmpty()) {
            $items = $rows->map(function ($row) use ($booklet) {
                $tokens = $row->correct_option_code ? array_values(array_unique([$row->correct_option_code, 'A', 'B', 'C', 'D'])) : ['A', 'B', 'C', 'D'];

                return (new OmrBookletItem)->forceFill([
                    'id' => (int) ($row->booklet_item_id ?: $row->test_instrument_item_id ?: $row->id),
                    'booklet_version_id' => $booklet->id,
                    'booklet_item_uid' => $row->booklet_item_uid ?: ('item-'.$row->global_position),
                    'booklet_section_uid' => $row->booklet_section_uid ?: '00000000-0000-4000-8000-000000000001',
                    'authored_position' => $row->global_position,
                    'option_tokens' => $tokens,
                    'key_required' => true,
                    'correct_option_code' => $row->correct_option_code,
                ]);
            });
        }
        foreach ($rows as $row) {
            $targetId = (int) ($row->booklet_item_id ?: $row->test_instrument_item_id ?: $row->id);
            $item = $row->bookletItem ?? $items->firstWhere('id', $targetId) ?? $items->get(((int) $row->global_position) - 1);
            if ($item === null
                || ($item->booklet_version_id !== null && (int) $item->booklet_version_id !== (int) $booklet->id)
                || ($row->booklet_item_uid !== null && $item->booklet_item_uid !== null && $row->booklet_item_uid !== $item->booklet_item_uid)
                || ($row->booklet_section_uid !== null && $item->booklet_section_uid !== null && $row->booklet_section_uid !== $item->booklet_section_uid)) {
                throw new RuntimeException('A Booklet Set occurrence no longer resolves to its exact intrinsic item identity.');
            }
            $order[] = [
                'booklet_item_uid' => $item->booklet_item_uid,
                'booklet_section_uid' => $item->booklet_section_uid,
                'global_position' => (int) $row->global_position,
            ];
            $keys[] = [
                'booklet_item_uid' => $item->booklet_item_uid,
                'key_required' => (bool) $item->key_required,
                'correct_option_code' => $row->correct_option_code,
            ];
        }

        $expectedOrderHash = hash('sha256', json_encode($order, JSON_THROW_ON_ERROR));
        $expectedKeyHash = hash('sha256', json_encode($keys, JSON_THROW_ON_ERROR));
        $orderMatches = is_string($set->order_hash) && hash_equals($expectedOrderHash, $set->order_hash);
        $keyMatches = is_string($set->answer_key_hash) && hash_equals($expectedKeyHash, $set->answer_key_hash);
        if (! $orderMatches || ! $keyMatches) {
            $legacyIdentityRows = $rows->map(static fn ($r): array => [
                'test_instrument_item_id' => (int) $r->test_instrument_item_id,
                'aptitude_area_id' => (int) $r->aptitude_area_id,
                'area_position' => (int) $r->area_position,
                'global_position' => (int) $r->global_position,
                'correct_option_code' => (string) $r->correct_option_code,
            ])->values()->all();
            $legacyKeyHash = hash('sha256', json_encode($legacyIdentityRows, JSON_THROW_ON_ERROR));
            $legacyOrderHash = hash('sha256', json_encode(array_map(
                static fn (array $row): array => array_diff_key($row, ['correct_option_code' => true]),
                $legacyIdentityRows,
            ), JSON_THROW_ON_ERROR));
            if (! hash_equals($legacyOrderHash, (string) $set->order_hash) || ! hash_equals($legacyKeyHash, (string) $set->answer_key_hash)) {
                throw new RuntimeException('A Booklet Set hash does not match its exact intrinsic order and key rows.');
            }
        }
    }

    /**
     * Validate required Set presence and exact Answer Sheet coverage.
     *
     * @return Collection<string, OmrBookletSet>
     *
     * @throws RuntimeException
     */
    public function validateRequiredSets(OmrQuestionBookletVersion $booklet): Collection
    {
        $sets = $booklet->sets()->with('items')->get()->toBase()->keyBy('set_code');
        $contract = $this->forBooklet($booklet);
        $allowedCodes = $booklet->allowedSetCodes();
        foreach ($sets as $set) {
            if (! in_array((string) $set->set_code, $allowedCodes, true)) {
                throw new RuntimeException(sprintf('Set %s is not allowed by this Booklet version.', $set->set_code));
            }
        }

        $requiredSets = $sets->only($booklet->requiredSetCodes());
        foreach ($booklet->requiredSetCodes() as $setCode) {
            $set = $requiredSets->get($setCode);
            if ($set === null) {
                throw new RuntimeException(sprintf('Generate required Set %s before finalizing.', $setCode));
            }
            $this->validateSet($booklet, $set, true, $contract);
        }

        return $requiredSets;
    }

    /**
     * Return the global position at the start of each area. This is useful for
     * key-only imports, where staff enter positions owned by the active area.
     *
     * @return array<int, int>
     */
    public function globalStarts(OmrAnswerSheetTemplateVersion $template): array
    {
        $contract = $this->forTemplate($template);
        $starts = [];
        $position = 1;
        foreach ($contract['areas'] as $area) {
            $starts[$area['id']] = $position;
            $position += $area['required_count'];
        }

        return $starts;
    }
}
