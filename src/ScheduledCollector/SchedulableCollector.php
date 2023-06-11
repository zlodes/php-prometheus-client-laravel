<?php

declare(strict_types=1);

namespace Zlodes\PrometheusClient\Laravel\ScheduledCollector;

use Zlodes\PrometheusClient\Laravel\Exception\CannotCollectScheduledMetrics;

interface SchedulableCollector
{
    /**
     * @throws CannotCollectScheduledMetrics
     */
    public function collect(): void;
}
