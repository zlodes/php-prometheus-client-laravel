<?php

declare(strict_types=1);

namespace Zlodes\PrometheusExporter\Laravel\Storage;

use Exception;
use Illuminate\Contracts\Redis\Connection;
use Zlodes\PrometheusExporter\Exceptions\MetricKeySerializationException;
use Zlodes\PrometheusExporter\Exceptions\MetricKeyUnserializationException;
use Zlodes\PrometheusExporter\Exceptions\StorageReadException;
use Zlodes\PrometheusExporter\Exceptions\StorageWriteException;
use Zlodes\PrometheusExporter\KeySerialization\JsonSerializer;
use Zlodes\PrometheusExporter\KeySerialization\Serializer;
use Zlodes\PrometheusExporter\Storage\DTO\MetricValue;
use Zlodes\PrometheusExporter\Storage\Storage;

final class RedisStorage implements Storage
{
    private const HASH_NAME = 'metrics';

    public function __construct(
        private readonly Connection $connection,
        private readonly Serializer $serializer = new JsonSerializer(),
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

        foreach ($rawHash as $serializedKey => $value) {
            try {
                $results[] = new MetricValue(
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
            $key = $this->serializer->serialize($value->metricNameWithLabels);
        } catch (MetricKeySerializationException $e) {
            throw new StorageWriteException(
                "Got setValue error. Cannot serialize metrics key",
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
            $key = $this->serializer->serialize($value->metricNameWithLabels);
        } catch (MetricKeySerializationException $e) {
            throw new StorageWriteException(
                "Got incrementValue error. Cannot serialize metrics key",
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
