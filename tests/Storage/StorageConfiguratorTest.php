<?php

declare(strict_types=1);

namespace Zlodes\PrometheusClient\Laravel\Tests\Storage;

use InvalidArgumentException;
use Orchestra\Testbench\TestCase;
use Zlodes\PrometheusClient\Laravel\ServiceProvider;
use Zlodes\PrometheusClient\Laravel\Storage\StorageConfigurator;
use Zlodes\PrometheusClient\Storage\Contracts\CounterStorage;
use Zlodes\PrometheusClient\Storage\Contracts\GaugeStorage;
use Zlodes\PrometheusClient\Storage\Contracts\HistogramStorage;
use Zlodes\PrometheusClient\Storage\Contracts\SummaryStorage;
use Zlodes\PrometheusClient\Storage\InMemory\InMemoryCounterStorage;
use Zlodes\PrometheusClient\Storage\InMemory\InMemoryGaugeStorage;
use Zlodes\PrometheusClient\Storage\InMemory\InMemoryHistogramStorage;
use Zlodes\PrometheusClient\Storage\InMemory\InMemorySummaryStorage;
use Zlodes\PrometheusClient\Storage\NullStorage;

class StorageConfiguratorTest extends TestCase
{
    public function testExtendOverridesBuiltInDriver(): void
    {
        $configurator = $this->app->make(StorageConfigurator::class);

        $configurator->extend('in_memory', [
            CounterStorage::class => NullStorage::class,
            GaugeStorage::class => NullStorage::class,
            HistogramStorage::class => NullStorage::class,
            SummaryStorage::class => NullStorage::class,
        ]);

        config()->set('prometheus-client.storage', 'in_memory');

        $configurator->configure();

        self::assertInstanceOf(NullStorage::class, $this->app->make(CounterStorage::class));
        self::assertInstanceOf(NullStorage::class, $this->app->make(GaugeStorage::class));
        self::assertInstanceOf(NullStorage::class, $this->app->make(HistogramStorage::class));
        self::assertInstanceOf(NullStorage::class, $this->app->make(SummaryStorage::class));
    }

    public function testExtendRegistersCustomDriver(): void
    {
        $configurator = $this->app->make(StorageConfigurator::class);

        $configurator->extend('custom', [
            CounterStorage::class => InMemoryCounterStorage::class,
            GaugeStorage::class => InMemoryGaugeStorage::class,
            HistogramStorage::class => InMemoryHistogramStorage::class,
            SummaryStorage::class => InMemorySummaryStorage::class,
        ]);

        config()->set('prometheus-client.storage', 'custom');

        $configurator->configure();

        self::assertInstanceOf(InMemoryCounterStorage::class, $this->app->make(CounterStorage::class));
        self::assertInstanceOf(InMemoryGaugeStorage::class, $this->app->make(GaugeStorage::class));
        self::assertInstanceOf(InMemoryHistogramStorage::class, $this->app->make(HistogramStorage::class));
        self::assertInstanceOf(InMemorySummaryStorage::class, $this->app->make(SummaryStorage::class));
    }

    public function testExtendWithNullDriverNameIsRejected(): void
    {
        $configurator = $this->app->make(StorageConfigurator::class);

        $this->expectException(InvalidArgumentException::class);

        $configurator->extend('null', [
            CounterStorage::class => InMemoryCounterStorage::class,
            GaugeStorage::class => InMemoryGaugeStorage::class,
            HistogramStorage::class => InMemoryHistogramStorage::class,
            SummaryStorage::class => InMemorySummaryStorage::class,
        ]);
    }

    public function testExtendWithMissingContractIsRejected(): void
    {
        $configurator = $this->app->make(StorageConfigurator::class);

        $this->expectException(InvalidArgumentException::class);

        $configurator->extend('incomplete', [
            CounterStorage::class => InMemoryCounterStorage::class,
            GaugeStorage::class => InMemoryGaugeStorage::class,
            HistogramStorage::class => InMemoryHistogramStorage::class,
            // SummaryStorage is missing
        ]);
    }

    public function testExtendWithMismatchedImplementationIsRejected(): void
    {
        $configurator = $this->app->make(StorageConfigurator::class);

        $this->expectException(InvalidArgumentException::class);

        $configurator->extend('mismatched', [
            CounterStorage::class => InMemoryCounterStorage::class,
            GaugeStorage::class => InMemoryCounterStorage::class, // doesn't implement GaugeStorage
            HistogramStorage::class => InMemoryHistogramStorage::class,
            SummaryStorage::class => InMemorySummaryStorage::class,
        ]);
    }

    public function testConfigureWithUnknownDriverNamesTheDriverInTheExceptionMessage(): void
    {
        config()->set('prometheus-client.storage', 'totally-unknown-driver');

        $configurator = $this->app->make(StorageConfigurator::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('totally-unknown-driver');

        $configurator->configure();
    }

    protected function getPackageProviders($app): array
    {
        return [
            ServiceProvider::class,
        ];
    }
}
