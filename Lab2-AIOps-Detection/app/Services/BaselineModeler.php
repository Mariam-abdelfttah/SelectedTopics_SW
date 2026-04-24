<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class BaselineModeler
{
    protected $prometheusClient;
    protected $endpoints;

    public function __construct(PrometheusClient $prometheusClient)
    {
        $this->prometheusClient = $prometheusClient;
        $this->endpoints = ['/api/normal', '/api/slow', '/api/db', '/api/error', '/api/validate'];
    }

    public function computeBaselines()
    {
        $baselines = [];

        foreach ($this->endpoints as $endpoint) {
            $baselines[$endpoint] = [
                'avg_latency' => $this->computeAverageLatency($endpoint),
                'request_rate' => $this->computeRequestRateBaseline($endpoint),
                'error_rate' => $this->computeErrorRateBaseline($endpoint),
                'last_updated' => now()->toIso8601String(),
            ];
        }

        $baselines['global'] = [
            'avg_latency' => $this->computeAverageLatency(),
            'request_rate' => $this->computeRequestRateBaseline(),
            'error_rate' => $this->computeErrorRateBaseline(),
            'last_updated' => now()->toIso8601String(),
        ];

        Cache::put('aiops_baselines', $baselines, now()->addMinutes(10));

        return $baselines;
    }

    protected function computeAverageLatency($endpoint = null)
    {
        $latencies = [];

        for ($i = 0; $i < 60; $i += 10) {
            $latency = $endpoint 
                ? $this->prometheusClient->getLatencyPercentile(95, $endpoint)
                : $this->prometheusClient->getLatencyPercentile(95);
            
            if ($latency > 0) {
                $latencies[] = $latency;
            }
        }

        if (empty($latencies)) {
            return 0.1;
        }

        return array_sum($latencies) / count($latencies);
    }

    protected function computeRequestRateBaseline($endpoint = null)
    {
        $rates = [];

        for ($i = 0; $i < 60; $i += 10) {
            $rate = $endpoint 
                ? $this->prometheusClient->getRequestRate($endpoint)
                : $this->prometheusClient->getRequestRate();
            
            if ($rate > 0) {
                $rates[] = $rate;
            }
        }

        if (empty($rates)) {
            return 1.0;
        }

        return array_sum($rates) / count($rates);
    }

    protected function computeErrorRateBaseline($endpoint = null)
    {
        $errors = [];

        for ($i = 0; $i < 60; $i += 10) {
            $errorRate = $endpoint 
                ? $this->prometheusClient->getErrorRate($endpoint)
                : $this->prometheusClient->getErrorRate();
            
            if ($errorRate >= 0) {
                $errors[] = $errorRate;
            }
        }

        if (empty($errors)) {
            return 0.05;
        }

        return array_sum($errors) / count($errors);
    }

    public function getBaselines()
    {
        $baselines = Cache::get('aiops_baselines');

        if (!$baselines) {
            $baselines = $this->computeBaselines();
        }

        return $baselines;
    }
}