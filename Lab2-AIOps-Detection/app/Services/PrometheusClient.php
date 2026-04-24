<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PrometheusClient
{
    protected $baseUrl;
    protected $timeout;

    public function __construct()
    {
        $this->baseUrl = config('aiops.prometheus.base_url', 'http://localhost:9090');
        $this->timeout = config('aiops.prometheus.timeout', 30);
    }

    public function query($query)
    {
        try {
            $response = Http::timeout($this->timeout)
                ->get($this->baseUrl . '/api/v1/query', [
                    'query' => $query
                ]);

            if ($response->successful()) {
                $data = $response->json();
                return $data['data']['result'] ?? [];
            }

            Log::warning("Prometheus query failed: {$query}", ['status' => $response->status()]);
            return [];
        } catch (\Exception $e) {
            Log::error("Prometheus connection error: {$e->getMessage()}");
            return [];
        }
    }

    public function getRequestRate($endpoint = null)
    {
        $query = 'sum(http_requests_total)';
        if ($endpoint) {
            $query = "sum(http_requests_total{path=\"$endpoint\"})";
        }

        $results = $this->query($query);
        return $this->extractMetricValue($results);
    }

    public function getErrorRate($endpoint = null)
    {
        $query = 'sum(http_errors_total) / sum(http_requests_total)';
        if ($endpoint) {
            $query = "sum(http_errors_total{path=\"$endpoint\"}) / sum(http_requests_total{path=\"$endpoint\"})";
        }

        $results = $this->query($query);
        return $this->extractMetricValue($results);
    }

    public function getLatencyPercentile($percentile = 95, $endpoint = null)
    {
        $query = "histogram_quantile($percentile/100, sum(http_request_duration_seconds_bucket) by (le, method, path))";
        if ($endpoint) {
            $query = "histogram_quantile($percentile/100, sum(http_request_duration_seconds_bucket{path=\"$endpoint\"}) by (le, method, path))";
        }

        $results = $this->query($query);
        return $this->extractMetricValue($results);
    }

    public function getErrorCategories()
    {
        $query = 'sum by(error_category) (http_errors_total)';
        $results = $this->query($query);

        $categories = [];
        foreach ($results as $result) {
            $category = $result['metric']['error_category'] ?? 'unknown';
            $categories[$category] = floatval($result['value'][1] ?? 0);
        }

        return $categories;
    }

    public function getAllMetrics()
    {
        $endpoints = ['/api/normal', '/api/slow', '/api/db', '/api/error', '/api/validate'];
        $metrics = [];

        foreach ($endpoints as $endpoint) {
            $metrics[$endpoint] = [
                'request_rate' => $this->getRequestRate($endpoint),
                'error_rate' => $this->getErrorRate($endpoint),
                'latency_p95' => $this->getLatencyPercentile(95, $endpoint),
                'latency_p99' => $this->getLatencyPercentile(99, $endpoint),
            ];
        }

        $metrics['global'] = [
            'request_rate' => $this->getRequestRate(),
            'error_rate' => $this->getErrorRate(),
            'latency_p95' => $this->getLatencyPercentile(95),
            'error_categories' => $this->getErrorCategories(),
        ];

        return $metrics;
    }

    protected function extractMetricValue($results)
    {
        if (empty($results) || !isset($results[0]['value'][1])) {
            return 0;
        }

        return floatval($results[0]['value'][1]);
    }
}