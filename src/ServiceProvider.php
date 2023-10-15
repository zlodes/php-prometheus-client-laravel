<?php

declare(strict_types=1);

namespace Zlodes\PrometheusClient\Laravel;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use Webmozart\Assert\Assert;
use Zlodes\PrometheusClient\Collector\CollectorFactory;
use Zlodes\PrometheusClient\Exporter\Exporter;
use Zlodes\PrometheusClient\Exporter\FetcherExporter;
use Zlodes\PrometheusClient\Fetcher\Fetcher;
use Zlodes\PrometheusClient\Fetcher\StoredMetricsFetcher;
use Zlodes\PrometheusClient\KeySerialization\JsonSerializer;
use Zlodes\PrometheusClient\KeySerialization\Serializer;
use Zlodes\PrometheusClient\Laravel\Command\ClearMetrics;
use Zlodes\PrometheusClient\Laravel\Command\ListMetrics;
use Zlodes\PrometheusClient\Laravel\Command\ScheduledCollect;
use Zlodes\PrometheusClient\Laravel\ScheduledCollector\SchedulableCollector;
use Zlodes\PrometheusClient\Laravel\ScheduledCollector\SchedulableCollectorArrayRegistry;
use Zlodes\PrometheusClient\Laravel\ScheduledCollector\SchedulableCollectorRegistry;
use Zlodes\PrometheusClient\Laravel\Storage\StorageConfigurator;
use Zlodes\PrometheusClient\Registry\ArrayRegistry;
use Zlodes\PrometheusClient\Registry\Registry;

final class ServiceProvider extends BaseServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/prometheus-client.php', 'prometheus-client');

        $this->app->singleton(CollectorFactory::class);
        $this->app->singleton(Registry::class, ArrayRegistry::class);
        $this->app->singleton(Fetcher::class, StoredMetricsFetcher::class);
        $this->app->singleton(Exporter::class, FetcherExporter::class);

        $this->app->singleton(Serializer::class, JsonSerializer::class);

        $this->registerSchedulableCollectors();
    }

    public function boot(StorageConfigurator $storageConfigurator): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                ListMetrics::class,
                ClearMetrics::class,
                ScheduledCollect::class,
            ]);

            $this->publishes([
                __DIR__ . '/../config/prometheus-client.php' => config_path('prometheus-client.php'),
            ], 'prometheus-client');
        }

        $storageConfigurator->configure();
    }

    private function registerSchedulableCollectors(): void
    {
        $this->app->singleton(SchedulableCollectorRegistry::class, SchedulableCollectorArrayRegistry::class);

        $this->callAfterResolving(
            SchedulableCollectorRegistry::class,
            static function (SchedulableCollectorRegistry $registry, Application $app): void {
                /** @var Repository $config */
                $config = $app->make(Repository::class);

                /** @psalm-var list<class-string<SchedulableCollector>> $collectors */
                $collectors = $config->get('prometheus-client.schedulable_collectors');
                Assert::allStringNotEmpty($collectors);

                foreach ($collectors as $collectorClass) {
                    Assert::true(
                        is_a($collectorClass, SchedulableCollector::class, true),
                        "$collectorClass isn't a valid SchedulableCollector"
                    );

                    $registry->push($collectorClass);
                }
            }
        );

        // TODO: Schedules Might be optional
        $this->callAfterResolving(Schedule::class, static function (Schedule $schedule): void {
            $schedule
                ->command(ScheduledCollect::class)
                ->everyMinute();
        });
    }
}
