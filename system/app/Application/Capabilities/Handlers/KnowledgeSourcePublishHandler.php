<?php

namespace App\Application\Capabilities\Handlers;

use App\Ai\Knowledge\Actions\PublishKnowledgeSourceVersion;
use App\Models\Ai\KnowledgeSource;
use App\Models\Ai\KnowledgeSourceVersion;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

final class KnowledgeSourcePublishHandler
{
    public function __construct(private readonly PublishKnowledgeSourceVersion $publish) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function handle(array $input = [], ?User $actor = null): array
    {
        $versionId = (string) ($input['version_id'] ?? '');
        $version = KnowledgeSourceVersion::query()->find($versionId);
        if ($version === null) {
            throw new RuntimeException('version_not_found');
        }
        $source = $version->source;
        if (! $source instanceof KnowledgeSource) {
            throw new RuntimeException('source_not_found');
        }

        $resolvedActor = $actor ?? Auth::user();
        if (! $resolvedActor instanceof User) {
            throw new RuntimeException('unauthenticated');
        }

        $this->publish->handle($resolvedActor, $source, $version);

        return [
            'version_id' => $version->id,
            'status' => $version->fresh()?->status,
        ];
    }
}
