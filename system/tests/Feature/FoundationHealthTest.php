<?php

test('home page renders the institution-first public landing contract', function (): void {
    $this->get('/')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Home')
            ->where('institution.name', 'Ilocos Sur Polytechnic State College')
            ->where('institution.campus', 'Tagudin Campus')
            ->where('activeSeason', null)
            ->has('hero.image.src')
            ->where('programs.0.isSimulated', true)
            ->has('programs')
            ->where('preview.source', 'simulated')
        )
        ->assertDontSee('E2-CONNECTED-SLICE')
        ->assertDontSee('Foundation runtime is alive')
        ->assertDontSee('E1b flips');
});

test('home landing exposes the active season badge payload when a cycle is active', function (): void {
    seedE2Foundation();

    $this->get('/')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Home')
            ->has('activeSeason.name')
            ->has('activeSeason.window')
            ->where('programs.0.isSimulated', false)
            ->where('programs.0.code', 'BSN')
            ->has('programs', 2)
        );
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
