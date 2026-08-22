<?php

namespace App\Jobs\Assessment;

use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\OmrTemplateAsset;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class GcOmrCanonicalTempAssetsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 300;

    public function handle(RecordAuditEvent $recordAuditEvent): void
    {
        $diskName = (string) config('omr.canonical.storage.disk', 'local');
        $disk = Storage::disk($diskName);
        $referenced = OmrTemplateAsset::query()
            ->where('disk', $diskName)
            ->pluck('path')
            ->flip();
        $cutoff = now()->subHours((int) config('omr.canonical.storage.temp_ttl_hours', 24))->getTimestamp();
        $deleted = [];
        foreach (['omr-canonical/temp', 'omr-canonical/drafts'] as $prefix) {
            foreach ($disk->allFiles($prefix) as $path) {
                if ($referenced->has($path)) {
                    continue;
                }
                $modified = $disk->lastModified($path);
                if ($modified >= $cutoff) {
                    continue;
                }
                $disk->delete($path);
                $deleted[] = $path;
            }
        }

        $recordAuditEvent->handle('omr.canonical.temp_assets_collected', [
            'disk' => $diskName,
            'deleted_count' => count($deleted),
            'version_assets_considered' => false,
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        app(RecordAuditEvent::class)->handle('omr.canonical.temp_asset_gc_failed', [
            'error' => $exception?->getMessage() ?? 'unknown',
        ]);
    }
}
