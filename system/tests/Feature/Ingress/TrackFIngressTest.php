<?php

declare(strict_types=1);

use App\Http\Middleware\EnsureActiveStaffSession;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SelectPracticeWorkspace;
use App\Models\Auth\StaffEmailChange;
use App\Models\User;
use App\Testing\PracticeWorkspace;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Cache\RateLimiting\Unlimited;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

function assertTrackFBaselineHeaders(TestResponse $response): void
{
    $response->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('X-XSS-Protection', '0')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
}

test('global security headers cover health, redirect, denied, server error, and not found responses', function (): void {
    Route::get('/__track-f/redirect', fn () => redirect('/'))->middleware('web');
    Route::get('/__track-f/denied', fn () => abort(403))->middleware('web');
    Route::get('/__track-f/error', fn () => abort(500))->middleware('web');

    foreach ([
        $this->get('/up')->assertOk(),
        $this->get('/__track-f/redirect')->assertRedirect('/'),
        $this->get('/__track-f/denied')->assertForbidden(),
        $this->get('/__track-f/error')->assertServerError(),
        $this->get('/__track-f/not-found')->assertNotFound(),
    ] as $response) {
        assertTrackFBaselineHeaders($response);
        $response->assertHeaderMissing('Content-Security-Policy');
        $response->assertHeaderMissing('Strict-Transport-Security');
    }
});

test('stricter route security headers are preserved by global defaults', function (): void {
    Route::get('/__track-f/strict-headers', fn () => response('strict')->withHeaders([
        'X-Frame-Options' => 'DENY',
        'Referrer-Policy' => 'no-referrer',
        'Content-Security-Policy' => "default-src 'none'",
        'Strict-Transport-Security' => 'max-age=0',
    ]))->middleware('web');

    $this->withServerVariables(['HTTPS' => 'on'])->get('/__track-f/strict-headers')
        ->assertOk()
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Referrer-Policy', 'no-referrer')
        ->assertHeader('Content-Security-Policy', "default-src 'none'")
        ->assertHeader('Strict-Transport-Security', 'max-age=0');
});

test('staff email confirmation token response retains no-referrer', function (): void {
    seedE2Foundation();
    $staff = User::query()->where('email', 'program.head.bsit@nexiam.test')->firstOrFail();
    $sessionVersion = (int) $staff->authenticationState->session_version;
    $token = Str::random(64);
    StaffEmailChange::query()->create([
        'id' => (string) Str::uuid(),
        'user_id' => $staff->id,
        'initiator_user_id' => $staff->id,
        'old_email' => $staff->email,
        'new_email' => 'confirmed.staff@example.test',
        'expected_session_version' => $sessionVersion,
        'token_digest' => hash('sha256', $token),
        'expires_at' => now()->addMinutes(15),
    ]);

    $this->actingAs($staff)->withSession([
        'auth.surface' => 'staff',
        'auth.session_version' => $sessionVersion,
    ])->get('/staff/profile/email/confirm/'.$token)
        ->assertOk()
        ->assertHeader('Referrer-Policy', 'no-referrer')
        ->assertHeader('Cache-Control', 'no-store, private');
});

test('HSTS is emitted for secure requests without extending to subdomains', function (): void {
    Route::get('/__track-f/secure', fn () => response('secure'))->middleware('web');

    $response = $this->withServerVariables(['HTTPS' => 'on'])->get('/__track-f/secure')->assertOk();

    assertTrackFBaselineHeaders($response);
    $response->assertHeader('Strict-Transport-Security', 'max-age=31536000');
});

test('untrusted forwarding headers do not change request identity or secure state', function (): void {
    Route::get('/__track-f/request-context', fn (Request $request) => response()->json([
        'ip' => $request->ip(),
        'secure' => $request->isSecure(),
    ]))->middleware('web');

    $response = $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.80'])->get('/__track-f/request-context', [
        'X-Forwarded-For' => '198.51.100.44',
        'X-Forwarded-Proto' => 'https',
    ])->assertOk();

    $response->assertExactJson(['ip' => '192.0.2.80', 'secure' => false])
        ->assertHeaderMissing('Strict-Transport-Security');
});

