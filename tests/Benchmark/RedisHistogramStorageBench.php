<?php

declare(strict_types=1);

namespace Zlodes\PrometheusClient\Laravel\Tests\Benchmark;

use Zlodes\PrometheusClient\Laravel\Storage\RedisStorage\HistogramRedisStorage;
use Zlodes\PrometheusClient\Storage\Commands\UpdateHistogram;
use Zlodes\PrometheusClient\Storage\DTO\MetricNameWithLabels;

class RedisHistogramStorageBench
{
    /**
     * @Revs(1000)
     * @Iterations(10)
     * @Warmup(5)
     */
    public function benchWriteDirectCalls(): void
    {
        app()->make(HistogramRedisStorage::class)
            ->updateHistogram(
                new UpdateHistogram(
                    new MetricNameWithLabels('foo'),
                    ['label' => 'value'],
                    1.5
                )
            );
    }
}
