<?php

namespace App\Application\Capabilities\Handlers;

use App\Ai\Knowledge\KnowledgeLifecycle;
use App\Models\Ai\KnowledgeSourceVersion;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

final class KnowledgeSourcePublishHandler
{
    public function __construct(private readonly KnowledgeLifecycle $lifecycle) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function handle(array $input = []): array
    {
        $versionId = (string) ($input['version_id'] ?? '');
        $version = KnowledgeSourceVersion::query()->find($versionId);
        if ($version === null) {
            throw new RuntimeException('version_not_found');
        }

        $actor = Auth::user();
        if (! $actor instanceof User) {
            throw new RuntimeException('unauthenticated');
        }

        $this->lifecycle->publish($actor, $version);

        return [
            'version_id' => $version->id,
            'status' => $version->fresh()?->status,
        ];
    }
}
