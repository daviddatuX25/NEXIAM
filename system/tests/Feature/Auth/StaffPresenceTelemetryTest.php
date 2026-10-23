<?php

use App\Auth\Support\UserAgentDeviceParser;
use App\Models\Auth\StaffAuthenticationState;
use App\Models\Auth\StaffPresenceSession;
use App\Models\User;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->withoutVite();
});

test('device parser identifies common desktop and mobile browsers and unknown agents', function (): void {
    $parser = app(UserAgentDeviceParser::class);

    expect($parser->parse('Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Safari/537.36'))
        ->toBe(['device_type' => 'Desktop', 'browser' => 'Chrome', 'os' => 'Linux'])
        ->and($parser->parse('Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1'))
        ->toBe(['device_type' => 'Mobile', 'browser' => 'Safari', 'os' => 'iOS'])
        ->and($parser->parse('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Safari/537.36 Edg/129.0.0.0'))
        ->toBe(['device_type' => 'Desktop', 'browser' => 'Edge', 'os' => 'Windows'])
        ->and($parser->parse(''))
        ->toBe(['device_type' => 'Unknown', 'browser' => 'Unknown', 'os' => 'Unknown'])
        ->and($parser->parse('Unrecognized agent'))
        ->toBe(['device_type' => 'Unknown', 'browser' => 'Unknown', 'os' => 'Unknown']);
});

test('authenticated staff requests record active presence and device metadata', function (string $email, string $uri, string $office, string $workspace, string $userAgent, string $device, string $browser, string $os): void {
    seedE2Foundation();
    $actor = User::query()->where('email', $email)->firstOrFail();
    $authState = StaffAuthenticationState::query()->where('user_id', $actor->id)->firstOrFail();

    $this->actingAs($actor)
        ->withSession([
            'auth.surface' => 'staff',
            'auth.session_version' => $authState->session_version,
        ])
        ->withHeader('User-Agent', $userAgent)
        ->withServerVariables(['REMOTE_ADDR' => '203.0.113.47'])
        ->get($uri)
        ->assertOk();

    $presence = StaffPresenceSession::query()->where('user_id', $actor->id)->whereNull('ended_at')->firstOrFail();
    expect($presence->office)->toBe($office)
        ->and($presence->workspace_area)->toBe($workspace)
        ->and($presence->device_type)->toBe($device)
        ->and($presence->browser)->toBe($browser)
        ->and($presence->os)->toBe($os)
        ->and($presence->ip_address)->toBe('203.0.113.47')
        ->and($presence->current_path)->not->toBeNull()
        ->and($presence->last_active_at)->not->toBeNull();
})->with([
    'Admin Lead' => [
        'admin.lead@nexiam.test', '/admin', 'admin', 'Executive Dashboard',
        'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 Chrome/129.0.0.0 Safari/537.36', 'Desktop', 'Chrome', 'Linux',
    ],
    'Registrar Admin' => [
        'registrar.admin@nexiam.test', '/setup/requirements', 'registrar', 'Institutional Setup',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/129.0.0.0 Safari/537.36 Edg/129.0.0.0', 'Desktop', 'Edge', 'Windows',
    ],
    'Guidance Counselor' => [
        'guidance.counselor@nexiam.test', '/setup/aptitude', 'guidance', 'Institutional Setup',
        'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1', 'Mobile', 'Safari', 'iOS',
    ],
]);

test('staff logout ends the active presence session', function (): void {
    seedE2Foundation();
    $admin = User::query()->where('email', 'admin.lead@nexiam.test')->firstOrFail();
    $authState = StaffAuthenticationState::query()->where('user_id', $admin->id)->firstOrFail();

    $this->actingAs($admin)->withSession([
        'auth.surface' => 'staff',
        'auth.session_version' => $authState->session_version,
    ])->get('/admin')->assertOk();

    $presence = StaffPresenceSession::query()->where('user_id', $admin->id)->whereNull('ended_at')->firstOrFail();
    $this->actingAs($admin)->post('/logout')->assertRedirect();

    expect($presence->fresh()->ended_at)->not->toBeNull()
        ->and($presence->fresh()->end_reason)->toBe('logout');

    $this->actingAs($admin)->withSession([
        'auth.surface' => 'staff',
        'auth.session_version' => $authState->session_version,
    ])->get('/staff/logs?state=all')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('presence.sessions', fn ($sessions) => collect($sessions)->contains(
            fn ($session) => $session['id'] === $presence->id && $session['status'] === 'offline',
        )));
});

test('admin sees presence from every office while guidance sees only masked own-office rows', function (): void {
    seedE2Foundation();
    $staffByOffice = [
        'admin' => User::query()->where('email', 'admin.lead@nexiam.test')->firstOrFail(),
        'registrar' => User::query()->where('email', 'registrar.admin@nexiam.test')->firstOrFail(),
        'guidance' => User::query()->where('email', 'guidance.counselor@nexiam.test')->firstOrFail(),
    ];

    foreach ($staffByOffice as $office => $user) {
        $authState = StaffAuthenticationState::query()->where('user_id', $user->id)->firstOrFail();
        $lastOctet = match ($office) {
            'admin' => '10',
            'registrar' => '20',
            default => '30',
        };
        StaffPresenceSession::query()->create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'institution_id' => $user->staffProfile->institution_id,
            'office' => $office,
            'session_id_hash' => hash('sha256', 'presence-'.$office),
            'auth_session_version' => $authState->session_version,
            'device_type' => 'Desktop',
            'browser' => 'Chrome',
            'os' => 'Linux',
            'ip_address' => '203.0.113.'.$lastOctet,
            'current_path' => '/staff/logs',
            'workspace_area' => 'Audit Logs',
            'logged_in_at' => now(),
            'first_observed_at' => now(),
            'last_active_at' => now(),
        ]);
    }

    $admin = $staffByOffice['admin'];
    $this->actingAs($admin)->get('/staff/logs')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Staff/Logs/Index')
        ->where('presence.sessions', fn ($sessions) => collect($sessions)->pluck('office')->unique()->sort()->values()->all()
            === ['admin', 'guidance', 'registrar'])
        ->where('presence.scope.can_filter_offices', true));

    $guidance = $staffByOffice['guidance'];
    $this->actingAs($guidance)->get('/staff/logs')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Staff/Logs/Index')
        ->where('presence.sessions', fn ($sessions) => collect($sessions)->isNotEmpty()
            && collect($sessions)->every(fn ($session) => $session['office'] === 'guidance'
                && $session['ip_address'] === '203.0.113.xxx'))
        ->where('presence.scope.office', 'guidance')
        ->where('presence.scope.can_filter_offices', false));
});
