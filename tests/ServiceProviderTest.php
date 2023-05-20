<?php

declare(strict_types=1);

namespace Zlodes\PrometheusExporter\Laravel\Tests;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Orchestra\Testbench\TestCase;
use Zlodes\PrometheusExporter\Exporter\Exporter;
use Zlodes\PrometheusExporter\KeySerialization\Serializer;
use Zlodes\PrometheusExporter\Laravel\ScheduledCollector\SchedulableCollectorArrayRegistry;
use Zlodes\PrometheusExporter\Laravel\ServiceProvider;
use Zlodes\PrometheusExporter\Registry\Registry;
use Zlodes\PrometheusExporter\Storage\Storage;

class ServiceProviderTest extends TestCase
{
    public function testBindings(): void
    {
        $interfaces = [
            Storage::class,
            Registry::class,
            Exporter::class,
            Serializer::class,
            SchedulableCollectorArrayRegistry::class,
        ];

        foreach ($interfaces as $interface) {
            $instance = $this->app->make($interface);

            self::assertNotNull($instance);
        }
    }

    public function testSchedule(): void
    {
        $schedule = $this->app->make(Schedule::class);

        /** @var list<Event> $events */
        $events = $schedule->events();

        $found = false;

        foreach ($events as $event) {
            $command = $event->command;

            if ($command === null) {
                continue;
            }

            if (str_contains($command, 'metrics:collect-scheduled')) {
                $found = true;
                break;
            }
        }

        self::assertTrue($found);
    }

    protected function getPackageProviders($app): array
    {
        return [
            ServiceProvider::class,
        ];
    }
}
