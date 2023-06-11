<?php

declare(strict_types=1);

namespace Zlodes\PrometheusClient\Laravel\Tests\Commands;

use Mockery;
use Orchestra\Testbench\TestCase;
use Psr\Log\LoggerInterface;
use Zlodes\PrometheusClient\Collector\Collector;
use Zlodes\PrometheusClient\Laravel\ScheduledCollector\SchedulableCollectorRegistry;
use Zlodes\PrometheusClient\Laravel\ServiceProvider;
use Zlodes\PrometheusClient\Laravel\Tests\Dummy\DummySchedulableCollector;

class ScheduledCollectTest extends TestCase
{
    public function testCommandRunWithEmptyCollector(): void
    {
        $this->app->instance(
            SchedulableCollectorRegistry::class,
            $schedulableCollectorRegistryMock = Mockery::mock(SchedulableCollectorRegistry::class)
        );

        $schedulableCollectorRegistryMock
            ->expects('getAll')
            ->andReturn([]);

        $this
            ->artisan('metrics:collect-scheduled')
            ->assertSuccessful();
    }

    public function testCommandRunWithNotEmptyCollector(): void
    {
        $this->app->instance(
            SchedulableCollectorRegistry::class,
            $schedulableCollectorRegistryMock = Mockery::mock(SchedulableCollectorRegistry::class)
        );

        $this->app->instance(
            Collector::class,
            $collectorMock = Mockery::mock(Collector::class)
        );

        $schedulableCollectorRegistryMock
            ->expects('getAll')
            ->andReturn([
                DummySchedulableCollector::class
            ]);

        $collectorMock
            ->expects('counterIncrement')
            ->with('hello_form_schedule');

        $this
            ->artisan('metrics:collect-scheduled')
            ->assertSuccessful();
    }

    public function testCommandRunWithWrongSchedulableClass(): void
    {
        $this->app->instance(
            SchedulableCollectorRegistry::class,
            $schedulableCollectorRegistryMock = Mockery::mock(SchedulableCollectorRegistry::class)
        );

        $this->app->instance(
            LoggerInterface::class,
            $loggerMock = Mockery::mock(LoggerInterface::class)
        );

        $schedulableCollectorRegistryMock
            ->expects('getAll')
            ->andReturn([
                'Not a class name',
            ]);

        $loggerMock
            ->allows('info');

        /** @var string $logMessage */
        $loggerMock
            ->expects('error')
            ->with(Mockery::capture($logMessage), Mockery::any());

        $this
            ->artisan('metrics:collect-scheduled')
            ->assertSuccessful();

        self::assertStringContainsString('Cannot collect scheduled metric', $logMessage);
    }

    protected function getPackageProviders($app): array
    {
        return [
            ServiceProvider::class,
        ];
    }
}
