<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('serves the capstone roadmap without authentication (dev workspace)', function () {
    $this->get('/capstone/status')->assertOk();
});

it('renders the repository-backed project status projection', function () {
    $response = $this->get('/capstone/status');

    $response
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Foundation/Status')
            ->has('foundation.phase')
            ->has('roadmap', 6)
            ->where('roadmap.4.items.1.name', 'Chatbot / assistant experience')
            ->where('roadmap.4.items.2.name', 'OMR-powered checking')
            ->where('roadmap.4.items.2.interface_checks.0.label', 'Capture dashboard')
            ->where('roadmap.4.items.2.subfeature_checks.0.label', 'Capture and batch ingest')
            ->where('roadmap.4.items.3.subfeature_checks.1.label', 'Page and answer-key builder')
            ->where('roadmap.4.items.2.sequence', fn (string $sequence): bool => str_contains($sequence, 'sequenceDiagram')),
        );
});
