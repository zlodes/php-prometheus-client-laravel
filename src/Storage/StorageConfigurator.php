<?php

declare(strict_types=1);

namespace Zlodes\PrometheusClient\Laravel\Storage;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Webmozart\Assert\Assert;
use Zlodes\PrometheusClient\Laravel\Storage\RedisStorage\CounterRedisStorage;
use Zlodes\PrometheusClient\Laravel\Storage\RedisStorage\GaugeRedisStorage;
use Zlodes\PrometheusClient\Laravel\Storage\RedisStorage\HistogramRedisStorage;
use Zlodes\PrometheusClient\Laravel\Storage\RedisStorage\SummaryRedisStorage;
use Zlodes\PrometheusClient\Storage\Contracts\CounterStorage;
use Zlodes\PrometheusClient\Storage\Contracts\GaugeStorage;
use Zlodes\PrometheusClient\Storage\Contracts\HistogramStorage;
use Zlodes\PrometheusClient\Storage\Contracts\SummaryStorage;
use Zlodes\PrometheusClient\Storage\InMemory\InMemoryCounterStorage;
use Zlodes\PrometheusClient\Storage\InMemory\InMemoryGaugeStorage;
use Zlodes\PrometheusClient\Storage\InMemory\InMemoryHistogramStorage;
use Zlodes\PrometheusClient\Storage\InMemory\InMemorySummaryStorage;
use Zlodes\PrometheusClient\Storage\NullStorage;

final class StorageConfigurator
{
    /** @var non-empty-array<non-empty-string, array<string, class-string>> */
    private array $storages = [
        'null' => [
            GaugeStorage::class => NullStorage::class,
            CounterStorage::class => NullStorage::class,
            HistogramStorage::class => NullStorage::class,
            SummaryStorage::class => NullStorage::class,
        ],
        'in_memory' => [
            GaugeStorage::class => InMemoryGaugeStorage::class,
            CounterStorage::class => InMemoryCounterStorage::class,
            HistogramStorage::class => InMemoryHistogramStorage::class,
            SummaryStorage::class => InMemorySummaryStorage::class,
        ],
        'redis' => [
            GaugeStorage::class => GaugeRedisStorage::class,
            CounterStorage::class => CounterRedisStorage::class,
            HistogramStorage::class => HistogramRedisStorage::class,
            SummaryStorage::class => SummaryRedisStorage::class,
        ],
    ];

    public function __construct(
        private readonly Application $app,
        private readonly Repository $config,
    ) {
    }

    /**
     * Registers (or overrides) a storage driver with a custom set of storage implementations.
     *
     * @param non-empty-string $driverName
     * @param array<class-string, class-string> $storages Storage contract => implementation
     */
    public function extend(string $driverName, array $storages): self
    {
        Assert::stringNotEmpty($driverName);
        Assert::notEq(
            $driverName,
            'null',
            'The "null" driver name is reserved and cannot be overridden.',
        );

        $requiredContracts = [
            CounterStorage::class,
            GaugeStorage::class,
            HistogramStorage::class,
            SummaryStorage::class,
        ];

        foreach ($requiredContracts as $contract) {
            Assert::keyExists(
                $storages,
                $contract,
                "Missing a storage implementation for the '$contract' contract "
                . "while extending '$driverName' driver."
            );

            $implementation = $storages[$contract];

            Assert::true(
                is_a($implementation, $contract, true),
                "The implementation '$implementation' for the '$driverName' driver must implement '$contract'."
            );
        }

        $this->storages[$driverName] = $storages;

        return $this;
    }

    public function configure(): void
    {
        $driverName = $this->getDriverName();

        $driverConfiguration = $this->storages[$driverName] ?? null;
        Assert::notNull(
            $driverConfiguration,
            sprintf(
                'Unknown storage driver "%s". Known drivers are: %s.',
                $driverName,
                implode(', ', array_keys($this->storages)),
            )
        );

        foreach ($driverConfiguration as $interface => $implementation) {
            $this->app->singleton($interface, $implementation);
        }
    }

    /**
     * @return non-empty-string
     */
    private function getDriverName(): string
    {
        $clientEnabled = $this->config->get('prometheus-client.enabled') === true;

        if ($clientEnabled === false) {
            return 'null';
        }

        $driverName = $this->config->get('prometheus-client.storage');
        Assert::stringNotEmpty($driverName);

        return $driverName;
    }
}
