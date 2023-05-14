<?php

declare(strict_types=1);

namespace Zlodes\PrometheusExporter\Laravel\Tests\Storage;

use Exception;
use Illuminate\Contracts\Redis\Connection;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Orchestra\Testbench\TestCase;
use RedisException;
use RuntimeException;
use Zlodes\PrometheusExporter\DTO\MetricNameWithLabels;
use Zlodes\PrometheusExporter\DTO\MetricValue;
use Zlodes\PrometheusExporter\Exceptions\StorageReadException;
use Zlodes\PrometheusExporter\Exceptions\StorageWriteException;
use Zlodes\PrometheusExporter\Laravel\Storage\RedisStorage;
use Zlodes\PrometheusExporter\Normalization\Contracts\MetricKeyDenormalizer;
use Zlodes\PrometheusExporter\Normalization\Contracts\MetricKeyNormalizer;
use Zlodes\PrometheusExporter\Normalization\Exceptions\CannotDenormalizeMetricsKey;
use Zlodes\PrometheusExporter\Normalization\Exceptions\CannotNormalizeMetricsKey;
use Zlodes\PrometheusExporter\Storage\StorageTesting;

class RedisStorageTest extends TestCase
{
    use StorageTesting;
    use MockeryPHPUnitIntegration;

    public function testRedisExceptionWhileFetch(): void
    {
        $storage = new RedisStorage(
            $connectionMock = Mockery::mock(Connection::class),
        );

        $connectionMock
            ->expects('command')
            ->with('HGETALL', Mockery::any())
            ->andThrow(new RedisException('Something went wrong'));

        $this->expectException(StorageReadException::class);
        $this->expectExceptionMessage('Cannot execute HGETALL command');

        $storage->fetch();
    }

    public function testNormalizationExceptionWhileFetch(): void
    {
        $storage = new RedisStorage(
            $connectionMock = Mockery::mock(Connection::class),
            $keyNormalizerMock = Mockery::mock(MetricKeyNormalizer::class),
        );

        $connectionMock
            ->expects('command')
            ->with('HGETALL', Mockery::any())
            ->andReturn([
                'foo' => 42,
            ]);

        $keyNormalizerMock
            ->expects('normalize')
            ->with('foo')
            ->andThrow(new CannotNormalizeMetricsKey('Something went wrong'));

        $this->expectException(StorageReadException::class);
        $this->expectExceptionMessage('Cannot normalize metrics key for key: foo');

        $storage->fetch();
    }

    public function testExceptionWhileFetch(): void
    {
        $storage = new RedisStorage(
            $connectionMock = Mockery::mock(Connection::class),
        );

        $connectionMock
            ->expects('command')
            ->with('HGETALL', Mockery::any())
            ->andThrow(new RuntimeException('Something went wrong'));

        $this->expectException(StorageReadException::class);

        $storage->fetch();
    }

    public function testExceptionWhileClear(): void
    {
        $storage = new RedisStorage(
            $connectionMock = Mockery::mock(Connection::class),
        );

        $connectionMock
            ->expects('command')
            ->with('DEL', Mockery::any())
            ->andThrow(new RuntimeException('Something went wrong'));

        $this->expectException(StorageWriteException::class);

        $storage->clear();
    }

    public function testDenormalizationExceptionWhileIncrementingValue(): void
    {
        $storage = new RedisStorage(
            Mockery::mock(Connection::class),
            metricKeyDenormalizer: $keyDenormalizerMock = Mockery::mock(MetricKeyDenormalizer::class),
        );

        $keyDenormalizerMock
            ->expects('denormalize')
            ->andThrow(new CannotDenormalizeMetricsKey('Something went wrong'));

        $this->expectException(StorageWriteException::class);
        $this->expectExceptionMessage('Cannot denormalize metrics key');

        $storage->incrementValue(new MetricValue(
            new MetricNameWithLabels('foo', []),
            1,
        ));
    }

    public function testRedisExceptionWhileIncrementingValue(): void
    {
        $storage = new RedisStorage(
            $connectionMock = Mockery::mock(Connection::class),
        );

        $connectionMock
            ->expects('command')
            ->with('HINCRBYFLOAT', Mockery::any())
            ->andThrow(new RedisException('Something went wrong'));

        $this->expectException(StorageWriteException::class);
        $this->expectExceptionMessage('Cannot execute HINCRBYFLOAT command');

        $storage->incrementValue(new MetricValue(
            new MetricNameWithLabels('foo', []),
            1,
        ));
    }

    public function testDenormalizationExceptionWhileSettingValue(): void
    {
        $storage = new RedisStorage(
            Mockery::mock(Connection::class),
            metricKeyDenormalizer: $keyDenormalizerMock = Mockery::mock(MetricKeyDenormalizer::class),
        );

        $keyDenormalizerMock
            ->expects('denormalize')
            ->andThrow(new CannotDenormalizeMetricsKey('Something went wrong'));

        $this->expectException(StorageWriteException::class);
        $this->expectExceptionMessage('Cannot denormalize metrics key');

        $storage->setValue(new MetricValue(
            new MetricNameWithLabels('foo', []),
            1,
        ));
    }

    public function testRedisExceptionWhileSettingValue(): void
    {
        $storage = new RedisStorage(
            $connectionMock = Mockery::mock(Connection::class),
        );

        $connectionMock
            ->expects('command')
            ->with('HSET', Mockery::any())
            ->andThrow(new RedisException('Something went wrong'));

        $this->expectException(StorageWriteException::class);
        $this->expectExceptionMessage('Cannot execute HSET command');

        $storage->setValue(new MetricValue(
            new MetricNameWithLabels('foo', []),
            1,
        ));
    }

    private function createStorage(): RedisStorage
    {
        /** @var RedisStorage */
        return $this->app->make(RedisStorage::class);
    }
}
