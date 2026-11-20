<?php

namespace Tests;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Saloon\Config;
use Tests\Support\ProviderHttp;

abstract class TestCase extends BaseTestCase
{
    private static bool $blocksStrayProviderRequests = false;

    public function createApplication(): Application
    {
        $app = parent::createApplication();
        $config = $app->make('config');
        $driver = $config->string('database.default');
        $connection = $config->array('database.connections.'.$driver, []);
        $database = $connection['database'] ?? null;
        $safe = ($driver === 'sqlite' && $database === ':memory:')
            || ($driver === 'pgsql' && $database === 'spectacles_testing');
        if (! $app->environment('testing') || ! $safe || ! empty($connection['url'])) {
            throw new \LogicException('Tests require in-memory SQLite or the dedicated spectacles_testing PostgreSQL database, without DB_URL.');
        }

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        ProviderHttp::reset();
        if (! self::$blocksStrayProviderRequests) {
            Config::preventStrayRequests();
            self::$blocksStrayProviderRequests = true;
        }
    }

    protected function tearDown(): void
    {
        ProviderHttp::reset();
        parent::tearDown();
    }

    /**
     * Return a value that the test requires to be present, failing the test when it is null.
     *
     * @template TValue
     *
     * @param  TValue|null  $value
     * @return TValue
     */
    protected function present(mixed $value): mixed
    {
        $this->assertNotNull($value);

        return $value;
    }

    /**
     * Read an array from decoded JSON, failing the test when the value at the dot-notation path is not an array.
     *
     * @return array<array-key, mixed>
     */
    protected function arrayAt(mixed $document, string|int|null $path = null): array
    {
        $value = $path === null ? $document : data_get($document, $path);
        $this->assertIsArray($value, 'Expected an array at ['.($path ?? '').'].');

        return $value;
    }

    /**
     * Read a list of arrays from decoded JSON, failing the test when any item at the dot-notation path is not an array.
     *
     * @return list<array<array-key, mixed>>
     */
    protected function arraysAt(mixed $document, string|int|null $path = null): array
    {
        return array_map(fn (mixed $item): array => $this->arrayAt($item), array_values($this->arrayAt($document, $path)));
    }

    /**
     * Read a string from decoded JSON, failing the test when the value at the dot-notation path is not a string.
     */
    protected function stringAt(mixed $document, string|int $path): string
    {
        $value = data_get($document, $path);
        $this->assertIsString($value, 'Expected a string at ['.$path.'].');

        return $value;
    }

    /**
     * Read an integer from decoded JSON, failing the test when the value at the dot-notation path is not an integer.
     */
    protected function intAt(mixed $document, string|int $path): int
    {
        $value = data_get($document, $path);
        $this->assertIsInt($value, 'Expected an integer at ['.$path.'].');

        return $value;
    }

    /**
     * @param  \Closure(): mixed  $write
     */
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
