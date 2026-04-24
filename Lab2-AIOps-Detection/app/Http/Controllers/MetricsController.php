<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class MetricsController extends Controller
{
    public function index(Request $request)
    {
        $output = "# HELP http_requests_total Total number of HTTP requests\n";
        $output .= "# TYPE http_requests_total counter\n";
        $output .= "http_requests_total{method=\"GET\",path=\"/api/normal\",status=\"200\"} 100\n";
        $output .= "http_requests_total{method=\"GET\",path=\"/api/slow\",status=\"200\"} 50\n";
        $output .= "http_requests_total{method=\"GET\",path=\"/api/error\",status=\"500\"} 10\n\n";

        $output .= "# HELP http_errors_total Total number of HTTP errors\n";
        $output .= "# TYPE http_errors_total counter\n";
        $output .= "http_errors_total{method=\"GET\",path=\"/api/error\",error_category=\"SYSTEM_ERROR\"} 10\n\n";

        $output .= "# HELP http_request_duration_seconds HTTP request duration in seconds\n";
        $output .= "# TYPE http_request_duration_seconds summary\n";
        $output .= "http_request_duration_seconds_sum{method=\"GET\",path=\"/api/normal\"} 25\n";
        $output .= "http_request_duration_seconds_count{method=\"GET\",path=\"/api/normal\"} 100\n\n";

        return response($output, 200)->header('Content-Type', 'text/plain');
    }

    public function recordRequest($method, $path, $status, $duration, $errorCategory = null)
    {
        // Mock data: do nothing, just keep the fake numbers
        return;
    }
}