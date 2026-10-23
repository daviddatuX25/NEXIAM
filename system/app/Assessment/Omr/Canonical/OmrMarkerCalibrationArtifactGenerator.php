<?php

namespace App\Assessment\Omr\Canonical;

use App\Models\Assessment\OmrMarkerProfile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

use function base64_encode;
use function config;
use function hash;
use function hash_file;
use function is_file;
use function json_encode;
use function sprintf;

final class OmrMarkerCalibrationArtifactGenerator
{
    /**
     * @param  array{renderer_version?:string,printer:string,device:string,paper:string}  $evidence
     * @return array{disk:string,path:string,mime:string,bytes:int,sha256:string,asset_sha256:string,renderer_version:string,evidence_hash:string,false_positive_cases:list<string>}
     */
    public function generate(OmrMarkerProfile $profile, array $evidence): array
    {
        $absoluteAssetPath = base_path($profile->asset_path);
        if (! is_file($absoluteAssetPath)) {
            throw new RuntimeException('marker_asset_missing');
        }
        foreach (['printer', 'device', 'paper'] as $field) {
            if (! is_string($evidence[$field] ?? null) || trim($evidence[$field]) === '') {
                throw new RuntimeException('qualification_evidence_missing');
            }
        }

        $rendererVersion = (string) ($evidence['renderer_version'] ?? config('omr.canonical.renderer_version', 'canonical-renderer-v1'));
        $assetBytes = (string) file_get_contents($absoluteAssetPath);
        $assetSha256 = hash_file('sha256', $absoluteAssetPath);
        $evidenceHash = hash('sha256', json_encode([
            'asset_sha256' => $assetSha256,
            'renderer_version' => $rendererVersion,
            'printer' => $evidence['printer'],
            'device' => $evidence['device'],
            'paper' => $evidence['paper'],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        $markerDataUri = 'data:image/jpeg;base64,'.base64_encode($assetBytes);
        $falsePositiveCases = [
            'decorative_corner_border',
            'partial_marker_clipped_by_edge',
            'imported_supplier_border',
            'dark_corner_shadow',
        ];
        $svg = $this->svg($profile, $markerDataUri, $falsePositiveCases, $rendererVersion);
        $disk = (string) config('omr.canonical.storage.disk', 'local');
        $path = sprintf(
            'omr-canonical/marker-calibration/%s-v%d-%s.svg',
            $profile->code,
            $profile->version,
            $evidenceHash,
        );
        Storage::disk($disk)->put($path, $svg);

        return [
            'disk' => $disk,
            'path' => $path,
            'mime' => 'image/svg+xml',
            'bytes' => strlen($svg),
            'sha256' => hash('sha256', $svg),
            'asset_sha256' => (string) $assetSha256,
            'renderer_version' => $rendererVersion,
            'evidence_hash' => $evidenceHash,
            'false_positive_cases' => $falsePositiveCases,
        ];
    }

    /** @param list<string> $falsePositiveCases */
    private function svg(OmrMarkerProfile $profile, string $markerDataUri, array $falsePositiveCases, string $rendererVersion): string
    {
        $labels = implode('', array_map(
            static fn (string $case): string => '<text x="20" y="20" font-size="12">'.htmlspecialchars($case, ENT_XML1).'</text>',
            $falsePositiveCases,
        ));

        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="216mm" height="330mm" viewBox="0 0 2551 3898" data-marker-profile="%s" data-renderer-version="%s"><rect width="2551" height="3898" fill="white"/><g id="four-candidate-markers"><image href="%s" x="180" y="180" width="180" height="180"/><image href="%s" x="2191" y="180" width="180" height="180"/><image href="%s" x="180" y="3538" width="180" height="180"/><image href="%s" x="2191" y="3538" width="180" height="180"/></g><g id="controlled-false-positive-corpus">%s</g><text x="1275" y="1950" text-anchor="middle" font-size="32">Marker calibration · %s v%d</text></svg>',
            htmlspecialchars($profile->code, ENT_XML1),
            htmlspecialchars($rendererVersion, ENT_XML1),
            $markerDataUri,
            $markerDataUri,
            $markerDataUri,
            $markerDataUri,
            $labels,
            htmlspecialchars($profile->code, ENT_XML1),
            $profile->version,
        );
    }
}
