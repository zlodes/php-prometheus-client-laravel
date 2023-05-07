<?php

declare(strict_types=1);

namespace Zlodes\PrometheusExporter\Laravel\Storage;

use Illuminate\Contracts\Redis\Connection;
use Zlodes\PrometheusExporter\Storage\Storage;

final class RedisStorage implements Storage
{
    private const HASH_NAME = 'metrics';

    public function __construct(private readonly Connection $connection)
    {
    }

    public function fetch(): array
    {
        /** @var array<non-empty-string, string> $rawHash */
        $rawHash = $this->connection->command('HGETALL', [self::HASH_NAME]);

        return array_map('floatval', $rawHash);
    }

    public function flush(): void
    {
        $this->connection->command('DEL', [self::HASH_NAME]);
    }

    public function getValue(string $key): float
    {
        $value = $this->connection->command('HGET', [self::HASH_NAME, $key]);

        if (is_string($value) === false) {
            return 0;
        }

        return (float) $value;
    }

    public function setValue(string $key, float|int $value): void
    {
        $this->connection->command('HSET', [self::HASH_NAME, $key, $value]);
    }

    public function incrementValue(string $key, float|int $value): void
    {
        $this->connection->command('HINCRBYFLOAT', [self::HASH_NAME, $key, $value]);
    }
}
