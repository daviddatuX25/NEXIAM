<?php

use App\Models\User;

test('monitoring index exposes real-time telemetry payload', function (): void {
    seedE2Foundation();
    $guidance = User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail();

    $response = $this->actingAs($guidance)->get('/exams/monitoring');

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Examination/MonitoringIndex')
            ->has('telemetry.is_live')
            ->has('telemetry.active_rooms')
            ->has('telemetry.total_rooms')
            ->has('telemetry.total_scheduled')
            ->has('telemetry.present_count')
        );
});
