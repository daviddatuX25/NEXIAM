<?php

namespace App\Ai\Knowledge\Actions;

use App\Ai\Knowledge\KnowledgeLifecycle;
use App\Ai\Knowledge\KnowledgeSourceAccess;
use App\Models\Ai\KnowledgeSource;
use App\Models\Ai\KnowledgeSourceVersion;
use App\Models\User;
use RuntimeException;

final class StartKnowledgeExtraction
{
    public function __construct(
        private readonly KnowledgeLifecycle $lifecycle,
        private readonly KnowledgeSourceAccess $access,
    ) {}

    public function handle(User $actor, KnowledgeSource $source, KnowledgeSourceVersion $version): void
    {
        $this->access->requireManageSource($actor, $source);
        abort_unless($version->knowledge_source_id === $source->getKey(), 404);
        $this->assertLatestVersion($source, $version);
        if ($version->approved_by !== null || $version->publication_digest !== null) {
            throw new RuntimeException('approved_version_must_be_republished');
        }
        $this->lifecycle->startExtraction($version);
    }

    private function assertLatestVersion(KnowledgeSource $source, KnowledgeSourceVersion $version): void
    {
        $latestId = $source->versions()->orderByDesc('version')->value('id');
        abort_unless($latestId === $version->getKey(), 404);
    }
}
