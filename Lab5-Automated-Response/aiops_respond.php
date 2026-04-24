<?php

/**
 * AIOps Automated Incident Response Engine
 * Monitors incidents.json and executes automated responses
 */

class Automator
{
    private $incidentFile;
    private $responseFile;
    private $policies;
    private $lastActions = [];

    public function __construct($incidentFile, $responseFile, $policyFile)
    {
        $this->incidentFile = $incidentFile;
        $this->responseFile = $responseFile;
        $this->policies = include($policyFile);
        $this->loadLastActions();
    }

    private function loadLastActions()
    {
        if (file_exists($this->responseFile)) {
            $responses = json_decode(file_get_contents($this->responseFile), true);
            if ($responses) {
                foreach ($responses as $response) {
                    $key = $response['incident_id'] . '_' . $response['action_taken'];
                    $this->lastActions[$key] = strtotime($response['timestamp']);
                }
            }
        }
    }

    private function isCoolingDown($incidentId, $action, $cooldown)
    {
        $key = $incidentId . '_' . $action;
        if (isset($this->lastActions[$key])) {
            $elapsed = time() - $this->lastActions[$key];
            return $elapsed < $cooldown;
        }
        return false;
    }

    private function executeAction($action, $incident, $policy)
    {
        $message = $policy['message'] ?? "Executing action: {$action}";
        
        switch ($action) {
            case 'restart_service':
                $result = $this->simulateRestartService();
                break;
            case 'send_alert':
                $result = $this->simulateSendAlert($incident);
                break;
            case 'scale_service':
                $result = $this->simulateScaleService();
                break;
            case 'throttle_traffic':
                $result = $this->simulateThrottleTraffic();
                break;
            case 'restart_endpoint':
                $result = $this->simulateRestartEndpoint($incident);
                break;
            default:
                $result = ['success' => false, 'message' => "Unknown action: {$action}"];
        }

        return [
            'action_taken' => $action,
            'result' => $result['message'],
            'success' => $result['success'],
            'notes' => $message
        ];
    }

    private function simulateRestartService()
    {
        echo "   🔄 Simulating: Restarting service...\n";
        sleep(1);
        $success = rand(80, 100) > 90; // 90% success rate
        return [
            'success' => $success,
            'message' => $success ? "Service restarted successfully" : "Service restart failed - manual intervention required"
        ];
    }

    private function simulateSendAlert($incident)
    {
        echo "   📧 Simulating: Sending alert for {$incident['incident_type']}...\n";
        // Always success
        return [
            'success' => true,
            'message' => "Alert sent to on-call engineer for {$incident['incident_type']}"
        ];
    }

    private function simulateScaleService()
    {
        echo "   📈 Simulating: Scaling up service capacity...\n";
        $newReplicas = rand(2, 5);
        return [
            'success' => true,
            'message' => "Service scaled up to {$newReplicas} replicas"
        ];
    }

    private function simulateThrottleTraffic()
    {
        echo "   🚦 Simulating: Throttling incoming traffic...\n";
        $newRate = rand(10, 20);
        return [
            'success' => true,
            'message' => "Traffic throttled to {$newRate} req/s"
        ];
    }

    private function simulateRestartEndpoint($incident)
    {
        $endpoint = $incident['affected_endpoints'][0] ?? 'unknown';
        echo "   🔄 Simulating: Restarting endpoint {$endpoint}...\n";
        return [
            'success' => true,
            'message' => "Endpoint {$endpoint} restarted"
        ];
    }

    private function handleEscalation($incident, $actionResult, $policy)
    {
        if (!$actionResult['success']) {
            $escalation = $policy['escalation'] ?? 'CRITICAL_ALERT';
            $escalationMessage = $this->policies['escalation_levels'][$escalation] ?? 'Unknown escalation';
            
            echo "   ⚠️ ESCALATION: Action failed! Raising {$escalation}...\n";
            
            return [
                'escalated' => true,
                'escalation_level' => $escalation,
                'escalation_action' => $escalationMessage
            ];
        }
        
        return ['escalated' => false];
    }

