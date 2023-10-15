<?php

declare(strict_types=1);

namespace Zlodes\PrometheusClient\Laravel\Tests\Http;

use Generator;
use Illuminate\Support\Facades\Route;
use Mockery;
use Orchestra\Testbench\TestCase;
use Zlodes\PrometheusClient\Exporter\Exporter;
use Zlodes\PrometheusClient\Laravel\Http\MetricsExporterController;
use Zlodes\PrometheusClient\Laravel\ServiceProvider;

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

        Route::get('/metrics', MetricsExporterController::class);

        $this
            ->get('/metrics')
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
