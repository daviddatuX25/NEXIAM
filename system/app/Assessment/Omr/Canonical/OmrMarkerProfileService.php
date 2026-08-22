<?php

namespace App\Assessment\Omr\Canonical;

use App\Models\Assessment\OmrMarkerProfile;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

use function config;
use function file_exists;
use function hash_file;
use function is_array;
use function is_string;
use function preg_match;

final class OmrMarkerProfileException extends RuntimeException
{
    public function __construct(public readonly string $errorCode, string $message)
    {
        parent::__construct($errorCode.': '.$message);
    }
}

final class OmrMarkerProfileService
{
    /**
     * @return list<array{code:string,version:int,family:string,asset_path:string,matching_config:array<string,mixed>}>
     */
    public function candidateDefinitions(): array
    {
        $assetPath = 'app/Assessment/Omr/Fiducial/omr_marker.jpg';
        $absolutePath = base_path($assetPath);
        if (! file_exists($absolutePath)) {
            throw new OmrMarkerProfileException('marker_asset_missing', "Marker asset {$assetPath} is missing.");
        }

        $matching = (array) config('omr.capture-policy.v1.fiducial', []);
        unset($matching['asset_name']);

        return [
            [
                'code' => 'omrchecker.sample',
                'version' => 1,
                'family' => 'omrchecker.bullseye',
                'asset_path' => $assetPath,
                'matching_config' => $matching,
            ],
            [
                'code' => 'nexiam.custom',
                'version' => 1,
                'family' => 'nexiam.fiducial',
                'asset_path' => $assetPath,
                'matching_config' => [...$matching, 'candidate_role' => 'comparison'],
            ],
        ];
    }

    /** @return Collection<int, OmrMarkerProfile> */
    public function seedCandidates(User $actor): Collection
    {
        $profiles = new Collection;
        foreach ($this->candidateDefinitions() as $definition) {
            $profile = OmrMarkerProfile::query()->firstOrNew([
                'code' => $definition['code'],
                'version' => $definition['version'],
            ]);
            $profile->fill([
                'family' => $definition['family'],
                'asset_path' => $definition['asset_path'],
                'asset_sha256' => (string) hash_file('sha256', base_path($definition['asset_path'])),
                'matching_config' => $definition['matching_config'],
                'created_by_user_id' => $profile->created_by_user_id ?? $actor->id,
            ]);
            if (! $profile->exists) {
                $profile->status = OmrMarkerProfile::STATUS_CANDIDATE;
            }
            $profile->save();
            $profiles->push($profile);
        }

        return $profiles;
    }

    /**
     * @param array<string, mixed> $evidence
     */
    public function qualify(OmrMarkerProfile $profile, array $evidence, User $actor): OmrMarkerProfile
    {
        $evidenceHash = $evidence['evidence_hash'] ?? null;
        $assetHash = $evidence['asset_sha256'] ?? null;
        foreach (['renderer_version', 'printer', 'device', 'paper'] as $required) {
            if (! is_string($evidence[$required] ?? null) || trim($evidence[$required]) === '') {
                throw new OmrMarkerProfileException('qualification_evidence_missing', "Marker qualification evidence requires {$required}.");
            }
        }
        if (! is_string($evidenceHash) || preg_match('/^[a-f0-9]{64}$/', $evidenceHash) !== 1) {
            throw new OmrMarkerProfileException('qualification_evidence_invalid', 'Marker qualification evidence hash must be SHA-256.');
        }
        if (! is_string($assetHash) || ! hash_equals((string) $profile->asset_sha256, $assetHash)) {
            throw new OmrMarkerProfileException('marker_asset_mismatch', 'Qualification evidence does not match the marker asset hash.');
        }
        if (! in_array($profile->status, [OmrMarkerProfile::STATUS_CANDIDATE, OmrMarkerProfile::STATUS_QUALIFIED], true)) {
            throw new OmrMarkerProfileException('qualification_state_invalid', 'Only candidate or currently qualified marker profiles may be qualified.');
        }

        return DB::transaction(function () use ($profile, $evidence, $evidenceHash, $actor): OmrMarkerProfile {
            $locked = OmrMarkerProfile::query()->lockForUpdate()->findOrFail($profile->id);
            OmrMarkerProfile::query()
                ->where('status', OmrMarkerProfile::STATUS_QUALIFIED)
                ->where('id', '!=', $locked->id)
                ->update([
                    'status' => OmrMarkerProfile::STATUS_RETIRED,
                    'updated_at' => now(),
                ]);

            $sizeMm = $evidence['size_mm'] ?? null;
            $quietZoneMm = $evidence['quiet_zone_mm'] ?? null;
            if (! is_numeric($sizeMm) || (float) $sizeMm <= 0 || ! is_numeric($quietZoneMm) || (float) $quietZoneMm < 0) {
                throw new OmrMarkerProfileException('qualification_geometry_invalid', 'Qualification must freeze a positive marker size and non-negative quiet zone.');
            }

            $locked->forceFill([
                'status' => OmrMarkerProfile::STATUS_QUALIFIED,
                'size_mm' => $sizeMm,
                'quiet_zone_mm' => $quietZoneMm,
                'qualification_evidence_hash' => $evidenceHash,
                'qualified_by_user_id' => $actor->id,
                'qualified_at' => now(),
            ])->save();

            return $locked->fresh();
        });
    }

    public function assertQualified(?OmrMarkerProfile $profile): OmrMarkerProfile
    {
        if ($profile === null || ! $profile->isQualified()) {
            throw new OmrMarkerProfileException('marker_profile_unqualified', 'A qualified marker profile is required.');
        }
        if ($profile->size_mm === null || $profile->quiet_zone_mm === null) {
            throw new OmrMarkerProfileException('marker_profile_unqualified', 'Qualified marker geometry is incomplete.');
        }

        return $profile;
    }

    /**
     * @param array<string, int|float> $insets
     * @return array{top:float,right:float,bottom:float,left:float}
     */
    public function validateInsets(array $insets): array
    {
        $minimum = (float) config('omr.canonical.marker.allowed_inset_range_mm.0', 3.0);
        $maximum = (float) config('omr.canonical.marker.allowed_inset_range_mm.1', 40.0);
        $validated = [];
        foreach (['top', 'right', 'bottom', 'left'] as $side) {
            $value = $insets[$side] ?? null;
            if (! is_numeric($value) || (float) $value < $minimum || (float) $value > $maximum) {
                throw new OmrMarkerProfileException('marker_inset_invalid', "Marker {$side} inset must be between {$minimum} and {$maximum} mm.");
            }
            $validated[$side] = (float) $value;
        }

        return $validated;
    }
}
