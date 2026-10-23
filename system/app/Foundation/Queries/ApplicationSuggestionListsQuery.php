<?php

namespace App\Foundation\Queries;

use App\Models\Foundation\ApplicationSuggestionList;

final class ApplicationSuggestionListsQuery
{
    private const CATEGORIES = ['schools', 'shs', 'programs'];

    /** @return array<string, array{entries: array, revision: int}> */
    public function forStaff(int $institutionId): array
    {
        $rows = ApplicationSuggestionList::query()->where('institution_id', $institutionId)->get()->keyBy('category');
        $result = [];
        foreach (self::CATEGORIES as $category) {
            $row = $rows->get($category);
            $result[$category] = ['entries' => $row?->entries ?? [], 'revision' => (int) ($row?->revision ?? 0)];
        }

        return $result;
    }

    /** @return array<string, list<array{key: string, label: string, aliases: list<string>}>> */
    public function forPublic(int $institutionId): array
    {
        $lists = $this->forStaff($institutionId);
        $result = [];
        foreach (self::CATEGORIES as $category) {
            $result[$category] = array_map(static fn (array $entry): array => [
                'key' => hash('sha256', mb_strtolower($entry['label'])),
                'label' => $entry['label'],
                'aliases' => $entry['aliases'],
            ], $lists[$category]['entries']);
        }

        return $result;
    }
}
