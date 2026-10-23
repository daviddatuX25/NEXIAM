<?php

namespace App\Ai\Knowledge\Actions;

use App\Ai\Knowledge\KnowledgeLifecycle;
use App\Ai\Knowledge\KnowledgeSourceAccess;
use App\Ai\Knowledge\PublicationDigest;
use App\Models\Ai\KnowledgeSource;
use App\Models\Ai\KnowledgeSourceApproval;
use App\Models\Ai\KnowledgeSourceVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final class ApproveKnowledgeSourceVersion
{
    private const REQUIRED_OFFICES = ['guidance', 'registrar'];

    public function __construct(
        private readonly KnowledgeLifecycle $lifecycle,
        private readonly KnowledgeSourceAccess $access,
        private readonly PublicationDigest $digest,
    ) {}

    /** Returns true when this action completes all required approval evidence. */
    public function handle(User $actor, KnowledgeSource $source, KnowledgeSourceVersion $version): bool
    {
        return DB::transaction(function () use ($actor, $source, $version): bool {
            $lockedSource = KnowledgeSource::query()->whereKey($source->getKey())->lockForUpdate()->firstOrFail();
            $lockedVersion = KnowledgeSourceVersion::query()
                ->whereKey($version->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            abort_unless($lockedVersion->knowledge_source_id === $lockedSource->getKey(), 404);
            $this->assertLatestVersion($lockedSource, $lockedVersion);
            $this->access->requireApproveSource($actor, $lockedSource);

            if ($lockedVersion->created_by === (string) $actor->getKey()) {
                throw new RuntimeException('creator_cannot_approve');
            }
            if ($lockedVersion->status !== KnowledgeSourceVersion::STATUS_REVIEW_REQUIRED) {
                throw new RuntimeException('illegal_transition:'.$lockedVersion->status);
            }

            if ($lockedSource->audience !== KnowledgeSource::AUDIENCE_CROSS_OFFICE_STAFF) {
                $this->lifecycle->approve($actor, $lockedVersion);

                return true;
            }

            $office = $actor->actorScope()->office;
            if (! in_array($office, self::REQUIRED_OFFICES, true)) {
                throw new RuntimeException('cross_office_approval_office_invalid');
            }
            if (KnowledgeSourceApproval::query()
                ->where('knowledge_source_version_id', $lockedVersion->getKey())
                ->where('office', $office)
                ->exists()) {
                throw new RuntimeException('office_already_approved_version');
            }

            $publicationDigest = $this->digest->forVersion($lockedVersion);
            KnowledgeSourceApproval::query()->create([
                'id' => (string) Str::uuid(),
                'knowledge_source_version_id' => $lockedVersion->getKey(),
                'office' => $office,
                'approver_id' => (string) $actor->getKey(),
                'approved_at' => now(),
                'publication_digest' => $publicationDigest,
            ]);

            $approvals = KnowledgeSourceApproval::query()
                ->where('knowledge_source_version_id', $lockedVersion->getKey())
                ->get()
                ->keyBy('office');
            if ($approvals->keys()->sort()->values()->all() !== self::REQUIRED_OFFICES) {
                return false;
            }
            if ($approvals->contains(fn (KnowledgeSourceApproval $approval): bool => $approval->publication_digest !== $publicationDigest
                || $approval->approver_id === $lockedVersion->created_by)) {
                throw new RuntimeException('cross_office_approval_digest_invalid');
            }

            $lockedVersion->forceFill([
                'status' => KnowledgeSourceVersion::STATUS_APPROVED,
                'approved_by' => (string) $actor->getKey(),
                'approved_at' => now(),
                'publication_digest' => $publicationDigest,
            ])->save();

            return true;
        });
    }

    private function assertLatestVersion(KnowledgeSource $source, KnowledgeSourceVersion $version): void
    {
        $latestId = $source->versions()->orderByDesc('version')->value('id');
        abort_unless($latestId === $version->getKey(), 404);
    }
}
