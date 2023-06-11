<?php

declare(strict_types=1);

namespace Zlodes\PrometheusClient\Laravel\Command;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Contracts\Foundation\Application;
use Psr\Log\LoggerInterface;
use Zlodes\PrometheusClient\Laravel\ScheduledCollector\SchedulableCollector;
use Zlodes\PrometheusClient\Laravel\ScheduledCollector\SchedulableCollectorRegistry;

final class ScheduledCollect extends Command
{
    protected $signature = 'metrics:collect-scheduled';
    protected $description = 'Collects scheduled metrics. Using by Laravel Scheduler';

    public function handle(
        LoggerInterface $logger,
        SchedulableCollectorRegistry $schedulableCollectorRegistry,
        Application $application,
    ): void {
        $collectorClasses = $schedulableCollectorRegistry->getAll();

        $logger->info("Running scheduled metrics collectors", [
            'collectors_count' => count($collectorClasses),
        ]);

        foreach ($collectorClasses as $collectorClass) {
            try {
                /** @var SchedulableCollector $collector */
                $collector = $application->make($collectorClass);

                $collector->collect();
            } catch (Exception $e) {
                $logger->error("Cannot collect scheduled metric: $e", [
                    'collector' => $collectorClass,
                ]);
            }
        }
    }
}
