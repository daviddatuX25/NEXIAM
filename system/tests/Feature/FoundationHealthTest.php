<?php

test('home page renders institutional landing without planning meta', function (): void {
    $this->get('/')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Home')
            ->where('institutionName', 'ISPSC Tagudin')
        )
        ->assertDontSee('E2-CONNECTED-SLICE')
        ->assertDontSee('Foundation runtime is alive')
        ->assertDontSee('E1b flips');
});

test('health endpoint reports database readiness', function (): void {
    // phpunit.xml forces QUEUE_CONNECTION=sync for isolation; assert the health
    // payload truthfully reflects the production queue shape under test.
    config()->set('queue.default', 'database');
    config()->set('queue.connections.database.after_commit', true);

    $response = $this->getJson('/health');

    $response
        ->assertOk()
        ->assertJsonPath('status', 'ok')
        ->assertJsonPath('database', 'ok')
        ->assertJsonPath('private_storage', 'ok')
        ->assertJsonPath('queue', 'configured_after_commit');
});

test('apply page exposes programs and open window without slice meta labels', function (): void {
    seedE2Foundation();

    $this->get('/apply')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Admission/Apply')
            ->where('page_state', 'ready')
            ->has('programs', 2)
            ->has('window.id')
            ->has('cycle.id')
        )
        ->assertDontSee('E2 connected slice')
        ->assertDontSee('Public intake (E2');
});
