<?php

return [
    'prometheus' => [
        'base_url' => env('PROMETHEUS_URL', 'http://localhost:9090'),
        'timeout' => 30,
    ],

    'detection' => [
        'interval_min' => 20,
        'interval_max' => 30,
        'anomaly_thresholds' => [
            'latency_multiplier' => 3.0,
            'error_rate_threshold' => 0.10,
            'traffic_multiplier' => 2.0,
        ],
    ],

    'incidents' => [
        'storage_path' => storage_path('aiops/incidents.json'),
    ],

    'alerting' => [
        'console' => true,
        'json' => true,
        'webhook' => env('ALERT_WEBHOOK_URL', null),
    ],
];