<?php

declare(strict_types=1);

namespace Zlodes\PrometheusExporter\Laravel\Storage;

use Exception;
use Illuminate\Contracts\Redis\Connection;
use Zlodes\PrometheusExporter\DTO\MetricValue;
use Zlodes\PrometheusExporter\Exceptions\StorageReadException;
use Zlodes\PrometheusExporter\Exceptions\StorageWriteException;
use Zlodes\PrometheusExporter\Normalization\Contracts\MetricKeyDenormalizer;
use Zlodes\PrometheusExporter\Normalization\Contracts\MetricKeyNormalizer;
use Zlodes\PrometheusExporter\Normalization\Exceptions\CannotDenormalizeMetricsKey;
use Zlodes\PrometheusExporter\Normalization\Exceptions\CannotNormalizeMetricsKey;
use Zlodes\PrometheusExporter\Normalization\JsonMetricKeyDenormalizer;
use Zlodes\PrometheusExporter\Normalization\JsonMetricKeyNormalizer;
use Zlodes\PrometheusExporter\Storage\Storage;

final class RedisStorage implements Storage
{
    private const HASH_NAME = 'metrics';

    public function __construct(
        private readonly Connection $connection,
        private readonly MetricKeyNormalizer $metricKeyNormalizer = new JsonMetricKeyNormalizer(),
        private readonly MetricKeyDenormalizer $metricKeyDenormalizer = new JsonMetricKeyDenormalizer(),
    ) {
    }

    public function fetch(): array
    {
        try {
            /** @var array<non-empty-string, string> $rawHash */
            $rawHash = $this->connection->command('HGETALL', [self::HASH_NAME]);
        } catch (Exception $e) {
            throw new StorageReadException(
                "Got fetch error. Cannot execute HGETALL command",
                previous: $e
            );
        }

        $results = [];

        foreach ($rawHash as $denormalizedKey => $value) {
            try {
                $results[] = new MetricValue(
                    $this->metricKeyNormalizer->normalize($denormalizedKey),
                    (float) $value
                );
            } catch (CannotNormalizeMetricsKey $e) {
                throw new StorageReadException(
                    "Got fetch error. Cannot normalize metrics key for key: $denormalizedKey",
                    previous: $e
                );
            }
        }

        return $results;
    }

    public function clear(): void
    {
        try {
            $this->connection->command('DEL', [self::HASH_NAME]);
        } catch (Exception $e) {
            throw new StorageWriteException(
                "Got clear error. Cannot execute DEL command",
                previous: $e
            );
        }
    }

    public function setValue(MetricValue $value): void
    {
        try {
            $key = $this->metricKeyDenormalizer->denormalize($value->metricNameWithLabels);
        } catch (CannotDenormalizeMetricsKey $e) {
            throw new StorageWriteException(
                "Got setValue error. Cannot denormalize metrics key",
                previous: $e
            );
        }

        try {
            $this->connection->command('HSET', [self::HASH_NAME, $key, $value->value]);
        } catch (Exception $e) {
            throw new StorageWriteException(
                "Got setValue error. Cannot execute HSET command",
                previous: $e
            );
        }
    }

    public function incrementValue(MetricValue $value): void
    {
        try {
            $key = $this->metricKeyDenormalizer->denormalize($value->metricNameWithLabels);
        } catch (CannotDenormalizeMetricsKey $e) {
            throw new StorageWriteException(
                "Got incrementValue error. Cannot denormalize metrics key",
                previous: $e
            );
        }

        try {
            $this->connection->command('HINCRBYFLOAT', [self::HASH_NAME, $key, $value->value]);
        } catch (Exception $e) {
            throw new StorageWriteException(
                "Got setValue error. Cannot execute HINCRBYFLOAT command",
                previous: $e
            );
        }
    }
}
