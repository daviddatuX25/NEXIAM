<?php

use App\Models\User;

test('grading index requires manual scoring capability', function (): void {
    seedE2Foundation();
    $registrar = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();

    $this->actingAs($registrar)
        ->get(route('grading.index'))
        ->assertForbidden();
});
