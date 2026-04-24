<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\PrometheusClient;
use App\Services\BaselineModeler;
use App\Services\AnomalyDetector;
use App\Services\EventCorrelator;
use App\Services\AlertingSystem;
use App\Models\Incident;

class AiopsDetect extends Command
{
    protected $signature = 'aiops:detect';
    protected $description = 'AIOps Detection Engine - Monitors metrics and detects anomalies';

    protected $prometheusClient;
    protected $baselineModeler;
    protected $anomalyDetector;
    protected $eventCorrelator;
    protected $alertingSystem;

    public function __construct(
        PrometheusClient $prometheusClient,
        BaselineModeler $baselineModeler,
        AnomalyDetector $anomalyDetector,
        EventCorrelator $eventCorrelator,
        AlertingSystem $alertingSystem
    ) {
        parent::__construct();
        $this->prometheusClient = $prometheusClient;
        $this->baselineModeler = $baselineModeler;
        $this->anomalyDetector = $anomalyDetector;
        $this->eventCorrelator = $eventCorrelator;
        $this->alertingSystem = $alertingSystem;
    }

    public function handle()
    {
        $this->info("🚀 AIOps Detection Engine Starting...");
        $this->info("📡 Connecting to Prometheus at " . config('aiops.prometheus.base_url'));

        while (true) {
            try {
                $this->line("");
                $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
                $this->info("📊 Scan at: " . now());

                // 1. Get current metrics
                $metrics = $this->prometheusClient->getAllMetrics();
                $this->line("✅ Metrics fetched successfully");

                // 2. Compute baselines
                $baselines = $this->baselineModeler->getBaselines();
                $this->line("📈 Baselines computed");

                // 3. Detect anomalies
                $anomalies = $this->anomalyDetector->detectAnomalies($metrics);
                if (!empty($anomalies)) {
                    $this->warn("⚠️ Anomalies detected: " . count($anomalies) . " signals");
                } else {
                    $this->line("✅ No anomalies detected");
                }

                // 4. Correlate into incidents
                $incidents = $this->eventCorrelator->correlate($anomalies);

                // 5. Store and alert
                foreach ($incidents as $incident) {
                    Incident::storeIncident($incident);
                    $this->alertingSystem->sendAlert($incident);
                    $this->error("🔔 INCIDENT: {$incident['incident_type']} - {$incident['summary']}");
                }

                // Display current metrics summary
                $this->displayMetricsSummary($metrics);

                // Wait 20-30 seconds
                $waitTime = rand(20, 30);
                $this->line("⏳ Next scan in {$waitTime} seconds...");
                sleep($waitTime);

            } catch (\Exception $e) {
                $this->error("❌ Error: " . $e->getMessage());
                sleep(30);
            }
        }
    }

    protected function displayMetricsSummary($metrics)
    {
        $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->info("📊 Current Metrics Summary:");

        if (isset($metrics['global'])) {
            $this->line("  • Global Request Rate: " . round($metrics['global']['request_rate'], 2) . " req/s");
            $this->line("  • Global Error Rate: " . round($metrics['global']['error_rate'] * 100, 2) . "%");
            $this->line("  • Global Latency P95: " . round($metrics['global']['latency_p95'] * 1000, 2) . " ms");
        }

        $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
    }
}