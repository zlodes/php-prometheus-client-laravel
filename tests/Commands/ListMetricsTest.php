<?php

declare(strict_types=1);

namespace Zlodes\PrometheusExporter\Laravel\Tests\Commands;

use EmptyIterator;
use Generator;
use Mockery;
use Orchestra\Testbench\TestCase;
use Zlodes\PrometheusExporter\Laravel\ServiceProvider;
use Zlodes\PrometheusExporter\MetricTypes\Counter;
use Zlodes\PrometheusExporter\Registry\Registry;

class ListMetricsTest extends TestCase
{
    public function testCommandRunWithEmptyRegistry(): void
    {
        $this->app->instance(
            Registry::class,
            $registryMock = Mockery::mock(Registry::class),
        );

        $registryMock
            ->expects('getAll')
            ->andReturn(new EmptyIterator());

        $this
            ->artisan('metrics:list')
            ->assertSuccessful();
    }

    public function testCommandRunWithNonEmptyRegistry(): void
    {
        $this->app->instance(
            Registry::class,
            $registryMock = Mockery::mock(Registry::class),
        );

        $registryMock
            ->expects('getAll')
            ->andReturnUsing(static function (): Generator {
                yield new Counter('foo', 'help');
                yield new Counter('bar', 'help');
                yield new Counter('baz', 'help');
            });

        $this
            ->artisan('metrics:list')
            ->assertSuccessful();
    }

    protected function getPackageProviders($app): array
    {
        return [
            ServiceProvider::class,
        ];
    }
}
