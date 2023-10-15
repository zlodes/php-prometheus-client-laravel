<?php

declare(strict_types=1);

namespace Zlodes\PrometheusClient\Laravel\Storage\RedisStorage;

use Exception;
use Illuminate\Contracts\Redis\Connection;
use Webmozart\Assert\Assert;
use Zlodes\PrometheusClient\Exception\MetricKeySerializationException;
use Zlodes\PrometheusClient\Exception\MetricKeyUnserializationException;
use Zlodes\PrometheusClient\Exception\StorageReadException;
use Zlodes\PrometheusClient\Exception\StorageWriteException;
use Zlodes\PrometheusClient\KeySerialization\JsonSerializer;
use Zlodes\PrometheusClient\KeySerialization\Serializer;
use Zlodes\PrometheusClient\Storage\Commands\UpdateHistogram;
use Zlodes\PrometheusClient\Storage\Contracts\HistogramStorage;
use Zlodes\PrometheusClient\Storage\DTO\HistogramMetricValue;

final class HistogramRedisStorage implements HistogramStorage
{
    public const HISTOGRAM_HASH_NAME_PREFIX = 'metrics_histogram_';
    public const HISTOGRAM_SUM_HASH_NAME = 'metrics_histograms_sum';
    public const HISTOGRAM_COUNT_HASH_NAME = 'metrics_histograms_count';

    public function __construct(
        private readonly Connection $connection,
        private readonly Serializer $metricKeySerializer = new JsonSerializer(),
    ) {
    }

    public function updateHistogram(UpdateHistogram $command): void
    {
        try {
            $keyWithLabels = $this->metricKeySerializer->serialize($command->metricNameWithLabels);
        } catch (MetricKeySerializationException $e) {
            throw new StorageWriteException('Cannot serialize metric key', previous: $e);
        }

        $metricHashName = self::HISTOGRAM_HASH_NAME_PREFIX . $keyWithLabels;

        $buckets = $command->buckets;
        $this->ensureHistogramExists($metricHashName, $buckets);

        $bucketsToUpdate = [
            "+Inf",
        ];

        foreach ($buckets as $bucket) {
            if ($command->value <= $bucket) {
                $bucketsToUpdate[] = (string) $bucket;
            }
        }

        // TODO: Might be optimized by using EVAL with prepared LUA script. We have to add a performance test for this.
        $this->connection->command('HINCRBY', [self::HISTOGRAM_COUNT_HASH_NAME, $keyWithLabels, 1]);
        $this->connection->command('HINCRBYFLOAT', [self::HISTOGRAM_SUM_HASH_NAME, $keyWithLabels, $command->value]);

        foreach ($bucketsToUpdate as $bucket) {
            $this->connection->command('HINCRBY', [$metricHashName, $bucket, 1]);
        }
    }

    public function fetchHistograms(): iterable
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
                $keyWithLabels = $this->metricKeySerializer->unserialize($serializedKey);
            } catch (MetricKeyUnserializationException $e) {
                throw new StorageReadException(
                    "Got fetch error. Cannot unserialize metrics key for key: $serializedKey",
                    previous: $e
                );
            }

            $histogramRedisKey = self::HISTOGRAM_HASH_NAME_PREFIX . $serializedKey;

            try {
                /** @var array<positive-int|non-empty-string, string> $bucketsWithValues */
                $bucketsWithValues = $this->connection->command('HGETALL', [$histogramRedisKey]);
            } catch (Exception $e) {
                throw new StorageReadException(
                    "Got fetch error. Cannot execute HGETALL command",
                    previous: $e
                );
            }

            Assert::notEmpty($bucketsWithValues);
            $bucketsWithValues = array_map('floatval', $bucketsWithValues);

            yield new HistogramMetricValue(
                $keyWithLabels,
                $bucketsWithValues,
                (float) $rawSumHash[$serializedKey],
                (int) $rawCountHash[$serializedKey]
            );
        }
    }

    public function clearHistograms(): void
    {
        try {
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
}
