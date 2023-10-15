<?php

declare(strict_types=1);

namespace Zlodes\PrometheusClient\Laravel\Tests\Storage\RedisStorage;

use Illuminate\Contracts\Redis\Connection;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Orchestra\Testbench\TestCase;
use RedisException;
use Zlodes\PrometheusClient\Exception\MetricKeySerializationException;
use Zlodes\PrometheusClient\Exception\StorageReadException;
use Zlodes\PrometheusClient\Exception\StorageWriteException;
use Zlodes\PrometheusClient\KeySerialization\Serializer;
use Zlodes\PrometheusClient\Laravel\Storage\RedisStorage\GaugeRedisStorage;
use Zlodes\PrometheusClient\Storage\Commands\UpdateGauge;
use Zlodes\PrometheusClient\Storage\DTO\MetricNameWithLabels;
use Zlodes\PrometheusClient\Storage\Testing\GaugeStorageTesting;

class GaugeRedisStorageTest extends TestCase
{
    use GaugeStorageTesting;
    use MockeryPHPUnitIntegration;

    public function testRedisExceptionWhileFetch(): void
    {
        $storage = new GaugeRedisStorage(
            $connectionMock = Mockery::mock(Connection::class),
        );

        $connectionMock
            ->expects('command')
            ->with('HGETALL', Mockery::any())
            ->andThrow(new RedisException('Something went wrong'));

        $this->expectException(StorageReadException::class);
        $this->expectExceptionMessage('Cannot execute HGETALL command');

        [...$storage->fetchGauges()];
    }

    public function testSerializationExceptionWhileFetch(): void
    {
        $storage = new GaugeRedisStorage(
            $connectionMock = Mockery::mock(Connection::class),
            $serializerMock = Mockery::mock(Serializer::class),
        );

        $connectionMock
            ->expects('command')
            ->with('HGETALL', Mockery::any())
            ->andReturn(['foo' => 'bar']);

        $serializerMock
            ->expects('unserialize')
            ->andThrow(new MetricKeySerializationException('Something went wrong'));

        $this->expectException(StorageReadException::class);
        $this->expectExceptionMessage('Cannot unserialize metrics key for key: foo');

        [...$storage->fetchGauges()];
    }

    public function testSerializerExceptionWhileIncrementingValue(): void
    {
        $storage = new GaugeRedisStorage(
            Mockery::mock(Connection::class),
            $serializerMock = Mockery::mock(Serializer::class),
        );

        $serializerMock
            ->expects('serialize')
            ->andThrow(new MetricKeySerializationException('Something went wrong'));

        $this->expectException(StorageWriteException::class);
        $this->expectExceptionMessage('Cannot serialize metrics key');

        $storage->updateGauge(
            new UpdateGauge(
                new MetricNameWithLabels('foo', []),
                42,
            )
        );
    }

    public function testRedisExceptionWhileIncrementingValue(): void
    {
        $storage = new GaugeRedisStorage(
            $connectionMock = Mockery::mock(Connection::class),
        );

        $connectionMock
            ->expects('command')
            ->with('HSET', ['metrics_gauges', 'foo', 42])
            ->andThrow(new RedisException('Something went wrong'));

        $this->expectException(StorageWriteException::class);
        $this->expectExceptionMessage('Cannot execute HSET command');

        $storage->updateGauge(
            new UpdateGauge(
                new MetricNameWithLabels('foo', []),
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

    protected function createStorage(): GaugeRedisStorage
    {
        return new GaugeRedisStorage(
            $this->app->make(Connection::class),
        );
    }
}
