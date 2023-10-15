<?php

declare(strict_types=1);

return [
    /**
     * If disabled, the NullStorage will be used
     */
    'enabled' => (bool) env('PROMETHEUS_CLIENT_ENABLED', true),

    /**
     * Here you can configure a Storage for metrics.
     * Available options: "null", "in_memory", "redis"
     */
    'storage' => env('PROMETHEUS_CLIENT_STORAGE', 'redis'),

    /**
     * Here you can specify a list of your SchedulableCollectors
     * Each element must be a class-string of class which implements SchedulableCollector interface.
     */
    'schedulable_collectors' => [],
];
