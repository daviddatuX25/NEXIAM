<?php

namespace App\Ai\Knowledge\Actions;

use App\Ai\Knowledge\KnowledgeLifecycle;
use App\Ai\Knowledge\KnowledgeSourceAccess;
use App\Models\Ai\KnowledgeSource;
use App\Models\Ai\KnowledgeSourceVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class RevokeKnowledgeSourceVersion
{
    public function __construct(
        private readonly KnowledgeLifecycle $lifecycle,
        private readonly KnowledgeSourceAccess $access,
    ) {}

    public function handle(User $actor, KnowledgeSource $source, KnowledgeSourceVersion $version, string $reason = 'revoked'): void
    {
        $reason = trim($reason);
        if ($reason === '') {
            $reason = 'revoked';
        }
        if (mb_strlen($reason) > 255) {
            throw new InvalidArgumentException('revoke_reason_too_long');
        }

        DB::transaction(function () use ($actor, $source, $version, $reason): void {
            $lockedSource = KnowledgeSource::query()->whereKey($source->getKey())->lockForUpdate()->firstOrFail();
            $lockedVersion = KnowledgeSourceVersion::query()
                ->whereKey($version->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            abort_unless($lockedVersion->knowledge_source_id === $lockedSource->getKey(), 404);
            $this->assertLatestVersion($lockedSource, $lockedVersion);
            $this->access->requireApproveSource($actor, $lockedSource);

            $this->lifecycle->revoke($actor, $lockedVersion, $reason);
        });
    }

    private function assertLatestVersion(KnowledgeSource $source, KnowledgeSourceVersion $version): void
    {
        $latestId = $source->versions()->orderByDesc('version')->value('id');
        abort_unless($latestId === $version->getKey(), 404);
    }
}