    public function run()
    {
        echo "\n" . str_repeat("=", 60) . "\n";
        echo "🤖 AIOps Automated Incident Response Engine\n";
        echo str_repeat("=", 60) . "\n";

        while (true) {
            echo "\n📡 Checking for new incidents at " . date('Y-m-d H:i:s') . "...\n";

            if (!file_exists($this->incidentFile)) {
                echo "   ⏳ No incidents file found. Waiting...\n";
                sleep(30);
                continue;
            }

            $incidents = json_decode(file_get_contents($this->incidentFile), true);
            
            if (empty($incidents)) {
                echo "   ✅ No active incidents.\n";
                sleep(30);
                continue;
            }

            $responses = [];
            $newResponses = [];

            // Load existing responses
            if (file_exists($this->responseFile)) {
                $responses = json_decode(file_get_contents($this->responseFile), true);
                if (!$responses) $responses = [];
            }

            foreach ($incidents as $incident) {
                $incidentType = $incident['incident_type'];
                $incidentId = $incident['incident_id'];
                
                if (!isset($this->policies['policies'][$incidentType])) {
                    echo "   ⚠️ No policy for incident type: {$incidentType}\n";
                    continue;
                }

                $policy = $this->policies['policies'][$incidentType];
                $action = $policy['action'];
                $cooldown = $policy['cooldown_seconds'];

                // Check cooldown
                if ($this->isCoolingDown($incidentId, $action, $cooldown)) {
                    echo "   ⏸️ Cooling down for {$incidentId} ({$action}) - skipping\n";
                    continue;
                }

                echo "\n📋 Processing incident: {$incidentId}\n";
                echo "   🔔 Type: {$incidentType}\n";
                echo "   🎯 Action: {$action}\n";

                // Execute action
                $actionResult = $this->executeAction($action, $incident, $policy);
                
                // Handle escalation
                $escalationResult = $this->handleEscalation($incident, $actionResult, $policy);

                // Create response log
                $responseLog = [
                    'incident_id' => $incidentId,
                    'incident_type' => $incidentType,
                    'action_taken' => $actionResult['action_taken'],
                    'timestamp' => date('Y-m-d H:i:s'),
                    'result' => $actionResult['result'],
                    'success' => $actionResult['success'],
                    'escalated' => $escalationResult['escalated'],
                    'escalation_level' => $escalationResult['escalated'] ? $escalationResult['escalation_level'] : null,
                    'notes' => $actionResult['notes']
                ];

                $newResponses[] = $responseLog;

                // Display result
                $status = $actionResult['success'] ? "✅ SUCCESS" : "❌ FAILED";
                echo "   {$status}: {$actionResult['result']}\n";
                if ($escalationResult['escalated']) {
                    echo "   📢 ESCALATED: {$escalationResult['escalation_level']}\n";
                }
            }

            // Save all responses
            if (!empty($newResponses)) {
                $allResponses = array_merge($responses, $newResponses);
                file_put_contents($this->responseFile, json_encode($allResponses, JSON_PRETTY_PRINT));
                echo "\n💾 Response logs saved to: {$this->responseFile}\n";
            }

            echo "\n⏳ Waiting 30 seconds before next check...\n";
            sleep(30);
        }
    }
}

// Run the automator
$incidentFile = __DIR__ . '/../Lab2-AIOps-Detection/storage/aiops/incidents.json';
$responseFile = __DIR__ . '/storage/responses.json';
$policyFile = __DIR__ . '/response_policies.php';

// Create storage directory
if (!is_dir(__DIR__ . '/storage')) {
    mkdir(__DIR__ . '/storage', 0755, true);
}

// Check if incidents file exists, if not use sample
if (!file_exists($incidentFile)) {
    echo "⚠️ No incidents.json found. Creating sample incident for demonstration...\n";
    
    $sampleIncidents = [[
        'incident_id' => 'DEMO_001',
        'incident_type' => 'ERROR_STORM',
        'severity' => 'high',
        'status' => 'active',
        'detected_at' => date('Y-m-d H:i:s'),
        'affected_service' => 'laravel_api',
        'affected_endpoints' => ['/api/error'],
        'summary' => 'Demo incident for testing'
    ]];
    
    $incidentDir = dirname($incidentFile);
    if (!is_dir($incidentDir)) {
        mkdir($incidentDir, 0755, true);
    }
    file_put_contents($incidentFile, json_encode($sampleIncidents, JSON_PRETTY_PRINT));
}

$automator = new Automator($incidentFile, $responseFile, $policyFile);
$automator->run();