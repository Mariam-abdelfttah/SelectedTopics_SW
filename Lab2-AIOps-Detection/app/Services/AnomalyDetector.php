<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class AnomalyDetector
{
    protected $baselineModeler;
    protected $thresholds;

    public function __construct(BaselineModeler $baselineModeler)
    {
        $this->baselineModeler = $baselineModeler;
        $this->thresholds = config('aiops.detection.anomaly_thresholds');
    }

    public function detectAnomalies($currentMetrics)
    {
        $baselines = $this->baselineModeler->getBaselines();
        $anomalies = [];

        foreach ($currentMetrics as $endpoint => $metrics) {
            if ($endpoint === 'global') {
                continue;
            }

            $endpointAnomalies = [];

            // Latency anomaly: > 3x baseline
            if (isset($metrics['latency_p95']) && isset($baselines[$endpoint]['avg_latency'])) {
                $threshold = $baselines[$endpoint]['avg_latency'] * $this->thresholds['latency_multiplier'];
                if ($metrics['latency_p95'] > $threshold && $threshold > 0) {
                    $endpointAnomalies[] = [
                        'type' => 'LATENCY_ANOMALY',
                        'signal' => 'latency',
                        'observed' => $metrics['latency_p95'],
                        'baseline' => $baselines[$endpoint]['avg_latency'],
                        'severity' => $this->calculateSeverity($metrics['latency_p95'], $baselines[$endpoint]['avg_latency']),
                    ];
                }
            }

            // Error rate anomaly: > 10%
            if (isset($metrics['error_rate'])) {
                if ($metrics['error_rate'] > 0.10) {
                    $endpointAnomalies[] = [
                        'type' => 'ERROR_RATE_ANOMALY',
                        'signal' => 'error_rate',
                        'observed' => $metrics['error_rate'],
                        'baseline' => $baselines[$endpoint]['error_rate'] ?? 0,
                        'threshold' => 0.10,
                        'severity' => 'high',
                    ];
                }
            }

            // Traffic anomaly: > 2x baseline
            if (isset($metrics['request_rate']) && isset($baselines[$endpoint]['request_rate'])) {
                $threshold = $baselines[$endpoint]['request_rate'] * $this->thresholds['traffic_multiplier'];
                if ($metrics['request_rate'] > $threshold && $threshold > 0) {
                    $endpointAnomalies[] = [
                        'type' => 'TRAFFIC_SPIKE',
                        'signal' => 'traffic',
                        'observed' => $metrics['request_rate'],
                        'baseline' => $baselines[$endpoint]['request_rate'],
                        'severity' => 'medium',
                    ];
                }
            }

            if (!empty($endpointAnomalies)) {
                $anomalies[$endpoint] = $endpointAnomalies;
            }
        }

        // Global anomalies check
        if (isset($currentMetrics['global']['error_rate'])) {
            if ($currentMetrics['global']['error_rate'] > 0.10) {
                $anomalies['global'] = $anomalies['global'] ?? [];
                $anomalies['global'][] = [
                    'type' => 'GLOBAL_ERROR_STORM',
                    'signal' => 'global_error_rate',
                    'observed' => $currentMetrics['global']['error_rate'],
                    'severity' => 'critical',
                ];
            }
        }

        return $anomalies;
    }

    protected function calculateSeverity($observed, $baseline)
    {
        if ($baseline <= 0) return 'low';
        $ratio = $observed / $baseline;
        if ($ratio > 9) return 'critical';
        if ($ratio > 6) return 'high';
        if ($ratio > 3) return 'medium';
        return 'low';
    }
}