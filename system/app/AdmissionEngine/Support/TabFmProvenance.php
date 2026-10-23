<?php

namespace App\AdmissionEngine\Support;

final class TabFmProvenance
{
    private const APPROVED_PACKAGE_VERSION = '9.1.0';

    public static function hostedUseIsApproved(): bool
    {
        $checkpointIdentity = (string) config('admission_engine.tabfm.checkpoint_id', '');
        $checkpointSha256 = (string) config('admission_engine.tabfm.checkpoint_sha256', '');
        $packageVersion = (string) config('admission_engine.tabfm.package_version', '');

        return config('admission_engine.tabfm.institutional_use_approved') === true
            && config('admission_engine.tabfm.hosted_use_approved') === true
            && trim($checkpointIdentity) !== ''
            && preg_match('/^[a-f0-9]{64}$/D', $checkpointSha256) === 1
            && $packageVersion === self::APPROVED_PACKAGE_VERSION;
    }

    /** @param array<string, mixed> $provenance */
    public static function matchesApproved(array $provenance, mixed $engineVersion): bool
    {
        $packageVersion = (string) config('admission_engine.tabfm.package_version', '');

        return self::hostedUseIsApproved()
            && ($provenance['package'] ?? null) === 'tabpfn'
            && ($provenance['package_version'] ?? null) === $packageVersion
            && $engineVersion === $packageVersion
            && ($provenance['checkpoint_identity'] ?? null) === (string) config('admission_engine.tabfm.checkpoint_id', '')
            && ($provenance['checkpoint_sha256'] ?? null) === (string) config('admission_engine.tabfm.checkpoint_sha256', '');
    }

    /** @param array<string, mixed> $payload */
    public static function isApprovedStoredEvaluation(array $payload, mixed $rowEngineVersion): bool
    {
        $coverage = $payload['modality_coverage'] ?? null;
        $provenance = is_array($coverage)
            ? ($coverage['inference_provenance'] ?? null)
            : null;

        return ($payload['status'] ?? null) === 'evaluated'
            && ($payload['inference_backend'] ?? null) === 'tabpfn_pytorch'
            && array_key_exists('fallback_reason', $payload)
            && $payload['fallback_reason'] === null
            && is_array($coverage)
            && is_array($provenance)
            && ! array_is_list($provenance)
            && ($payload['engine_version'] ?? null) === $rowEngineVersion
            && self::matchesApproved($provenance, $rowEngineVersion);
    }
}
