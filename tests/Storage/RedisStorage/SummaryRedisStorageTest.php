<?php

declare(strict_types=1);

namespace Zlodes\PrometheusClient\Laravel\Tests\Storage\RedisStorage;

use Illuminate\Contracts\Redis\Connection;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Orchestra\Testbench\TestCase;
use RedisException;
use Zlodes\PrometheusClient\Exception\MetricKeySerializationException;
use Zlodes\PrometheusClient\Exception\MetricKeyUnserializationException;
use Zlodes\PrometheusClient\Exception\StorageReadException;
use Zlodes\PrometheusClient\Exception\StorageWriteException;
use Zlodes\PrometheusClient\KeySerialization\Serializer;
use Zlodes\PrometheusClient\Laravel\Storage\RedisStorage\HistogramRedisStorage;
use Zlodes\PrometheusClient\Laravel\Storage\RedisStorage\SummaryRedisStorage;
use Zlodes\PrometheusClient\Storage\Commands\UpdateHistogram;
use Zlodes\PrometheusClient\Storage\Commands\UpdateSummary;
use Zlodes\PrometheusClient\Storage\DTO\MetricNameWithLabels;
use Zlodes\PrometheusClient\Storage\Testing\SummaryStorageTesting;

class SummaryRedisStorageTest extends TestCase
{
    use SummaryStorageTesting;
    use MockeryPHPUnitIntegration;

    public function testCleanup(): void
    {
        $storage = new SummaryRedisStorage(
            $connectionMock = Mockery::mock(Connection::class),
        );

        $connectionMock
            ->expects('command')
            ->with('KEYS', ['*metrics_summary_*'])
            ->andReturn([
                'laravel_database_metrics_summary_foo',
                'metrics_summary_bar',
            ]);

        $connectionMock
            ->expects('command')
            ->with('DEL', ['metrics_summary_foo']);

        $connectionMock
            ->expects('command')
            ->with('DEL', ['metrics_summary_bar']);

        $storage->clearSummaries();
    }

    public function testRedisErrorWhileCleanup(): void
    {
        $storage = new SummaryRedisStorage(
            $connectionMock = Mockery::mock(Connection::class),
        );

        $connectionMock
            ->expects('command')
            ->with('KEYS', ['*metrics_summary_*'])
            ->andReturn([
                'laravel_database_metrics_summary_foo',
            ]);

        $connectionMock
            ->expects('command')
            ->with('DEL', ['metrics_summary_foo'])
            ->andThrow(new RedisException('Something went wrong'));

        $this->expectException(StorageWriteException::class);

        $storage->clearSummaries();
    }

    public function testRedisExceptionWhileFetchKeys(): void
    {
        $storage = new SummaryRedisStorage(
            $connectionMock = Mockery::mock(Connection::class),
        );

        $connectionMock
            ->expects('command')
            ->with('KEYS', ['*metrics_summary_*'])
            ->andThrow(new RedisException('Something went wrong'));

        $this->expectException(StorageReadException::class);

        [...$storage->fetchSummaries()];
    }

    public function testSerializationExceptionWhileFetching(): void
    {
        $storage = new SummaryRedisStorage(
            $connectionMock = Mockery::mock(Connection::class),
            $serializerMock = Mockery::mock(Serializer::class),
        );

        $connectionMock
            ->expects('command')
            ->with('KEYS', ['*metrics_summary_*'])
            ->andReturn([
                'laravel_database_metrics_summary_foo',
            ]);

        $serializerMock
            ->expects('unserialize')
            ->with('foo')
            ->andThrow(new MetricKeyUnserializationException());

        $this->expectException(StorageReadException::class);
        $this->expectExceptionMessage('Cannot unserialize metrics key for key: foo');

        [...$storage->fetchSummaries()];

        [...$storage->fetchSummaries()];
    }

    public function testSerializerExceptionWhileUpdatingHistogram(): void
    {
        $storage = new SummaryRedisStorage(
            Mockery::mock(Connection::class),
            $serializerMock = Mockery::mock(Serializer::class),
        );

        $serializerMock
            ->expects('serialize')
            ->andThrow(new MetricKeySerializationException('Something went wrong'));

        $this->expectException(StorageWriteException::class);
        $this->expectExceptionMessage('Cannot serialize metric key');

        $storage->updateSummary(
            new UpdateSummary(
                new MetricNameWithLabels('foo'),
                42,
            )
        );
    }


    protected function setUp(): void
    {
        parent::setUp();

        /** @var Connection $redis */
        $redis = $this->app->make(Connection::class);

        $redis->command('FLUSHALL');
    }

    protected function createStorage(): SummaryRedisStorage
    {
        return new SummaryRedisStorage(
            $this->app->make(Connection::class),
        );
    }
}
