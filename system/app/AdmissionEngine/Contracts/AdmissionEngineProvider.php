<?php

namespace App\AdmissionEngine\Contracts;

use App\AdmissionEngine\DTOs\AdmissionInputPayload;
use App\AdmissionEngine\DTOs\EngineEvaluationResult;
use App\AdmissionEngine\DTOs\EngineManifest;

interface AdmissionEngineProvider
{
    public function identifier(): string;

    public function slot(): string;

    public function manifest(?int $academicCycleId = null): EngineManifest;

    public function canEvaluate(AdmissionInputPayload $input): bool;

    public function evaluate(AdmissionInputPayload $input): EngineEvaluationResult;
}
