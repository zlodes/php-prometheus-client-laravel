<?php

declare(strict_types=1);

namespace Zlodes\PrometheusExporter\Laravel\Exceptions;

use RuntimeException;

final class CannotCollectScheduledMetrics extends RuntimeException
{
}
