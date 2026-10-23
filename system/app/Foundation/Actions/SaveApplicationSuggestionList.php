<?php

namespace App\Foundation\Actions;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Models\Foundation\ApplicationSuggestionList;
use App\Models\Foundation\Institution;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SaveApplicationSuggestionList
{
    public function __construct(
        private readonly CapabilityAuthorizer $authorizer,
        private readonly RecordAuditEvent $audit,
    ) {}

    /** @param array{category: string, expected_revision: int|string, entries: list<array{label: string, aliases?: list<string>}>} $attributes */
    public function handle(array $attributes, ?User $actor): ApplicationSuggestionList
    {
        $institution = Institution::current();
        if (! $this->authorized($actor, $institution)) {
            throw new AuthorizationException;
        }

        $category = $attributes['category'];
        $entries = $this->canonicalize($attributes['entries']);
        $payloadHash = hash('sha256', json_encode($entries, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($attributes, $actor, $institution, $category, $entries, $payloadHash): ApplicationSuggestionList {
            $lockedInstitution = Institution::query()->lockForUpdate()->findOrFail($institution->id);
            if (! $this->authorized($actor, $lockedInstitution)) {
                throw new AuthorizationException;
            }
            $list = ApplicationSuggestionList::query()
                ->where('institution_id', $lockedInstitution->id)->where('category', $category)
                ->lockForUpdate()->first();

            if ($list !== null && $list->entries === $entries) {
                return $list;
            }
            if ((int) ($list?->revision ?? 0) !== (int) $attributes['expected_revision']) {
                throw ValidationException::withMessages([
                    'expected_revision' => 'This suggestion list changed after you opened it. Reload the saved list and review your draft before saving again.',
                ]);
            }
            $list ??= new ApplicationSuggestionList(['institution_id' => $lockedInstitution->id, 'category' => $category]);
            $list->fill([
                'entries' => $entries,
                'revision' => $list->exists ? (int) $list->revision + 1 : 1,
                'updated_by_user_id' => $actor?->id,
            ])->save();
            $this->audit->handle('setup.application_suggestions_saved', [
                'institution_id' => (int) $lockedInstitution->id, 'category' => $category,
                'revision' => (int) $list->revision, 'count' => count($entries), 'hash' => $payloadHash,
            ], (string) ($actor?->id ?? 'system'));

            return $list;
        });
    }

    /** @param array<array{label: string, aliases?: list<string>}> $entries
     * @return list<array{label: string, aliases: list<string>}>
     */
    private function canonicalize(array $entries): array
    {
        $result = [];
        $labels = [];
        foreach ($entries as $entry) {
            $label = trim(preg_replace('/\s+/u', ' ', $entry['label']) ?? $entry['label']);
            if ($label === '') {
                continue;
            }
            $labelKey = mb_strtolower($label);
            if (! isset($labels[$labelKey])) {
                $labels[$labelKey] = count($result);
                $result[] = ['label' => $label, 'aliases' => []];
            }
            $index = $labels[$labelKey];
            $aliasKeys = array_fill_keys(array_map('mb_strtolower', $result[$index]['aliases']), true);
            $aliasKeys[$labelKey] = true;
            foreach ($entry['aliases'] ?? [] as $alias) {
                $alias = trim(preg_replace('/\s+/u', ' ', $alias) ?? $alias);
                $aliasKey = mb_strtolower($alias);
                if ($alias !== '' && ! isset($aliasKeys[$aliasKey])) {
                    $result[$index]['aliases'][] = $alias;
                    $aliasKeys[$aliasKey] = true;
                }
            }
            if (count($result[$index]['aliases']) > 20) {
                throw ValidationException::withMessages([
                    'entries' => 'A name can have at most 20 aliases after duplicate rows are combined. Remove some aliases and save again.',
                ]);
            }
        }

        return $result;
    }

    private function authorized(?User $actor, Institution $institution): bool
    {
        return $actor !== null && $this->authorizer->decideFresh($actor, CapabilityCatalog::REGISTRAR_REQUIREMENTS_MANAGE, [
            'institution_id' => (int) $institution->id,
            'office' => 'registrar',
        ])->allowed;
    }
}
