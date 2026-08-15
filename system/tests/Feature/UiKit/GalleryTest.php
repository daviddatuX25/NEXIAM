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
            ->has('nexi', fn (Assert $nexi) => $nexi
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

it('shares nexi presentation seam on staff home', function () {
    seedE2Foundation();
    $user = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();

    $this->actingAs($user)
        ->get(route('staff.home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('nexi.enabled')
            ->where('nexi.mode', 'degraded')
            ->has('nexi.interaction_modes')
            ->has('nexi.usage.limit'));
});
