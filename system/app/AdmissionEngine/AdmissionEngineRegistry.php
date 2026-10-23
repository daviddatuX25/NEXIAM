<?php

namespace App\AdmissionEngine;

use App\AdmissionEngine\Contracts\AdmissionEngineProvider;
use App\AdmissionEngine\Providers\TabFmPairwiseProvider;
use App\AdmissionEngine\Providers\YuKeeKMeansProvider;

final readonly class AdmissionEngineRegistry
{
    public function __construct(
        public TabFmPairwiseProvider $tabFmPairwise,
        public YuKeeKMeansProvider $yuKee,
    ) {}

    /** @return list<AdmissionEngineProvider> */
    public function providers(): array
    {
        return [$this->tabFmPairwise, $this->yuKee];
    }

    public function forSlot(string $slot): ?AdmissionEngineProvider
    {
        foreach ($this->providers() as $provider) {
            if ($provider->slot() === $slot) {
                return $provider;
            }
        }

        return null;
    }
}
