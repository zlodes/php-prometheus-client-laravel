<?php

declare(strict_types=1);

namespace Zlodes\PrometheusClient\Laravel\Storage;

use Exception;
use Generator;
use Illuminate\Contracts\Redis\Connection;
use Zlodes\PrometheusClient\Exception\MetricKeySerializationException;
use Zlodes\PrometheusClient\Exception\MetricKeyUnserializationException;
use Zlodes\PrometheusClient\Exception\StorageReadException;
use Zlodes\PrometheusClient\Exception\StorageWriteException;
use Zlodes\PrometheusClient\KeySerialization\JsonSerializer;
use Zlodes\PrometheusClient\KeySerialization\Serializer;
use Zlodes\PrometheusClient\Storage\DTO\MetricNameWithLabels;
use Zlodes\PrometheusClient\Storage\DTO\MetricValue;
use Zlodes\PrometheusClient\Storage\Storage;

final class RedisStorage implements Storage
{
    public const SIMPLE_HASH_NAME = 'metrics_simple';
    public const HISTOGRAM_HASH_NAME_PREFIX = 'metrics_histogram_';
    public const HISTOGRAM_SUM_HASH_NAME = 'metrics_histograms_sum';
    public const HISTOGRAM_COUNT_HASH_NAME = 'metrics_histograms_count';

    public function __construct(
        private readonly Connection $connection,
        private readonly Serializer $serializer = new JsonSerializer(),
    ) {
    }

    public function fetch(): Generator
    {
        yield from $this->fetchGaugeAndCounterMetrics();

        yield from $this->fetchHistogramMetrics();
    }

    public function clear(): void
    {
        try {
            $this->connection->command('DEL', [self::SIMPLE_HASH_NAME]);
            $this->connection->command('DEL', [self::HISTOGRAM_SUM_HASH_NAME]);
            $this->connection->command('DEL', [self::HISTOGRAM_COUNT_HASH_NAME]);

            // Using leading asterisk to ignore Laravel Redis connection prefix (like laravel_database_)
            $histogramKeyPattern = ['*' . self::HISTOGRAM_HASH_NAME_PREFIX . '*'];

            $this->connection->command('EVAL', [
                "return redis.call('del', unpack(redis.call('keys', ARGV[1])))",
                $histogramKeyPattern,
                0,
            ]);
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
            $key = $this->serializer->serialize($value->metricNameWithLabels);
        } catch (MetricKeySerializationException $e) {
            throw new StorageWriteException(
                "Got setValue error. Cannot serialize metrics key",
                previous: $e
            );
        }

        try {
            $this->connection->command('HSET', [self::SIMPLE_HASH_NAME, $key, $value->value]);
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
            $key = $this->serializer->serialize($value->metricNameWithLabels);
        } catch (MetricKeySerializationException $e) {
            throw new StorageWriteException(
                "Got incrementValue error. Cannot serialize metrics key",
                previous: $e
            );
        }

        try {
            $this->connection->command('HINCRBYFLOAT', [self::SIMPLE_HASH_NAME, $key, $value->value]);
        } catch (Exception $e) {
            throw new StorageWriteException(
                "Got setValue error. Cannot execute HINCRBYFLOAT command",
                previous: $e
            );
        }
    }

    public function persistHistogram(MetricValue $value, array $buckets): void
    {
        try {
            $key = $this->serializer->serialize($value->metricNameWithLabels);
        } catch (MetricKeySerializationException $e) {
            throw new StorageWriteException('Cannot serialize metric key', previous: $e);
        }

        $metricHashName = self::HISTOGRAM_HASH_NAME_PREFIX . $key;

        $this->ensureHistogramExists($metricHashName, $buckets);

        $bucketsToUpdate = [
            "+Inf",
        ];

        foreach ($buckets as $bucket) {
            if ($value->value <= $bucket) {
                $bucketsToUpdate[] = (string) $bucket;
            }
        }

        // TODO: Might be optimized by using EVAL with prepared LUA script. We have to add a performance test for this.
        $this->connection->command('HINCRBY', [self::HISTOGRAM_COUNT_HASH_NAME, $key, 1]);
        $this->connection->command('HINCRBYFLOAT', [self::HISTOGRAM_SUM_HASH_NAME, $key, $value->value]);

        foreach ($bucketsToUpdate as $bucket) {
            $this->connection->command('HINCRBY', [$metricHashName, $bucket, 1]);
        }
    }

