<?php

namespace App\Application\Capabilities\Handlers;

use App\Ai\Knowledge\KnowledgeCatalogQuery;
use App\Models\User;

final class KnowledgeSourcesListHandler
{
    public function __construct(private readonly KnowledgeCatalogQuery $catalog) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function handle(array $input = [], ?User $actor = null): array
    {
        if ($actor === null) {
            return ['sources' => [], 'page_state' => 'denied'];
        }

        return $this->catalog->handle($actor, [
            'search' => isset($input['search']) && is_string($input['search']) ? trim($input['search']) : null,
            'status' => isset($input['status']) && is_string($input['status']) ? $input['status'] : null,
        ]);
    }
}