test('framework hosting-domain heuristics cannot trust a direct attacker or an ordinary LAN peer', function (): void {
    Route::get('/__track-f/request-context', fn (Request $request) => response()->json([
        'ip' => $request->ip(),
    ]))->middleware('web');
    TrustProxies::at(['127.0.0.1/32', '::1/128']);

    foreach (['nexiam.on-forge.com', 'nexiam.on-vapor.com'] as $host) {
        $response = $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.80'])->get('/__track-f/request-context', [
            'Host' => $host,
            'X-Forwarded-For' => '198.51.100.44',
            'X-Forwarded-Proto' => 'https',
        ])->assertOk();

        $response->assertExactJson(['ip' => '192.0.2.80', 'secure' => false])
            ->assertHeaderMissing('Strict-Transport-Security');
    }
});

test('loopback IPv6 proxy is explicitly trusted without trusting a LAN peer', function (): void {
    Route::get('/__track-f/request-context', fn (Request $request) => response()->json([
        'ip' => $request->ip(),
        'secure' => $request->isSecure(),
    ]))->middleware('web');
    TrustProxies::at(['127.0.0.1/32', '::1/128']);

    $response = $this->withServerVariables(['REMOTE_ADDR' => '::1'])->get('/__track-f/request-context', [
        'X-Forwarded-For' => '198.51.100.44',
        'X-Forwarded-Proto' => 'https',
    ])->assertOk();

    $response->assertExactJson(['ip' => '198.51.100.44', 'secure' => true]);
});

test('configured proxy CIDRs control forwarded request identity and HTTPS', function (): void {
    Route::get('/__track-f/request-context', fn (Request $request) => response()->json([
        'ip' => $request->ip(),
        'secure' => $request->isSecure(),
    ]))->middleware('web');
    TrustProxies::at(['192.0.2.0/24']);

    $response = $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.80'])->get('/__track-f/request-context', [
        'X-Forwarded-For' => '198.51.100.44',
        'X-Forwarded-Proto' => 'https',
    ])->assertOk();

    $response->assertExactJson(['ip' => '198.51.100.44', 'secure' => true])
        ->assertHeader('Strict-Transport-Security', 'max-age=31536000');
});

test('trusted proxy configuration is an environment-derived array without wildcard or broad network defaults', function (): void {
    expect(config('app.trusted_proxies'))->toBeArray()
        ->and(config('app.trusted_proxies'))->not->toContain('*')
        ->and(config('app.trusted_proxies'))->not->toContain('10.0.0.0/8')
        ->and(config('app.trusted_proxies'))->not->toContain('172.16.0.0/12');
});

test('named limiter thresholds are configured and equal-limit routes keep independent buckets', function (): void {
    $request = Request::create('/');
    $searchLimiter = RateLimiter::limiter('search-suggestions');
    $consultationLimiter = RateLimiter::limiter('consultation-lookup');
    $xiamLimiter = RateLimiter::limiter('xiam-turns');
    $intakeLimiter = RateLimiter::limiter('intake-apply');

    expect($searchLimiter)->toBeCallable()
        ->and($consultationLimiter)->toBeCallable()
        ->and($xiamLimiter)->toBeCallable()
        ->and($intakeLimiter)->toBeCallable();
    expect($searchLimiter($request)->maxAttempts)->toBe(60)
        ->and($consultationLimiter($request)->maxAttempts)->toBe(60)
        ->and($xiamLimiter($request)->maxAttempts)->toBe(30)
        ->and($intakeLimiter($request)->maxAttempts)->toBe(10);

    Route::get('/__track-f/search', fn () => response('ok'))->middleware('throttle:search-suggestions');
    Route::get('/__track-f/consultation', fn () => response('ok'))->middleware('throttle:consultation-lookup');
    Route::post('/__track-f/xiam', fn () => response('ok'))->middleware('throttle:xiam-turns');
    Route::post('/__track-f/intake', fn () => response('ok'))->middleware('throttle:intake-apply');

    foreach (range(1, 60) as $_) {
        $this->get('/__track-f/search')->assertOk();
    }
    $searchThrottled = $this->get('/__track-f/search')->assertTooManyRequests();
    assertTrackFBaselineHeaders($searchThrottled);
    expect($searchThrottled->headers->has('Retry-After'))->toBeTrue();

    foreach (range(1, 60) as $_) {
        $this->get('/__track-f/consultation')->assertOk();
    }
    $consultationThrottled = $this->get('/__track-f/consultation')->assertTooManyRequests();
    expect($consultationThrottled->headers->has('Retry-After'))->toBeTrue();

    foreach (range(1, 30) as $_) {
        $this->post('/__track-f/xiam')->assertOk();
    }
    $xiamThrottled = $this->post('/__track-f/xiam')->assertTooManyRequests();
    expect($xiamThrottled->headers->has('Retry-After'))->toBeTrue();
    TrustProxies::at(['127.0.0.1/32', '::1/128']);
    foreach (range(1, 10) as $attempt) {
        $this->withServerVariables(['REMOTE_ADDR' => '192.168.1.44'])->post('/__track-f/intake', [], [
            'X-Forwarded-For' => '198.51.100.'.$attempt,
        ])->assertOk();
    }
    $intakeThrottled = $this->withServerVariables(['REMOTE_ADDR' => '192.168.1.44'])->post('/__track-f/intake', [], [
        'X-Forwarded-For' => '203.0.113.200',
    ])->assertTooManyRequests();
    expect($intakeThrottled->headers->has('Retry-After'))->toBeTrue();
});

