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
use Zlodes\PrometheusClient\Exporter\StoredMetricsExporter;
use Zlodes\PrometheusClient\KeySerialization\JsonSerializer;
use Zlodes\PrometheusClient\KeySerialization\Serializer;
use Zlodes\PrometheusClient\Laravel\Command\ClearMetrics;
use Zlodes\PrometheusClient\Laravel\Command\ListMetrics;
use Zlodes\PrometheusClient\Laravel\Command\ScheduledCollect;
use Zlodes\PrometheusClient\Laravel\ScheduledCollector\SchedulableCollector;
use Zlodes\PrometheusClient\Laravel\ScheduledCollector\SchedulableCollectorArrayRegistry;
use Zlodes\PrometheusClient\Laravel\ScheduledCollector\SchedulableCollectorRegistry;
use Zlodes\PrometheusClient\Registry\ArrayRegistry;
use Zlodes\PrometheusClient\Registry\Registry;
use Zlodes\PrometheusClient\Storage\NullStorage;
use Zlodes\PrometheusClient\Storage\Storage;

final class ServiceProvider extends BaseServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/prometheus-exporter.php', 'prometheus-exporter');

        $this->app->singleton(CollectorFactory::class);
        $this->app->singleton(Registry::class, ArrayRegistry::class);
        $this->app->singleton(Exporter::class, StoredMetricsExporter::class);

        $this->app->singleton(Serializer::class, JsonSerializer::class);

        $this->registerStorage();
        $this->registerSchedulableCollectors();
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                ListMetrics::class,
                ClearMetrics::class,
                ScheduledCollect::class,
            ]);

            $this->publishes([
                __DIR__ . '/../config/prometheus-exporter.php' => config_path('prometheus-exporter.php'),
            ], 'prometheus-exporter');
        }
    }

    private function registerStorage(): void
    {
        $this->app->singleton(Storage::class, static function (Application $app): Storage {
            /** @var Repository $config */
            $config = $app->make(Repository::class);

            $clientEnabled = $config->get('prometheus-exporter.enabled') ?? true;
            Assert::boolean($clientEnabled);

            if ($clientEnabled === false) {
                return new NullStorage();
            }

            /** @psalm-var class-string<Storage> $storageClass */
            $storageClass = $config->get('prometheus-exporter.storage');
            Assert::true(
                is_a($storageClass, Storage::class, true),
                'Config value in prometheus-exporter.storage must be a class-string<Storage>'
            );

            /** @var Storage */
            return $app->make($storageClass);
        });
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
                $collectors = $config->get('prometheus-exporter.schedulable_collectors');
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
