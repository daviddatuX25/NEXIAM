<?php

namespace App\Ai\Knowledge\Actions;

use App\Ai\Knowledge\KnowledgeSourceAccess;
use App\Models\Ai\KnowledgeSource;
use App\Models\Ai\KnowledgeSourceVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class EstablishCrossOfficeKnowledgeScope
{
    public function __construct(private readonly KnowledgeSourceAccess $access) {}

    public function handle(User $actor, KnowledgeSource $source, KnowledgeSourceVersion $version): void
    {
        DB::transaction(function () use ($actor, $source, $version): void {
            $lockedSource = KnowledgeSource::query()->whereKey($source->getKey())->lockForUpdate()->firstOrFail();
            $lockedVersion = KnowledgeSourceVersion::query()
                ->whereKey($version->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            abort_unless($lockedVersion->knowledge_source_id === $lockedSource->getKey(), 404);
            $this->assertLatestVersion($lockedSource, $lockedVersion);
            $this->access->requireEstablishCrossOffice($actor, $lockedSource);

            if ($lockedSource->audience === KnowledgeSource::AUDIENCE_CROSS_OFFICE_STAFF) {
                throw new RuntimeException('cross_office_scope_already_established');
            }
            if ($lockedVersion->status !== KnowledgeSourceVersion::STATUS_REVIEW_REQUIRED) {
                throw new RuntimeException('cross_office_scope_requires_review');
            }
            if ($lockedVersion->created_by === (string) $actor->getKey()) {
                throw new RuntimeException('creator_cannot_establish_cross_office_scope');
            }

            $lockedSource->forceFill([
                'audience' => KnowledgeSource::AUDIENCE_CROSS_OFFICE_STAFF,
                'classification' => KnowledgeSource::CLASSIFICATION_OFFICE_INTERNAL,
            ])->save();
        });
    }

    private function assertLatestVersion(KnowledgeSource $source, KnowledgeSourceVersion $version): void
    {
        $latestId = $source->versions()->orderByDesc('version')->value('id');
        abort_unless($latestId === $version->getKey(), 404);
    }
}
