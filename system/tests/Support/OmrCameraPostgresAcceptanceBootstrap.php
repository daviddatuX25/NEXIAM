<?php

declare(strict_types=1);

const OMR_CAMERA_POSTGRES_ACCEPTANCE_DATABASE = 'nexiam_omr_assisted_verify_20261019_a4c8';

$expectedEnvironment = [
    'APP_ENV' => 'testing',
    'DB_CONNECTION' => 'pgsql',
    'DB_DATABASE' => OMR_CAMERA_POSTGRES_ACCEPTANCE_DATABASE,
    'DB_HOST' => 'postgres',
    'DB_PORT' => '5432',
    'DB_USERNAME' => 'nexiam',
    'DB_PASSWORD' => 'nexiam-local-only',
    'DB_URL' => '',
    'NEXIAM_TEST_PIN_SQLITE' => '0',
    'CACHE_STORE' => 'array',
    'QUEUE_CONNECTION' => 'sync',
    'SESSION_DRIVER' => 'array',
];

foreach ($expectedEnvironment as $key => $expected) {
    if (getenv($key) !== $expected) {
        fwrite(STDERR, 'Refusing OMR camera PostgreSQL acceptance outside exact target: '.$key." mismatch.\n");
        exit(1);
    }
}

$configCache = sys_get_temp_dir().'/omr-camera-postgres-'.getmypid().'-'.bin2hex(random_bytes(8)).'-config.php';
putenv('APP_CONFIG_CACHE='.$configCache);
$_ENV['APP_CONFIG_CACHE'] = $configCache;
$_SERVER['APP_CONFIG_CACHE'] = $configCache;

$pdo = new PDO(
    'pgsql:host=postgres;port=5432;dbname='.OMR_CAMERA_POSTGRES_ACCEPTANCE_DATABASE,
    'nexiam',
    'nexiam-local-only',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
);
if ((string) $pdo->query('SELECT current_database()')->fetchColumn() !== OMR_CAMERA_POSTGRES_ACCEPTANCE_DATABASE) {
    fwrite(STDERR, "Refusing OMR camera PostgreSQL acceptance outside exact target database.\n");
    exit(1);
}

require dirname(__DIR__, 2).'/vendor/autoload.php';
