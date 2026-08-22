<?php

namespace App\Assessment\Actions\Omr\Canonical;

use App\Assessment\Omr\Canonical\OmrMarkerProfileService;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\OmrMarkerProfile;
use App\Models\User;

final class QualifyOmrMarkerProfile
{
    public function __construct(
        private readonly OmrMarkerProfileService $profiles,
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {
    }

    /** @param array<string, mixed> $evidence */
    public function handle(OmrMarkerProfile $profile, array $evidence, User $actor): OmrMarkerProfile
    {
        $qualified = $this->profiles->qualify($profile, $evidence, $actor);
        $this->recordAuditEvent->handle('omr.marker_profile_qualified', [
            'marker_profile_id' => $qualified->id,
            'code' => $qualified->code,
            'version' => $qualified->version,
            'asset_sha256' => $qualified->asset_sha256,
            'qualification_evidence_hash' => $qualified->qualification_evidence_hash,
            'renderer_version' => $evidence['renderer_version'] ?? null,
            'printer' => $evidence['printer'] ?? null,
            'device' => $evidence['device'] ?? null,
            'paper' => $evidence['paper'] ?? null,
        ], (string) $actor->id);

        return $qualified;
    }
}
