<?php

namespace Tests;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $app = parent::createApplication();
        $driver = $app['config']->get('database.default');
        $connection = $app['config']->get('database.connections.'.$driver, []);
        $database = $connection['database'] ?? null;
        $safe = ($driver === 'sqlite' && $database === ':memory:')
            || ($driver === 'pgsql' && $database === 'spectacles_testing');
        if (! $app->environment('testing') || ! $safe || ! empty($connection['url'])) {
            throw new \LogicException('Tests require in-memory SQLite or the dedicated spectacles_testing PostgreSQL database, without DB_URL.');
        }

        return $app;
    }

    protected function assertDatabaseRejects(\Closure $write): void
    {
        $error = null;
        try {
            DB::transaction($write);
        } catch (QueryException $exception) {
            $error = $exception;
        }
        $this->assertNotNull($error, 'The database accepted an invalid write.');
        $this->assertContains((string) $error->getCode(), ['23000', '23001', '23502', '23503', '23505', '23514'], $error->getMessage());
    }
}
