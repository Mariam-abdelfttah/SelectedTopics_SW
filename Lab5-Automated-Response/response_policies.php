<?php

return [
    'policies' => [
        'LATENCY_SPIKE' => [
            'action' => 'restart_service',
            'simulate' => true,
            'escalation' => 'CRITICAL_ALERT',
            'cooldown_seconds' => 300,
            'message' => 'High latency detected, restarting service...'
        ],
        'ERROR_STORM' => [
            'action' => 'send_alert',
            'simulate' => true,
            'escalation' => 'PAGER_DUTY',
            'cooldown_seconds' => 60,
            'message' => 'Error storm detected, sending alert...'
        ],
        'TRAFFIC_SURGE' => [
            'action' => 'scale_service',
            'simulate' => true,
            'escalation' => 'AUTO_SCALE_UP',
            'cooldown_seconds' => 120,
            'message' => 'Traffic surge detected, scaling up...'
        ],
        'SERVICE_DEGRADATION' => [
            'action' => 'throttle_traffic',
            'simulate' => true,
            'escalation' => 'WARNING_ALERT',
            'cooldown_seconds' => 180,
            'message' => 'Service degradation detected, throttling traffic...'
        ],
        'LOCALIZED_ENDPOINT_FAILURE' => [
            'action' => 'restart_endpoint',
            'simulate' => true,
            'escalation' => 'HIGH_ALERT',
            'cooldown_seconds' => 240,
            'message' => 'Endpoint failure detected, restarting endpoint...'
        ]
    ],
    'escalation_levels' => [
        'WARNING_ALERT' => 'Send warning to dashboard',
        'HIGH_ALERT' => 'Send high priority alert',
        'CRITICAL_ALERT' => 'Page on-call engineer',
        'PAGER_DUTY' => 'Trigger PagerDuty incident',
        'AUTO_SCALE_UP' => 'Request additional resources'
    ]
];