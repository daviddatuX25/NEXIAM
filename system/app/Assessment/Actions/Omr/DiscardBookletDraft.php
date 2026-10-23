<?php

namespace App\Assessment\Actions\Omr;

use App\Assessment\Omr\BookletAuthorization;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\OmrCaptureBatch;
use App\Models\Assessment\OmrDiagnosticRun;
use App\Models\Assessment\OmrQuestionBookletVersion;
use App\Models\Assessment\OmrTemplateBookletPair;
use App\Models\Examination\ExamSession;
use App\Models\Examination\ExamSessionMaterialSnapshot;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class DiscardBookletDraft
{
    public function __construct(
        private readonly BookletAuthorization $authorization,
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {}

    /**
     * @return array{discarded_version_id: int, parent_booklet_deleted: bool, surviving_version_id: ?int}
     */
    public function handle(
        OmrQuestionBookletVersion $version,
        User $actor,
        ?int $expectedLockVersion = null,
    ): array {
        $this->authorization->assertManage($actor);

        return DB::transaction(function () use ($version, $actor, $expectedLockVersion): array {
            $booklet = $version->booklet()->lockForUpdate()->firstOrFail();
            $lockedVersion = OmrQuestionBookletVersion::query()
                ->lockForUpdate()
                ->findOrFail($version->id);

            if (! $lockedVersion->isDraft()) {
                throw new RuntimeException('Only draft Booklets can be discarded.');
            }

            if ($expectedLockVersion !== null && (int) $lockedVersion->lock_version !== $expectedLockVersion) {
                throw new RuntimeException('This Booklet changed elsewhere. Reload it before discarding.');
            }

            $versionId = (int) $lockedVersion->id;
            $referencesExist = ExamSession::query()->where('booklet_version_id', $versionId)->exists()
                || ExamSessionMaterialSnapshot::query()->where('booklet_version_id', $versionId)->exists()
                || OmrDiagnosticRun::query()->where('booklet_version_id', $versionId)->exists()
                || OmrCaptureBatch::query()->where('booklet_version_id', $versionId)->exists()
                || OmrTemplateBookletPair::query()->where('booklet_version_id', $versionId)->exists();

            if ($referencesExist) {
                throw new RuntimeException('This draft Booklet is in use and cannot be discarded.');
            }

            $lockedVersion->sets()->delete();
            $lockedVersion->items()->delete();
            $lockedVersion->questions()->delete();
            $lockedVersion->delete();

            $survivingVersionId = $booklet->versions()
                ->orderByDesc('id')
                ->value('id');
            $parentBookletDeleted = $survivingVersionId === null;

            if ($parentBookletDeleted) {
                $booklet->delete();
            }

            $this->recordAuditEvent->handle('omr.booklet_draft_discarded', [
                'discarded_version_id' => $versionId,
                'parent_booklet_id' => $booklet->id,
                'parent_booklet_deleted' => $parentBookletDeleted,
                'surviving_version_id' => $survivingVersionId === null ? null : (int) $survivingVersionId,
            ], (string) $actor->id);

            return [
                'discarded_version_id' => $versionId,
                'parent_booklet_deleted' => $parentBookletDeleted,
                'surviving_version_id' => $survivingVersionId === null ? null : (int) $survivingVersionId,
            ];
        });
    }
}
