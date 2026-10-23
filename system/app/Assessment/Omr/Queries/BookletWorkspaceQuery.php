<?php

namespace App\Assessment\Omr\Queries;

use App\Models\Assessment\OmrBookletItem;
use App\Models\Assessment\OmrBookletSet;
use App\Models\Assessment\OmrQuestionBooklet;
use App\Models\Assessment\OmrQuestionBookletVersion;
use App\Models\Examination\ExamSession;
use App\Models\Examination\ExamSessionMaterialSnapshot;
use App\Models\Foundation\Institution;
use App\Models\User;
use RuntimeException;

final class BookletWorkspaceQuery
{
    public function __construct(
        private readonly BookletContractQuery $contract,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forBooklet(
        OmrQuestionBooklet $booklet,
        User $actor,
        bool $canViewQuestionContent,
        ?int $selectedVersionId = null,
        bool $readOnly = false,
        bool $kitContextPresent = false,
        ?bool $kitIsSynthetic = null,
        ?int $selectedInstrumentVersionId = null,
    ): array {
        $booklet->load([
            'versions' => fn ($query) => $query->orderByDesc('version'),
            'versions.items.section',
            'versions.sets.items.bookletItem',
            'versions.sections',
        ]);
        $version = $selectedVersionId !== null
            ? $booklet->versions->firstWhere('id', $selectedVersionId)
            : ($kitContextPresent ? null : $booklet->versions->first());

        $lifecycleStatus = $version?->archived_at !== null
            ? 'archived'
            : ($version?->isFinalized() ? 'official' : 'draft');

        $isInUse = $version !== null && (
            ExamSession::query()->where('booklet_version_id', $version->id)->exists()
            || ExamSessionMaterialSnapshot::query()->where('booklet_version_id', $version->id)->exists()
        );
        $contract = $version !== null ? $this->contract->forBooklet($version) : [
            'template_version_id' => null,
            'areas' => [],
            'areas_by_id' => [],
            'total_count' => 0,
        ];
        $allowMultipleSets = (bool) ($version?->allow_multiple_sets ?? false);
        $allowedSetCodes = $allowMultipleSets ? ['A', 'B', 'C', 'D'] : ['A'];
        $requiredSetCodes = $allowedSetCodes;

        $itemsByArea = $version?->items->groupBy(fn (OmrBookletItem $item): ?int => $item->section?->id === null ? null : (int) $item->section->id) ?? collect();
        $areas = collect($contract['areas'])->map(function (array $area) use ($itemsByArea): array {
            $count = $itemsByArea->get($area['id'], collect())->count();

            return [
                ...$area,
                'completed_count' => $count,
                'is_complete' => $count === $area['required_count'],
            ];
        })->values()->all();

        $items = $version?->items
            ->sortBy(fn (OmrBookletItem $item): string => sprintf('%08d:%08d', $item->section?->display_order ?? 0, $item->authored_position))
            ->map(fn (OmrBookletItem $item): array => $this->itemProjection($item, $canViewQuestionContent))
            ->values()
            ->all() ?? [];

        $savedSets = $version?->sets->keyBy('set_code') ?? collect();
        $orderedItems = $version?->items
            ->sortBy(fn (OmrBookletItem $item): string => sprintf('%08d:%08d', $item->section?->display_order ?? 0, $item->authored_position))
            ->values() ?? collect();
        $sets = collect($requiredSetCodes)
            ->map(function (string $setCode) use ($savedSets, $contract, $orderedItems, $version, $canViewQuestionContent): array {
                /** @var OmrBookletSet|null $set */
                $set = $savedSets->get($setCode);
                $setItems = $set?->items ?? collect();
                $progressBySection = $setItems->groupBy('booklet_section_uid');

                return [
                    'id' => $set === null ? $setCode : (int) $set->id,
                    'set_code' => $setCode,
                    'status' => (string) ($set?->status ?? OmrBookletSet::STATUS_DRAFT),
                    'is_complete' => $this->setIsComplete($version, $set, $contract),
                    'order_hash' => $set?->order_hash,
                    'answer_key_hash' => $set?->answer_key_hash,
                    'diagnostics' => $set?->diagnostics_json ?? [],
                    'areas' => collect($contract['areas'])->map(function (array $section) use ($progressBySection, $orderedItems): array {
                        $expected = $orderedItems->where('booklet_section_uid', $section['uid']);
                        $rows = $progressBySection->get($section['uid'], collect());
                        $completed = $rows->filter(function ($row) use ($expected): bool {
                            $item = $expected->firstWhere('id', $row->booklet_item_id);
                            if ($item === null || $item->booklet_item_uid !== $row->booklet_item_uid) {
                                return false;
                            }

                            return ! $item->key_required
                                ? $row->correct_option_code === null
                                : in_array($row->correct_option_code, $item->option_tokens ?? [], true);
                        });

                        return [
                            'id' => (int) $section['id'],
                            'booklet_section_uid' => $section['uid'],
                            'required_count' => (int) $section['required_count'],
                            'completed_count' => $completed->count(),
                            'missing_positions' => $expected
                                ->filter(fn (OmrBookletItem $item): bool => ! $rows->contains('booklet_item_uid', $item->booklet_item_uid))
                                ->pluck('authored_position')->values()->all(),
                            'is_complete' => $completed->count() === $expected->count()
                                && $expected->count() === (int) $section['required_count'],
                        ];
                    })->values()->all(),
                    'items' => $setItems
                        ->sortBy('global_position')
                        ->map(fn ($item): array => [
                            'id' => (int) $item->id,
                            'booklet_item_id' => $item->booklet_item_id !== null ? (int) $item->booklet_item_id : null,
                            'booklet_item_uid' => $item->booklet_item_uid,
                            'booklet_section_uid' => $item->booklet_section_uid,
                            'booklet_version_id' => $item->booklet_version_id !== null ? (int) $item->booklet_version_id : null,
                            'area_position' => (int) $item->area_position,
                            'global_position' => (int) $item->global_position,
                            'correct_option_code' => $item->correct_option_code,
                            'question' => $canViewQuestionContent && $item->bookletItem !== null
                                ? $this->itemProjection($item->bookletItem, true)
                                : null,
                        ])
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->all();

        if ($version?->authoring_mode === OmrQuestionBookletVersion::AUTHORING_MODE_ANSWER_KEYS_ONLY) {
            $areas = collect($areas)
                ->map(function (array $area) use ($sets): array {
                    $progress = collect(collect($sets)->firstWhere('set_code', 'A')['areas'] ?? [])
                        ->firstWhere('booklet_section_uid', $area['uid']);

                    return [
                        ...$area,
                        'completed_count' => (int) ($progress['completed_count'] ?? 0),
                        'is_complete' => (bool) ($progress['is_complete'] ?? false),
                    ];
                })
                ->values()
                ->all();
        }

        $intrinsicSections = collect($contract['areas'])->map(function (array $section) use ($orderedItems): array {
            return [
                'id' => (int) $section['id'],
                'uid' => $section['uid'],
                'name' => $section['name'],
                'items' => $orderedItems
                    ->where('booklet_section_uid', $section['uid'])
                    ->map(fn (OmrBookletItem $item): array => [
                        'id' => (int) $item->id,
                        'booklet_item_uid' => (string) $item->booklet_item_uid,
                        'booklet_section_uid' => (string) $item->booklet_section_uid,
                        'local_number' => (string) $item->authored_position,
                        'option_tokens' => $item->option_tokens ?? [],
                        'key_required' => $item->key_required === null ? null : (bool) $item->key_required,
                    ])
                    ->values()
                    ->all(),
            ];
        })->values()->all();
        $nextAction = $readOnly
            ? 'view'
            : ($kitContextPresent && $selectedVersionId === null
                ? 'select_booklet'
                : $this->nextAction($version, $contract, $sets));
        $enableMultiSetBooklets = Institution::current()->usesMultiSetBooklets();

        return [
            'id' => (int) $booklet->id,
            'name' => (string) $booklet->name,
            'mode' => $version?->authoring_mode ?? OmrQuestionBookletVersion::AUTHORING_MODE_ANSWER_KEYS_ONLY,
            'version_id' => $version?->id,
            'version' => $version?->version,
            'status' => $version?->status ?? 'draft',
            'lifecycle_status' => $lifecycleStatus,
            'can_discard' => ! $readOnly && ($version?->isDraft() ?? false),
            'can_archive' => ! $readOnly && ($version?->isFinalized() && $version?->archived_at === null),
            'is_in_use' => $isInUse,
            'can_restore' => ! $readOnly && ($version?->isFinalized() && $version?->archived_at !== null),
            'destroy_url' => $version !== null && ! $readOnly ? route('setup.exam-materials.booklets.destroy', $version->id, false) : null,
            'archive_url' => $version !== null && ! $readOnly ? route('setup.exam-materials.booklets.archive', $version->id, false) : null,
            'restore_url' => $version !== null && ! $readOnly ? route('setup.exam-materials.booklets.restore', $version->id, false) : null,
            'lock_version' => (int) ($version?->lock_version ?? 1),
            'is_finalized' => $version?->isFinalized() ?? false,
            'sections' => $areas,
            'section_items' => $intrinsicSections,
            'items' => $items,
            'sets' => $sets,
            'allow_multiple_sets' => (bool) ($version?->allow_multiple_sets ?? false),
            'enable_multi_set_booklets' => $enableMultiSetBooklets,
            'is_retained_disabled' => (bool) ($version?->allow_multiple_sets ?? false) && ! $enableMultiSetBooklets,
            'allowed_set_codes' => $allowedSetCodes,
            'required_set_codes' => $requiredSetCodes,
            'is_ready_to_finalize' => ! $readOnly && $nextAction === 'finalize',
            'next_action' => $nextAction,
            'can_view_question_content' => $canViewQuestionContent,
            'read_only' => $readOnly,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function itemProjection(OmrBookletItem $item, bool $includeContent): array
    {
        $content = $includeContent && is_array($item->encrypted_content) ? $item->encrypted_content : null;
        $prompt = is_array($content)
            ? (string) ($content['prompt'] ?? $content['stem'] ?? '')
            : null;
        $choices = is_array($content) && is_array($content['choices'] ?? null)
            ? $content['choices']
            : null;

        return [
            'id' => (int) $item->id,
            'booklet_item_uid' => (string) $item->booklet_item_uid,
            'booklet_section_uid' => (string) $item->booklet_section_uid,
            'booklet_section_id' => (int) ($item->section?->id ?? 0),
            'section_name' => (string) ($item->section?->name ?? ''),
            'section_display_order' => (int) ($item->section?->display_order ?? 0),
            'section_expected_count' => (int) ($item->section?->expected_count ?? 0),
            'option_tokens' => $item->option_tokens ?? [],
            'key_required' => $item->key_required === null ? null : (bool) $item->key_required,
            'authored_position' => (int) $item->authored_position,
            'correct_option_code' => $item->correct_option_code,
            'content_hash' => (string) $item->content_hash,
            'content' => $content === null ? null : ['prompt' => $prompt, 'choices' => $choices],
            'stem' => $prompt,
            'choices' => $choices,
        ];
    }

    private function setIsComplete(
        ?OmrQuestionBookletVersion $version,
        ?OmrBookletSet $set,
        array $contract,
    ): bool {
        if ($version === null || $set === null) {
            return false;
        }

        try {
            $this->contract->validateSet($version, $set, true, $contract);
        } catch (RuntimeException) {
            return false;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $contract
     * @param  list<array<string, mixed>>  $sets
     */
    private function nextAction(?OmrQuestionBookletVersion $version, array $contract, array $sets): string
    {
        if ($version === null) {
            return 'create';
        }
        if ($version->isFinalized()) {
            return 'read_only';
        }
        if ($contract['areas'] === []) {
            return 'author_sections';
        }
        if ($version->items->count() < $contract['total_count']) {
            return 'author_items';
        }
        $projectedSets = collect($sets)->keyBy('set_code');
        foreach ($version->requiredSetCodes() as $setCode) {
            $set = $version->sets->firstWhere('set_code', $setCode);
            if ($set === null || $set->source_content_hash !== $version->content_hash) {
                return 'generate_sets';
            }
            if (! ($projectedSets->get($setCode)['is_complete'] ?? false)) {
                return 'complete_sets';
            }
        }

        return collect($sets)->contains(fn (array $set): bool => ! $set['is_complete'])
            ? 'complete_sets'
            : 'finalize';
    }
}
