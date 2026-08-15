<?php

namespace App\Application\Capabilities\Handlers;

use App\Ai\Knowledge\KnowledgeLifecycle;
use App\Jobs\Ai\ReconcileKnowledgeIndexJob;
use App\Models\Ai\KnowledgeSourceVersion;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

final class KnowledgeSourceRevokeHandler
{
    public function __construct(private readonly KnowledgeLifecycle $lifecycle) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function handle(array $input = []): array
    {
        $versionId = (string) ($input['version_id'] ?? '');
        $reason = (string) ($input['reason'] ?? 'revoked');
        $version = KnowledgeSourceVersion::query()->find($versionId);
        if ($version === null) {
            throw new RuntimeException('version_not_found');
        }

        $actor = Auth::user();
        if (! $actor instanceof User) {
            throw new RuntimeException('unauthenticated');
        }

        $this->lifecycle->revoke($actor, $version, $reason);

        if (app()->environment('testing') || (bool) config('nexi.process_inline', false)) {
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
