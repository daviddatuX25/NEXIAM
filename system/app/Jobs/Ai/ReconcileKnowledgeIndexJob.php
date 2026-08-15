<?php

namespace App\Jobs\Ai;

use App\Ai\Contracts\KnowledgeIndexPort;
use App\Models\Ai\KnowledgeIndexEntry;
use App\Models\Ai\KnowledgeSourceVersion;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Derived-index cleanup: remove entries whose version is no longer active.
 */
class ReconcileKnowledgeIndexJob implements ShouldQueue
{
    use Queueable;

    public function handle(KnowledgeIndexPort $index): void
    {
        unset($index);

        KnowledgeIndexEntry::query()
            ->where('status', 'active')
            ->whereHas('version', fn ($q) => $q->whereNotIn('status', [KnowledgeSourceVersion::STATUS_ACTIVE]))
            ->update(['status' => 'removed']);

        // Ensure revoked/superseded versions are marked removed even if relation filter missed.
        $inactiveIds = KnowledgeSourceVersion::query()
            ->whereIn('status', [
                KnowledgeSourceVersion::STATUS_REVOKED,
                KnowledgeSourceVersion::STATUS_SUPERSEDED,
                KnowledgeSourceVersion::STATUS_DELETED,
                KnowledgeSourceVersion::STATUS_DELETION_PENDING,
            ])
            ->pluck('id');

        if ($inactiveIds->isNotEmpty()) {
            KnowledgeIndexEntry::query()
                ->whereIn('knowledge_source_version_id', $inactiveIds)
                ->update(['status' => 'removed']);
        }
    }
}
