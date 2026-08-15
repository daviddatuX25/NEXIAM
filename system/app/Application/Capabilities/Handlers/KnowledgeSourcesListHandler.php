<?php

namespace App\Application\Capabilities\Handlers;

use App\Models\Ai\KnowledgeSource;
use App\Models\Ai\KnowledgeSourceVersion;

final class KnowledgeSourcesListHandler
{
    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function handle(array $input = []): array
    {
        $status = isset($input['status']) && is_string($input['status']) ? $input['status'] : null;
        $search = isset($input['search']) && is_string($input['search']) ? trim($input['search']) : null;

        $sources = KnowledgeSource::query()
            ->with(['versions' => fn ($q) => $q->orderByDesc('version')->limit(1)])
            ->orderByDesc('updated_at')
            ->limit(100)
            ->get()
            ->map(function (KnowledgeSource $source) use ($status, $search): ?array {
                /** @var KnowledgeSourceVersion|null $latest */
                $latest = $source->versions->first();
                if ($latest === null) {
                    return null;
                }
                if ($status !== null && $status !== '' && $latest->status !== $status) {
                    return null;
                }
                if ($search !== null && $search !== '') {
                    $hay = mb_strtolower($source->title.' '.$source->key.' '.$latest->title);
                    if (! str_contains($hay, mb_strtolower($search))) {
                        return null;
                    }
                }

                return [
                    'id' => $source->id,
                    'key' => $source->key,
                    'title' => $source->title,
                    'classification' => $source->classification,
                    'latest_version' => [
                        'id' => $latest->id,
                        'version' => $latest->version,
                        'status' => $latest->status,
                        'title' => $latest->title,
                        'updated_at' => optional($latest->updated_at)?->toIso8601String(),
                        'published_at' => optional($latest->published_at)?->toIso8601String(),
                    ],
                ];
            })
            ->filter()
            ->values()
            ->all();

        return [
            'sources' => $sources,
            'page_state' => $sources === [] ? 'empty' : 'ready',
        ];
    }
}
