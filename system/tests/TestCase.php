<?php

namespace Tests;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Env;

abstract class TestCase extends BaseTestCase
{
    protected function refreshApplication(): void
    {
        $this->pinSqliteTestingEnvironment();

        $this->app = $this->createApplication();
    }

    protected function setUp(): void
    {
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

    protected function pinSqliteTestingEnvironment(): void
    {
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
