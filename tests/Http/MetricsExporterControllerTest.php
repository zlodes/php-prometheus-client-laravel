<?php

declare(strict_types=1);

namespace Zlodes\PrometheusExporter\Laravel\Tests\Http;

use Generator;
use Illuminate\Support\Facades\Route;
use Mockery;
use Orchestra\Testbench\TestCase;
use Zlodes\PrometheusExporter\Exporter\Exporter;
use Zlodes\PrometheusExporter\Laravel\Http\MetricsExporterController;
use Zlodes\PrometheusExporter\Laravel\ServiceProvider;

class MetricsExporterControllerTest extends TestCase
{
    public function testControllerResponse(): void
    {
        $this->app->instance(
            Exporter::class,
            $exporterMock = Mockery::mock(Exporter::class)
        );

        $exporterMock
            ->expects('export')
            ->andReturnUsing(static function (): Generator {
                yield 'foo';
                yield 'bar';
                yield 'baz';
            });

        Route::get('/super-metrics', MetricsExporterController::class);

        $this
            ->get('/super-metrics')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee('foo')
            ->assertSee('bar');
    }

    protected function getPackageProviders($app): array
    {
        return [
            ServiceProvider::class,
        ];
    }
}
