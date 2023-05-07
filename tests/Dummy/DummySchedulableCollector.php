<?php

declare(strict_types=1);

namespace Zlodes\PrometheusExporter\Laravel\Tests\Dummy;

use Zlodes\PrometheusExporter\Collector\Collector;
use Zlodes\PrometheusExporter\Laravel\ScheduledCollector\SchedulableCollector;

final class DummySchedulableCollector implements SchedulableCollector
{
    public function __construct(private readonly Collector $collector)
    {
    }

    public function collect(): void
    {
        $this->collector->counterIncrement('hello_form_schedule');
    }
}
