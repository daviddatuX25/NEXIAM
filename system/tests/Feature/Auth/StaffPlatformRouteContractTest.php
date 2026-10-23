<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

test('staff platform routes expose their named HTTP contracts', function (): void {
    $routes = [
        'staff.personnel.require-mfa' => 'POST',
        'staff.profile.mfa.enroll.begin' => 'POST',
        'staff.profile.mfa.enroll.confirm' => 'POST',
        'staff.session.keepalive' => 'POST',
        'staff.session.status' => 'GET',
    ];

    foreach ($routes as $name => $method) {
        $route = Route::getRoutes()->getByName($name);

        expect($route)->not->toBeNull()
            ->and($route->methods())->toContain($method);
    }
});

test('guests are denied the staff platform endpoints', function (): void {
    $responses = [
        $this->post('/staff/personnel/1/require-mfa'),
        $this->post('/staff/profile/mfa/enroll/begin'),
        $this->post('/staff/profile/mfa/enroll/confirm'),
        $this->post('/staff/session-keepalive'),
        $this->get('/staff/session-keepalive'),
    ];

    foreach ($responses as $response) {
        expect($response->status())->toBeIn([302, 401, 403, 404, 419]);
    }
});

test('non-staff users are denied the staff platform endpoints', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $responses = [
        $this->post('/staff/personnel/1/require-mfa'),
        $this->post('/staff/profile/mfa/enroll/begin'),
        $this->post('/staff/profile/mfa/enroll/confirm'),
        $this->post('/staff/session-keepalive'),
        $this->get('/staff/session-keepalive'),
    ];

    foreach ($responses as $response) {
        expect($response->status())->toBeIn([302, 401, 403, 404, 419]);
    }
});
