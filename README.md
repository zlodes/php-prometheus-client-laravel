# PHP Prometheus Exporter for Laravel

[![codecov](https://codecov.io/gh/zlodes/php-prometheus-exporter-laravel/branch/master/graph/badge.svg?token=JYPUW0UYT5)](https://codecov.io/gh/zlodes/php-prometheus-exporter-laravel)

This is a Laravel adapter/bridge package for [zlodes/prometheus-exporter](https://github.com/zlodes/php-prometheus-exporter).

Now supports only Counter and Gauge metric types.

> **Warning**
> This package is still in development. Use it on your own risk until 1.0.0 release.

## Installation

```shell
composer require zlodes/prometheus-exporter-laravel
```

## Configuration

### Metrics route (required)

Your application is responsible for metrics route registration. There is a ready to use [controller](src/Http/MetricsExporterController.php). You can configure groups, middleware or prefixes as you want.

Route registration example:

```php
use Illuminate\Support\Facades\Route;
use Zlodes\PrometheusExporter\Laravel\Http\MetricsExporterController;

Route::get('/metrics', MetricsExporterController::class);
```

### Storage (optional)

By-default, it uses [RedisStorage](src/Storage/RedisStorage.php), you can configure in three simple steps:

1. Create a class implements `Storage` interface
2. Publish a config:
   ```shell
   php artisan vendor:publish --tag=prometheus-exporter
   ```
3. Set `storage` in the config

## Metrics registration

In your `ServiceProvider::register`:
```php
$this->callAfterResolving(Registry::class, static function (Registry $registry): void {
   $registry
       ->registerMetric(
           new Counter('unhandled_exceptions', 'Number of exceptions caught by Exception Handler')
       )
       ->registerMetric(
           new Gauge('laravel_queue_size', 'Laravel queue length by Queue')
       );
});
```

## Metrics Collector usage

You can work with your metrics whenever you want. Just use `Collector`: 

```php
use Zlodes\PrometheusExporter\Collector\Collector;

class DummyController
{
    public function __invoke(Zlodes\PrometheusExporter\Collector\Collector $collector)
    {
         $collector->counterIncrement('unhandled_exceptions');
    }
}
```

> **Info**
> For further details, see [zlodes/prometheus-exporter](https://github.com/zlodes/php-prometheus-exporter)


## Roadmap

- [ ] Ability to disable Scheduled tasks
- [ ] Configure Semantic Release for GitHub Actions
- [ ] Document Scheduled collectors
- [ ] Scheduled Collectors by config

## Testing

### Run tests

```shell
php ./vendor/bin/phpunit
```
