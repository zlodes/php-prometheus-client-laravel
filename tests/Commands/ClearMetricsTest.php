<?php

declare(strict_types=1);

namespace Zlodes\PrometheusClient\Laravel\Tests\Commands;

use Mockery;
use Orchestra\Testbench\TestCase;
use Zlodes\PrometheusClient\Laravel\ServiceProvider;
use Zlodes\PrometheusClient\Storage\Contracts\CounterStorage;
use Zlodes\PrometheusClient\Storage\Contracts\GaugeStorage;
use Zlodes\PrometheusClient\Storage\Contracts\HistogramStorage;
use Zlodes\PrometheusClient\Storage\Contracts\SummaryStorage;

class ClearMetricsTest extends TestCase
{
    public function testCommandRun(): void
    {
        $this->app->instance(
            CounterStorage::class,
            $counterStorageMock = Mockery::mock(CounterStorage::class),
        );

        $this->app->instance(
            GaugeStorage::class,
            $gaugeStorageMock = Mockery::mock(GaugeStorage::class),
        );

        $this->app->instance(
            HistogramStorage::class,
            $histogramStorageMock = Mockery::mock(HistogramStorage::class),
        );

        $this->app->instance(
            SummaryStorage::class,
            $summaryStorageMock = Mockery::mock(SummaryStorage::class),
        );

        $counterStorageMock->expects('clearCounters');
        $gaugeStorageMock->expects('clearGauges');
        $histogramStorageMock->expects('clearHistograms');
        $summaryStorageMock->expects('clearSummaries');

        $this
            ->artisan('metrics:clear')
            ->assertSuccessful();
    }

    protected function getPackageProviders($app): array
    {
        return [
            ServiceProvider::class,
        ];
    }
}
