<?php

declare(strict_types=1);

return [
    /**
     * If disabled, the NullStorage will be used
     */
    'enabled' => (bool) env('PROMETHEUS_CLIENT_ENABLED', true),

    /**
     * Here you can configure a Storage for metrics
     *
     * Available options:
     * - \Zlodes\PrometheusClient\Storage\InMemoryStorage::class
     * - \Zlodes\PrometheusClient\Laravel\Storage\RedisStorage::class
     * - Your own storage implements Storage interface
     */
    'storage' => \Zlodes\PrometheusClient\Laravel\Storage\RedisStorage::class,

    /**
     * Here you can specify a list of your SchedulableCollectors
     * Each element must be a class-string of class which implements SchedulableCollector interface.
     */
    'schedulable_collectors' => [],
];
