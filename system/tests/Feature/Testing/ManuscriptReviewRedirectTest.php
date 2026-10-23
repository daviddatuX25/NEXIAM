<?php

test('manuscript route redirects cleanly to the active published manuscript review reader', function (): void {
    $this->get('/manuscript')
        ->assertRedirect()
        ->assertHeader('Location', url('/manuscript-review/85733ec6bc596d730a1f49c07529efa40e5dc86980120222/index.html'));
});

test('manuscript review route without token redirects to manuscript index', function (): void {
    $this->get('/manuscript-review')
        ->assertRedirect(route('manuscript.index'));
});
