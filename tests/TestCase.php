<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();
        $connection = $app['config']->get('database.default');
        $database = $app['config']->get("database.connections.{$connection}");
        // This executes before RefreshDatabase can run any destructive test migrations.
        if ($connection === 'pgsql' && ! preg_match('/\Aphase1_test_[a-f0-9]{16}\z/', $database['search_path'] ?? '')) {
            throw new \RuntimeException('PostgreSQL tests require the isolated schema runner: php scripts/test-postgres.php');
        }
        if ($connection === 'sqlite' && ($database['database'] ?? '') !== ':memory:') {
            throw new \RuntimeException('SQLite tests require an in-memory database.');
        }

        return $app;
    }
}
