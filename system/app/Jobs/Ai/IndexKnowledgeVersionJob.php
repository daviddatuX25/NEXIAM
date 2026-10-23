<?php

namespace App\Jobs\Ai;

use App\Ai\Contracts\KnowledgeIndexPort;
use App\Ai\Knowledge\KnowledgeLifecycle;
use App\Models\Ai\KnowledgeSource;
use App\Models\Ai\KnowledgeSourceVersion;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class IndexKnowledgeVersionJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $versionId) {}

    public function handle(KnowledgeIndexPort $index, KnowledgeLifecycle $lifecycle): void
    {
        try {
            DB::transaction(function () use ($index, $lifecycle): void {
                $candidate = KnowledgeSourceVersion::query()->find($this->versionId);
                if ($candidate === null) {
                    return;
                }

                $source = KnowledgeSource::query()
                    ->whereKey($candidate->knowledge_source_id)
                    ->lockForUpdate()
                    ->firstOrFail();
                $version = KnowledgeSourceVersion::query()
                    ->whereKey($candidate->getKey())
                    ->lockForUpdate()
                    ->first();
                if ($version === null
                    || $version->knowledge_source_id !== $source->getKey()
                    || $version->status !== KnowledgeSourceVersion::STATUS_INDEXING) {
                    return;
                }

                $version->setRelation('source', $source);
                if ($source->source_class === KnowledgeSource::SOURCE_CLASS_PUBLIC_FAQ
                    && is_string($version->question)
                    && is_string($version->answer)
                    && trim($version->question) !== ''
                    && trim($version->answer) !== '') {
                    $body = trim($version->question."\n".$version->answer);
                } else {
                    // Title must be searchable — staff often ask by program/policy name.
                    $body = (string) ($version->extraction_text ?: $version->body_text);
                }
                $text = trim($version->title."\n".$body);
                $index->upsert([
                    'version_id' => $version->id,
                    'source_id' => $version->knowledge_source_id,
                    'text' => $text,
                    'meta' => [
                        'title' => $version->title,
                        'digest' => $version->publication_digest,
                    ],
                ]);
                $lifecycle->markActive($version);
            });
        } catch (\Throwable) {
            DB::transaction(function (): void {
                $candidate = KnowledgeSourceVersion::query()->find($this->versionId);
                if ($candidate === null) {
                    return;
                }

                $source = KnowledgeSource::query()
                    ->whereKey($candidate->knowledge_source_id)
                    ->lockForUpdate()
                    ->first();
                $version = KnowledgeSourceVersion::query()
                    ->whereKey($candidate->getKey())
                    ->lockForUpdate()
                    ->first();
                if ($source === null
                    || $version === null
                    || $version->knowledge_source_id !== $source->getKey()
                    || $version->status !== KnowledgeSourceVersion::STATUS_INDEXING) {
                    return;
                }

                $version->forceFill([
                    'status' => KnowledgeSourceVersion::STATUS_INDEX_FAILED,
                    'failure_reason' => 'index_failed',
                ])->save();
            });
        }
    }
}
