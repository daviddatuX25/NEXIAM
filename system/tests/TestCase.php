<?php

namespace Tests;

use App\Models\Auth\StaffSessionLease;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

abstract class TestCase extends BaseTestCase
{
    protected function refreshApplication(): void
    {
        $this->pinSqliteTestingEnvironment();

        if (getenv('DOCUMENT_RECOVERY_SYNTHETIC_ENV') === '1') {
            $viewCachePath = __DIR__.'/../storage/framework/views';
            if (! is_dir($viewCachePath) && ! mkdir($viewCachePath, 0700, true) && ! is_dir($viewCachePath)) {
                throw new \RuntimeException("Unable to create the isolated Blade view cache directory: {$viewCachePath}");
            }

            if (! is_writable($viewCachePath)) {
                throw new \RuntimeException("The isolated Blade view cache directory is not writable: {$viewCachePath}");
            }
        }

        $this->app = $this->createApplication();
    }

    protected function setUp(): void
    {

        if (getenv('NEXIAM_TEST_FULL_REFRESH_EACH_TEST') === '1' && ! RefreshDatabaseState::$migrated) {
            $connection = DB::connection();
            if ($connection->getDriverName() !== 'pgsql'
                || $connection->getDatabaseName() !== 'nexiam_messaging_acceptance') {
                throw new \RuntimeException('Refusing per-test full migration refresh outside the dedicated PostgreSQL messaging acceptance database.');
            }

            DB::statement('DROP SCHEMA public CASCADE');
            DB::statement('CREATE SCHEMA public AUTHORIZATION CURRENT_USER');
            RefreshDatabaseState::$migrated = false;
        }

        parent::setUp();

        $this->withoutMiddleware([
            PreventRequestForgery::class,
            ValidateCsrfToken::class,
            VerifyCsrfToken::class,
        ]);

        // Linux is case-sensitive; app pages live under resources/js/Pages.
        config([
            'inertia.pages.paths' => [resource_path('js/Pages')],
            'inertia.devtools.enabled' => false,
        ]);
    }

    public function actingAs(Authenticatable $user, $guard = null)
    {
        parent::actingAs($user, $guard);

        if ($user instanceof User) {
            $state = $user->authenticationState()->first();
            if ($state !== null && $user->staffProfile()->exists()) {
                $this->app['session']->start();
                $sessionId = (string) $this->app['session']->getId();
                $generation = (string) Str::uuid();

                $this->app['session']->put('auth.surface', 'staff');
                $this->app['session']->put('auth.session_version', (int) $state->session_version);
                $this->app['session']->put('auth.staff_inactivity_generation', $generation);

                StaffSessionLease::query()->updateOrCreate(
                    [
                        'session_id_hash' => hash_hmac('sha256', $sessionId, (string) config('app.key')),
                    ],
                    [
                        'user_id' => $user->getKey(),
                        'inactivity_generation' => $generation,
                        'authenticated_at' => now(),
                        'idle_expires_at' => now()->addMinutes(15),
                        'absolute_expires_at' => now()->addHours(8),
                        'effective_idle_minutes' => 15,
                        'revoked_at' => null,
                    ]
                );

                return $this;
            }
        }

        $this->app['session']->forget(['auth.surface', 'auth.session_version', 'auth.staff_inactivity_generation']);

        return $this;
    }

    public function withSession(array $data)
    {
        if ($data === [] || array_key_exists('auth.surface', $data) || array_key_exists('auth.session_version', $data)) {
            $this->app['session']->forget(['auth.surface', 'auth.session_version']);
        }

        $result = parent::withSession($data);
        $this->app['session']->save();
        $this->app['session']->start();

        return $result;
    }

    protected function pinSqliteTestingEnvironment(): void
    {
        // NEXIAM_TEST_PIN_SQLITE=0 keeps the caller's DB_* env (Compose
        // postgres runs, e.g. 35.42 focused suites against real CHECK/FK
        // constraints). Any other value pins the in-memory sqlite sandbox.
        if (getenv('NEXIAM_TEST_PIN_SQLITE') === '0') {
            return;
        }

        putenv('APP_ENV=testing');
        putenv('DB_CONNECTION=sqlite');
        putenv('DB_DATABASE=:memory:');
        putenv('DB_URL');
        putenv('DB_HOST');
        putenv('INERTIA_DEVTOOLS_ENABLED=false');
        putenv('QUEUE_CONNECTION=sync');
        putenv('MAIL_MAILER=log');

        $_ENV['APP_ENV'] = 'testing';
        $_ENV['DB_CONNECTION'] = 'sqlite';
        $_ENV['DB_DATABASE'] = ':memory:';
        $_ENV['DB_URL'] = '';
        $_ENV['DB_HOST'] = '';
        $_ENV['INERTIA_DEVTOOLS_ENABLED'] = 'false';
        $_ENV['QUEUE_CONNECTION'] = 'sync';
        $_ENV['MAIL_MAILER'] = 'log';

        $_SERVER['APP_ENV'] = 'testing';
        $_SERVER['DB_CONNECTION'] = 'sqlite';
        $_SERVER['DB_DATABASE'] = ':memory:';
        $_SERVER['DB_URL'] = '';
        $_SERVER['DB_HOST'] = '';
        $_SERVER['INERTIA_DEVTOOLS_ENABLED'] = 'false';
        $_SERVER['QUEUE_CONNECTION'] = 'sync';
        $_SERVER['MAIL_MAILER'] = 'log';

        // artisan test may already have created an immutable Env repository
        // from Compose's pgsql process environment; rebuild it for the test app.
        Env::enablePutenv();
    }
}
