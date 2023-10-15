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
use Zlodes\PrometheusClient\Storage\Commands\UpdateSummary;
use Zlodes\PrometheusClient\Storage\Contracts\SummaryStorage;
use Zlodes\PrometheusClient\Storage\DTO\SummaryMetricValue;

final class SummaryRedisStorage implements SummaryStorage
{
    public const SUMMARY_LIST_NAME_PREFIX = 'metrics_summary_';

    public function __construct(
        private readonly Connection $connection,
        private readonly Serializer $metricKeySerializer = new JsonSerializer(),
    ) {
    }

    public function updateSummary(UpdateSummary $command): void
    {
        try {
            $keyWithLabels = $this->metricKeySerializer->serialize($command->metricNameWithLabels);
        } catch (MetricKeySerializationException $e) {
            throw new StorageWriteException('Cannot serialize metric key', previous: $e);
        }

        $metricListName = self::SUMMARY_LIST_NAME_PREFIX . $keyWithLabels;

        $this->connection->command('RPUSH', [$metricListName, $command->value]);
    }

    public function fetchSummaries(): iterable
    {
        try {
            $rawKeys = $this->getSummaryKeys();
        } catch (Exception $e) {
            throw new StorageReadException(
                "Got fetch error. Cannot execute KEYS command",
                previous: $e
            );
        }

        foreach ($rawKeys as $keyWithPrefix) {
            $serializedKey = substr($keyWithPrefix, strlen(self::SUMMARY_LIST_NAME_PREFIX));
            Assert::stringNotEmpty($serializedKey);

            try {
                $keyWithLabels = $this->metricKeySerializer->unserialize($serializedKey);
            } catch (MetricKeyUnserializationException $e) {
                throw new StorageReadException(
                    "Got fetch error. Cannot unserialize metrics key for key: $serializedKey",
                    previous: $e
                );
            }

            /** @var non-empty-list<string> $elements */
            $elements = $this->connection->command('LRANGE', [$keyWithPrefix, 0, -1]);

            yield new SummaryMetricValue(
                $keyWithLabels,
                array_map('floatval', $elements)
            );
        }
    }

    public function clearSummaries(): void
    {
        try {
            foreach ($this->getSummaryKeys() as $key) {
                $this->connection->command('DEL', [$key]);
            }
        } catch (Exception $e) {
            throw new StorageWriteException(
                "Got clear error. Cannot execute KEYS or EVAL command",
                previous: $e
            );
        }
    }

    /**
     * @return list<non-empty-string>
     *
     * @throws Exception
     */
    private function getSummaryKeys(): array
    {
        // Using leading asterisk to ignore Laravel Redis connection prefix (like laravel_database_)
        $summaryKeysPattern = ['*' . self::SUMMARY_LIST_NAME_PREFIX . '*'];

        /** @var list<non-empty-string> $rawKeys */
        $rawKeys = $this->connection->command('KEYS', $summaryKeysPattern);

        // Drop prefixes before prefix (like laravel_database_)
        return array_map(static function (string $rawKey): string {
            $prefixStartPosition = strpos($rawKey, self::SUMMARY_LIST_NAME_PREFIX);
            Assert::integer($prefixStartPosition);

            $key = substr($rawKey, $prefixStartPosition);
            Assert::stringNotEmpty($key);

            return $key;
        }, $rawKeys);
    }
}
