# PHP Prometheus Exporter for Laravel

[![codecov](https://codecov.io/gh/zlodes/php-prometheus-client-laravel/branch/master/graph/badge.svg?token=JYPUW0UYT5)](https://codecov.io/gh/zlodes/php-prometheus-client-laravel)

This is a Laravel adapter/bridge package for [zlodes/prometheus-client](https://github.com/zlodes/php-prometheus-client).

## First steps

### Installation 

```shell
composer require zlodes/prometheus-client-laravel
```

### Register a route for the metrics controller

Your application is responsible for metrics route registration. 
There is a [controller](src/Http/MetricsExporterController.php) ready to use. 
You can configure groups, middleware or prefixes as you want.

Example:

```php
use Illuminate\Support\Facades\Route;
use Zlodes\PrometheusClient\Laravel\Http\MetricsExporterController;

Route::get('/metrics', MetricsExporterController::class);
```

### Configure Storage for metrics [optional]

By default, it uses Redis storage.

If you want to use a different storage backend (e.g. a custom driver shipped by your application), register it
by extending the `StorageConfigurator` in your own `ServiceProvider::register()`:

```php
use Zlodes\PrometheusClient\Laravel\Storage\StorageConfigurator;
use Zlodes\PrometheusClient\Storage\Contracts\CounterStorage;
use Zlodes\PrometheusClient\Storage\Contracts\GaugeStorage;
use Zlodes\PrometheusClient\Storage\Contracts\HistogramStorage;
use Zlodes\PrometheusClient\Storage\Contracts\SummaryStorage;

// your ServiceProvider::register()
$this->callAfterResolving(
    StorageConfigurator::class,
    static function (StorageConfigurator $configurator): void {
        $configurator->extend('my_driver', [
            CounterStorage::class => MyCounterStorage::class,
            GaugeStorage::class => MyGaugeStorage::class,
            HistogramStorage::class => MyHistogramStorage::class,
            SummaryStorage::class => MySummaryStorage::class,
        ]);
    }
);
```

Then publish the config and select your driver:

```shell
php artisan vendor:publish --tag=prometheus-client
```

```dotenv
PROMETHEUS_CLIENT_STORAGE=my_driver
```

`callAfterResolving` is the right hook here: this package's `ServiceProvider::boot()` asks the container to resolve
`StorageConfigurator` as part of its method signature, which runs your registered callback first, and only after
that does `boot()` call `$storageConfigurator->configure()`. This guarantees your custom driver is registered
before the storage bindings are resolved.

All four storage contracts (`CounterStorage`, `GaugeStorage`, `HistogramStorage`, `SummaryStorage`) must be provided
for a driver — `extend()` validates this, along with checking that each implementation actually satisfies its
contract. The built-in `null` driver name is reserved (it backs the `enabled => false` kill switch) and cannot be
overridden, but you can freely override `in_memory` or `redis` if you want to replace the built-in implementations.


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
use Zlodes\PrometheusClient\Collector\CollectorFactory;

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
> For further details, see [zlodes/prometheus-client](https://github.com/zlodes/php-prometheus-client)

### Available console commands

| Command                     | Description                                    |
|-----------------------------|------------------------------------------------|
| `php artisan metrics:list`  | Lists all registered metrics                   |
| `php artisan metrics:clear` | Clears metrics storage                         |
| `metrics:collect-scheduled` | Runs `ScheduledCollectors`. Using by Scheduler |

## Upgrade guide

### From 1.x to 2.x

1. Run `php artisan vendor:publish --tag=prometheus-client` to publish a brand-new config
2. Configure the new config based on the previous one (`prometheus-exporter.php`)
3. Drop legacy config (`prometheus-exporter.php`)

## Testing

### Run tests

```shell
php ./vendor/bin/phpunit
```
