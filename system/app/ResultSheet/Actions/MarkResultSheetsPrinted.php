<?php

namespace App\ResultSheet\Actions;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Release\ResultRelease;
use App\Models\User;
use RuntimeException;

/**
 * Manual printed-status toggle (legacy parity): staff mark/unmark released
 * records as physically printed, independent of rendering. Capability-gated
 * and audited per release record.
 */
final class MarkResultSheetsPrinted
{
    public function __construct(
        private readonly CapabilityAuthorizer $authorizer,
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {
    }

    /**
     * @param  list<int>  $releaseIds
     */
    public function handle(User $actor, array $releaseIds, bool $printed): int
    {
        if (! $this->authorizer->decide($actor, CapabilityCatalog::GUIDANCE_RESULTS_RELEASE, [])->allowed) {
            throw new RuntimeException('The result release capability is required to manage printed status.');
        }

        $releaseIds = array_values(array_unique(array_map('intval', $releaseIds)));

        if ($releaseIds === []) {
            return 0;
        }

        $count = ResultRelease::query()
            ->whereIn('id', $releaseIds)
            ->where('is_current', true)
            ->update(['result_printed_at' => $printed ? now() : null]);

        foreach ($releaseIds as $releaseId) {
            $this->recordAuditEvent->handle('release.result_printed_toggled', [
                'release_id' => $releaseId,
                'printed' => $printed,
            ], (string) $actor->id);
        }

        return $count;
    }
}
