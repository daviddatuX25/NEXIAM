<?php

namespace App\Jobs\Assessment;

use App\Assessment\Omr\Canonical\OmrMarkerCalibrationArtifactGenerator;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\OmrMarkerProfile;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

final class RenderOmrMarkerCalibrationArtifactJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 300;

    /** @param array<string, mixed> $evidence */
    public function __construct(
        public readonly int $profileId,
        public readonly array $evidence,
        public readonly int $actorId,
    ) {
    }

    public function handle(
        OmrMarkerCalibrationArtifactGenerator $generator,
        RecordAuditEvent $recordAuditEvent,
    ): void {
        $profile = OmrMarkerProfile::query()->findOrFail($this->profileId);
        $artifact = $generator->generate($profile, $this->evidence);

        $recordAuditEvent->handle('omr.marker_calibration_artifact_rendered', [
            'marker_profile_id' => $profile->id,
            'artifact_path' => $artifact['path'],
            'artifact_sha256' => $artifact['sha256'],
            'asset_sha256' => $artifact['asset_sha256'],
            'evidence_hash' => $artifact['evidence_hash'],
            'renderer_version' => $artifact['renderer_version'],
            'false_positive_cases' => $artifact['false_positive_cases'],
        ], (string) $this->actorId);
    }

    public function failed(?Throwable $exception): void
    {
        app(RecordAuditEvent::class)->handle('omr.marker_calibration_artifact_failed', [
            'marker_profile_id' => $this->profileId,
            'error' => $exception?->getMessage() ?? 'unknown',
        ], (string) $this->actorId);
    }
}
