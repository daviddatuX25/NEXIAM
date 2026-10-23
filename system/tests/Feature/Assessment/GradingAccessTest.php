<?php

test('guidance counselor with manual scoring capability can access scoring routes', function (): void {
    $fixture = buildGradingFixture($this);

    $this->actingAs($fixture['guidance'])
        ->get('/scoring')
        ->assertOk();

    $this->actingAs($fixture['guidance'])
        ->get('/scoring/sessions/'.$fixture['session']->id)
        ->assertOk();
});

test('a user without scoring capability is forbidden from scoring routes', function (): void {
    $fixture = buildGradingFixture($this);

    $this->actingAs($fixture['registrar'])
        ->get('/scoring')
        ->assertForbidden();

    $this->actingAs($fixture['registrar'])
        ->get('/scoring/sessions/'.$fixture['session']->id)
        ->assertForbidden();
});
