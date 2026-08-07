<?php

use App\Models\Foundation\AuditEvent;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('staff can view the login page', function (): void {
    $this->get('/login')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page->component('Auth/Login'));
});

test('staff can authenticate and reach the staff home', function (): void {
    $this->seed();

    $response = $this->post('/login', [
        'email' => 'registrar.clerk@nexiam.test',
        'password' => 'password',
    ]);

    $response->assertRedirect(route('staff.home'));
    $this->assertAuthenticated();

    $this->get('/staff')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Staff/Home')
            ->where('staff.email', 'registrar.clerk@nexiam.test')
            ->has('staff.capabilities')
            ->where('staff.scope.office', 'registrar'));

    expect(AuditEvent::query()->where('event_type', 'auth.staff_login')->exists())->toBeTrue();
});

test('invalid credentials are rejected', function (): void {
    User::factory()->create([
        'email' => 'nobody@nexiam.test',
        'password' => Hash::make('password'),
    ]);

    $this->from('/login')->post('/login', [
        'email' => 'nobody@nexiam.test',
        'password' => 'wrong-password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('authenticated staff can logout', function (): void {
    $this->seed();
    $user = User::query()->where('email', 'registrar.clerk@nexiam.test')->firstOrFail();

    $this->actingAs($user)
        ->post('/logout')
        ->assertRedirect(route('login'));

    $this->assertGuest();
});
