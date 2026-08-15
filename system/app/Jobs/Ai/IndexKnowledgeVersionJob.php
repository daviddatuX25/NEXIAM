<?php

namespace App\Jobs\Ai;

use App\Ai\Contracts\KnowledgeIndexPort;
use App\Ai\Knowledge\KnowledgeLifecycle;
use App\Models\Ai\KnowledgeSourceVersion;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class IndexKnowledgeVersionJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $versionId) {}

    public function handle(KnowledgeIndexPort $index, KnowledgeLifecycle $lifecycle): void
    {
        $version = KnowledgeSourceVersion::query()->find($this->versionId);
        if ($version === null) {
            return;
        }

        try {
            // Title must be searchable — staff often ask by program/policy name.
            $body = (string) ($version->extraction_text ?: $version->body_text);
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
            $lifecycle->markActive($version->fresh() ?? $version);
        } catch (\Throwable) {
            $version->forceFill([
                'status' => KnowledgeSourceVersion::STATUS_INDEX_FAILED,
                'failure_reason' => 'index_failed',
            ])->save();
        }
    }
}
