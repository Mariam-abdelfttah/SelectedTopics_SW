<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Incident extends Model
{
    protected $fillable = [
        'incident_id',
        'incident_type',
        'severity',
        'status',
        'detected_at',
        'affected_service',
        'affected_endpoints',
        'triggering_signals',
        'baseline_values',
        'observed_values',
        'summary',
    ];

    protected $casts = [
        'affected_endpoints' => 'array',
        'triggering_signals' => 'array',
        'baseline_values' => 'array',
        'observed_values' => 'array',
        'detected_at' => 'datetime',
    ];

    public static function storeIncident($incident)
    {
        $storagePath = config('aiops.incidents.storage_path');

        $directory = dirname($storagePath);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $incidents = [];
        if (file_exists($storagePath)) {
            $incidents = json_decode(file_get_contents($storagePath), true) ?: [];
        }

        // Check if incident already exists (avoid duplicates)
        $exists = false;
        foreach ($incidents as $existing) {
            if ($existing['incident_type'] === $incident['incident_type'] && 
                $existing['affected_endpoints'] === $incident['affected_endpoints'] &&
                $existing['status'] === 'active') {
                $exists = true;
                break;
            }
        }

        if (!$exists) {
            $incidents[] = $incident;
            $incidents = array_slice($incidents, -100);
            file_put_contents($storagePath, json_encode($incidents, JSON_PRETTY_PRINT));
        }

        return $incident;
    }
}