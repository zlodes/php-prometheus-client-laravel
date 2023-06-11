<?php

declare(strict_types=1);

namespace Zlodes\PrometheusClient\Laravel\ScheduledCollector;

use Webmozart\Assert\Assert;

final class SchedulableCollectorArrayRegistry implements SchedulableCollectorRegistry
{
    /** @var list<class-string<SchedulableCollector>> */
    private array $schedulableCollectors = [];

    public function push(string $schedulableCollector): self
    {
        Assert::true(is_a($schedulableCollector, SchedulableCollector::class, true));

        $this->schedulableCollectors[] = $schedulableCollector;

        Assert::uniqueValues($this->schedulableCollectors);

        return $this;
    }

    /**
     * @return list<class-string<SchedulableCollector>>
     */
    public function getAll(): array
    {
        return $this->schedulableCollectors;
    }
}
