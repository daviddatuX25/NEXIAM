<?php

declare(strict_types=1);

/**
 * Validate the isolated PostgreSQL target before Laravel tests can perform any fixture writes.
 *
 * This bootstrap runs before PHPUnit snapshots test error handlers. Booting Laravel here
 * would install Laravel's exception handler too early and make each test appear risky.
 */
$requiredEnvironment = [
    'APP_ENV' => 'testing',
    'DB_CONNECTION' => 'pgsql',
    'DB_HOST' => 'postgres',
    'DB_PORT' => '5432',
    'DB_USERNAME' => 'nexiam',
    'DB_PASSWORD' => 'nexiam-local-only',
    'DB_URL' => '',
    'NEXIAM_TEST_PIN_SQLITE' => '0',
];
foreach ($requiredEnvironment as $name => $expected) {
    if (getenv($name) !== $expected) {
        throw new RuntimeException(sprintf('Refusing Test Kit PostgreSQL acceptance: %s must be exactly %s.', $name, var_export($expected, true)));
    }
}

$database = (string) getenv('DB_DATABASE');
if (! preg_match('/^nexiam_test_kit_acceptance_[a-z0-9_]+$/', $database) || $database === 'nexiam' || strlen($database) > 63) {
    throw new RuntimeException('Refusing Test Kit PostgreSQL acceptance outside a unique, valid nexiam_test_kit_acceptance_* database.');
}
if ((string) (getenv('NEXIAM_TEST_KIT_SOURCE_DATABASE') ?: 'nexiam') !== 'nexiam') {
    throw new RuntimeException('Refusing Test Kit PostgreSQL acceptance because the structure-only source must be the local nexiam database.');
}

$configCachePath = sys_get_temp_dir().'/'.$database.'-config.php';
if (file_exists($configCachePath)) {
    throw new RuntimeException('Refusing Test Kit acceptance because the unique database-specific config-cache path already exists.');
}
foreach ([...$requiredEnvironment, 'DB_DATABASE' => $database, 'APP_CONFIG_CACHE' => $configCachePath] as $name => $value) {
    putenv($name.'='.$value);
    $_ENV[$name] = $value;
    $_SERVER[$name] = $value;
}

$connection = new PDO(
    sprintf('pgsql:host=%s;port=%s;dbname=%s', getenv('DB_HOST'), getenv('DB_PORT'), $database),
    (string) getenv('DB_USERNAME'),
    (string) getenv('DB_PASSWORD'),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
);
$currentDatabase = (string) $connection->query('SELECT current_database()')->fetchColumn();
if ($currentDatabase !== $database || ! preg_match('/^nexiam_test_kit_acceptance_[a-z0-9_]+$/', $currentDatabase)) {
    throw new RuntimeException('Refusing Test Kit acceptance because PostgreSQL current_database() is not the guarded unique acceptance database.');
}
