<?php

declare(strict_types=1);

namespace Zlodes\PrometheusExporter\Laravel;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use Webmozart\Assert\Assert;
use Zlodes\PrometheusExporter\Exporter\Exporter;
use Zlodes\PrometheusExporter\Exporter\StoredMetricsExporter;
use Zlodes\PrometheusExporter\Laravel\Commands\ClearMetrics;
use Zlodes\PrometheusExporter\Laravel\Commands\ListMetrics;
use Zlodes\PrometheusExporter\Laravel\Commands\ScheduledCollect;
use Zlodes\PrometheusExporter\Laravel\ScheduledCollector\SchedulableCollector;
use Zlodes\PrometheusExporter\Laravel\ScheduledCollector\SchedulableCollectorArrayRegistry;
use Zlodes\PrometheusExporter\Laravel\ScheduledCollector\SchedulableCollectorRegistry;
use Zlodes\PrometheusExporter\Normalization\Contracts\MetricKeyDenormalizer;
use Zlodes\PrometheusExporter\Normalization\Contracts\MetricKeyNormalizer;
use Zlodes\PrometheusExporter\Normalization\JsonMetricKeyDenormalizer;
use Zlodes\PrometheusExporter\Normalization\JsonMetricKeyNormalizer;
use Zlodes\PrometheusExporter\Registry\ArrayRegistry;
use Zlodes\PrometheusExporter\Registry\Registry;
use Zlodes\PrometheusExporter\Storage\Storage;

final class ServiceProvider extends BaseServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/prometheus-exporter.php', 'prometheus-exporter');

        $this->app->singleton(Registry::class, ArrayRegistry::class);
        $this->app->singleton(Exporter::class, StoredMetricsExporter::class);

        $this->app->singleton(MetricKeyNormalizer::class, JsonMetricKeyNormalizer::class);
        $this->app->singleton(MetricKeyDenormalizer::class, JsonMetricKeyDenormalizer::class);

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

                /** @psalm-var list<class-string<SchedulableCollector>> $storageClass */
                $collectors = $config->get('prometheus-exporter.schedulable_collectors');
                Assert::allStringNotEmpty($collectors);

                foreach ($collectors as $collectorClass) {
                    /** @psalm-var class-string<Storage> $storageClass */
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
