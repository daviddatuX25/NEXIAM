<?php

namespace App\Jobs\Ai;

use App\Ai\Knowledge\KnowledgeLifecycle;
use App\Models\Ai\KnowledgeSourceVersion;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

/**
 * Fake safe parser — no network, strips control chars (AD-25 quarantine pass).
 */
class ExtractKnowledgeVersionJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $versionId) {}

    public function handle(KnowledgeLifecycle $lifecycle): void
    {
        $version = KnowledgeSourceVersion::query()->find($this->versionId);
        if ($version === null) {
            return;
        }

        try {
            $raw = (string) $version->body_text;
            if ($version->quarantine_path && Storage::disk('local')->exists($version->quarantine_path)) {
                $raw = (string) Storage::disk('local')->get($version->quarantine_path);
            }
            $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $raw) ?? '';
            $clean = trim($clean);
            if ($clean === '') {
                $version->forceFill([
                    'status' => KnowledgeSourceVersion::STATUS_REJECTED,
                    'failure_reason' => 'empty_extraction',
                ])->save();

                return;
            }
            $lifecycle->markReviewRequired($version, $clean);
        } catch (\Throwable) {
            $version->forceFill([
                'status' => KnowledgeSourceVersion::STATUS_REJECTED,
                'failure_reason' => 'extract_failed',
            ])->save();
        }
    }
}
