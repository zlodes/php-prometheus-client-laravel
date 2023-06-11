<?php

declare(strict_types=1);

namespace Zlodes\PrometheusClient\Laravel\Tests\Commands;

use Mockery;
use Orchestra\Testbench\TestCase;
use Zlodes\PrometheusClient\Laravel\ServiceProvider;
use Zlodes\PrometheusClient\Storage\Storage;

class ClearMetricsTest extends TestCase
{
    public function testCommandRun(): void
    {
        $this->app->instance(
            Storage::class,
            $storageMock = Mockery::mock(Storage::class),
        );

        $storageMock
            ->expects('clear');

        $this
            ->artisan('metrics:clear')
            ->assertSuccessful();
    }

    protected function getPackageProviders($app): array
    {
        return [
            ServiceProvider::class,
        ];
    }
}
