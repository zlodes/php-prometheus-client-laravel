<?php

declare(strict_types=1);

namespace Zlodes\PrometheusClient\Laravel\Storage\RedisStorage;

use Exception;
use Illuminate\Contracts\Redis\Connection;
use Zlodes\PrometheusClient\Exception\MetricKeySerializationException;
use Zlodes\PrometheusClient\Exception\StorageReadException;
use Zlodes\PrometheusClient\Exception\StorageWriteException;
use Zlodes\PrometheusClient\KeySerialization\JsonSerializer;
use Zlodes\PrometheusClient\KeySerialization\Serializer;
use Zlodes\PrometheusClient\Storage\Commands\IncrementCounter;
use Zlodes\PrometheusClient\Storage\Contracts\CounterStorage;
use Zlodes\PrometheusClient\Storage\DTO\MetricValue;

final class CounterRedisStorage implements CounterStorage
{
    private const HASH_NAME = 'metrics_counters';

    public function __construct(
        private readonly Connection $connection,
        private readonly Serializer $metricKeySerializer = new JsonSerializer(),
    ) {
    }

    public function incrementCounter(IncrementCounter $command): void
    {
        try {
            $metricKeyWithLabels = $this->metricKeySerializer->serialize($command->metricNameWithLabels);
        } catch (MetricKeySerializationException $e) {
            throw new StorageWriteException(
                "Got incrementValue error. Cannot serialize metrics key",
                previous: $e
            );
        }

        try {
            $this->connection->command('HINCRBYFLOAT', [self::HASH_NAME, $metricKeyWithLabels, $command->value]);
        } catch (Exception $e) {
            throw new StorageWriteException(
                "Got increment error. Cannot execute HINCRBYFLOAT command",
                previous: $e
            );
        }
    }

    public function fetchCounters(): iterable
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

        foreach ($rawHash as $serializedKey => $value) {
            try {
                yield new MetricValue(
                    $this->metricKeySerializer->unserialize($serializedKey),
                    (float) $value
                );
            } catch (MetricKeySerializationException $e) {
                throw new StorageReadException(
                    "Got fetch error. Cannot unserialize metrics key for key: $serializedKey",
                    previous: $e
                );
            }
        }
    }

    public function clearCounters(): void
    {
        $this->connection->command('DEL', [self::HASH_NAME]);
    }
}
