<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AlertingSystem
{
    protected $suppressionWindow = 300; // 5 minutes
    protected $alertedIncidents = [];

    public function __construct()
    {
        $this->alertedIncidents = Cache::get('alerted_incidents', []);
    }

    public function sendAlert($incident)
    {
        $incidentId = $incident['incident_id'];

        // Check for duplicate alerts
        if ($this->isDuplicateAlert($incidentId)) {
            return false;
        }

        $alert = $this->formatAlert($incident);

        // Console alert
        if (config('aiops.alerting.console', true)) {
            $this->sendConsoleAlert($alert);
        }

        // JSON alert
        if (config('aiops.alerting.json', true)) {
            $this->sendJsonAlert($alert);
        }

        // Webhook alert
        $webhookUrl = config('aiops.alerting.webhook');
        if ($webhookUrl) {
            $this->sendWebhookAlert($webhookUrl, $alert);
        }

        // Mark as alerted
        $this->markAsAlerted($incidentId);

        return true;
    }

    protected function formatAlert($incident)
    {
        return [
            'incident_id' => $incident['incident_id'],
            'incident_type' => $incident['incident_type'],
            'severity' => $incident['severity'],
            'timestamp' => $incident['detected_at'],
            'summary' => $incident['summary'],
            'affected_endpoints' => $incident['affected_endpoints'],
            'triggering_signals' => $incident['triggering_signals'],
            'observed_values' => $incident['observed_values'],
        ];
    }

    protected function sendConsoleAlert($alert)
    {
        $emoji = $this->getSeverityEmoji($alert['severity']);
        
        echo "\n" . "═══════════════════════════════════════════════════════════════\n";
        echo "{$emoji} AIOps ALERT [{$alert['severity']}] - {$alert['incident_type']}\n";
        echo "═══════════════════════════════════════════════════════════════\n";
        echo "ID: {$alert['incident_id']}\n";
        echo "Time: {$alert['timestamp']}\n";
        echo "Summary: {$alert['summary']}\n";
        echo "Endpoints: " . implode(', ', $alert['affected_endpoints']) . "\n";
        echo "Signals: " . implode(', ', $alert['triggering_signals']) . "\n";
        echo "═══════════════════════════════════════════════════════════════\n\n";
    }

    protected function sendJsonAlert($alert)
    {
        $alertLog = storage_path('logs/aiops_alerts.json');
        $existingAlerts = [];

        if (file_exists($alertLog)) {
            $existingAlerts = json_decode(file_get_contents($alertLog), true) ?: [];
        }

        $existingAlerts[] = $alert;
        $existingAlerts = array_slice($existingAlerts, -1000);

        file_put_contents($alertLog, json_encode($existingAlerts, JSON_PRETTY_PRINT));
    }

    protected function sendWebhookAlert($webhookUrl, $alert)
    {
        try {
            Http::timeout(5)->post($webhookUrl, $alert);
        } catch (\Exception $e) {
            Log::error("Webhook alert failed: {$e->getMessage()}");
        }
    }

    protected function isDuplicateAlert($incidentId)
    {
        return isset($this->alertedIncidents[$incidentId]) &&
               $this->alertedIncidents[$incidentId] > (time() - $this->suppressionWindow);
    }

    protected function markAsAlerted($incidentId)
    {
        $this->alertedIncidents[$incidentId] = time();

        // Clean old entries
        foreach ($this->alertedIncidents as $id => $timestamp) {
            if ($timestamp < (time() - $this->suppressionWindow)) {
                unset($this->alertedIncidents[$id]);
            }
        }

        Cache::put('alerted_incidents', $this->alertedIncidents, now()->addMinutes(10));
    }

    protected function getSeverityEmoji($severity)
    {
        switch ($severity) {
            case 'critical': return "🔴";
            case 'high': return "🟠";
            case 'medium': return "🟡";
            case 'low': return "🔵";
            default: return "⚪";
        }
    }
}