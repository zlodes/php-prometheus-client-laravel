# PHP Prometheus Exporter for Laravel

[![codecov](https://codecov.io/gh/zlodes/php-prometheus-exporter-laravel/branch/master/graph/badge.svg?token=JYPUW0UYT5)](https://codecov.io/gh/zlodes/php-prometheus-exporter-laravel)

This is a Laravel adapter/bridge package for [zlodes/prometheus-exporter](https://github.com/zlodes/php-prometheus-exporter).

Now supports only Counter and Gauge metric types.

> **Warning**
> This package is still in development. Use it on your own risk until 1.0.0 release.

## First steps

### Installation 

 ```shell
   composer require zlodes/prometheus-exporter-laravel
   ```

### Register a route for the metrics controller

Your application is responsible for metrics route registration. There is a ready to use [controller](src/Http/MetricsExporterController.php). You can configure groups, middleware or prefixes as you want.

Example:

```php
use Illuminate\Support\Facades\Route;
use Zlodes\PrometheusExporter\Laravel\Http\MetricsExporterController;

Route::get('/metrics', MetricsExporterController::class);
```

### Configure a Storage for metrics [optional]

By-default, it uses [RedisStorage](src/Storage/RedisStorage.php). If you want to use other storage, you can do it easily following these three steps:

1. Create a class implements `Storage` interface.
2. Publish a config:
   ```shell
   php artisan vendor:publish --tag=prometheus-exporter
   ```
3. Set your `storage` class in the config.



1. Install it via Composer:
  
2. Register a route for [MetricsExporterController](src/Http/MetricsExporterController.php)
3. Configure a `Storage`. It uses a Redis by default. **[Optional]**
4. 

## Configuration

### Metrics route (required)

Your application is responsible for metrics route registration. There is a ready to use [controller](src/Http/MetricsExporterController.php). You can configure groups, middleware or prefixes as you want.



### Storage (optional)



## Metrics registration

In your `ServiceProvider::register`:
```php
$this->callAfterResolving(Registry::class, static function (Registry $registry): void {
   $registry
       ->registerMetric(
           new Counter('dummy_controller_hits', 'Dummy controller hits count')
       )
       ->registerMetric(
           new Gauge('laravel_queue_size', 'Laravel queue length by Queue')
       );
});
```

## Metrics Collector usage

You can work with your metrics whenever you want. Just use `Collector`: 

```php
use Zlodes\PrometheusExporter\Collector\CollectorFactory;

class DummyController
{
    public function __invoke(CollectorFactory $collector)
    {
         $collector->counter('dummy_controller_hits')->increment();
    }
}
```

## Schedulable collectors

At times, there may be a need to gather metrics on a scheduled basis. The package offers a feature to register a SchedulableCollector that executes every minute using the Laravel Scheduler.

You can define your `SchedulableCollectors` using a [config](config/prometheus-exporter.php) or register it in SchedulableCollectorRegistry directly in a `ServiceProvider`:

```php
$this->callAfterResolving(
   SchedulableCollectorRegistry::class,
   static function (SchedulableCollectorRegistry $schedulableCollectorRegistry): void {
       $schedulableCollectorRegistry->push(YourSchedulableCollector::class);
   }
);
```

> **Note**
> For further details, see [zlodes/prometheus-exporter](https://github.com/zlodes/php-prometheus-exporter)

### Available console commands

```shell
php declare 
```

## Roadmap

- [x] Scheduled Collectors by config
- [x] Document Scheduled collectors
- [ ] Ability to disable Scheduled tasks
- [ ] Configure Semantic Release for GitHub Actions

## Testing

### Run tests

```shell
php ./vendor/bin/phpunit
```
