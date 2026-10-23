<?php

namespace App\Assessment\Actions\Omr;

use App\Assessment\Omr\BookletAuthorization;
use App\Assessment\Support\MaterialOwnerAccess;
use App\Authorization\CapabilityCatalog;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\OmrQuestionBookletVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class RestoreBookletVersion
{
    public function __construct(
        private readonly BookletAuthorization $authorization,
        private readonly MaterialOwnerAccess $ownerAccess,
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {}

    public function handle(OmrQuestionBookletVersion $version, User $actor): OmrQuestionBookletVersion
    {
        $this->authorization->assertManage($actor);

        return DB::transaction(function () use ($version, $actor): OmrQuestionBookletVersion {
            $lockedVersion = OmrQuestionBookletVersion::query()
                ->lockForUpdate()
                ->findOrFail($version->id);
            $institutionId = $this->ownerAccess->institutionId($actor, CapabilityCatalog::GUIDANCE_BOOKLET_MANAGE);
            if (! $lockedVersion->booklet()->where('institution_id', $institutionId)->exists()) {
                throw new RuntimeException('Booklet version does not belong to the authorized institution.');
            }

            if (! $lockedVersion->isFinalized()) {
                throw new RuntimeException('Only finalized Booklet versions can be restored.');
            }

            if ($lockedVersion->archived_at === null) {
                return $lockedVersion;
            }

            $previousArchivedAt = $lockedVersion->archived_at->toIso8601String();
            $lockedVersion->update([
                'archived_at' => null,
            ]);

            $this->recordAuditEvent->handle('omr.booklet_version_restored', [
                'booklet_version_id' => $lockedVersion->id,
                'status' => $lockedVersion->status,
                'previous_archived_at' => $previousArchivedAt,
            ], (string) $actor->id);

            return $lockedVersion;
        });
    }
}
