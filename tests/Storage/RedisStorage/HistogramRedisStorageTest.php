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
use Zlodes\PrometheusClient\Storage\Commands\UpdateHistogram;
use Zlodes\PrometheusClient\Storage\DTO\MetricNameWithLabels;
use Zlodes\PrometheusClient\Storage\Testing\HistogramStorageTesting;

class HistogramRedisStorageTest extends TestCase
{
    use HistogramStorageTesting;
    use MockeryPHPUnitIntegration;

    public function testSerializerExceptionWhileUpdatingHistogram(): void
    {
        $storage = new HistogramRedisStorage(
            Mockery::mock(Connection::class),
            $serializerMock = Mockery::mock(Serializer::class),
        );

        $serializerMock
            ->expects('serialize')
            ->andThrow(new MetricKeySerializationException('Something went wrong'));

        $this->expectException(StorageWriteException::class);
        $this->expectExceptionMessage('Cannot serialize metric key');

        $storage->updateHistogram(
            new UpdateHistogram(
                new MetricNameWithLabels('foo'),
                [0, 1, 2],
                42
            )
        );
    }

    public function testRedisExceptionWhileFetchingSum(): void
    {
        $storage = new HistogramRedisStorage(
            $connectionMock = Mockery::mock(Connection::class),
        );

        $connectionMock
            ->expects('command')
            ->with('HGETALL', ['metrics_histograms_sum'])
            ->andThrow(new RedisException('Something went wrong'));

        $this->expectException(StorageReadException::class);

        [...$storage->fetchHistograms()];
    }

    public function testRedisExceptionWhileFetchingCount(): void
    {
        $storage = new HistogramRedisStorage(
            $connectionMock = Mockery::mock(Connection::class),
        );

        $connectionMock
            ->expects('command')
            ->with('HGETALL', ['metrics_histograms_sum'])
            ->andReturn(['foo' => 100]);

        $connectionMock
            ->expects('command')
            ->with('HGETALL', ['metrics_histograms_count'])
            ->andThrow(new RedisException('Something went wrong'));

        $this->expectException(StorageReadException::class);

        [...$storage->fetchHistograms()];
    }

    public function testSerializerExceptionWhileFetching(): void
    {
        $storage = new HistogramRedisStorage(
            $connectionMock = Mockery::mock(Connection::class),
            $serializerMock = Mockery::mock(Serializer::class),
        );

        $connectionMock
            ->expects('command')
            ->with('HGETALL', ['metrics_histograms_sum'])
            ->andReturn(['foo' => 100]);

        $connectionMock
            ->expects('command')
            ->with('HGETALL', ['metrics_histograms_count'])
            ->andReturn(['foo' => 1]);

        $serializerMock
            ->expects('unserialize')
            ->with('foo')
            ->andThrow(new MetricKeyUnserializationException('Something went wrong'));

        $this->expectException(StorageReadException::class);
        $this->expectExceptionMessage('Cannot unserialize metrics key for key: foo');

        [...$storage->fetchHistograms()];
    }

    public function testRedisExceptionWhileFetching(): void
    {
        $storage = new HistogramRedisStorage(
            $connectionMock = Mockery::mock(Connection::class),
        );

        $connectionMock
            ->expects('command')
            ->with('HGETALL', ['metrics_histograms_sum'])
            ->andReturn(['foo' => 100]);

        $connectionMock
            ->expects('command')
            ->with('HGETALL', ['metrics_histograms_count'])
            ->andReturn(['foo' => 1]);

        $connectionMock
            ->expects('command')
            ->with('HGETALL', ['metrics_histogram_foo'])
            ->andThrow(new RedisException('Something went wrong'));

        $this->expectException(StorageReadException::class);
        $this->expectExceptionMessage('Cannot execute HGETALL command');

        [...$storage->fetchHistograms()];
    }

    public function testRedisExceptionWhileCleanupSumDel(): void
    {
        $storage = new HistogramRedisStorage(
            $connectionMock = Mockery::mock(Connection::class),
        );

        $connectionMock
            ->expects('command')
            ->with('DEL', ['metrics_histograms_sum'])
            ->andThrow(new RedisException('Something went wrong'));

        $this->expectException(StorageWriteException::class);

        $storage->clearHistograms();
    }

    public function testRedisExceptionWhileCleanupCountDel(): void
    {
        $storage = new HistogramRedisStorage(
            $connectionMock = Mockery::mock(Connection::class),
        );

        $connectionMock
            ->expects('command')
            ->with('DEL', ['metrics_histograms_sum']);

        $connectionMock
            ->expects('command')
            ->with('DEL', ['metrics_histograms_count'])
            ->andThrow(new RedisException('Something went wrong'));

        $this->expectException(StorageWriteException::class);

        $storage->clearHistograms();
    }

    public function testRedisExceptionWhileCleanupEval(): void
    {
        $storage = new HistogramRedisStorage(
            $connectionMock = Mockery::mock(Connection::class),
        );

        $connectionMock
            ->expects('command')
            ->with('DEL', ['metrics_histograms_sum']);

        $connectionMock
            ->expects('command')
            ->with('DEL', ['metrics_histograms_count']);

        $connectionMock
            ->expects('command')
            ->with('EVAL', Mockery::any())
            ->andThrow(new RedisException('Something went wrong'));

        $this->expectException(StorageWriteException::class);

        $storage->clearHistograms();
    }

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Connection $redis */
        $redis = $this->app->make(Connection::class);

        $redis->command('FLUSHALL');
    }

    protected function createStorage(): HistogramRedisStorage
    {
        return new HistogramRedisStorage(
            $this->app->make(Connection::class),
        );
    }
}
