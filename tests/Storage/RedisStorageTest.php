<?php

declare(strict_types=1);

namespace Zlodes\PrometheusExporter\Laravel\Tests\Storage;

use Orchestra\Testbench\TestCase;
use Zlodes\PrometheusExporter\Laravel\Storage\RedisStorage;
use Zlodes\PrometheusExporter\Storage\StorageTesting;

class RedisStorageTest extends TestCase
{
    use StorageTesting;

    private function createStorage(): RedisStorage
    {
        /** @var RedisStorage */
        return $this->app->make(RedisStorage::class);
    }
}
