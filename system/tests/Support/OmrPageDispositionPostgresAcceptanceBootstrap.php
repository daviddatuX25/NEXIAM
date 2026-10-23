<?php

declare(strict_types=1);
use Illuminate\Container\Container;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Bootstrap\HandleExceptions;
use Illuminate\Support\Facades\DB;

const OMR_PAGE_DISPOSITION_POSTGRES_DATABASE = 'nexiam_test_kit_acceptance_omr_disposition_20261023';

$expectedEnvironment = [
    'APP_ENV' => 'testing',
    'DB_CONNECTION' => 'pgsql',
    'DB_DATABASE' => OMR_PAGE_DISPOSITION_POSTGRES_DATABASE,
    'DB_HOST' => 'postgres',
    'DB_PORT' => '5432',
    'DB_USERNAME' => 'nexiam',
    'DB_PASSWORD' => 'nexiam-local-only',
    'DB_URL' => '',
    'NEXIAM_TEST_PIN_SQLITE' => '0',
    'CACHE_STORE' => 'array',
    'QUEUE_CONNECTION' => 'sync',
    'SESSION_DRIVER' => 'array',
    'BROADCAST_CONNECTION' => 'null',
    'MAIL_MAILER' => 'array',
    'PULSE_ENABLED' => 'false',
    'TELESCOPE_ENABLED' => 'false',
    'NIGHTWATCH_ENABLED' => 'false',
    'PEST_BROWSER_ENABLED' => 'false',
    'OMR_PROVIDER' => 'fake',
    'OMR_ALLOW_FAKE_PROVIDER' => 'true',
    'XIAM_AI_GATEWAY' => 'fake',
    'INERTIA_DEVTOOLS_ENABLED' => 'false',
];

foreach ($expectedEnvironment as $key => $expected) {
    putenv($key.'='.$expected);
    $_ENV[$key] = $expected;
    $_SERVER[$key] = $expected;
}

$configCache = sys_get_temp_dir().'/omr-page-disposition-'.getmypid().'-'.bin2hex(random_bytes(8)).'-config.php';
putenv('APP_CONFIG_CACHE='.$configCache);
$_ENV['APP_CONFIG_CACHE'] = $configCache;
$_SERVER['APP_CONFIG_CACHE'] = $configCache;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$connection = DB::connection();

if (! app()->environment('testing')
    || config('database.default') !== 'pgsql'
    || (string) config('database.connections.pgsql.database') !== OMR_PAGE_DISPOSITION_POSTGRES_DATABASE
    || $connection->getName() !== 'pgsql'
    || $connection->getDatabaseName() !== OMR_PAGE_DISPOSITION_POSTGRES_DATABASE
    || (string) $connection->selectOne('SELECT current_database() AS name')->name !== OMR_PAGE_DISPOSITION_POSTGRES_DATABASE) {
    fwrite(STDERR, "Refusing OMR page-disposition tests: effective Laravel app/database did not match isolated PostgreSQL target.\n");
    exit(1);
}
fwrite(STDOUT, sprintf(
    "Verified Laravel app_env=%s db_connection=%s database=%s current_database=%s\n",
    app()->environment(),
    config('database.default'),
    $connection->getDatabaseName(),
    (string) $connection->selectOne('SELECT current_database() AS name')->name,
));
HandleExceptions::flushState();
Container::setInstance(null);
