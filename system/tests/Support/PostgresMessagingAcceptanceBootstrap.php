<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\DB;

$expectedEnvironment = [
    'APP_ENV' => 'testing',
    'NEXIAM_TEST_PIN_SQLITE' => '0',
    'NEXIAM_TEST_FULL_REFRESH_EACH_TEST' => '1',
    'DB_CONNECTION' => 'pgsql',
    'DB_DATABASE' => 'nexiam_messaging_acceptance',
    'DB_HOST' => 'postgres',
    'DB_PORT' => '5432',
    'DB_USERNAME' => 'nexiam',
    'DB_PASSWORD' => 'nexiam-local-only',
    'DB_URL' => '',
    'APP_CONFIG_CACHE' => '/tmp/nexiam-messaging-postgres-acceptance-config.php',
];

foreach ($expectedEnvironment as $name => $expectedValue) {
    $actualValue = getenv($name);

    if ($actualValue !== $expectedValue) {
        throw new RuntimeException(sprintf(
            'Refusing PostgreSQL messaging acceptance before PHPUnit setup: %s must be exactly %s; got %s.',
            $name,
            var_export($expectedValue, true),
            var_export($actualValue, true),
        ));
    }
}

$configCachePath = getenv('APP_CONFIG_CACHE');
if (is_file($configCachePath)) {
    throw new RuntimeException('Refusing messaging acceptance because the isolated config-cache path already exists.');
}

require dirname(__DIR__, 2).'/vendor/autoload.php';

foreach ($expectedEnvironment as $name => $expectedValue) {
    putenv($name.'='.$expectedValue);
    $_ENV[$name] = $expectedValue;
    $_SERVER[$name] = $expectedValue;
}

Env::enablePutenv();

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$connection = DB::connection();
$resolved = [
    'driver' => $connection->getDriverName(),
    'database' => $connection->getDatabaseName(),
    'host' => $connection->getConfig('host'),
    'port' => (string) $connection->getConfig('port'),
];
$expectedResolved = [
    'driver' => 'pgsql',
    'database' => 'nexiam_messaging_acceptance',
    'host' => 'postgres',
    'port' => '5432',
];

if ($resolved !== $expectedResolved) {
    throw new RuntimeException(sprintf(
        'Refusing PostgreSQL messaging acceptance before PHPUnit migration setup; resolved database config is %s.',
        var_export($resolved, true),
    ));
}
