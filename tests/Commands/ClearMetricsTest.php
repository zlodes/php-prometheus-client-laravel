<?php

declare(strict_types=1);

namespace Zlodes\PrometheusExporter\Laravel\Tests\Commands;

use Mockery;
use Orchestra\Testbench\TestCase;
use Zlodes\PrometheusExporter\Laravel\ServiceProvider;
use Zlodes\PrometheusExporter\Storage\Storage;

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
