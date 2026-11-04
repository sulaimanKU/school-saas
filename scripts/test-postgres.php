<?php

use Illuminate\Contracts\Console\Kernel;

// Run the same suite in a newly created, isolated PostgreSQL schema, never in public.
require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if ($app->configurationIsCached()) {
    throw new RuntimeException('Run php artisan config:clear before isolated PostgreSQL tests. Cached database configuration must never be used for this runner.');
}
$connection = $app['db']->connection('pgsql');
$settings = $connection->getConfig();
$schema = 'phase1_test_'.bin2hex(random_bytes(8));
$connection->statement('CREATE SCHEMA "'.$schema.'"');
$exit = 1;
try {
    $environment = array_merge(getenv(), [
        'APP_ENV' => 'testing', 'DB_CONNECTION' => 'pgsql', 'DB_URL' => '', 'DB_SCHEMA' => $schema,
        'SESSION_DRIVER' => 'array', 'CACHE_STORE' => 'array', 'QUEUE_CONNECTION' => 'sync',
        'LOCAL_PREVIEW_ENABLED' => 'false', 'LOCAL_ACADEMIC_CRUD_ENABLED' => 'false', 'LOCAL_FINANCE_ENABLED' => 'false', 'MAIL_MAILER' => 'array', 'APP_MAINTENANCE_DRIVER' => 'file',
        'DB_HOST' => $settings['host'], 'DB_PORT' => (string) $settings['port'], 'DB_DATABASE' => $settings['database'],
        'DB_USERNAME' => $settings['username'], 'DB_PASSWORD' => $settings['password'],
    ]);
    $process = proc_open([PHP_BINARY, 'vendor/phpunit/phpunit/phpunit', '--colors=never'], [0 => STDIN, 1 => STDOUT, 2 => STDERR], $pipes, dirname(__DIR__), $environment);
    if (! is_resource($process)) {
        throw new RuntimeException('Could not start test runner.');
    }
    $exit = proc_close($process);
} finally {
    // Identifier was generated here, never supplied externally; public is never a target.
    if (! preg_match('/\Aphase1_test_[a-f0-9]{16}\z/', $schema)) {
        throw new RuntimeException('Unsafe schema name');
    }
    $connection->statement('DROP SCHEMA "'.$schema.'" CASCADE');
}
exit($exit);
