<?php

declare(strict_types=1);

namespace Zlodes\PrometheusExporter\Laravel\Commands;

use Illuminate\Console\Command;
use Zlodes\PrometheusExporter\Storage\Storage;

final class FlushMetrics extends Command
{
    protected $signature = 'metrics:flush';
    protected $description = 'Drop all the metrics values';

    public function handle(Storage $storage): void
    {
        $storage->flush();
    }
}
