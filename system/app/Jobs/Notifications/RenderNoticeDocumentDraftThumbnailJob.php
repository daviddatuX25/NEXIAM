<?php

declare(strict_types=1);

namespace App\Jobs\Notifications;

use App\Models\Notifications\NoticeDocumentDraft;
use App\Notifications\Templates\Actions\NoticeDocumentWorkflow;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

final class RenderNoticeDocumentDraftThumbnailJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 45;

    public function __construct(public readonly int $draftId, public readonly string $renderSha256) {}

    public function uniqueId(): string
    {
        return 'notice-document-draft-thumb:'.$this->draftId.':'.$this->renderSha256;
    }

    public function handle(NoticeDocumentWorkflow $workflow): void
    {
        $draft = NoticeDocumentDraft::query()->whereKey($this->draftId)->first();
        if ($draft === null) {
            return;
        }
        try {
            $family = $draft->template()->first();
            if ($family === null || ! hash_equals($workflow->renderDigest($family, $draft->state), $this->renderSha256)) {
                return;
            }
            $workflow->warmDraftThumbnail($draft);
        } catch (Throwable) {
            // Presentation-only
        }
    }
}
