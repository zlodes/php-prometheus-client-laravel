<?php

declare(strict_types=1);

namespace Zlodes\PrometheusClient\Laravel\Command;

use Illuminate\Console\Command;
use Zlodes\PrometheusClient\Registry\Registry;

final class ListMetrics extends Command
{
    protected $signature = 'metrics:list';
    protected $description = 'Outputs a table with all the registered metrics';

    public function handle(Registry $registry): void
    {
        $metrics = [];
        $counter = 0;

        foreach ($registry->getAll() as $metric) {
            ++$counter;

            $metrics[] = [
                $counter,
                $metric->name,
                $metric->getPrometheusType(),
                $metric->help,
            ];
        }

        $tableHeader = [
            '#',
            'Name',
            'Type',
            'Help',
        ];

        $this->table($tableHeader, $metrics);
    }
}
