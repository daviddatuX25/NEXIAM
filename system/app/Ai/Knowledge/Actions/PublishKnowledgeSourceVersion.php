<?php

namespace App\Ai\Knowledge\Actions;

use App\Ai\Knowledge\KnowledgeLifecycle;
use App\Ai\Knowledge\KnowledgeSourceAccess;
use App\Ai\Knowledge\PublicationDigest;
use App\Models\Ai\KnowledgeSource;
use App\Models\Ai\KnowledgeSourceApproval;
use App\Models\Ai\KnowledgeSourceConsensusSignature;
use App\Models\Ai\KnowledgeSourceVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class PublishKnowledgeSourceVersion
{
    private const REQUIRED_OFFICES = ['guidance', 'registrar'];

    public function __construct(
        private readonly KnowledgeLifecycle $lifecycle,
        private readonly KnowledgeSourceAccess $access,
        private readonly PublicationDigest $digest,
    ) {}

    public function handle(User $actor, KnowledgeSource $source, KnowledgeSourceVersion $version): void
    {
        $versionToPublish = DB::transaction(function () use ($actor, $source, $version): KnowledgeSourceVersion {
            $lockedSource = KnowledgeSource::query()->whereKey($source->getKey())->lockForUpdate()->firstOrFail();
            $lockedVersion = KnowledgeSourceVersion::query()
                ->whereKey($version->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            abort_unless($lockedVersion->knowledge_source_id === $lockedSource->getKey(), 404);
            $this->assertLatestVersion($lockedSource, $lockedVersion);
            $this->access->requireApproveSource($actor, $lockedSource);

            if (! in_array($lockedVersion->status, [
                KnowledgeSourceVersion::STATUS_APPROVED,
                KnowledgeSourceVersion::STATUS_INDEX_FAILED,
            ], true)) {
                throw new RuntimeException('illegal_transition:'.$lockedVersion->status);
            }

            $currentDigest = $this->digest->forVersion($lockedVersion);
            if ($lockedVersion->publication_digest !== $currentDigest) {
                throw new RuntimeException('digest_invalidated');
            }

            if ($lockedSource->audience === KnowledgeSource::AUDIENCE_CROSS_OFFICE_STAFF) {
                $approvals = KnowledgeSourceApproval::query()
                    ->where('knowledge_source_version_id', $lockedVersion->getKey())
                    ->get()
                    ->keyBy('office');
                if ($approvals->keys()->sort()->values()->all() !== self::REQUIRED_OFFICES
                    || $approvals->contains(fn (KnowledgeSourceApproval $approval): bool => $approval->publication_digest !== $currentDigest
                        || $approval->approver_id === $lockedVersion->created_by)) {
                    throw new RuntimeException('cross_office_approvals_required');
                }
            }

            $this->assertConsensusSignatures($lockedVersion, $currentDigest);

            return $lockedVersion;
        });

        $this->lifecycle->publish($actor, $versionToPublish);
    }

    private function assertConsensusSignatures(KnowledgeSourceVersion $version, string $currentDigest): void
    {
        $signatures = $version->consensusSignatures()->get();
        if ($signatures->isEmpty()) {
            return;
        }

        $signedOffices = $signatures->pluck('office')->sort()->values()->all();
        $supportedParticipantSets = [
            ['admin', 'registrar'],
            ['guidance', 'registrar'],
        ];
        if (! in_array($signedOffices, $supportedParticipantSets, true)) {
            throw new RuntimeException('consensus_signatures_required');
        }

        if ($signatures->contains(fn (KnowledgeSourceConsensusSignature $signature): bool => $signature->signed_digest !== $currentDigest
            || (int) $signature->reviewed_version !== (int) $version->version
            || (string) $signature->signer_id === (string) $version->created_by)) {
            throw new RuntimeException('consensus_signatures_invalidated');
        }
    }

    private function assertLatestVersion(KnowledgeSource $source, KnowledgeSourceVersion $version): void
    {
        $latestId = $source->versions()->orderByDesc('version')->value('id');
        abort_unless($latestId === $version->getKey(), 404);
    }
}
