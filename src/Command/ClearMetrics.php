<?php

declare(strict_types=1);

namespace Zlodes\PrometheusClient\Laravel\Command;

use Illuminate\Console\Command;
use Zlodes\PrometheusClient\Storage\Contracts\CounterStorage;
use Zlodes\PrometheusClient\Storage\Contracts\GaugeStorage;
use Zlodes\PrometheusClient\Storage\Contracts\HistogramStorage;
use Zlodes\PrometheusClient\Storage\Contracts\SummaryStorage;

final class ClearMetrics extends Command
{
    protected $signature = 'metrics:clear';
    protected $description = 'Drop all the metrics values';

    public function handle(
        CounterStorage $counterStorage,
        GaugeStorage $gaugeStorage,
        HistogramStorage $histogramStorage,
        SummaryStorage $summaryStorage,
    ): void {
        $counterStorage->clearCounters();
        $gaugeStorage->clearGauges();
        $histogramStorage->clearHistograms();
        $summaryStorage->clearSummaries();
    }
}
