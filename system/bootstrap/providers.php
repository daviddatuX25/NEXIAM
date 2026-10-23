<?php

use App\Ai\Telemetry\GenAiTelemetryServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;

return [
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    GenAiTelemetryServiceProvider::class,
];
