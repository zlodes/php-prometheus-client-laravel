<?php

declare(strict_types=1);

namespace Zlodes\PrometheusExporter\Laravel\Tests\Commands;

use Mockery;
use Orchestra\Testbench\TestCase;
use Zlodes\PrometheusExporter\Laravel\ServiceProvider;
use Zlodes\PrometheusExporter\Storage\Storage;

class FlushMetricsTest extends TestCase
{
    public function testCommandRun(): void
    {
        $this->app->instance(
            Storage::class,
            $storageMock = Mockery::mock(Storage::class),
        );

        $storageMock
            ->expects('flush');

        $this
            ->artisan('metrics:flush')
            ->assertSuccessful();
    }

    protected function getPackageProviders($app): array
    {
        return [
            ServiceProvider::class,
        ];
    }
}
