<?php

namespace App\AdmissionEngine\Providers;

use App\AdmissionEngine\Contracts\AdmissionEngineProvider;
use App\AdmissionEngine\DTOs\AdmissionInputPayload;
use App\AdmissionEngine\DTOs\EngineEvaluationResult;
use App\AdmissionEngine\DTOs\EngineManifest;

final class YuKeeKMeansProvider implements AdmissionEngineProvider
{
    public function manifest(?int $academicCycleId = null): EngineManifest
    {
        $artifactExists = is_file((string) config('admission_engine.yukee.artifact_path'));

        return new EngineManifest(
            identifier: $this->identifier(),
            slot: $this->slot(),
            displayName: 'YuKee / K-Means compatibility',
            description: 'Artifact-compatible cluster archetype and radar profile evidence.',
            engineVersion: (string) config('admission_engine.yukee.version', 'yukee-kmeans-v1'),
            runtimeStatus: $artifactExists ? 'ready' : 'standby',
            cycleReadiness: $artifactExists ? 'active' : 'awaiting_artifact',
            statusBadge: $artifactExists ? 'Ready · Artifact Present' : 'Standby · GF-10 Path A',
            requiredArtifacts: ['yukee_kmeans.joblib model artifact', 'centroid parameter manifest'],
            supportedInputs: ['GWA', 'Typed aptitude outputs from approved instruments'],
        );
    }

    public function identifier(): string
    {
        return 'yu_kee';
    }

    public function slot(): string
    {
        return 'yu_kee';
    }

    public function canEvaluate(AdmissionInputPayload $input): bool
    {
        return false;
    }

    public function evaluate(AdmissionInputPayload $input): EngineEvaluationResult
    {
        return new EngineEvaluationResult(
            $this->identifier(),
            $this->slot(),
            'standby',
            (string) config('admission_engine.yukee.version', 'yukee-kmeans-v1'),
            null,
            null,
            now()->toIso8601String(),
            [],
            [],
            is_file((string) config('admission_engine.yukee.artifact_path'))
                ? 'Standby — artifact detected; YuKee inference remains unavailable until its verified centroid manifest is integrated.'
                : 'Standby — awaiting verified .joblib model artifact and centroid manifest under GF-10 Path A.',
        );
    }
}
