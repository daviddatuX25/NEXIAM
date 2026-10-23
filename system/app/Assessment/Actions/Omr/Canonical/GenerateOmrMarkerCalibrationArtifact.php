<?php

namespace App\Assessment\Actions\Omr\Canonical;

use App\Foundation\Actions\RecordAuditEvent;
use App\Jobs\Assessment\RenderOmrMarkerCalibrationArtifactJob;
use App\Models\Assessment\OmrMarkerProfile;
use App\Models\User;

final class GenerateOmrMarkerCalibrationArtifact
{
    public function __construct(
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {}

    /** @param array<string, mixed> $evidence */
    public function handle(OmrMarkerProfile $profile, array $evidence, User $actor): string
    {
        RenderOmrMarkerCalibrationArtifactJob::dispatch(
            profileId: $profile->id,
            evidence: $evidence,
            actorId: (int) $actor->id,
        );

        $this->recordAuditEvent->handle('omr.marker_calibration_artifact_requested', [
            'marker_profile_id' => $profile->id,
            'asset_sha256' => $profile->asset_sha256,
            'renderer_version' => $evidence['renderer_version'] ?? null,
            'printer' => $evidence['printer'] ?? null,
            'device' => $evidence['device'] ?? null,
            'paper' => $evidence['paper'] ?? null,
        ], (string) $actor->id);

        return 'queued';
    }
}
