<?php

namespace App\Application\Capabilities\Handlers;

use App\Ai\Knowledge\Actions\RevokeKnowledgeSourceVersion;
use App\Jobs\Ai\ReconcileKnowledgeIndexJob;
use App\Models\Ai\KnowledgeSource;
use App\Models\Ai\KnowledgeSourceVersion;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

final class KnowledgeSourceRevokeHandler
{
    public function __construct(private readonly RevokeKnowledgeSourceVersion $revoke) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function handle(array $input = [], ?User $actor = null): array
    {
        $versionId = (string) ($input['version_id'] ?? '');
        $reason = (string) ($input['reason'] ?? 'revoked');
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

        $this->revoke->handle($resolvedActor, $source, $version, $reason);
        if (app()->environment('testing') || (bool) config('xiam.process_inline', false)) {
            ReconcileKnowledgeIndexJob::dispatchSync();
        } else {
            ReconcileKnowledgeIndexJob::dispatch();
        }

        return [
            'version_id' => $version->id,
            'status' => KnowledgeSourceVersion::STATUS_REVOKED,
        ];
    }
}
