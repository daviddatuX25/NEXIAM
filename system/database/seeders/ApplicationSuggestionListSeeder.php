<?php

namespace Database\Seeders;

use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Foundation\ApplicationSuggestionList;
use App\Models\Foundation\AuditEvent;
use App\Models\Foundation\Institution;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ApplicationSuggestionListSeeder extends Seeder
{
    public function run(): void
    {
        $reference = json_decode(
            file_get_contents(database_path('data/application-suggestions.json')),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        $entriesByCategory = [];
        foreach ($reference['lists'] as $category => $records) {
            foreach ($reference['metadata']['supplements'][$category] ?? [] as $filename) {
                $supplement = json_decode(
                    file_get_contents(database_path('data/'.$filename)),
                    true,
                    flags: JSON_THROW_ON_ERROR,
                );
                array_push($records, ...$supplement['records']);
            }
            $entriesByCategory[$category] = $this->entriesFor($records);
        }
        $institution = Institution::current();

        DB::transaction(function () use ($institution, $reference, $entriesByCategory): void {
            Institution::query()->whereKey($institution->getKey())->lockForUpdate()->firstOrFail();

            foreach ($entriesByCategory as $category => $entries) {
                $list = ApplicationSuggestionList::query()
                    ->where('institution_id', $institution->getKey())
                    ->where('category', $category)
                    ->lockForUpdate()
                    ->first();
                if ($list === null) {
                    ApplicationSuggestionList::query()->create([
                        'institution_id' => $institution->getKey(),
                        'category' => $category,
                        'entries' => $entries,
                        'revision' => 1,
                    ]);

                    continue;
                }
                if ($list->entries === $entries) {
                    continue;
                }
                $previousHash = hash('sha256', json_encode($list->entries, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
                $registeredBaseline = false;
                foreach ($reference['metadata']['previousSeeds'][$category] ?? [] as $baseline) {
                    if ($list->revision === $baseline['revision'] && hash_equals($baseline['hash'], $previousHash)) {
                        $registeredBaseline = true;
                        break;
                    }
                }
                if ($list->updated_by_user_id !== null || $list->entries === [] || ! $registeredBaseline) {
                    continue;
                }
                if ($list->revision > 1 && ! AuditEvent::query()
                    ->where('event_type', 'setup.application_suggestions_saved')
                    ->where('payload->institution_id', (int) $institution->getKey())
                    ->where('payload->category', $category)
                    ->where('payload->revision', $list->revision)
                    ->where('payload->hash', $previousHash)
                    ->where('payload->source', 'reviewed_seed')
                    ->exists()) {
                    continue;
                }

                $previousRevision = $list->revision;
                $list->update(['entries' => $entries, 'revision' => $list->revision + 1]);
                app(RecordAuditEvent::class)->handle('setup.application_suggestions_saved', [
                    'institution_id' => (int) $institution->getKey(),
                    'category' => $category,
                    'previous_revision' => $previousRevision,
                    'revision' => (int) $list->revision,
                    'count' => count($entries),
                    'hash' => hash('sha256', json_encode($entries, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)),
                    'previous_hash' => $previousHash,
                    'source' => 'reviewed_seed',
                    'source_version' => $reference['metadata']['catalogVersion'],
                ], 'system');
            }
        });
    }

    /**
     * @param  list<array{label: string, aliases: list<string>, category: string}>  $records
     * @return list<array{label: string, aliases: list<string>}>
     */
    private function entriesFor(array $records): array
    {
        $entries = [];
        $positions = [];
        foreach ($records as $record) {
            $suffix = match ($record['category']) {
                'legacy-shs-strand' => ' (legacy SHS strand)',
                'current-sshs-elective-cluster' => ' (Strengthened SHS elective cluster)',
                default => '',
            };
            $label = trim(preg_replace('/\s+/u', ' ', $record['label'].$suffix));
            $labelKey = mb_strtolower($label);
            if (! isset($positions[$labelKey])) {
                $positions[$labelKey] = count($entries);
                $entries[] = ['label' => $label, 'aliases' => []];
            }
            $index = $positions[$labelKey];
            $knownAliases = array_fill_keys(array_map('mb_strtolower', $entries[$index]['aliases']), true);
            $knownAliases[$labelKey] = true;
            foreach ([...$record['aliases'], ...($suffix !== '' ? [$record['label']] : [])] as $alias) {
                $alias = trim(preg_replace('/\s+/u', ' ', $alias));
                $aliasKey = mb_strtolower($alias);
                if ($alias !== '' && ! isset($knownAliases[$aliasKey])) {
                    $entries[$index]['aliases'][] = $alias;
                    $knownAliases[$aliasKey] = true;
                }
            }
        }

        return $entries;
    }
}