test('authenticated Xiam limiter is keyed by staff principal rather than forwarded client IP', function (): void {
    Route::post('/__track-f/xiam-principal', fn () => response('ok'))->middleware('throttle:xiam-turns');
    $staff = User::factory()->create();
    $this->actingAs($staff);

    foreach (range(1, 30) as $_) {
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.41'])->post('/__track-f/xiam-principal')->assertOk();
    }

    $throttled = $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.42'])
        ->post('/__track-f/xiam-principal')->assertTooManyRequests();

    expect($throttled->headers->has('Retry-After'))->toBeTrue();
});

test('consultation lookup and dashboard searches use the named limiter', function (): void {
    foreach (['consultation.lookup', 'consultation.index'] as $routeName) {
        expect(Route::getRoutes()->getByName($routeName)?->gatherMiddleware())
            ->toContain('throttle:consultation-lookup');
    }
});

test('practice workspace binding resolves one request-scoped instance', function (): void {
    $workspace = app(PracticeWorkspace::class);

    expect(app(PracticeWorkspace::class))->toBe($workspace)
        ->and(app()->isShared(PracticeWorkspace::class))->toBeTrue();
});

test('ordinary consultation dashboard loads bypass the lookup limiter while searching is limited', function (): void {
    $indexRoute = Route::get('/consultation', fn () => response('dashboard'))
        ->name('consultation.index');
    $request = Request::create('/consultation');
    $request->setRouteResolver(fn () => $indexRoute);
    $consultationLimiter = RateLimiter::limiter('consultation-lookup');

    expect($consultationLimiter($request))->toBeInstanceOf(Unlimited::class);

    $request->query->set('q', 'Smith');

    expect($consultationLimiter($request))->toBeInstanceOf(Limit::class)
        ->and($consultationLimiter($request)->maxAttempts)->toBe(60);
});

test('invalid staff epoch is rejected before practice workspace or inertia session projection', function (): void {
    seedE2Foundation();
    $staff = User::query()->where('email', 'program.head.bsit@nexiam.test')->firstOrFail();
    $sessionVersion = (int) $staff->authenticationState->session_version;
    $staff->authenticationState->forceFill(['session_version' => $sessionVersion + 1])->save();

    $staffRoute = Route::getRoutes()->match(Request::create('/staff', 'GET'));
    $middleware = app('router')->gatherRouteMiddleware($staffRoute);
    $middlewareNames = array_map(
        static fn (string|Closure $name): string => is_string($name) ? explode(':', $name, 2)[0] : $name::class,
        $middleware,
    );
    $sessionGateIndex = array_search(EnsureActiveStaffSession::class, $middlewareNames, true);
    $workspaceIndex = array_search(SelectPracticeWorkspace::class, $middlewareNames, true);
    $inertiaIndex = array_search(HandleInertiaRequests::class, $middlewareNames, true);

    expect($sessionGateIndex)->toBeInt()
        ->toBeLessThan($workspaceIndex)
        ->toBeLessThan($inertiaIndex);

    $this->actingAs($staff)->withSession([
        'auth.surface' => 'staff',
        'auth.session_version' => $sessionVersion,
    ])->get('/staff')
        ->assertRedirect(route('login'))
        ->assertSessionMissing('auth.staff_inactivity_generation')
        ->assertSessionMissing('practice_fixture_set_id');
});
