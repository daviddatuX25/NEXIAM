<?php

declare(strict_types=1);

namespace App\Ai\Knowledge\Actions;

use App\Ai\Knowledge\KnowledgeSourceAccess;
use App\Ai\Knowledge\PublicationDigest;
use App\Models\Ai\KnowledgeSource;
use App\Models\Ai\KnowledgeSourceConsensusSignature;
use App\Models\Ai\KnowledgeSourceVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final class SignKnowledgeSourceConsensus
{
    public function __construct(
        private readonly KnowledgeSourceAccess $access,
        private readonly PublicationDigest $digest,
    ) {}

    /** @param list<string> $participatingOffices */
    public function handle(
        User $actor,
        KnowledgeSource $source,
        KnowledgeSourceVersion $version,
        array $participatingOffices,
    ): KnowledgeSourceConsensusSignature {
        return DB::transaction(function () use ($actor, $source, $version, $participatingOffices): KnowledgeSourceConsensusSignature {
            $lockedSource = KnowledgeSource::query()->whereKey($source->getKey())->lockForUpdate()->firstOrFail();
            $lockedVersion = KnowledgeSourceVersion::query()
                ->whereKey($version->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            abort_unless($lockedVersion->knowledge_source_id === $lockedSource->getKey(), 404);

            $latestVersionId = $lockedSource->versions()->orderByDesc('version')->value('id');
            abort_unless($latestVersionId === $lockedVersion->getKey(), 404);

            $office = $actor->actorScope()->office;
            if (! in_array($office, $participatingOffices, true)) {
                throw new RuntimeException('consensus_office_not_participating');
            }
            if (! $this->access->isOfficeHead($actor, $office) || ! $this->access->canApproveCatalog($actor)) {
                throw new RuntimeException('consensus_signer_unauthorized');
            }
            if ($lockedVersion->created_by === (string) $actor->getKey()) {
                throw new RuntimeException('creator_cannot_sign');
            }

            $publicationDigest = $this->digest->forVersion($lockedVersion);

            $signature = $lockedVersion->consensusSignatures()->firstOrNew(['office' => $office]);
            if (! $signature->exists) {
                $signature->id = (string) Str::uuid();
            }
            $signature->forceFill([
                'signer_id' => (string) $actor->getKey(),
                'signed_digest' => $publicationDigest,
                'reviewed_version' => $lockedVersion->version,
                'signed_at' => now(),
            ])->save();

            return $signature;
        });
    }
}
