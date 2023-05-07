<?php

declare(strict_types=1);

namespace Zlodes\PrometheusExporter\Laravel\Tests\ScheduledCollector;

use InvalidArgumentException;
use Zlodes\PrometheusExporter\Laravel\ScheduledCollector\SchedulableCollectorArrayRegistry;
use PHPUnit\Framework\TestCase;
use Zlodes\PrometheusExporter\Laravel\Tests\Dummy\DummySchedulableCollector;

class SchedulableCollectorArrayRegistryTest extends TestCase
{
    public function testPushAndGetAll(): void
    {
        $repository = new SchedulableCollectorArrayRegistry();

        self::assertEmpty($repository->getAll());

        $repository->push(DummySchedulableCollector::class);

        self::assertEquals(
            [
                DummySchedulableCollector::class,
            ],
            $repository->getAll()
        );
    }

    public function testPushDuplicate(): void
    {
        $repository = new SchedulableCollectorArrayRegistry();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Expected an array of unique values');

        $repository->push(DummySchedulableCollector::class);
        $repository->push(DummySchedulableCollector::class);
    }

    public function testPushInappropriateString(): void
    {
        $repository = new SchedulableCollectorArrayRegistry();

        $this->expectException(InvalidArgumentException::class);

        $repository->push('foo');
    }
}