    /**
     * @param non-empty-string $metricHashName
     * @param non-empty-list<int|float> $buckets
     *
     * @return void
     */
    private function ensureHistogramExists(string $metricHashName, array $buckets): void
    {
        $exists = $this->connection->command('EXISTS', [$metricHashName]) === 1;

        if ($exists) {
            return;
        }

        foreach ($buckets as $bucket) {
            $this->connection->command('HSET', [$metricHashName, $bucket, 0]);
        }

        $this->connection->command('HSET', [$metricHashName, "+Inf", 0]);
    }

    /**
     * @return Generator<int, MetricValue>
     */
    private function fetchGaugeAndCounterMetrics(): Generator
    {
        try {
            /** @var array<non-empty-string, string> $rawHash */
            $rawHash = $this->connection->command('HGETALL', [self::SIMPLE_HASH_NAME]);
        } catch (Exception $e) {
            throw new StorageReadException(
                "Got fetch error. Cannot execute HGETALL command",
                previous: $e
            );
        }

        foreach ($rawHash as $serializedKey => $value) {
            try {
                yield new MetricValue(
                    $this->serializer->unserialize($serializedKey),
                    (float) $value
                );
            } catch (MetricKeyUnserializationException $e) {
                throw new StorageReadException(
                    "Got fetch error. Cannot unserialize metrics key for key: $serializedKey",
                    previous: $e
                );
            }
        }
    }

    /**
     * @return Generator<int, MetricValue>
     */
    private function fetchHistogramMetrics(): Generator
    {
        try {
            /** @var array<non-empty-string, string> $rawSumHash */
            $rawSumHash = $this->connection->command('HGETALL', [self::HISTOGRAM_SUM_HASH_NAME]);

            /** @var array<non-empty-string, string> $rawCountHash */
            $rawCountHash = $this->connection->command('HGETALL', [self::HISTOGRAM_COUNT_HASH_NAME]);
        } catch (Exception $e) {
            throw new StorageReadException(
                "Got fetch error. Cannot execute HGETALL command",
                previous: $e
            );
        }

        $metricKeys = array_keys($rawSumHash);

        foreach ($metricKeys as $serializedKey) {
            try {
                $metricNameWithLabels = $this->serializer->unserialize($serializedKey);
            } catch (MetricKeyUnserializationException $e) {
                throw new StorageReadException(
                    "Got fetch error. Cannot unserialize metrics key for key: $serializedKey",
                    previous: $e
                );
            }

            $histogramRedisKey = self::HISTOGRAM_HASH_NAME_PREFIX . $serializedKey;

            try {
                /** @var array<int|non-empty-string, int|float|string> $rawHistogramHash */
                $rawHistogramHash = $this->connection->command('HGETALL', [$histogramRedisKey]);
            } catch (Exception $e) {
                throw new StorageReadException(
                    "Got fetch error. Cannot execute HGETALL command",
                    previous: $e
                );
            }

            foreach ($rawHistogramHash as $bucket => $value) {
                yield new MetricValue(
                    new MetricNameWithLabels(
                        $metricNameWithLabels->metricName,
                        [
                            ...$metricNameWithLabels->labels,
                            'le' => (string) $bucket,
                        ]
                    ),
                    (float) $value
                );
            }

            yield new MetricValue(
                new MetricNameWithLabels(
                    $metricNameWithLabels->metricName . '_sum',
                    $metricNameWithLabels->labels
                ),
                (float) $rawSumHash[$serializedKey]
            );

            yield new MetricValue(
                new MetricNameWithLabels(
                    $metricNameWithLabels->metricName . '_count',
                    $metricNameWithLabels->labels
                ),
                (float) $rawCountHash[$serializedKey]
            );
        }
    }
}
