<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('redirects guests away from the ui kit gallery', function () {
    $this->get(route('ui-kit.gallery'))->assertRedirect(route('login'));
});

it('renders the ui kit gallery for authenticated staff', function () {
    seedE2Foundation();
    $user = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();

    $this->actingAs($user)
        ->get(route('ui-kit.gallery'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('UiKit/Gallery')
            ->has('xiam', fn (Assert $xiam) => $xiam
                ->where('enabled', true)
                ->where('mode', 'degraded')
                ->has('disclosure')
                ->where('default_interaction', 'inquiry')
                ->has('interaction_modes')
                ->has('usage', fn (Assert $usage) => $usage
                    ->has('used')
                    ->has('limit')
                    ->has('unit')
                    ->has('period'))
                ->etc()));
});

it('shares xiam presentation seam on staff home', function () {
    seedE2Foundation();
    $user = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();

    $this->actingAs($user)
        ->get(route('staff.home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('xiam.enabled')
            ->where('xiam.mode', 'degraded')
            ->has('xiam.interaction_modes')
            ->has('xiam.usage.limit'));
});
