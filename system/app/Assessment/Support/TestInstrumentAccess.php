<?php

namespace App\Assessment\Support;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Models\Assessment\TestInstrument;
use App\Models\Foundation\Institution;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

final class TestInstrumentAccess
{
    public function __construct(private readonly CapabilityAuthorizer $capabilities) {}

    public function institutionId(User $actor): int
    {
        $principal = User::query()->find($actor->getKey());

        if ($principal === null) {
            throw new AuthorizationException('Test instrument access is unavailable.');
        }

        $scope = $principal->actorScope();
        $institutionId = (int) ($scope->institutionId ?? 0);

        if ($scope->office !== 'guidance'
            || $institutionId < 1
            || ! Institution::query()->whereKey($institutionId)->exists()
            || ! $this->capabilities->decideFresh(
                $principal,
                CapabilityCatalog::GUIDANCE_ASSESSMENT_CATALOG,
                ['office' => 'guidance', 'institution_id' => $institutionId],
            )->allowed) {
            throw new AuthorizationException('Test instrument access is unavailable.');
        }

        return $institutionId;
    }

    public function instrument(User $actor, int $instrumentId): TestInstrument
    {
        return TestInstrument::query()
            ->where('institution_id', $this->institutionId($actor))
            ->whereKey($instrumentId)
            ->firstOrFail();
    }
}
