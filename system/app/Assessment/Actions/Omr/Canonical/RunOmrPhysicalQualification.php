<?php

namespace App\Assessment\Actions\Omr\Canonical;

use App\Assessment\Omr\Canonical\OmrMarkerProfileService;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrMarkerProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

use function abs;
use function array_map;
use function max;

final class RunOmrPhysicalQualification
{
    public function __construct(
        private readonly OmrMarkerProfileService $profiles,
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {
    }

    /** @param array<string, mixed> $evidence */
    public function handle(OmrAnswerSheetTemplateVersion $version, User $actor, array $evidence): OmrAnswerSheetTemplateVersion
    {
        if ($version->status !== OmrAnswerSheetTemplateVersion::STATUS_DRAFT) {
            throw new RuntimeException('template_version_not_compiled');
        }
        $manifest = (array) $version->authoring_manifest;
        $profileId = $manifest['marker_profile']['id'] ?? null;
        $profile = $profileId ? OmrMarkerProfile::query()->find($profileId) : null;
        $this->profiles->assertQualified($profile);
        $measured = array_map('floatval', (array) ($evidence['measured_marker_center_mm'] ?? []));
        $paper = (array) ($manifest['paper'] ?? []);
        $insets = (array) ($manifest['marker_insets_mm'] ?? []);
        $expected = [
            (float) ($paper['width_mm'] ?? 0) - (float) ($insets['left'] ?? 0) - (float) ($insets['right'] ?? 0),
            (float) ($paper['height_mm'] ?? 0) - (float) ($insets['top'] ?? 0) - (float) ($insets['bottom'] ?? 0),
        ];
        $tolerance = 1.0;
        if (count($measured) !== 2 || min($expected) <= 0 || max(abs($measured[0] - $expected[0]), abs($measured[1] - $expected[1])) > $tolerance) {
            throw new RuntimeException('physical_measurement_out_of_tolerance');
        }
        $outcomes = (array) ($evidence['outcomes'] ?? []);
        if ($outcomes === [] || in_array('fail', $outcomes, true)) {
            throw new RuntimeException('physical_evidence_failed');
        }

        return DB::transaction(function () use ($version, $actor, $evidence, $manifest, $profile, $expected): OmrAnswerSheetTemplateVersion {
            $locked = OmrAnswerSheetTemplateVersion::query()->lockForUpdate()->findOrFail($version->id);
            $manifest['physical_qualification'] = [
                'status' => 'physical_qualified',
                'renderer_version' => $evidence['renderer_version'],
                'marker_profile_id' => $profile->id,
                'marker_profile_asset_sha256' => $profile->asset_sha256,
                'printer' => $evidence['printer'],
                'device' => $evidence['device'],
                'paper' => $evidence['paper'],
                'measured_marker_center_mm' => $evidence['measured_marker_center_mm'],
                'expected_marker_center_mm' => $expected,
                'outcomes' => $evidence['outcomes'],
            ];
            $locked->forceFill(['authoring_manifest' => $manifest])->save();
            $saved = $locked->fresh();
            $this->recordAuditEvent->handle('omr.canonical.physical_qualified', [
                'template_version_id' => $saved->id,
                'marker_profile_id' => $profile->id,
                'printer' => $evidence['printer'],
                'device' => $evidence['device'],
                'paper' => $evidence['paper'],
                'measured_marker_center_mm' => $evidence['measured_marker_center_mm'],
            ], (string) $actor->id);

            return $saved;
        });
    }
}
