<?php

arch('controllers do not open database transactions directly')
    ->expect('App\Http\Controllers')
    ->not->toUse('Illuminate\Support\Facades\DB')
    ->ignoring('App\Http\Controllers\HealthController');

arch('foundation models do not depend on HTTP request types')
    ->expect('App\Models')
    ->not->toUse('Illuminate\Http');

arch('application code does not reference a Legacy namespace')
    ->expect('App')
    ->not->toUse('Legacy');
