<?php

declare(strict_types=1);

namespace Zlodes\PrometheusClient\Laravel\Tests;

use Generator;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Zlodes\PrometheusClient\Exporter\Exporter;
use Zlodes\PrometheusClient\Fetcher\Fetcher;
use Zlodes\PrometheusClient\KeySerialization\Serializer;
use Zlodes\PrometheusClient\Laravel\ScheduledCollector\SchedulableCollectorArrayRegistry;
use Zlodes\PrometheusClient\Laravel\ServiceProvider;
use Zlodes\PrometheusClient\Laravel\Storage\RedisStorage\CounterRedisStorage;
use Zlodes\PrometheusClient\Laravel\Storage\RedisStorage\GaugeRedisStorage;
use Zlodes\PrometheusClient\Laravel\Storage\RedisStorage\HistogramRedisStorage;
use Zlodes\PrometheusClient\Laravel\Storage\RedisStorage\SummaryRedisStorage;
use Zlodes\PrometheusClient\Laravel\Storage\StorageConfigurator;
use Zlodes\PrometheusClient\Registry\Registry;
use Zlodes\PrometheusClient\Storage\Contracts\CounterStorage;
use Zlodes\PrometheusClient\Storage\Contracts\GaugeStorage;
use Zlodes\PrometheusClient\Storage\Contracts\HistogramStorage;
use Zlodes\PrometheusClient\Storage\Contracts\SummaryStorage;
use Zlodes\PrometheusClient\Storage\NullStorage;

class ServiceProviderTest extends TestCase
{
    public function testBindings(): void
    {
        $interfaces = [
            Registry::class,
            Fetcher::class,
            Exporter::class,
            Serializer::class,
            CounterStorage::class,
            GaugeStorage::class,
            HistogramStorage::class,
            SummaryStorage::class,

            SchedulableCollectorArrayRegistry::class,
        ];

        foreach ($interfaces as $interface) {
            $instance = $this->app->make($interface);

            self::assertNotNull($instance);
        }
    }

    public function testSchedule(): void
    {
        $schedule = $this->app->make(Schedule::class);

        /** @var list<Event> $events */
        $events = $schedule->events();

        $found = false;

        foreach ($events as $event) {
            $command = $event->command;

            if ($command === null) {
                continue;
            }

            if (str_contains($command, 'metrics:collect-scheduled')) {
                $found = true;
                break;
            }
        }

        self::assertTrue($found);
    }

    #[DataProvider('storageContractsDataProvider')]
    public function testDefaultStorageIsRedis(string $contract, string $implementation): void
    {
        $storage = $this->app->make($contract);

        self::assertInstanceOf($implementation, $storage);
    }

    public static function storageContractsDataProvider(): Generator
    {
        yield 'counter' => [CounterStorage::class, CounterRedisStorage::class];
        yield 'gauge' => [GaugeStorage::class, GaugeRedisStorage::class];
        yield 'histogram' => [HistogramStorage::class, HistogramRedisStorage::class];
        yield 'summary' => [SummaryStorage::class, SummaryRedisStorage::class];
    }

    public function testMetricsDisabled(): void
    {
        config()->set('prometheus-client.enabled', false);

        // Run StorageConfigurator to reload bindings
        $this->app->make(StorageConfigurator::class)
            ->configure();

        $storage = $this->app->make(CounterStorage::class);

        self::assertInstanceOf(NullStorage::class, $storage);
    }

    protected function getPackageProviders($app): array
    {
        return [
            ServiceProvider::class,
        ];
    }
}
