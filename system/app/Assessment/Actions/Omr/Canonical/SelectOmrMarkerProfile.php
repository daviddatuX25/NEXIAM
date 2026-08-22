<?php

namespace App\Assessment\Actions\Omr\Canonical;

use App\Assessment\Omr\Canonical\OmrAuthoringDraftException;
use App\Assessment\Omr\Canonical\OmrAuthoringDraftService;
use App\Assessment\Omr\Canonical\OmrMarkerProfileService;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\OmrMarkerProfile;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class SelectOmrMarkerProfile
{
    public function __construct(
        private readonly OmrMarkerProfileService $profiles,
        private readonly OmrAuthoringDraftService $drafts,
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {
    }

    /** @param array<string, int|float>|null $insets */
    public function handle(OmrTemplateAuthoringDraft $draft, User $actor, OmrMarkerProfile $profile, ?array $insets, int $expectedLockVersion): OmrTemplateAuthoringDraft
    {
        $this->drafts->assertOwner($draft, $actor);
        if ($profile->status === OmrMarkerProfile::STATUS_RETIRED) {
            throw new OmrAuthoringDraftException('marker_profile_retired', 'Retired marker profiles cannot be selected.');
        }
        $validatedInsets = $this->profiles->validateInsets($insets ?? ['top' => 12, 'right' => 12, 'bottom' => 14, 'left' => 12]);

        return DB::transaction(function () use ($draft, $actor, $profile, $validatedInsets, $expectedLockVersion): OmrTemplateAuthoringDraft {
            $locked = OmrTemplateAuthoringDraft::query()->lockForUpdate()->findOrFail($draft->id);
            if ((int) $locked->lock_version !== $expectedLockVersion) {
                throw new OmrAuthoringDraftException('concurrency_conflict', 'Draft changed since it was loaded.');
            }
            $authoring = (array) $locked->authoring_json;
            $authoring['marker'] = [
                'profile_id' => $profile->id,
                'profile_status' => $profile->status,
                'insets_mm' => $validatedInsets,
                'physical_size_mm' => $profile->size_mm,
                'quiet_zone_mm' => $profile->quiet_zone_mm,
            ];
            $locked->forceFill([
                'marker_profile_id' => $profile->id,
                'authoring_json' => $authoring,
                'lock_version' => $expectedLockVersion + 1,
            ])->save();
            $saved = $locked->fresh();
            $this->recordAuditEvent->handle('omr.canonical.marker_profile_selected', [
                'draft_id' => $saved->id,
                'marker_profile_id' => $profile->id,
                'marker_profile_status' => $profile->status,
                'insets_mm' => $validatedInsets,
            ], (string) $actor->id);

            return $saved;
        });
    }
}
