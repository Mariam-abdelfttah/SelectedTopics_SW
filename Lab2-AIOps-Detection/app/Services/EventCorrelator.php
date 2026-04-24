<?php

namespace App\Services;

use Illuminate\Support\Str;

class EventCorrelator
{
    public function correlate($anomalies)
    {
        $incidents = [];

        if (empty($anomalies)) {
            return $incidents;
        }

        foreach ($anomalies as $endpoint => $endpointAnomalies) {
            $anomalyTypes = array_unique(array_column($endpointAnomalies, 'type'));

            $incidentType = $this->determineIncidentType($anomalyTypes);
            
            $incident = [
                'incident_id' => $this->generateIncidentId(),
                'incident_type' => $incidentType,
                'severity' => $this->calculateSeverity($endpointAnomalies),
                'status' => 'active',
                'detected_at' => now()->toIso8601String(),
                'affected_service' => 'laravel_api',
                'affected_endpoints' => [$endpoint],
                'triggering_signals' => array_unique(array_column($endpointAnomalies, 'signal')),
                'baseline_values' => $this->extractBaselines($endpointAnomalies),
                'observed_values' => $this->extractObserved($endpointAnomalies),
                'summary' => $this->generateSummary($incidentType, $endpoint),
            ];

            $incidents[] = $incident;
        }

        return $incidents;
    }

    protected function determineIncidentType($anomalyTypes)
    {
        if (in_array('LATENCY_ANOMALY', $anomalyTypes)) {
            return 'LATENCY_SPIKE';
        }
        if (in_array('ERROR_RATE_ANOMALY', $anomalyTypes) || in_array('ERROR_SPIKE', $anomalyTypes)) {
            return 'ERROR_STORM';
        }
        if (in_array('TRAFFIC_SPIKE', $anomalyTypes)) {
            return 'TRAFFIC_SURGE';
        }
        if (in_array('GLOBAL_ERROR_STORM', $anomalyTypes)) {
            return 'SERVICE_DEGRADATION';
        }
        return 'SERVICE_DEGRADATION';
    }

    protected function calculateSeverity($anomalies)
    {
        $severities = array_column($anomalies, 'severity');
        if (in_array('critical', $severities)) return 'critical';
        if (in_array('high', $severities)) return 'high';
        return 'medium';
    }

    protected function generateIncidentId()
    {
        return 'inc_' . now()->format('Ymd_His') . '_' . Str::random(6);
    }

    protected function extractBaselines($anomalies)
    {
        $baselines = [];
        foreach ($anomalies as $anomaly) {
            if (isset($anomaly['baseline'])) {
                $baselines[$anomaly['signal']] = $anomaly['baseline'];
            }
        }
        return $baselines;
    }

    protected function extractObserved($anomalies)
    {
        $observed = [];
        foreach ($anomalies as $anomaly) {
            $observed[$anomaly['signal']] = $anomaly['observed'];
        }
        return $observed;
    }

    protected function generateSummary($incidentType, $endpoint)
    {
        $summaries = [
            'LATENCY_SPIKE' => "High latency detected on {$endpoint}",
            'ERROR_STORM' => "Error storm affecting {$endpoint}",
            'TRAFFIC_SURGE' => "Unusual traffic pattern on {$endpoint}",
            'SERVICE_DEGRADATION' => "Service degradation on {$endpoint}",
        ];
        return $summaries[$incidentType] ?? "Anomaly detected on {$endpoint}";
    }
}