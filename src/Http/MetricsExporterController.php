<?php

declare(strict_types=1);

namespace Zlodes\PrometheusClient\Laravel\Http;

use Illuminate\Http\Response;
use Zlodes\PrometheusClient\Exporter\Exporter;

final class MetricsExporterController
{
    public function __invoke(Exporter $exporter): Response
    {
        $iterator = $exporter->export();
        $result = '';

        foreach ($iterator as $metric) {
            $result .= $metric . "\n\n";
        }

        return new Response($result, 200, [
            'Content-Type' => 'text/plain',
        ]);
    }
}
