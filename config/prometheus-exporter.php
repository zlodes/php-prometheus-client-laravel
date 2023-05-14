<?php

declare(strict_types=1);

return [
    /**
     * Here you can configure a Storage for metrics
     *
     * Available options:
     * - \Zlodes\PrometheusExporter\Storage\InMemoryStorage::class
     * - \Zlodes\PrometheusExporter\Laravel\Storage\RedisStorage::class
     * - Your own storage implements Storage interface
     */
    'storage' => \Zlodes\PrometheusExporter\Laravel\Storage\RedisStorage::class,

    /**
     * Here you can specify a list of your SchedulableCollectors
     * Each element must be a class-string of class which implements SchedulableCollector interface.
     */
    'schedulable_collectors' => [],
];
