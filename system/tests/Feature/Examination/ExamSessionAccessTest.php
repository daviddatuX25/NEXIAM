<?php

namespace Tests\Feature\Examination;

use App\Models\User;

test('exam session listing denied without capability', function (): void {
    seedE2Foundation();
    $hr = User::query()->where('email', config('seed.hr.email'))->firstOrFail();

    $this->actingAs($hr)
        ->get(route('exams.sessions.index'))
        ->assertForbidden();
});
