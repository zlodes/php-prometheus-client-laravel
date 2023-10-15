<?php

declare(strict_types=1);

namespace Zlodes\PrometheusClient\Laravel\Tests\ScheduledCollector;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Orchestra\Testbench\TestCase;
use Zlodes\PrometheusClient\Laravel\ScheduledCollector\SchedulableCollectorRegistry;
use Zlodes\PrometheusClient\Laravel\ServiceProvider;
use Zlodes\PrometheusClient\Laravel\Tests\Dummy\DummySchedulableCollector;

final class SchedulableCollectorsProvidedByConfigTest extends TestCase
{
    public function testDefaultConfig(): void
    {
        /** @var SchedulableCollectorRegistry $registry */
        $registry = $this->app->make(SchedulableCollectorRegistry::class);

        self::assertEmpty($registry->getAll());
    }

    public function testConfigWithCorrectValues(): void
    {
        config([
            'prometheus-client.schedulable_collectors' => [
                DummySchedulableCollector::class,
            ],
        ]);

        /** @var SchedulableCollectorRegistry $registry */
        $registry = $this->app->make(SchedulableCollectorRegistry::class);

        self::assertEquals([DummySchedulableCollector::class], $registry->getAll());
    }

    public function testConfigWithWrongValues(): void
    {
        config([
            'prometheus-client.schedulable_collectors' => [
                DummySchedulableCollector::class, // valid
                Model::class, // invalid
            ],
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('isn\'t a valid SchedulableCollector');

        $this->app->make(SchedulableCollectorRegistry::class);
    }

    protected function getPackageProviders($app): array
    {
        return [
            ServiceProvider::class,
        ];
    }
}
