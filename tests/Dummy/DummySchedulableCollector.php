<?php

declare(strict_types=1);

namespace Zlodes\PrometheusClient\Laravel\Tests\Dummy;

use Zlodes\PrometheusClient\Collector\Collector;
use Zlodes\PrometheusClient\Laravel\ScheduledCollector\SchedulableCollector;

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
