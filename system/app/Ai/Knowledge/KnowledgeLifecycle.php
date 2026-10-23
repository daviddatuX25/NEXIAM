<?php

namespace App\Ai\Knowledge;

use App\Ai\Contracts\KnowledgeIndexPort;
use App\Jobs\Ai\ExtractKnowledgeVersionJob;
use App\Jobs\Ai\IndexKnowledgeVersionJob;
use App\Models\Ai\KnowledgeSource;
use App\Models\Ai\KnowledgeSourceVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class KnowledgeLifecycle
{
    public function __construct(
        private readonly PublicationDigest $digest,
        private readonly KnowledgeIndexPort $index,
    ) {}

    public function startExtraction(KnowledgeSourceVersion $version): void
    {
        $this->assertStatus($version, [
            KnowledgeSourceVersion::STATUS_DRAFT,
            KnowledgeSourceVersion::STATUS_INDEX_FAILED,
            KnowledgeSourceVersion::STATUS_REJECTED,
        ]);
        $version->forceFill([
            'status' => KnowledgeSourceVersion::STATUS_EXTRACTING,
            'failure_reason' => null,
        ])->save();

        if (app()->environment('testing') || (bool) config('xiam.process_inline', false)) {
            ExtractKnowledgeVersionJob::dispatchSync($version->id);

            return;
        }

        ExtractKnowledgeVersionJob::dispatch($version->id);
    }

    public function markReviewRequired(KnowledgeSourceVersion $version, string $extractionText): void
    {
        $version->forceFill([
            'status' => KnowledgeSourceVersion::STATUS_REVIEW_REQUIRED,
            'extraction_text' => $extractionText,
            'extraction_digest' => hash('sha256', $extractionText),
        ])->save();
    }

    public function approve(User $actor, KnowledgeSourceVersion $version): void
    {
        DB::transaction(function () use ($actor, $version): void {
            $version = KnowledgeSourceVersion::query()
                ->whereKey($version->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($version->created_by === (string) $actor->getKey()) {
                throw new RuntimeException('creator_cannot_approve');
            }

            $this->assertStatus($version, [KnowledgeSourceVersion::STATUS_REVIEW_REQUIRED]);

            $version->load('fixtures');
            $hasCite = $version->fixtures->contains(fn ($f) => $f->kind === 'citation');
            $hasRefuse = $version->fixtures->contains(fn ($f) => $f->kind === 'refusal');
            if (! $hasCite || ! $hasRefuse) {
                throw new RuntimeException('fixtures_required');
            }

            $digest = $this->digest->forVersion($version);
            $version->forceFill([
                'status' => KnowledgeSourceVersion::STATUS_APPROVED,
                'approved_by' => (string) $actor->getKey(),
                'approved_at' => now(),
                'publication_digest' => $digest,
            ])->save();
        });
    }

    public function publish(User $actor, KnowledgeSourceVersion $version): void
    {
        unset($actor);

        $version = DB::transaction(function () use ($version): KnowledgeSourceVersion {
            $version = KnowledgeSourceVersion::query()
                ->whereKey($version->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertStatus($version, [
                KnowledgeSourceVersion::STATUS_APPROVED,
                KnowledgeSourceVersion::STATUS_INDEX_FAILED,
            ]);

            if ($version->approved_by === null || $version->approved_by === '') {
                throw new RuntimeException('approver_required');
            }

            if ($version->approved_by === $version->created_by) {
                throw new RuntimeException('creator_cannot_approve');
            }

            if ($version->publication_digest !== $this->digest->forVersion($version)) {
                throw new RuntimeException('digest_invalidated');
            }

            $version->forceFill(['status' => KnowledgeSourceVersion::STATUS_INDEXING])->save();

            return $version;
        });

        if (app()->environment('testing') || (bool) config('xiam.process_inline', false)) {
            IndexKnowledgeVersionJob::dispatchSync($version->id);

            return;
        }

        IndexKnowledgeVersionJob::dispatch($version->id);
    }

    public function markActive(KnowledgeSourceVersion $version): void
    {
        DB::transaction(function () use ($version): void {
            $source = KnowledgeSource::query()
                ->whereKey($version->knowledge_source_id)
                ->lockForUpdate()
                ->firstOrFail();
            $version = KnowledgeSourceVersion::query()
                ->whereKey($version->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            abort_unless($version->knowledge_source_id === $source->getKey(), 404);
            $this->assertStatus($version, [KnowledgeSourceVersion::STATUS_INDEXING]);

            KnowledgeSourceVersion::query()
                ->where('knowledge_source_id', $source->getKey())
                ->where('status', KnowledgeSourceVersion::STATUS_ACTIVE)
                ->where('id', '!=', $version->id)
                ->each(function (KnowledgeSourceVersion $prior): void {
                    $prior->forceFill(['status' => KnowledgeSourceVersion::STATUS_SUPERSEDED])->save();
                    $this->index->remove($prior->id);
                });

            $version->forceFill([
                'status' => KnowledgeSourceVersion::STATUS_ACTIVE,
                'published_at' => now(),
                'failure_reason' => null,
            ])->save();
        });
    }

    public function revoke(User $actor, KnowledgeSourceVersion $version, string $reason = 'revoked'): void
    {
        DB::transaction(function () use ($actor, $version, $reason): void {
            $source = KnowledgeSource::query()
                ->whereKey($version->knowledge_source_id)
                ->lockForUpdate()
                ->firstOrFail();
            $version = KnowledgeSourceVersion::query()
                ->whereKey($version->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            abort_unless($version->knowledge_source_id === $source->getKey(), 404);
            $this->assertStatus($version, [
                KnowledgeSourceVersion::STATUS_ACTIVE,
                KnowledgeSourceVersion::STATUS_APPROVED,
                KnowledgeSourceVersion::STATUS_INDEXING,
            ]);

            $version->forceFill([
                'status' => KnowledgeSourceVersion::STATUS_REVOKED,
                'revoked_at' => now(),
                'revoke_reason' => $reason,
                'approved_by' => $version->approved_by ?? (string) $actor->id,
            ])->save();

            // Synchronous retrieval denial before async index cleanup reconcile.
            $this->index->remove($version->id);
        });
    }

    /**
     * @param  list<string>  $allowed
     */
    private function assertStatus(KnowledgeSourceVersion $version, array $allowed): void
    {
        if (! in_array($version->status, $allowed, true)) {
            throw new RuntimeException('illegal_transition:'.$version->status);
        }
    }
}
