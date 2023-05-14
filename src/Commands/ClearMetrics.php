<?php

declare(strict_types=1);

namespace Zlodes\PrometheusExporter\Laravel\Commands;

use Illuminate\Console\Command;
use Zlodes\PrometheusExporter\Storage\Storage;

final class ClearMetrics extends Command
{
    protected $signature = 'metrics:clear';
    protected $description = 'Drop all the metrics values';

    public function handle(Storage $storage): void
    {
        $storage->clear();
    }
}
