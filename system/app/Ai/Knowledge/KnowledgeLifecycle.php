<?php

namespace App\Ai\Knowledge;

use App\Ai\Contracts\KnowledgeIndexPort;
use App\Jobs\Ai\ExtractKnowledgeVersionJob;
use App\Jobs\Ai\IndexKnowledgeVersionJob;
use App\Models\Ai\KnowledgeCitationFixture;
use App\Models\Ai\KnowledgeSource;
use App\Models\Ai\KnowledgeSourceVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

final class KnowledgeLifecycle
{
    public function __construct(
        private readonly PublicationDigest $digest,
        private readonly KnowledgeIndexPort $index,
    ) {}

    /**
     * @param  array{title: string, body_text: string, key?: string}  $input
     */
    public function createDraft(User $actor, array $input): KnowledgeSourceVersion
    {
        return DB::transaction(function () use ($actor, $input): KnowledgeSourceVersion {
            $key = $input['key'] ?? Str::slug($input['title']).'-'.Str::lower(Str::random(4));
            $source = KnowledgeSource::query()->create([
                'id' => (string) Str::uuid(),
                'key' => $key,
                'title' => $input['title'],
                'source_class' => 'institutional_policy',
                'audience' => 'staff_and_applicant_inquiry',
                'purpose' => 'policy_inquiry',
                'classification' => 'public_institutional',
                'institution_id' => $actor->actorScope()->institutionId,
                'owner_user_id' => (string) $actor->id,
            ]);

            $body = trim($input['body_text']);
            $checksum = hash('sha256', $body);
            $path = 'knowledge/quarantine/'.$source->id.'/v1.txt';
            Storage::disk('local')->put($path, $body);

            $version = KnowledgeSourceVersion::query()->create([
                'id' => (string) Str::uuid(),
                'knowledge_source_id' => $source->id,
                'version' => 1,
                'status' => KnowledgeSourceVersion::STATUS_DRAFT,
                'title' => $input['title'],
                'body_text' => $body,
                'content_checksum' => $checksum,
                'quarantine_path' => $path,
                'created_by' => (string) $actor->id,
            ]);

            KnowledgeCitationFixture::query()->create([
                'id' => (string) Str::uuid(),
                'knowledge_source_version_id' => $version->id,
                'kind' => 'citation',
                'query_text' => 'policy',
                'expected_excerpt' => mb_substr($body, 0, 80),
                'expected_outcome' => 'cite',
            ]);
            KnowledgeCitationFixture::query()->create([
                'id' => (string) Str::uuid(),
                'knowledge_source_version_id' => $version->id,
                'kind' => 'refusal',
                'query_text' => 'unrelated personal grades',
                'expected_excerpt' => null,
                'expected_outcome' => 'refuse',
            ]);

            return $version->fresh(['source', 'fixtures']) ?? $version;
        });
    }

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

        if (app()->environment('testing') || (bool) config('nexi.process_inline', false)) {
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
            'approved_by' => (string) $actor->id,
            'approved_at' => now(),
            'publication_digest' => $digest,
        ])->save();
    }

    public function publish(User $actor, KnowledgeSourceVersion $version): void
    {
        unset($actor);
        $this->assertStatus($version, [
            KnowledgeSourceVersion::STATUS_APPROVED,
            KnowledgeSourceVersion::STATUS_INDEX_FAILED,
        ]);

        if ($version->publication_digest !== $this->digest->forVersion($version)) {
            throw new RuntimeException('digest_invalidated');
        }

        $version->forceFill(['status' => KnowledgeSourceVersion::STATUS_INDEXING])->save();

        if (app()->environment('testing') || (bool) config('nexi.process_inline', false)) {
            IndexKnowledgeVersionJob::dispatchSync($version->id);

            return;
        }

        IndexKnowledgeVersionJob::dispatch($version->id);
    }

    public function markActive(KnowledgeSourceVersion $version): void
    {
        DB::transaction(function () use ($version): void {
            KnowledgeSourceVersion::query()
                ->where('knowledge_source_id', $version->knowledge_source_id)
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
