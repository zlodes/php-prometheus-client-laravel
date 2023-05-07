<?php

declare(strict_types=1);

namespace Zlodes\PrometheusExporter\Laravel;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use Webmozart\Assert\Assert;
use Zlodes\PrometheusExporter\Collector\Collector;
use Zlodes\PrometheusExporter\Collector\PersistentCollector;
use Zlodes\PrometheusExporter\Exporter\Exporter;
use Zlodes\PrometheusExporter\Exporter\PersistentExporter;
use Zlodes\PrometheusExporter\Laravel\Commands\FlushMetrics;
use Zlodes\PrometheusExporter\Laravel\Commands\ListMetrics;
use Zlodes\PrometheusExporter\Laravel\Commands\ScheduledCollect;
use Zlodes\PrometheusExporter\Laravel\ScheduledCollector\SchedulableCollectorArrayRegistry;
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

        $this->app->singleton(Collector::class, PersistentCollector::class);
        $this->app->singleton(Registry::class, ArrayRegistry::class);
        $this->app->singleton(Exporter::class, PersistentExporter::class);

        $this->app->singleton(Storage::class, static function (Application $app): Storage {
            /** @var Repository $config */
            $config = $app->make(Repository::class);

            /** @psalm-var class-string<Storage> $storageClass */
            $storageClass = $config->get('prometheus-exporter.storage');
            Assert::true(is_a($storageClass, Storage::class, true));

            /** @var Storage */
            return $app->make($storageClass);
        });

        $this->app->singleton(MetricKeyNormalizer::class, JsonMetricKeyNormalizer::class);
        $this->app->singleton(MetricKeyDenormalizer::class, JsonMetricKeyDenormalizer::class);

        $this->app->singleton(SchedulableCollectorArrayRegistry::class);

        // TODO: Schedules Might be optional
        $this->callAfterResolving(Schedule::class, static function (Schedule $schedule): void {
            $schedule
                ->command(ScheduledCollect::class)
                ->everyMinute();
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                ListMetrics::class,
                FlushMetrics::class,
                ScheduledCollect::class,
            ]);

            $this->publishes([
                __DIR__ . '/../config/prometheus-exporter.php' => config_path('prometheus-exporter.php'),
            ], 'prometheus-exporter');
        }
    }
}
