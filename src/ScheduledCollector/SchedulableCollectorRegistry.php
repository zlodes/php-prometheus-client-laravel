<?php

declare(strict_types=1);

namespace Zlodes\PrometheusClient\Laravel\ScheduledCollector;

interface SchedulableCollectorRegistry
{
    /**
     * @param class-string<SchedulableCollector> $schedulableCollector
     *
     * @return $this
     */
    public function push(string $schedulableCollector): self;

    /**
     * @return list<class-string<SchedulableCollector>>
     */
    public function getAll(): array;
}
