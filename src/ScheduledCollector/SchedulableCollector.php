<?php

declare(strict_types=1);

namespace Zlodes\PrometheusExporter\Laravel\ScheduledCollector;

use Zlodes\PrometheusExporter\Laravel\Exceptions\CannotCollectScheduledMetrics;

interface SchedulableCollector
{
    /**
     * @throws CannotCollectScheduledMetrics
     */
    public function collect(): void;
}
