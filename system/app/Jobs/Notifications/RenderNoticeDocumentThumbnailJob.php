<?php

declare(strict_types=1);

namespace App\Jobs\Notifications;

use App\Models\Notifications\NoticeDocumentVersion;
use App\Notifications\Templates\Actions\NoticeDocumentWorkflow;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

final class RenderNoticeDocumentThumbnailJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 45;

    public function __construct(public readonly int $documentVersionId, public readonly string $renderSha256) {}

    public function uniqueId(): string
    {
        return 'notice-document-thumb:'.$this->documentVersionId.':'.$this->renderSha256;
    }

    public function handle(NoticeDocumentWorkflow $workflow): void
    {
        $version = NoticeDocumentVersion::query()->whereKey($this->documentVersionId)->first();
        if ($version === null || ! hash_equals($version->render_sha256, $this->renderSha256)) {
            return;
        }
        try {
            $workflow->warmThumbnail($version);
        } catch (Throwable) {
            // The thumbnail is optional and cannot change ready-document validity.
        }
    }
}
